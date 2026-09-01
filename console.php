<?php

/**
 * Console do framework.
 *
 *     php console.php scaffold:crud tabela campo:tipo ...
 *     php console.php auth:install [Modelo] [Prefixo]
 *     php console.php relatorio:pdf modelo|tabela [arquivo.pdf]
 *
 * Todos os comandos param no primeiro problema e nao deixam arquivos pela
 * metade: os arquivos so sao gravados depois que tudo foi validado.
 */

if (PHP_SAPI !== 'cli') {
    exit("Este arquivo so pode ser executado pelo terminal.\n");
}

require_once __DIR__ . '/nucleo/bootstrap.php';

use Nucleo\Config;
use Nucleo\Database;
use Nucleo\RelatorioPdf;

const TIPOS_ACEITOS = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'time'];
const CAMPOS_RESERVADOS = ['id', 'criado_em'];

$comando    = $argv[1] ?? '';
$argumentos = array_slice($argv, 2);
$detalhado  = in_array('-v', $argumentos, true);
$argumentos = array_values(array_filter($argumentos, fn (string $a): bool => $a !== '-v'));

try {
    match ($comando) {
        'scaffold:crud' => gerarCrud($argumentos),
        'auth:install'  => gerarAutenticacao($argumentos),
        'relatorio:pdf' => gerarRelatorioPdf($argumentos),
        default         => ajuda($comando),
    };
} catch (Throwable $erro) {
    fwrite(STDERR, "\n[ERRO] " . $erro->getMessage() . "\n");

    if ($detalhado) {
        fwrite(STDERR, "\n" . $erro->getFile() . ':' . $erro->getLine() . "\n");
        fwrite(STDERR, $erro->getTraceAsString() . "\n");
    } else {
        fwrite(STDERR, "\nUse -v para ver os detalhes tecnicos.\n");
    }

    exit(1);
}

exit(0);

// =====================================================================
// Ajuda
// =====================================================================

function ajuda(string $comando): void
{
    $texto = <<<'TXT'
    Console do framework MVC

    Uso:
      php console.php scaffold:crud <tabela> <campo:tipo> ... [opcoes]
      php console.php auth:install [Modelo|tabela] [Prefixo]
      php console.php relatorio:pdf <modelo|tabela> [arquivo.pdf]

    Tipos de campo:
      string  text  integer  decimal  boolean  date  datetime  time

    Relacao 1:N (a tabela pai precisa existir antes):
      php console.php scaffold:crud matriculas nome:string turma_id:belongs_to=turmas

    Opcoes do scaffold:crud:
      --auth[=prefixo]   exige login em todas as rotas do recurso
      --modelo=Nome      define o nome da classe do model
      --sem-menu         nao adiciona o recurso a configuracoes/menu.php

    Opcao geral:
      -v                 mostra os detalhes tecnicos quando algo falha

    Exemplos:
      php console.php scaffold:crud produtos nome:string preco:decimal --auth
      php console.php auth:install Cliente
      php console.php auth:install Professor professor

    TXT;

    echo $texto;

    if ($comando !== '') {
        throw new InvalidArgumentException("Comando desconhecido: {$comando}");
    }
}

// =====================================================================
// scaffold:crud
// =====================================================================

function gerarCrud(array $argumentos): void
{
    [$posicionais, $opcoes] = separarOpcoes($argumentos, ['auth', 'modelo', 'sem-menu']);

    if (count($posicionais) < 2) {
        throw new InvalidArgumentException(
            "Uso: php console.php scaffold:crud <tabela> <campo:tipo> ...\n"
            . 'Exemplo: php console.php scaffold:crud produtos nome:string preco:decimal'
        );
    }

    $tabela = strtolower($posicionais[0]);
    validarNome($tabela, 'nome de tabela');

    $campos = interpretarCampos(array_slice($posicionais, 1));
    $classe = $opcoes['modelo'] ?? classeDaTabela($tabela);

    if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $classe)) {
        throw new InvalidArgumentException(
            "Nome de model invalido: {$classe}. Use PascalCase, por exemplo --modelo=Produto"
        );
    }

    $recurso  = pascal($tabela);
    $pasta    = strtolower($recurso);
    $provider = interpretarOpcaoAuth($opcoes);

    validarRelacoes($campos, $tabela);

    // Sem essa checagem o CRUD nasceria redirecionando para uma tela de
    // login que nao existe, e os testes gerados falhariam de cara.
    if ($provider !== false && !Nucleo\Autenticacao::instalado($provider)) {
        throw new RuntimeException(
            'A tela de login ' . ($provider === '' ? '/auth' : '/auth-' . str_replace('_', '-', $provider))
            . " ainda nao existe.\nInstale-a antes:\n  php console.php auth:install"
            . ($provider === '' ? '' : " <Modelo> {$provider}")
        );
    }

    // -------------------------------------------------------------
    // 1. Monta tudo na memoria e confere se os caminhos estao livres.
    // -------------------------------------------------------------
    $arquivos = [
        CAMINHO_MODELOS . "/{$classe}.php"                          => modeloGerado($tabela, $classe, $campos),
        CAMINHO_CONTROLLERS . "/{$recurso}Controller.php"           => controllerGerado($tabela, $classe, $recurso, $pasta, $campos, $provider),
        CAMINHO_VIEWS . "/{$pasta}/index.php"                       => indexGerado($tabela, $pasta, $campos),
        CAMINHO_VIEWS . "/{$pasta}/formulario.php"                  => formularioGerado($pasta, $campos),
        CAMINHO_VIEWS . "/{$pasta}/ver.php"                         => verGerado($pasta, $campos),
        CAMINHO_RAIZ . "/testes/modelos/{$classe}Test.php"          => testeModeloGerado($tabela, $classe, $campos),
        CAMINHO_RAIZ . "/testes/controllers/{$recurso}ControllerTest.php" => testeControllerGerado($tabela, $classe, $recurso, $pasta, $campos, $provider),
    ];

    conferirCaminhosLivres(array_keys($arquivos));

    // -------------------------------------------------------------
    // 2. Banco de dados (com desfazer se algo falhar).
    // -------------------------------------------------------------
    $esquemas = lerEsquemas();

    try {
        registrarEsquema($tabela, esquema($tabela, $campos, false), false);
        registrarEsquema($tabela, esquema($tabela, $campos, true), true);

        Database::migrar();
        sincronizarColunas($tabela, $campos);
    } catch (Throwable $e) {
        restaurarEsquemas($esquemas);

        throw $e;
    }

    // -------------------------------------------------------------
    // 3. So agora grava os arquivos.
    // -------------------------------------------------------------
    escreverArquivos($arquivos);

    $noMenu = !isset($opcoes['sem-menu']) && registrarNoMenu($pasta, $recurso);

    echo "CRUD criado: /{$pasta}\n";

    foreach (array_keys($arquivos) as $caminho) {
        echo '  + ' . caminhoRelativo($caminho) . "\n";
    }

    echo '  ~ banco/esquema.sqlite.sql, banco/esquema.mysql.sql' . "\n";

    if ($noMenu) {
        echo '  ~ configuracoes/menu.php' . "\n";
    }

    echo "\n";

    if ($provider === false) {
        echo "ATENCAO: todas as rotas de /{$pasta} sao publicas, inclusive excluir e o relatorio.\n";
        echo "Para exigir login, gere com --auth ou chame exigirAutenticacao() no controller.\n";
    } else {
        echo 'Rotas protegidas pelo login '
            . ($provider === '' ? '/auth' : '/auth-' . str_replace('_', '-', $provider))
            . ".\n";
    }

    echo "\nRode os testes com: php testes/executar.php {$classe}\n";
}

/**
 * Traduz "nome:string" e "turma_id:belongs_to=turmas" em [nome, tipo, relacao].
 *
 * @return list<array{0:string,1:string,2:?string}>
 */
function interpretarCampos(array $definicoes): array
{
    $campos = [];
    $vistos = [];

    foreach ($definicoes as $definicao) {
        if (!str_contains($definicao, ':')) {
            throw new InvalidArgumentException(
                "Campo sem tipo: {$definicao}. Use o formato nome:tipo, por exemplo {$definicao}:string"
            );
        }

        [$nome, $tipo] = explode(':', strtolower($definicao), 2);
        $relacao = null;

        if (str_starts_with($tipo, 'belongs_to=')) {
            $relacao = substr($tipo, strlen('belongs_to='));

            if ($relacao === '') {
                throw new InvalidArgumentException(
                    "Informe a tabela pai: {$nome}:belongs_to=nome_da_tabela"
                );
            }

            validarNome($relacao, 'nome de tabela relacionada');
            $tipo = 'integer';
        }

        validarNome($nome, 'nome de campo');

        if (in_array($nome, CAMPOS_RESERVADOS, true)) {
            throw new InvalidArgumentException(
                "O campo \"{$nome}\" e reservado pelo framework e nao deve ser informado."
            );
        }

        if (isset($vistos[$nome])) {
            throw new InvalidArgumentException("O campo \"{$nome}\" foi informado duas vezes.");
        }

        if (!in_array($tipo, TIPOS_ACEITOS, true)) {
            throw new InvalidArgumentException(
                "Tipo invalido em \"{$definicao}\": {$tipo}.\n"
                . 'Tipos aceitos: ' . implode(', ', TIPOS_ACEITOS) . ' ou belongs_to=tabela_pai'
            );
        }

        $vistos[$nome] = true;
        $campos[]      = [$nome, $tipo, $relacao];
    }

    return $campos;
}

/**
 * A tabela pai de um belongs_to precisa existir antes: caso contrario o
 * CRUD nasce quebrado (model inexistente, insert recusado pela FK).
 */
function validarRelacoes(array $campos, string $tabela): void
{
    foreach (relacoesUnicas($campos) as $campo) {
        $pai = $campo[2];

        if ($pai === $tabela) {
            continue; // auto-relacionamento
        }

        if (tabelaConhecida($pai)) {
            continue;
        }

        throw new RuntimeException(
            "A tabela pai \"{$pai}\" nao existe (campo {$campo[0]}).\n"
            . "Gere-a primeiro:\n"
            . "  php console.php scaffold:crud {$pai} nome:string"
        );
    }
}

/** A tabela existe no banco, no esquema ou como model? */
function tabelaConhecida(string $tabela): bool
{
    if (is_file(CAMINHO_MODELOS . '/' . classeDaTabela($tabela) . '.php')) {
        return true;
    }

    foreach (['sqlite', 'mysql'] as $driver) {
        $arquivo = CAMINHO_BANCO . "/esquema.{$driver}.sql";

        if (is_file($arquivo) && preg_match(padraoCreateTable($tabela), (string) file_get_contents($arquivo))) {
            return true;
        }
    }

    try {
        return colunasDaTabela($tabela) !== [];
    } catch (Throwable) {
        return false;
    }
}

/**
 * Le a opcao --auth: ausente = false, --auth = provider padrao,
 * --auth=professor = provider nomeado.
 */
function interpretarOpcaoAuth(array $opcoes): string|false
{
    if (!array_key_exists('auth', $opcoes)) {
        return false;
    }

    $valor = $opcoes['auth'];

    if ($valor === true || $valor === '') {
        return '';
    }

    $prefixo = normalizarPrefixoAutenticacao((string) $valor);

    return $prefixo === 'auth' ? '' : $prefixo;
}

// =====================================================================
// Banco de dados
// =====================================================================

function sincronizarColunas(string $tabela, array $campos): void
{
    $pdo       = Database::conexao();
    $driver    = Config::obter('banco.driver', 'sqlite');
    $existentes = colunasDaTabela($tabela);

    foreach ($campos as [$nome, $tipo]) {
        if (in_array($nome, $existentes, true)) {
            continue;
        }

        // Colunas novas entram como NULL: a tabela pode ja ter registros.
        $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN {$nome} " . tipoSql($tipo, $driver === 'mysql') . ' NULL');
    }
}

function colunasDaTabela(string $tabela): array
{
    $pdo     = Database::conexao();
    $driver  = Config::obter('banco.driver', 'sqlite');
    $colunas = [];

    if ($driver === 'mysql') {
        foreach ($pdo->query("SHOW COLUMNS FROM `{$tabela}`") as $coluna) {
            $colunas[] = $coluna['Field'];
        }
    } else {
        foreach ($pdo->query("PRAGMA table_info({$tabela})") as $coluna) {
            $colunas[] = $coluna['name'];
        }
    }

    return $colunas;
}

/**
 * Cria um indice UNIQUE se ele ainda nao existir.
 * Sem isso duas contas poderiam ter o mesmo e-mail e o login ficaria ambiguo.
 */
function garantirIndiceUnico(string $tabela, string $coluna): bool
{
    $pdo    = Database::conexao();
    $driver = Config::obter('banco.driver', 'sqlite');
    $indice = "idx_{$tabela}_{$coluna}_unico";

    try {
        if ($driver === 'mysql') {
            $existe = $pdo->query("SHOW INDEX FROM `{$tabela}` WHERE Key_name = '{$indice}'")->fetch();

            if ($existe !== false) {
                return true;
            }

            $pdo->exec("CREATE UNIQUE INDEX `{$indice}` ON `{$tabela}` (`{$coluna}`)");

            return true;
        }

        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS {$indice} ON {$tabela} ({$coluna})");

        return true;
    } catch (Throwable $e) {
        echo "AVISO: nao foi possivel criar o indice unico de {$tabela}.{$coluna}.\n";
        echo '       ' . $e->getMessage() . "\n";
        echo "       Provavelmente ja existem valores repetidos. Corrija-os e rode de novo.\n";

        return false;
    }
}

function tipoSql(string $tipo, bool $mysql): string
{
    if ($mysql) {
        return match ($tipo) {
            'integer'  => 'INT',
            'decimal'  => 'DECIMAL(12,2)',
            'boolean'  => 'TINYINT(1)',
            'date'     => 'DATE',
            'datetime' => 'DATETIME',
            'time'     => 'TIME',
            'text'     => 'TEXT',
            default    => 'VARCHAR(255)',
        };
    }

    return match ($tipo) {
        'integer', 'boolean' => 'INTEGER',
        'decimal'            => 'REAL',
        default              => 'TEXT',
    };
}

function esquema(string $tabela, array $campos, bool $mysql): string
{
    $colunas = array_map(
        fn (array $campo): string => "{$campo[0]} " . tipoSql($campo[1], $mysql) . ' NULL',
        $campos
    );

    $chaves = array_map(
        fn (array $campo): string => "CONSTRAINT fk_{$tabela}_{$campo[0]} FOREIGN KEY ({$campo[0]}) REFERENCES {$campo[2]}(id)",
        array_filter($campos, fn (array $campo): bool => ($campo[2] ?? null) !== null)
    );

    $definicoes = implode(",\n    ", array_merge($colunas, $chaves));

    return $mysql
        ? "CREATE TABLE IF NOT EXISTS {$tabela} (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    {$definicoes}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        : "CREATE TABLE IF NOT EXISTS {$tabela} (\n    id INTEGER PRIMARY KEY AUTOINCREMENT,\n    {$definicoes}\n);";
}

/** Expressao que encontra o CREATE TABLE de uma tabela especifica. */
function padraoCreateTable(string $tabela): string
{
    return '/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?'
        . preg_quote($tabela, '/')
        . '`?\s*\(.*?\)\s*(?:ENGINE\s*=\s*[^;]+)?;/is';
}

/**
 * Grava a definicao da tabela no arquivo de esquema.
 *
 * Se a tabela ja estiver no arquivo, a definicao antiga e SUBSTITUIDA.
 * Sem isso o arquivo acumularia dois "CREATE TABLE IF NOT EXISTS produtos"
 * e uma instalacao limpa criaria a tabela sem as colunas novas.
 */
function registrarEsquema(string $tabela, string $definicao, bool $mysql): void
{
    $arquivo  = arquivoEsquema($mysql);
    $conteudo = is_file($arquivo) ? (string) file_get_contents($arquivo) : '';
    $padrao   = padraoCreateTable($tabela);

    if (preg_match($padrao, $conteudo)) {
        $conteudo = preg_replace_callback($padrao, fn (): string => rtrim($definicao), $conteudo, 1);
    } else {
        $conteudo = rtrim($conteudo) . "\n\n" . rtrim($definicao) . "\n";
    }

    file_put_contents($arquivo, ltrim((string) $conteudo, "\n"), LOCK_EX);
}

function arquivoEsquema(bool $mysql): string
{
    return CAMINHO_BANCO . '/esquema.' . ($mysql ? 'mysql' : 'sqlite') . '.sql';
}

/** @return array<string,string> */
function lerEsquemas(): array
{
    $copias = [];

    foreach ([false, true] as $mysql) {
        $arquivo = arquivoEsquema($mysql);

        if (is_file($arquivo)) {
            $copias[$arquivo] = (string) file_get_contents($arquivo);
        }
    }

    return $copias;
}

function restaurarEsquemas(array $copias): void
{
    foreach ($copias as $arquivo => $conteudo) {
        file_put_contents($arquivo, $conteudo, LOCK_EX);
    }
}

// =====================================================================
// Nomes
// =====================================================================

function validarNome(string $nome, string $tipo): void
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $nome)) {
        throw new InvalidArgumentException(
            "{$tipo} invalido: \"{$nome}\".\n"
            . 'Use apenas letras minusculas, numeros e "_", comecando por uma letra.'
        );
    }
}

function pascal(string $nome): string
{
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $nome)));
}

/**
 * Singular aproximado em portugues, usado para nomear a classe do model.
 *
 *     produtos    -> produto        professores -> professor
 *     animais     -> animal         opcoes      -> opcao
 *     viagens     -> viagem         itens       -> item
 *
 * Nenhuma regra automatica acerta 100% dos plurais. Quando errar, informe o
 * nome da classe na mao:
 *
 *     php console.php scaffold:crud funis nome:string --modelo=Funil
 */
function singular(string $tabela): string
{
    static $excecoes = [
        'pais'     => 'pais',
        'paises'   => 'pais',
        'itens'    => 'item',
        'status'   => 'status',
        'onibus'   => 'onibus',
        'lapis'    => 'lapis',
        'virus'    => 'virus',
        'atlas'    => 'atlas',
        'oculos'   => 'oculos',
        'pires'    => 'pires',
        'meses'    => 'mes',
        'caes'     => 'cao',
        'paes'     => 'pao',
        'males'    => 'mal',
        'consules' => 'consul',
    ];

    if (isset($excecoes[$tabela])) {
        return $excecoes[$tabela];
    }

    $regras = [
        '/oes$/'           => 'ao',  // opcoes    -> opcao
        '/aes$/'           => 'ao',  // paes      -> pao
        '/ais$/'           => 'al',  // animais   -> animal
        '/eis$/'           => 'el',  // papeis    -> papel
        '/ois$/'           => 'ol',  // lencois   -> lencol
        '/uis$/'           => 'ul',  // azuis     -> azul
        '/ens$/'           => 'em',  // viagens   -> viagem
        '/ns$/'            => 'm',   // jardins   -> jardim
        '/(r|z|s|l|n)es$/' => '$1',  // professores -> professor
        '/s$/'             => '',    // produtos  -> produto
    ];

    foreach ($regras as $padrao => $troca) {
        if (preg_match($padrao, $tabela)) {
            return (string) preg_replace($padrao, $troca, $tabela);
        }
    }

    return $tabela;
}

function classeDaTabela(string $tabela): string
{
    return pascal(singular($tabela));
}

function relacoesUnicas(array $campos): array
{
    $relacoes = [];

    foreach ($campos as $campo) {
        if (($campo[2] ?? null) !== null) {
            $relacoes[$campo[2]] = $campo;
        }
    }

    return array_values($relacoes);
}

// =====================================================================
// Arquivos
// =====================================================================

function caminhoRelativo(string $caminho): string
{
    return str_starts_with($caminho, CAMINHO_RAIZ . '/')
        ? substr($caminho, strlen(CAMINHO_RAIZ) + 1)
        : $caminho;
}

function conferirCaminhosLivres(array $caminhos): void
{
    $ocupados = array_values(array_filter($caminhos, 'is_file'));

    if ($ocupados === []) {
        return;
    }

    throw new RuntimeException(
        "Estes arquivos ja existem e nao serao sobrescritos:\n  "
        . implode("\n  ", array_map('caminhoRelativo', $ocupados))
        . "\n\nApague-os (ou use outro nome de tabela) antes de gerar de novo."
    );
}

/**
 * Grava todos os arquivos de uma vez. Se algum falhar, apaga os que ja
 * tinham sido criados para nao deixar o projeto pela metade.
 *
 * @param array<string,string> $arquivos caminho => conteudo
 */
function escreverArquivos(array $arquivos): void
{
    $criados = [];
    $pastas  = [];

    try {
        foreach ($arquivos as $caminho => $conteudo) {
            $pasta = dirname($caminho);

            if (!is_dir($pasta)) {
                mkdir($pasta, 0777, true);
                $pastas[] = $pasta;
            }

            if (file_put_contents($caminho, rtrim($conteudo, "\n") . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Nao foi possivel gravar: ' . caminhoRelativo($caminho));
            }

            $criados[] = $caminho;
        }
    } catch (Throwable $e) {
        foreach ($criados as $caminho) {
            @unlink($caminho);
        }

        foreach (array_reverse($pastas) as $pasta) {
            @rmdir($pasta);
        }

        throw $e;
    }
}

/**
 * Acrescenta o recurso a configuracoes/menu.php para ele aparecer na
 * barra lateral sem ninguem precisar editar HTML.
 */
function registrarNoMenu(string $rota, string $texto): bool
{
    $arquivo = CAMINHO_CONFIGURACOES . '/menu.php';

    if (!is_file($arquivo)) {
        return false;
    }

    $conteudo = (string) file_get_contents($arquivo);

    if (preg_match("/'rota'\s*=>\s*'" . preg_quote($rota, '/') . "'/", $conteudo)) {
        return false;
    }

    $linha = "    ['rota' => '{$rota}', 'texto' => '{$texto}'],\n";

    if (str_contains($conteudo, '    // scaffold:crud')) {
        $conteudo = str_replace('    // scaffold:crud', $linha . '    // scaffold:crud', $conteudo);
    } elseif (preg_match('/\n\];\s*$/', $conteudo)) {
        $conteudo = (string) preg_replace('/\n\];(\s*)$/', "\n" . $linha . '];$1', $conteudo, 1);
    } else {
        return false;
    }

    file_put_contents($arquivo, $conteudo, LOCK_EX);

    return true;
}

/** Separa "--opcao=valor" dos argumentos comuns. */
function separarOpcoes(array $argumentos, array $aceitas): array
{
    $opcoes      = [];
    $posicionais = [];

    foreach ($argumentos as $argumento) {
        if (!str_starts_with($argumento, '--')) {
            $posicionais[] = $argumento;
            continue;
        }

        [$nome, $valor] = array_pad(explode('=', substr($argumento, 2), 2), 2, true);
        $nome = strtolower($nome);

        if (!in_array($nome, $aceitas, true)) {
            throw new InvalidArgumentException(
                "Opcao desconhecida: --{$nome}.\nOpcoes aceitas: --" . implode(', --', $aceitas)
            );
        }

        $opcoes[$nome] = $valor;
    }

    return [$posicionais, $opcoes];
}

// =====================================================================
// Geradores: model
// =====================================================================

function modeloGerado(string $tabela, string $classe, array $campos): string
{
    $preenchiveis = implode(', ', array_map(fn (array $c): string => "'{$c[0]}'", $campos));

    return strtr(<<<'PHP'
        <?php

        namespace Modelos;

        use Nucleo\Model;
        use Nucleo\Validador;

        class {{CLASSE}} extends Model
        {
            protected string $tabela = '{{TABELA}}';
            protected array $preenchiveis = [{{PREENCHIVEIS}}];
            protected string $ordemPadrao = 'id DESC';

            /**
             * Regras de validacao do formulario.
             * Devolve um array vazio quando esta tudo certo.
             */
            public function validar(array $dados, int|string|null $ignorarId = null): array
            {
                return (new Validador($dados))
        {{REGRAS}}
                    ->erros();
            }
        {{RELACOES}}}
        PHP, [
        '{{CLASSE}}'       => $classe,
        '{{TABELA}}'       => $tabela,
        '{{PREENCHIVEIS}}' => $preenchiveis,
        '{{REGRAS}}'       => regrasDeValidacao($campos),
        '{{RELACOES}}'     => metodosRelacoesModelo($campos),
    ]);
}

/**
 * O primeiro campo vira obrigatorio; os demais ganham a regra do seu tipo.
 * Ajuste a vontade depois de gerar.
 */
function regrasDeValidacao(array $campos): string
{
    $linhas = [];

    foreach ($campos as $indice => [$nome, $tipo, $relacao]) {
        $regras = [];

        if ($indice === 0 || $relacao !== null) {
            $regras[] = "->obrigatorio('{$nome}')";
        }

        if ($nome === 'email') {
            $regras[] = "->email('{$nome}')";
        }

        $regras[] = match ($tipo) {
            'integer', 'decimal' => "->numerico('{$nome}')",
            'string'             => "->maximo('{$nome}', 255)",
            default              => null,
        };

        foreach (array_filter($regras) as $regra) {
            $linhas[] = '            ' . $regra;
        }
    }

    return implode("\n", $linhas);
}

function metodosRelacoesModelo(array $campos): string
{
    $metodos = [];

    foreach (relacoesUnicas($campos) as $campo) {
        $tabelaPai = $campo[2];
        $classePai = classeDaTabela($tabelaPai);

        $metodos[] = strtr(<<<'PHP'

                /** Opcoes da tabela pai, usadas no <select> do formulario. */
                public function {{METODO}}(): array
                {
                    return (new \Modelos\{{CLASSE_PAI}}())->todos();
                }

            PHP, [
            '{{METODO}}'     => $tabelaPai,
            '{{CLASSE_PAI}}' => $classePai,
        ]);
    }

    return implode('', $metodos);
}

// =====================================================================
// Geradores: controller
// =====================================================================

function controllerGerado(
    string $tabela,
    string $classe,
    string $recurso,
    string $pasta,
    array $campos,
    string|false $provider
): string {
    $dados = implode("\n", array_map(
        fn (array $c): string => "            '{$c[0]}' => \$this->post('{$c[0]}'),",
        $campos
    ));

    $relacoes = '';

    foreach (relacoesUnicas($campos) as $campo) {
        $relacoes .= "\n            '{$campo[2]}' => \$this->modelo->{$campo[2]}(),";
    }

    $guarda = $provider === false
        ? ''
        : '        $this->exigirAutenticacao(' . ($provider === '' ? '' : "'{$provider}'") . ");\n\n";

    $filtros = '';

    foreach (array_merge([['id', 'integer', null]], $campos) as [$nome, $tipo, $relacao]) {
        $exato = $nome === 'id' || $relacao !== null || in_array($tipo, ['integer', 'decimal', 'boolean'], true);

        $filtros .= "        \$filtro = \$this->get('{$nome}');\n"
            . "        if (is_scalar(\$filtro) && (string) \$filtro !== '') {\n"
            . ($exato
                ? "            \$condicoes[] = '{$nome} = ?';\n            \$parametros[] = \$filtro;\n"
                : "            \$condicoes[] = '{$nome} LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE;\n            \$parametros[] = Sql::comoLike((string) \$filtro);\n")
            . "        }\n\n";
    }

    $colunas = implode(', ', array_merge(["'id'"], array_map(fn (array $c): string => "'{$c[0]}'", $campos)));

    return strtr(<<<'PHP'
        <?php

        namespace Controllers;

        use Modelos\{{CLASSE}};
        use Nucleo\Controller;
        use Nucleo\RelatorioPdf;
        use Nucleo\Sql;

        class {{RECURSO}}Controller extends Controller
        {
            private {{CLASSE}} $modelo;

            public function __construct()
            {
                $this->modelo = new {{CLASSE}}();
            }

            /** GET /{{PASTA}} */
            public function index(): void
            {
        {{GUARDA}}        $this->view('{{PASTA}}/index', [
                    'titulo'    => '{{RECURSO}}',
                    'registros' => $this->modelo->todos(),
                ]);
            }

            /** GET /{{PASTA}}/criar */
            public function criar(): void
            {
        {{GUARDA}}        $this->view('{{PASTA}}/formulario', [
                    'titulo'   => 'Novo {{CLASSE}}',
                    'registro' => null,{{RELACOES}}
                ]);
            }

            /** POST /{{PASTA}}/salvar */
            public function salvar(): void
            {
        {{GUARDA}}        $this->exigirFormularioValido();

                $dados = [
        {{DADOS}}
                ];

                $erros = $this->modelo->validar($dados);

                if ($erros !== []) {
                    $this->voltarComErros($erros, '{{PASTA}}/criar');
                }

                $id = $this->modelo->criar($dados);

                $this->mensagem('sucesso', '{{CLASSE}} criado com sucesso.');
                $this->redirecionar('{{PASTA}}/ver/' . $id);
            }

            /** GET /{{PASTA}}/ver/1 */
            public function ver(string $id): void
            {
        {{GUARDA}}        $registro = $this->modelo->buscar($id);

                if ($registro === null) {
                    $this->naoEncontrado();
                }

                $this->view('{{PASTA}}/ver', [
                    'titulo'   => '{{CLASSE}}',
                    'registro' => $registro,
                ]);
            }

            /** GET /{{PASTA}}/editar/1 */
            public function editar(string $id): void
            {
        {{GUARDA}}        $registro = $this->modelo->buscar($id);

                if ($registro === null) {
                    $this->naoEncontrado();
                }

                $this->view('{{PASTA}}/formulario', [
                    'titulo'   => 'Editar {{CLASSE}}',
                    'registro' => $registro,{{RELACOES}}
                ]);
            }

            /** POST /{{PASTA}}/atualizar/1 */
            public function atualizar(string $id): void
            {
        {{GUARDA}}        $this->exigirFormularioValido();

                if (!$this->modelo->existe($id)) {
                    $this->naoEncontrado();
                }

                $dados = [
        {{DADOS}}
                ];

                $erros = $this->modelo->validar($dados, $id);

                if ($erros !== []) {
                    $this->voltarComErros($erros, '{{PASTA}}/editar/' . $id);
                }

                $this->modelo->atualizar($id, $dados);

                $this->mensagem('sucesso', '{{CLASSE}} atualizado com sucesso.');
                $this->redirecionar('{{PASTA}}/ver/' . $id);
            }

            /**
             * GET /{{PASTA}}/relatorio
             *
             * Cada campo da query string vira um filtro:
             *     /{{PASTA}}/relatorio?{{CAMPO}}=teste
             */
            public function relatorio(): void
            {
        {{GUARDA}}        $condicoes  = [];
                $parametros = [];

        {{FILTROS}}        $sql = 'SELECT * FROM ' . $this->modelo->tabela();

                if ($condicoes !== []) {
                    $sql .= ' WHERE ' . implode(' AND ', $condicoes);
                }

                $sql .= ' ORDER BY id DESC';

                $registros = $this->modelo->consultar($sql, $parametros);
                $pdf = RelatorioPdf::conteudo('Relatorio de {{TABELA}}', [{{COLUNAS}}], $registros);

                $this->pdf($pdf, '{{PASTA}}.pdf');
            }

            /**
             * POST /{{PASTA}}/excluir/1
             *
             * So aceita POST com token: um link ou um <img> em outro site
             * nao conseguem apagar registros.
             */
            public function excluir(string $id): void
            {
        {{GUARDA}}        $this->exigirFormularioValido();

                if (!$this->modelo->excluir($id)) {
                    $this->naoEncontrado();
                }

                $this->mensagem('sucesso', '{{CLASSE}} excluido com sucesso.');
                $this->redirecionar('{{PASTA}}');
            }
        }
        PHP, [
        '{{CLASSE}}'   => $classe,
        '{{RECURSO}}'  => $recurso,
        '{{PASTA}}'    => $pasta,
        '{{TABELA}}'   => $tabela,
        '{{GUARDA}}'   => $guarda,
        '{{DADOS}}'    => $dados,
        '{{RELACOES}}' => $relacoes,
        '{{FILTROS}}'  => $filtros,
        '{{COLUNAS}}'  => $colunas,
        '{{CAMPO}}'    => $campos[0][0],
    ]);
}

// =====================================================================
// Geradores: views
// =====================================================================

function indexGerado(string $tabela, string $pasta, array $campos): string
{
    $cabecalhos = '';
    $celulas    = '';

    foreach ($campos as [$nome, $tipo, $relacao]) {
        $cabecalhos .= "                <th>{$nome}</th>\n";
        $celulas .= $tipo === 'boolean'
            ? "                <td><?= e(sim_nao(\$registro['{$nome}'] ?? null)) ?></td>\n"
            : "                <td><?= e(\$registro['{$nome}'] ?? '') ?></td>\n";
    }

    return strtr(<<<'HTML'
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">{{TABELA}}</h1>
                <p class="text-secondary mb-0">Gerencie os registros cadastrados.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="<?= url('{{PASTA}}/relatorio') ?>">Relatorio PDF</a>
                <a class="btn btn-primary" href="<?= url('{{PASTA}}/criar') ?>">Novo registro</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>ID</th>
        {{CABECALHOS}}                <th class="text-end">Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td><a href="<?= url('{{PASTA}}/ver/' . $registro['id']) ?>"><?= e($registro['id']) ?></a></td>
        {{CELULAS}}                <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('{{PASTA}}/editar/' . $registro['id']) ?>">Editar</a>
                            <form class="d-inline" method="post" action="<?= url('{{PASTA}}/excluir/' . $registro['id']) ?>" onsubmit="return confirm('Excluir este registro?')">
                                <?= campo_csrf() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach ?>
                    <?php if ($registros === []): ?>
                    <tr><td colspan="{{COLSPAN}}" class="text-center text-secondary py-4">Nenhum registro cadastrado.</td></tr>
                    <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
        HTML, [
        '{{TABELA}}'     => $tabela,
        '{{PASTA}}'      => $pasta,
        '{{CABECALHOS}}' => $cabecalhos,
        '{{CELULAS}}'    => $celulas,
        '{{COLSPAN}}'    => (string) (count($campos) + 2),
    ]);
}

function formularioGerado(string $pasta, array $campos): string
{
    $blocos = [];

    foreach ($campos as [$nome, $tipo, $relacao]) {
        $blocos[] = $relacao !== null
            ? campoRelacao($nome, $relacao)
            : ($tipo === 'boolean' ? campoBoolean($nome) : campoSimples($nome, $tipo));
    }

    return strtr(<<<'HTML'
        <div class="mb-4">
            <h1 class="h3 mb-1"><?= e($titulo) ?></h1>
            <p class="text-secondary mb-0">Preencha os dados abaixo.</p>
        </div>

        <form class="card border-0 shadow-sm p-4" method="post" action="<?= url('{{PASTA}}/' . ($registro ? 'atualizar/' . $registro['id'] : 'salvar')) ?>">
            <?= campo_csrf() ?>
            <div class="row g-3">
        {{CAMPOS}}    </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-secondary" href="<?= url('{{PASTA}}') ?>">Cancelar</a>
            </div>
        </form>
        HTML, [
        '{{PASTA}}'  => $pasta,
        '{{CAMPOS}}' => implode('', $blocos),
    ]);
}

function campoSimples(string $nome, string $tipo): string
{
    $tipoHtml = match ($tipo) {
        'integer'  => 'number',
        'decimal'  => 'number',
        'date'     => 'date',
        'datetime' => 'datetime-local',
        'time'     => 'time',
        default    => 'text',
    };

    $passo = $tipo === 'decimal' ? ' step="0.01"' : '';

    if ($tipo === 'text') {
        return strtr(<<<'HTML'
                <div class="col-12">
                    <label class="form-label" for="{{NOME}}">{{NOME}}</label>
                    <textarea class="form-control <?= tem_erro('{{NOME}}') ? 'is-invalid' : '' ?>" id="{{NOME}}" name="{{NOME}}" rows="4"><?= e(antigo('{{NOME}}', $registro['{{NOME}}'] ?? '')) ?></textarea>
                    <?php if ($mensagem = erro_de('{{NOME}}')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
                </div>

            HTML, ['{{NOME}}' => $nome]);
    }

    return strtr(<<<'HTML'
            <div class="col-md-6">
                <label class="form-label" for="{{NOME}}">{{NOME}}</label>
                <input class="form-control <?= tem_erro('{{NOME}}') ? 'is-invalid' : '' ?>" id="{{NOME}}" type="{{TIPO}}"{{PASSO}} name="{{NOME}}" value="<?= e(antigo('{{NOME}}', $registro['{{NOME}}'] ?? '')) ?>">
                <?php if ($mensagem = erro_de('{{NOME}}')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
            </div>

        HTML, [
        '{{NOME}}'  => $nome,
        '{{TIPO}}'  => $tipoHtml,
        '{{PASSO}}' => $passo,
    ]);
}

/**
 * Checkbox com campo escondido antes dele.
 *
 * O navegador NAO envia nada quando a caixa esta desmarcada. O input
 * hidden garante que o formulario sempre mande 0 ou 1, e nunca "on".
 */
function campoBoolean(string $nome): string
{
    return strtr(<<<'HTML'
            <div class="col-md-6">
                <label class="form-label" for="{{NOME}}">{{NOME}}</label>
                <div class="form-check">
                    <input type="hidden" name="{{NOME}}" value="0">
                    <input class="form-check-input" id="{{NOME}}" type="checkbox" name="{{NOME}}" value="1" <?= antigo('{{NOME}}', $registro['{{NOME}}'] ?? '') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="{{NOME}}">Sim</label>
                </div>
                <?php if ($mensagem = erro_de('{{NOME}}')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
            </div>

        HTML, ['{{NOME}}' => $nome]);
}

function campoRelacao(string $nome, string $tabelaPai): string
{
    return strtr(<<<'HTML'
            <div class="col-md-6">
                <label class="form-label" for="{{NOME}}">{{NOME}}</label>
                <?php $selecionado = antigo('{{NOME}}', $registro['{{NOME}}'] ?? ''); ?>
                <select class="form-select <?= tem_erro('{{NOME}}') ? 'is-invalid' : '' ?>" id="{{NOME}}" name="{{NOME}}">
                    <option value="">Selecione...</option>
                    <?php foreach ((${{PAI}} ?? []) as $opcao): ?>
                        <option value="<?= e($opcao['id']) ?>" <?= (string) $selecionado === (string) $opcao['id'] ? 'selected' : '' ?>><?= e($opcao['nome'] ?? $opcao['descricao'] ?? ('#' . $opcao['id'])) ?></option>
                    <?php endforeach ?>
                </select>
                <?php if ($mensagem = erro_de('{{NOME}}')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
            </div>

        HTML, [
        '{{NOME}}' => $nome,
        '{{PAI}}'  => $tabelaPai,
    ]);
}

function verGerado(string $pasta, array $campos): string
{
    $linhas = '';

    foreach ($campos as [$nome, $tipo, $relacao]) {
        $valor = $tipo === 'boolean'
            ? "sim_nao(\$registro['{$nome}'] ?? null)"
            : "\$registro['{$nome}'] ?? ''";

        $linhas .= "        <dt class=\"col-sm-3\">{$nome}</dt>\n"
            . "        <dd class=\"col-sm-9\"><?= e({$valor}) ?></dd>\n";
    }

    return strtr(<<<'HTML'
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h1 class="h3 mb-0"><?= e($titulo) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="<?= url('{{PASTA}}') ?>">Voltar</a>
                <a class="btn btn-primary" href="<?= url('{{PASTA}}/editar/' . $registro['id']) ?>">Editar</a>
                <form method="post" action="<?= url('{{PASTA}}/excluir/' . $registro['id']) ?>" onsubmit="return confirm('Excluir este registro?')">
                    <?= campo_csrf() ?>
                    <button class="btn btn-outline-danger" type="submit">Excluir</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <dl class="row g-0 mb-0 p-4">
                <dt class="col-sm-3">id</dt>
                <dd class="col-sm-9"><?= e($registro['id']) ?></dd>
        {{LINHAS}}    </dl>
        </div>
        HTML, [
        '{{PASTA}}'  => $pasta,
        '{{LINHAS}}' => $linhas,
    ]);
}

// =====================================================================
// Geradores: testes
// =====================================================================

function tipoSqlTeste(string $tipo): string
{
    return match ($tipo) {
        'integer', 'boolean' => 'INTEGER',
        'decimal'            => 'REAL',
        default              => 'TEXT',
    } . ' NULL';
}

function valorTeste(string $tipo, bool $atualizado = false): mixed
{
    return match ($tipo) {
        'integer'  => $atualizado ? 2 : 1,
        'decimal'  => $atualizado ? 20.5 : 10.5,
        'boolean'  => $atualizado ? 0 : 1,
        'date'     => $atualizado ? '2026-02-02' : '2026-01-01',
        'datetime' => $atualizado ? '2026-02-02 12:00:00' : '2026-01-01 10:00:00',
        'time'     => $atualizado ? '12:00:00' : '10:00:00',
        default    => $atualizado ? 'Atualizado' : 'Teste',
    };
}

/** Valor de um campo dentro do teste (relacoes usam o id criado no preparar). */
function valorNoTeste(array $campo, bool $atualizado = false): string
{
    if (($campo[2] ?? null) !== null) {
        return $atualizado
            ? "\$this->idsRelacoesAtualizadas['{$campo[0]}']"
            : "\$this->idsRelacoes['{$campo[0]}']";
    }

    return var_export(valorTeste($campo[1], $atualizado), true);
}

function listaDeDados(array $campos, bool $atualizado = false, ?string $zerar = null): string
{
    $linhas = array_map(function (array $campo) use ($atualizado, $zerar): string {
        $valor = $campo[0] === $zerar ? "''" : valorNoTeste($campo, $atualizado);

        return "            '{$campo[0]}' => {$valor},";
    }, $campos);

    return implode("\n", $linhas);
}

/**
 * Monta o array de tabelas que o teste passa para recriarTabelas():
 * primeiro as tabelas pai (versao minima), depois a tabela do recurso.
 *
 * Recriar, em vez de "CREATE TABLE IF NOT EXISTS", garante que o resultado
 * nao dependa da ordem em que as classes de teste rodam.
 */
function tabelasDoTeste(string $tabela, array $campos): string
{
    $linhas = [];

    foreach (relacoesUnicas($campos) as $campo) {
        if ($campo[2] === $tabela) {
            continue;
        }

        $linhas[] = "            '{$campo[2]}' => 'CREATE TABLE {$campo[2]} (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NULL)',";
    }

    $linhas[] = "            '{$tabela}' => \"CREATE TABLE {$tabela} (\n"
        . "                id INTEGER PRIMARY KEY AUTOINCREMENT,\n"
        . '                ' . definicoesDaTabelaDeTeste($tabela, $campos) . "\n"
        . '            )",';

    return implode("\n", $linhas);
}

/** Popula as tabelas pai e guarda os ids usados pelos testes. */
function idsDasRelacoes(array $campos): string
{
    $ids = '';

    foreach (relacoesUnicas($campos) as $campo) {
        $pai = $campo[2];

        $ids .= "\n        Database::conexao()->exec(\"INSERT INTO {$pai} (nome) VALUES ('Opcao 1'), ('Opcao 2')\");\n"
            . "        \$this->idsRelacoes['{$campo[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$pai} ORDER BY id ASC LIMIT 1')->fetchColumn();\n"
            . "        \$this->idsRelacoesAtualizadas['{$campo[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$pai} ORDER BY id DESC LIMIT 1')->fetchColumn();";
    }

    return $ids;
}

function definicoesDaTabelaDeTeste(string $tabela, array $campos): string
{
    $colunas = array_map(
        fn (array $campo): string => "{$campo[0]} " . tipoSqlTeste($campo[1]),
        $campos
    );

    $chaves = array_map(
        fn (array $campo): string => "CONSTRAINT fk_{$tabela}_{$campo[0]} FOREIGN KEY ({$campo[0]}) REFERENCES {$campo[2]}(id)",
        array_filter($campos, fn (array $campo): bool => ($campo[2] ?? null) !== null)
    );

    return implode(",\n                ", array_merge($colunas, $chaves));
}

function testeModeloGerado(string $tabela, string $classe, array $campos): string
{
    $principal = $campos[0][0];
    $conferirRelacoes = '';

    foreach (relacoesUnicas($campos) as $campo) {
        $conferirRelacoes .= "        \$opcoes = \$this->modelo->{$campo[2]}();\n"
            . "        \$this->assertTotal(2, \$opcoes);\n"
            . "        \$this->assertVerdadeiro(in_array(\$this->idsRelacoes['{$campo[0]}'], array_column(\$opcoes, 'id'), true));\n\n";
    }

    return strtr(<<<'PHP'
        <?php

        namespace Testes\Modelos;

        use Modelos\{{CLASSE}};
        use Nucleo\Database;
        use Testes\Suporte\TesteBase;

        class {{CLASSE}}Test extends TesteBase
        {
            private {{CLASSE}} $modelo;
            private array $idsRelacoes = [];
            private array $idsRelacoesAtualizadas = [];

            public function preparar(): void
            {
                // Cada teste monta as proprias tabelas: a ordem em que as
                // classes rodam nao interfere no resultado.
                $this->recriarTabelas([
        {{TABELAS}}
                ]);
        {{IDS_RELACOES}}

                $this->modelo = new {{CLASSE}}();
            }

            public function testeExecutaCrudCompleto(): void
            {
                $dados = [
        {{DADOS}}
                ];

        {{CONFERIR_RELACOES}}        $id = $this->modelo->criar($dados);
                $registro = $this->modelo->buscar($id);

                $this->assertVerdadeiro($id > 0);
                $this->assertIgual($dados['{{PRINCIPAL}}'], $registro['{{PRINCIPAL}}']);
                $this->assertIgual(1, $this->modelo->contar());

                $this->assertVerdadeiro($this->modelo->atualizar($id, ['{{PRINCIPAL}}' => {{ATUALIZADO}}]));
                $this->assertIgual({{ATUALIZADO}}, $this->modelo->buscar($id)['{{PRINCIPAL}}']);

                $this->assertVerdadeiro($this->modelo->excluir($id));
                $this->assertNulo($this->modelo->buscar($id));
            }

            public function testeValidaOsCamposObrigatorios(): void
            {
                $dados = [
        {{DADOS_INVALIDOS}}
                ];

                $erros = $this->modelo->validar($dados);

                $this->assertNaoVazio($erros);
                $this->assertTemChave('{{PRINCIPAL}}', $erros);
            }
        }
        PHP, [
        '{{CLASSE}}'            => $classe,
        '{{TABELA}}'            => $tabela,
        '{{TABELAS}}'           => tabelasDoTeste($tabela, $campos),
        '{{IDS_RELACOES}}'      => idsDasRelacoes($campos),
        '{{CONFERIR_RELACOES}}' => $conferirRelacoes,
        '{{DADOS}}'             => listaDeDados($campos),
        '{{DADOS_INVALIDOS}}'   => listaDeDados($campos, false, $principal),
        '{{PRINCIPAL}}'         => $principal,
        '{{ATUALIZADO}}'        => valorNoTeste($campos[0], true),
    ]);
}

function testeControllerGerado(
    string $tabela,
    string $classe,
    string $recurso,
    string $pasta,
    array $campos,
    string|false $provider
): string {
    $principal = $campos[0][0];

    $entrar = $provider === false
        ? ''
        : "        Sessao::definir(Sessao::chaveAutenticacao(" . ($provider === '' ? '' : "'{$provider}'") . "), 1);\n";

    $rotaLogin = $provider === false
        ? ''
        : ($provider === '' ? 'auth/login' : 'auth-' . str_replace('_', '-', $provider) . '/login');

    $testeLogin = $provider === false ? '' : strtr(<<<'PHP'


            public function testeExigeLoginNasRotas(): void
            {
                $this->limparSessao();

                $semLogin = $this->requisitar('{{PASTA}}');

                $this->assertVerdadeiro($semLogin->redirecionouPara('{{ROTA_LOGIN}}'));
            }
        PHP, ['{{PASTA}}' => $pasta, '{{ROTA_LOGIN}}' => $rotaLogin]);

    return strtr(<<<'PHP'
        <?php

        namespace Testes\Controllers;

        use Modelos\{{CLASSE}};
        use Nucleo\Database;
        use Nucleo\Sessao;
        use Testes\Suporte\TesteBase;

        class {{RECURSO}}ControllerTest extends TesteBase
        {
            private {{CLASSE}} $modelo;
            private array $idsRelacoes = [];
            private array $idsRelacoesAtualizadas = [];

            public function preparar(): void
            {
                $this->limparSessao();

                // Cada teste monta as proprias tabelas: a ordem em que as
                // classes rodam nao interfere no resultado.
                $this->recriarTabelas([
        {{TABELAS}}
                ]);
        {{IDS_RELACOES}}

                $this->modelo = new {{CLASSE}}();
        {{ENTRAR}}    }

            public function testeExecutaRotasDoCrud(): void
            {
                $lista = $this->requisitar('{{PASTA}}');
                $this->assertIgual(200, $lista->status);
                $this->assertContem('{{PRINCIPAL}}', $lista->html);
                $this->assertContem('{{PASTA}}/relatorio', $lista->html);

                $formulario = $this->requisitar('{{PASTA}}/criar');
                $this->assertIgual(200, $formulario->status);
                $this->assertContem('Salvar', $formulario->html);

                $salvar = $this->postar('{{PASTA}}/salvar', [
        {{DADOS}}
                ]);
                $this->assertVerdadeiro($salvar->redirecionouPara('{{PASTA}}/ver/1'));

                $registro = $this->modelo->todos()[0] ?? null;
                $this->assertNaoNulo($registro);

                $id = (int) $registro['id'];
                $this->assertIgual({{VALOR_INICIAL}}, $registro['{{PRINCIPAL}}']);

                $ver = $this->requisitar('{{PASTA}}/ver/' . $id);
                $this->assertIgual(200, $ver->status);
                $this->assertContem('{{PASTA}}/editar/' . $id, $ver->html);

                $editar = $this->requisitar('{{PASTA}}/editar/' . $id);
                $this->assertIgual(200, $editar->status);
                $this->assertContem('Editar {{CLASSE}}', $editar->html);

                $atualizar = $this->postar('{{PASTA}}/atualizar/' . $id, [
        {{DADOS_ATUALIZADOS}}
                ]);
                $this->assertVerdadeiro($atualizar->redirecionouPara('{{PASTA}}/ver/' . $id));
                $this->assertIgual({{VALOR_ATUALIZADO}}, $this->modelo->buscar($id)['{{PRINCIPAL}}']);

                $excluir = $this->postar('{{PASTA}}/excluir/' . $id);
                $this->assertVerdadeiro($excluir->redirecionouPara('{{PASTA}}'));
                $this->assertNulo($this->modelo->buscar($id));
            }

            public function testeRecusaDadosInvalidos(): void
            {
                $resposta = $this->postar('{{PASTA}}/salvar', [
        {{DADOS_INVALIDOS}}
                ]);

                $this->assertVerdadeiro($resposta->redirecionouPara('{{PASTA}}/criar'));
                $this->assertIgual(0, $this->modelo->contar());
            }

            public function testeRecusaFormularioSemToken(): void
            {
                $id = $this->modelo->criar([
        {{DADOS}}
                ]);

                $semToken = $this->postarSemToken('{{PASTA}}/excluir/' . $id);

                $this->assertVerdadeiro($semToken->foiRedirecionado());
                $this->assertNaoNulo($this->modelo->buscar($id));
            }

            public function testeExclusaoNaoAceitaGet(): void
            {
                $id = $this->modelo->criar([
        {{DADOS}}
                ]);

                $porGet = $this->requisitar('{{PASTA}}/excluir/' . $id);

                $this->assertIgual(404, $porGet->status);
                $this->assertNaoNulo($this->modelo->buscar($id));
            }

            public function testeGeraRelatorioEmPdf(): void
            {
                $this->modelo->criar([
        {{DADOS}}
                ]);

                $relatorio = $this->requisitar('{{PASTA}}/relatorio', 'GET', [
                    '{{PRINCIPAL}}' => {{VALOR_INICIAL}},
                ]);

                $this->assertIgual(200, $relatorio->status);
                $this->assertContem('%PDF-1.4', $relatorio->html);
                $this->assertContem('Relatorio de {{TABELA}}', $relatorio->html);
            }{{TESTE_LOGIN}}
        }
        PHP, [
        '{{CLASSE}}'            => $classe,
        '{{RECURSO}}'           => $recurso,
        '{{PASTA}}'             => $pasta,
        '{{TABELA}}'            => $tabela,
        '{{TABELAS}}'           => tabelasDoTeste($tabela, $campos),
        '{{IDS_RELACOES}}'      => idsDasRelacoes($campos),
        '{{ENTRAR}}'            => $entrar,
        '{{DADOS}}'             => listaDeDados($campos),
        '{{DADOS_ATUALIZADOS}}' => listaDeDados($campos, true),
        '{{DADOS_INVALIDOS}}'   => listaDeDados($campos, false, $principal),
        '{{PRINCIPAL}}'         => $principal,
        '{{VALOR_INICIAL}}'     => valorNoTeste($campos[0]),
        '{{VALOR_ATUALIZADO}}'  => valorNoTeste($campos[0], true),
        '{{TESTE_LOGIN}}'       => $testeLogin,
    ]);
}

// =====================================================================
// auth:install
// =====================================================================

function gerarAutenticacao(array $argumentos): void
{
    [$posicionais] = separarOpcoes($argumentos, []);

    if (count($posicionais) > 2) {
        throw new InvalidArgumentException(
            "Uso: php console.php auth:install [Modelo|tabela] [Prefixo]\n"
            . "Exemplos:\n"
            . "  php console.php auth:install\n"
            . "  php console.php auth:install Cliente\n"
            . '  php console.php auth:install Professor professor'
        );
    }

    $modelo   = resolverModeloAutenticacao($posicionais[0] ?? null);
    $provider = normalizarPrefixoAutenticacao($posicionais[1] ?? null);

    $rota         = Nucleo\Autenticacao::rotaBase($provider);
    $vista        = Nucleo\Autenticacao::pastaViews($provider);
    $controlador  = Nucleo\Autenticacao::controlador($provider);
    $chaveAuth    = Nucleo\Sessao::chaveAutenticacao($provider);
    $chaveUsuario = Nucleo\Sessao::chaveUsuario($provider);

    $caminhos = [
        CAMINHO_CONTROLLERS . "/{$controlador}.php",
        CAMINHO_VIEWS . "/{$vista}/login.php",
        CAMINHO_VIEWS . "/{$vista}/registrar.php",
        CAMINHO_RAIZ . "/testes/controllers/{$controlador}Test.php",
    ];

    if ($modelo['novo']) {
        $caminhos[] = $modelo['arquivo'];
    }

    if (Nucleo\Autenticacao::instalado($provider)) {
        throw new RuntimeException(sprintf(
            "A tela de login /%s ja existe (controllers/%s.php).\n"
            . "Para dar login a outra tabela, use um prefixo:\n"
            . '  php console.php auth:install %s <prefixo>',
            $rota,
            $controlador,
            $modelo['classe']
        ));
    }

    conferirCaminhosLivres($caminhos);

    // -------------------------------------------------------------
    // 1. Banco de dados (com desfazer se algo falhar).
    // -------------------------------------------------------------
    $esquemas = lerEsquemas();

    try {
        foreach ([false, true] as $mysql) {
            if ($modelo['novo']) {
                registrarEsquema($modelo['tabela'], esquemaAutenticacaoPadrao($mysql), $mysql);
            } else {
                acrescentarColunasAoEsquema($modelo['tabela'], ['email', 'senha'], $mysql);
            }
        }

        Database::migrar();
        sincronizarColunas($modelo['tabela'], [['email', 'string', null], ['senha', 'string', null]]);
    } catch (Throwable $e) {
        restaurarEsquemas($esquemas);

        throw $e;
    }

    $colunas = colunasDaTabela($modelo['tabela']);

    foreach (['email', 'senha'] as $campo) {
        if (!in_array($campo, $colunas, true)) {
            restaurarEsquemas($esquemas);

            throw new RuntimeException(
                "Nao foi possivel criar a coluna \"{$campo}\" na tabela {$modelo['tabela']}."
            );
        }
    }

    garantirIndiceUnico($modelo['tabela'], 'email');

    $temNome = in_array('nome', $colunas, true);

    // -------------------------------------------------------------
    // 2. Model: cria do zero ou adiciona o trait ao que ja existe.
    // -------------------------------------------------------------
    $modeloOriginal = $modelo['novo'] ? null : (string) file_get_contents($modelo['arquivo']);

    try {
        if (!$modelo['novo']) {
            tornarModeloAutenticavel($modelo['arquivo']);
        }

        $arquivos = [
            CAMINHO_CONTROLLERS . "/{$controlador}.php" => controllerAutenticacaoGerado(
                $modelo['classe'],
                $temNome,
                $controlador,
                $rota,
                $vista,
                $chaveAuth,
                $chaveUsuario
            ),
            CAMINHO_VIEWS . "/{$vista}/login.php"     => viewLoginGerada($rota),
            CAMINHO_VIEWS . "/{$vista}/registrar.php" => viewRegistroGerada($temNome, $rota),
            CAMINHO_RAIZ . "/testes/controllers/{$controlador}Test.php" => testeAutenticacaoGerado(
                $modelo['classe'],
                $modelo['tabela'],
                $controlador,
                $rota,
                $provider,
                $temNome,
                $colunas
            ),
        ];

        if ($modelo['novo']) {
            $arquivos = [$modelo['arquivo'] => modeloAutenticavelGerado($modelo['classe'], $modelo['tabela'])] + $arquivos;
        }

        escreverArquivos($arquivos);
    } catch (Throwable $e) {
        if ($modeloOriginal !== null) {
            file_put_contents($modelo['arquivo'], $modeloOriginal, LOCK_EX);
        }

        restaurarEsquemas($esquemas);

        throw $e;
    }

    echo "Autenticacao aplicada ao modelo {$modelo['classe']}.\n";

    foreach (array_keys($arquivos) as $caminho) {
        echo '  ' . ($modelo['novo'] && $caminho === $modelo['arquivo'] ? '+' : '+') . ' ' . caminhoRelativo($caminho) . "\n";
    }

    if (!$modelo['novo']) {
        echo '  ~ ' . caminhoRelativo($modelo['arquivo']) . "\n";
    }

    echo '  ~ banco/esquema.sqlite.sql, banco/esquema.mysql.sql' . "\n\n";
    echo "Rotas:\n";
    echo "  /{$rota}/registrar   cria uma conta\n";
    echo "  /{$rota}/login       entra\n";
    echo "  /{$rota}/sair        encerra a sessao\n\n";
    echo 'Para exigir login em um CRUD: php console.php scaffold:crud <tabela> ... --auth'
        . ($provider === '' ? '' : "={$provider}") . "\n";
    echo "Rode os testes com: php testes/executar.php {$controlador}\n";
}

function normalizarPrefixoAutenticacao(?string $prefixo): string
{
    if ($prefixo === null || trim($prefixo) === '' || strtolower(trim($prefixo)) === 'auth') {
        return Nucleo\Autenticacao::PADRAO;
    }

    $prefixo = strtolower(trim($prefixo));

    if (!preg_match('/^[a-z][a-z0-9_-]*$/', $prefixo)) {
        throw new InvalidArgumentException(
            "Prefixo de autenticacao invalido: \"{$prefixo}\".\n"
            . 'Use apenas letras minusculas, numeros, "_" e "-", comecando por uma letra.'
        );
    }

    return str_replace('-', '_', $prefixo);
}

function resolverModeloAutenticacao(?string $alvo): array
{
    $alvo ??= 'Usuario';

    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $alvo)) {
        throw new InvalidArgumentException(
            "Modelo invalido: \"{$alvo}\". Informe o nome da classe (Cliente) ou da tabela (clientes)."
        );
    }

    $arquivoUsuario = CAMINHO_MODELOS . '/Usuario.php';

    if (in_array(strtolower($alvo), ['usuario', 'usuarios'], true) && !is_file($arquivoUsuario)) {
        return [
            'classe'  => 'Usuario',
            'tabela'  => 'usuarios',
            'arquivo' => $arquivoUsuario,
            'novo'    => true,
        ];
    }

    $candidatos = array_unique([pascal($alvo), classeDaTabela(strtolower($alvo))]);

    foreach ($candidatos as $classe) {
        $arquivo = CAMINHO_MODELOS . "/{$classe}.php";

        if (!is_file($arquivo)) {
            continue;
        }

        $nomeCompleto = "Modelos\\{$classe}";

        if (!class_exists($nomeCompleto)) {
            throw new RuntimeException("Nao foi possivel carregar o modelo: {$nomeCompleto}");
        }

        $instancia = new $nomeCompleto();

        if (!$instancia instanceof Nucleo\Model) {
            throw new RuntimeException("O modelo {$nomeCompleto} deve herdar de Nucleo\\Model.");
        }

        return [
            'classe'  => $classe,
            'tabela'  => $instancia->tabela(),
            'arquivo' => $arquivo,
            'novo'    => false,
        ];
    }

    $existentes = array_map(
        fn (string $caminho): string => basename($caminho, '.php'),
        glob(CAMINHO_MODELOS . '/*.php') ?: []
    );

    throw new RuntimeException(
        "Modelo nao encontrado: \"{$alvo}\" (procurei por " . implode(' e ', $candidatos) . ").\n"
        . ($existentes === []
            ? "Ainda nao existe nenhum model. Gere um antes:\n  php console.php scaffold:crud clientes nome:string"
            : 'Models disponiveis: ' . implode(', ', $existentes))
    );
}

/**
 * Acrescenta colunas ao CREATE TABLE que ja esta no arquivo de esquema.
 *
 * As colunas entram como NULL de proposito: a tabela pode ja ter registros
 * e o CRUD gerado antes do login continua cadastrando sem e-mail e senha.
 * Quem exige credenciais e o model (criarComSenha) e a tela de cadastro.
 */
function acrescentarColunasAoEsquema(string $tabela, array $colunas, bool $mysql): void
{
    $arquivo  = arquivoEsquema($mysql);
    $conteudo = is_file($arquivo) ? (string) file_get_contents($arquivo) : '';
    $padrao   = padraoCreateTable($tabela);

    if (!preg_match($padrao, $conteudo, $bloco)) {
        throw new RuntimeException(
            "A tabela \"{$tabela}\" nao esta em " . caminhoRelativo($arquivo) . ".\n"
            . 'Gere o CRUD antes: php console.php scaffold:crud ' . $tabela . ' nome:string'
        );
    }

    $original = $bloco[0];
    $novo     = $original;

    foreach ($colunas as $coluna) {
        if (preg_match('/\b' . preg_quote($coluna, '/') . '\b/i', $novo)) {
            continue;
        }

        $definicao = "{$coluna} " . tipoSql('string', $mysql) . ' NULL';

        // Entra logo antes do parentese que fecha a definicao da tabela.
        $novo = (string) preg_replace(
            '/,?\s*\)(\s*(?:ENGINE\s*=\s*[^;]+)?;)$/is',
            ",\n    {$definicao}\n)\$1",
            $novo,
            1
        );
    }

    if ($novo !== $original) {
        file_put_contents($arquivo, str_replace($original, $novo, $conteudo), LOCK_EX);
    }
}

function esquemaAutenticacaoPadrao(bool $mysql): string
{
    return $mysql
        ? "CREATE TABLE IF NOT EXISTS usuarios (\n"
            . "    id INT AUTO_INCREMENT PRIMARY KEY,\n"
            . "    nome VARCHAR(100) NOT NULL,\n"
            . "    email VARCHAR(150) NOT NULL UNIQUE,\n"
            . "    senha VARCHAR(255) NOT NULL,\n"
            . "    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n"
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        : "CREATE TABLE IF NOT EXISTS usuarios (\n"
            . "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    nome TEXT NOT NULL,\n"
            . "    email TEXT NOT NULL UNIQUE,\n"
            . "    senha TEXT NOT NULL,\n"
            . "    criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP\n"
            . ');';
}

/**
 * Acrescenta o trait Autenticavel a um model que ja existe, preservando a
 * indentacao e a ordem dos "use" do arquivo.
 */
function tornarModeloAutenticavel(string $arquivo): void
{
    $conteudo = file_get_contents($arquivo);

    if ($conteudo === false) {
        throw new RuntimeException('Nao foi possivel ler o modelo: ' . caminhoRelativo($arquivo));
    }

    if (!preg_match('/class\s+[A-Za-z]\w*\s+extends\s+[A-Za-z]\w*\s*\R?\s*\{/', $conteudo)) {
        throw new RuntimeException(
            'Nao encontrei a declaracao da classe em ' . caminhoRelativo($arquivo) . '.'
        );
    }

    // 1. import: "use Nucleo\Autenticavel;" antes de "use Nucleo\Model;"
    if (!preg_match('/^use\s+Nucleo\\\\Autenticavel;/m', $conteudo)) {
        $conteudo = preg_replace_callback(
            '/^use\s+Nucleo\\\\Model;/m',
            fn (array $m): string => "use Nucleo\\Autenticavel;\n" . $m[0],
            $conteudo,
            1,
            $trocas
        );

        if ($trocas !== 1 || $conteudo === null) {
            throw new RuntimeException(
                'Nao encontrei "use Nucleo\\Model;" em ' . caminhoRelativo($arquivo) . '.'
            );
        }
    }

    // 2. trait dentro da classe, logo depois da chave de abertura.
    if (!preg_match('/\{\s*use\s+Autenticavel\s*;/', $conteudo)) {
        $conteudo = preg_replace_callback(
            '/(class\s+[A-Za-z]\w*\s+extends\s+[A-Za-z]\w*\s*\R\{\R)/',
            fn (array $m): string => $m[1] . "    use Autenticavel;\n\n",
            $conteudo,
            1,
            $trocas
        );

        if ($trocas !== 1 || $conteudo === null) {
            throw new RuntimeException(
                'Nao consegui ativar o trait Autenticavel em ' . caminhoRelativo($arquivo) . '.'
            );
        }
    }

    // 3. $preenchiveis ganha email e senha, mantendo a indentacao original.
    $padrao = '/^([ \t]*)protected\s+array\s+\$preenchiveis\s*=\s*\[(.*?)\];/ms';

    if (preg_match($padrao, $conteudo, $encontrado)) {
        preg_match_all("/['\"]([a-z][a-z0-9_]*)['\"]/", $encontrado[2], $nomes);
        $campos = $nomes[1] ?? [];

        foreach (['email', 'senha'] as $campo) {
            if (!in_array($campo, $campos, true)) {
                $campos[] = $campo;
            }
        }

        $lista = implode(', ', array_map(fn (string $c): string => "'{$c}'", $campos));

        $conteudo = preg_replace_callback(
            $padrao,
            fn (array $m): string => $m[1] . 'protected array $preenchiveis = [' . $lista . '];',
            $conteudo,
            1
        );
    } else {
        $conteudo = preg_replace_callback(
            '/(    use Autenticavel;\R\R)/',
            fn (array $m): string => $m[1] . "    protected array \$preenchiveis = ['email', 'senha'];\n\n",
            (string) $conteudo,
            1
        );
    }

    file_put_contents($arquivo, (string) $conteudo, LOCK_EX);
}

function modeloAutenticavelGerado(string $classe, string $tabela): string
{
    return strtr(<<<'PHP'
        <?php

        namespace Modelos;

        use Nucleo\Autenticavel;
        use Nucleo\Model;

        class {{CLASSE}} extends Model
        {
            use Autenticavel;

            protected string $tabela = '{{TABELA}}';
            protected array $preenchiveis = ['nome', 'email', 'senha'];
            protected string $ordemPadrao = 'id DESC';
        }
        PHP, [
        '{{CLASSE}}' => $classe,
        '{{TABELA}}' => $tabela,
    ]);
}

function controllerAutenticacaoGerado(
    string $classe,
    bool $temNome,
    string $controlador,
    string $rota,
    string $vista,
    string $chaveAuth,
    string $chaveUsuario
): string {
    return strtr(<<<'PHP'
        <?php

        namespace Controllers;

        use Modelos\{{CLASSE}};
        use Nucleo\Controller;
        use Nucleo\Sessao;
        use Nucleo\Validador;

        class {{CONTROLADOR}} extends Controller
        {
            private {{CLASSE}} $modelo;

            public function __construct()
            {
                $this->modelo = new {{CLASSE}}();
            }

            /** GET e POST /{{ROTA}}/login */
            public function login(): void
            {
                if ($this->ehPost()) {
                    $this->exigirTokenValido();

                    $email = (string) $this->post('email', '');
                    $senha = (string) $this->post('senha', '');

                    $registro = $this->modelo->autenticar($email, $senha);

                    if ($registro !== null) {
                        // Troca o id da sessao: sem isso um id capturado antes
                        // do login continuaria valendo depois ("session fixation").
                        Sessao::regenerar();
                        Sessao::definir('{{CHAVE_AUTH}}', $registro['id']);
                        Sessao::definir('{{CHAVE_USUARIO}}', $registro['id']);

                        $this->mensagem('sucesso', 'Bem-vindo!');
                        $this->redirecionar();
                    }

                    Sessao::guardarEntrada(['email' => $email]);
                    $this->mensagem('erro', 'E-mail ou senha invalidos.');
                    $this->redirecionar('{{ROTA}}/login');
                }

                $this->view('{{VISTA}}/login', ['titulo' => 'Entrar']);
            }

            /** GET e POST /{{ROTA}}/registrar */
            public function registrar(): void
            {
                if ($this->ehPost()) {
                    $this->exigirTokenValido();

                    $senha = (string) $this->post('senha', '');
                    $dados = [{{CAMPO_NOME}}
                        'email' => (string) $this->post('email', ''),
                    ];

                    $erros = (new Validador($dados + ['senha' => $senha])){{REGRA_NOME}}
                        ->obrigatorio('email', 'e-mail')
                        ->email('email', 'e-mail')
                        ->obrigatorio('senha')
                        ->minimo('senha', {{SENHA_MINIMA}})
                        ->erros();

                    if ($erros === [] && $this->modelo->buscarPorEmail($dados['email']) !== null) {
                        $erros['email'] = 'Este e-mail ja esta cadastrado.';
                    }

                    if ($erros !== []) {
                        $this->voltarComErros($erros, '{{ROTA}}/registrar');
                    }

                    // criarComSenha() aplica password_hash(): a senha nunca
                    // chega ao banco em texto puro.
                    $this->modelo->criarComSenha($dados, $senha);

                    $this->mensagem('sucesso', 'Conta criada. Agora entre com seus dados.');
                    $this->redirecionar('{{ROTA}}/login');
                }

                $this->view('{{VISTA}}/registrar', ['titulo' => 'Criar conta']);
            }

            /** GET /{{ROTA}}/sair */
            public function sair(): void
            {
                Sessao::remover('{{CHAVE_AUTH}}');
                Sessao::remover('{{CHAVE_USUARIO}}');
                Sessao::regenerar();

                $this->mensagem('sucesso', 'Sessao encerrada.');
                $this->redirecionar('{{ROTA}}/login');
            }
        }
        PHP, [
        '{{CLASSE}}'         => $classe,
        '{{CONTROLADOR}}'    => $controlador,
        '{{ROTA}}'           => $rota,
        '{{VISTA}}'          => $vista,
        '{{CHAVE_AUTH}}'     => $chaveAuth,
        '{{CHAVE_USUARIO}}'  => $chaveUsuario,
        '{{CAMPO_NOME}}'     => $temNome ? "\n                'nome'  => (string) \$this->post('nome', '')," : '',
        '{{REGRA_NOME}}'     => $temNome ? "\n                    ->obrigatorio('nome')" : '',
        '{{SENHA_MINIMA}}'   => (string) Nucleo\Autenticacao::SENHA_MINIMA,
    ]);
}

function viewLoginGerada(string $rota): string
{
    return strtr(<<<'HTML'
        <div class="row justify-content-center">
            <div class="col-12 col-md-7 col-lg-5">
                <div class="card border-0 shadow-sm p-4">
                    <h1 class="h3 mb-4">Entrar</h1>

                    <form method="post" action="<?= url('{{ROTA}}/login') ?>">
                        <?= campo_csrf() ?>
                        <div class="mb-3">
                            <label class="form-label" for="email">E-mail</label>
                            <input class="form-control" id="email" type="email" name="email" autocomplete="email" value="<?= e(antigo('email')) ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="senha">Senha</label>
                            <input class="form-control" id="senha" type="password" name="senha" autocomplete="current-password" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Entrar</button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        <a href="<?= url('{{ROTA}}/registrar') ?>">Criar uma conta</a>
                    </p>
                </div>
            </div>
        </div>
        HTML, ['{{ROTA}}' => $rota]);
}

function viewRegistroGerada(bool $temNome, string $rota): string
{
    $campoNome = $temNome ? <<<'HTML'
                    <div class="mb-3">
                            <label class="form-label" for="nome">Nome</label>
                            <input class="form-control <?= tem_erro('nome') ? 'is-invalid' : '' ?>" id="nome" type="text" name="nome" autocomplete="name" value="<?= e(antigo('nome')) ?>" required>
                            <?php if ($mensagem = erro_de('nome')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
                        </div>

        HTML : '';

    return strtr(<<<'HTML'
        <div class="row justify-content-center">
            <div class="col-12 col-md-7 col-lg-5">
                <div class="card border-0 shadow-sm p-4">
                    <h1 class="h3 mb-4">Criar conta</h1>

                    <form method="post" action="<?= url('{{ROTA}}/registrar') ?>">
                        <?= campo_csrf() ?>
                        {{CAMPO_NOME}}<div class="mb-3">
                            <label class="form-label" for="email">E-mail</label>
                            <input class="form-control <?= tem_erro('email') ? 'is-invalid' : '' ?>" id="email" type="email" name="email" autocomplete="email" value="<?= e(antigo('email')) ?>" required>
                            <?php if ($mensagem = erro_de('email')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="senha">Senha</label>
                            <input class="form-control <?= tem_erro('senha') ? 'is-invalid' : '' ?>" id="senha" type="password" name="senha" autocomplete="new-password" minlength="{{SENHA_MINIMA}}" required>
                            <?php if ($mensagem = erro_de('senha')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Criar conta</button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        <a href="<?= url('{{ROTA}}/login') ?>">Ja tenho uma conta</a>
                    </p>
                </div>
            </div>
        </div>
        HTML, [
        '{{ROTA}}'         => $rota,
        '{{CAMPO_NOME}}'   => $campoNome,
        '{{SENHA_MINIMA}}' => (string) Nucleo\Autenticacao::SENHA_MINIMA,
    ]);
}

function testeAutenticacaoGerado(
    string $classe,
    string $tabela,
    string $controlador,
    string $rota,
    string $provider,
    bool $temNome,
    array $colunas
): string {
    $definicoes = implode(",\n                        ", array_map(
        fn (string $coluna): string => "{$coluna} TEXT NULL",
        array_values(array_filter($colunas, fn (string $c): bool => $c !== 'id'))
    ));

    $argumentoProvider = $provider === '' ? '' : "'{$provider}'";

    return strtr(<<<'PHP'
        <?php

        namespace Testes\Controllers;

        use Modelos\{{CLASSE}};
        use Nucleo\Database;
        use Nucleo\Sessao;
        use Testes\Suporte\TesteBase;

        class {{CONTROLADOR}}Test extends TesteBase
        {
            private {{CLASSE}} $modelo;

            public function preparar(): void
            {
                $this->limparSessao();

                $this->recriarTabelas([
                    '{{TABELA}}' => "CREATE TABLE {{TABELA}} (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        {{DEFINICOES}}
                    )",
                ]);

                $this->modelo = new {{CLASSE}}();
            }

            public function testeRegistraEntraESai(): void
            {
                $registrar = $this->postar('{{ROTA}}/registrar', [{{DADO_NOME}}
                    'email' => 'ana@example.com',
                    'senha' => 'segredo123',
                ]);
                $this->assertVerdadeiro($registrar->redirecionouPara('{{ROTA}}/login'));

                $conta = $this->modelo->buscarPorEmail('ana@example.com');
                $this->assertNaoNulo($conta);

                // A senha nunca fica em texto puro no banco.
                $this->assertDiferente('segredo123', $conta['senha']);
                $this->assertVerdadeiro(password_verify('segredo123', $conta['senha']));

                $login = $this->postar('{{ROTA}}/login', [
                    'email' => 'ana@example.com',
                    'senha' => 'segredo123',
                ]);
                $this->assertVerdadeiro($login->foiRedirecionado());
                $this->assertVerdadeiro(autenticado({{PROVIDER}}));
                $this->assertIgual($conta['id'], usuario_id({{PROVIDER}}));

                $sair = $this->requisitar('{{ROTA}}/sair');
                $this->assertVerdadeiro($sair->redirecionouPara('{{ROTA}}/login'));
                $this->assertFalso(autenticado({{PROVIDER}}));
            }

            public function testeRecusaSenhaErrada(): void
            {
                $this->modelo->criarComSenha(['email' => 'ana@example.com'], 'segredo123');

                $login = $this->postar('{{ROTA}}/login', [
                    'email' => 'ana@example.com',
                    'senha' => 'errada',
                ]);

                $this->assertVerdadeiro($login->redirecionouPara('{{ROTA}}/login'));
                $this->assertFalso(autenticado({{PROVIDER}}));
            }

            public function testeRecusaCadastroInvalido(): void
            {
                $curta = $this->postar('{{ROTA}}/registrar', [{{DADO_NOME}}
                    'email' => 'ana@example.com',
                    'senha' => '123',
                ]);
                $this->assertVerdadeiro($curta->redirecionouPara('{{ROTA}}/registrar'));

                $semEmail = $this->postar('{{ROTA}}/registrar', [{{DADO_NOME}}
                    'email' => 'nao-e-um-email',
                    'senha' => 'segredo123',
                ]);
                $this->assertVerdadeiro($semEmail->redirecionouPara('{{ROTA}}/registrar'));

                $this->assertIgual(0, $this->modelo->contar());
            }

            public function testeRecusaEmailRepetido(): void
            {
                $this->modelo->criarComSenha(['email' => 'ana@example.com'], 'segredo123');

                $repetido = $this->postar('{{ROTA}}/registrar', [{{DADO_NOME}}
                    'email' => 'ana@example.com',
                    'senha' => 'outrasenha',
                ]);

                $this->assertVerdadeiro($repetido->redirecionouPara('{{ROTA}}/registrar'));
                $this->assertIgual(1, $this->modelo->contar());
            }

            public function testeRecusaLoginSemToken(): void
            {
                $this->modelo->criarComSenha(['email' => 'ana@example.com'], 'segredo123');

                $login = $this->postarSemToken('{{ROTA}}/login', [
                    'email' => 'ana@example.com',
                    'senha' => 'segredo123',
                ]);

                $this->assertVerdadeiro($login->foiRedirecionado());
                $this->assertFalso(autenticado({{PROVIDER}}));
            }
        }
        PHP, [
        '{{CLASSE}}'      => $classe,
        '{{CONTROLADOR}}' => $controlador,
        '{{TABELA}}'      => $tabela,
        '{{DEFINICOES}}'  => $definicoes,
        '{{ROTA}}'        => $rota,
        '{{PROVIDER}}'    => $argumentoProvider,
        '{{DADO_NOME}}'   => $temNome ? "\n            'nome'  => 'Ana'," : '',
    ]);
}

// =====================================================================
// relatorio:pdf
// =====================================================================

function gerarRelatorioPdf(array $argumentos): void
{
    [$posicionais] = separarOpcoes($argumentos, []);

    if ($posicionais === [] || count($posicionais) > 2) {
        throw new InvalidArgumentException(
            "Uso: php console.php relatorio:pdf <modelo|tabela> [arquivo.pdf]\n"
            . 'Exemplo: php console.php relatorio:pdf produtos relatorios/produtos.pdf'
        );
    }

    $modelo    = resolverModeloRelatorio($posicionais[0]);
    $registros = $modelo['instancia']->todos();
    $colunas   = $registros === [] ? colunasDaTabela($modelo['tabela']) : array_keys($registros[0]);
    $arquivo   = caminhoRelatorioPdf($posicionais[1] ?? "relatorios/{$modelo['tabela']}.pdf");

    RelatorioPdf::gerar("Relatorio de {$modelo['tabela']}", $colunas, $registros, $arquivo);

    echo 'Relatorio PDF criado: ' . caminhoRelativo($arquivo) . "\n";
    echo '  ' . count($registros) . " registro(s).\n";
}

function resolverModeloRelatorio(string $alvo): array
{
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $alvo)) {
        throw new InvalidArgumentException("Modelo ou tabela invalido: \"{$alvo}\".");
    }

    $candidatos = array_unique([pascal($alvo), classeDaTabela(strtolower($alvo))]);

    foreach ($candidatos as $classe) {
        $arquivo = CAMINHO_MODELOS . "/{$classe}.php";

        if (!is_file($arquivo)) {
            continue;
        }

        $nomeCompleto = "Modelos\\{$classe}";

        if (!class_exists($nomeCompleto)) {
            throw new RuntimeException("Nao foi possivel carregar o modelo: {$nomeCompleto}");
        }

        $instancia = new $nomeCompleto();

        if (!$instancia instanceof Nucleo\Model) {
            throw new RuntimeException("O modelo {$nomeCompleto} deve herdar de Nucleo\\Model.");
        }

        return [
            'classe'    => $classe,
            'tabela'    => $instancia->tabela(),
            'instancia' => $instancia,
        ];
    }

    $existentes = array_map(
        fn (string $caminho): string => basename($caminho, '.php'),
        glob(CAMINHO_MODELOS . '/*.php') ?: []
    );

    throw new RuntimeException(
        "Modelo nao encontrado: \"{$alvo}\" (procurei por " . implode(' e ', $candidatos) . ").\n"
        . ($existentes === []
            ? 'Gere um CRUD antes: php console.php scaffold:crud produtos nome:string'
            : 'Models disponiveis: ' . implode(', ', $existentes))
    );
}

function caminhoRelatorioPdf(string $caminho): string
{
    if (trim($caminho) === '') {
        throw new InvalidArgumentException('O arquivo do relatorio nao pode ser vazio.');
    }

    if ($caminho[0] === '/') {
        return $caminho;
    }

    $completo = CAMINHO_RAIZ . '/' . ltrim($caminho, '/');
    $pasta    = dirname($completo);

    if (!is_dir($pasta)) {
        mkdir($pasta, 0777, true);
    }

    $real = realpath($pasta);

    if ($real === false || !str_starts_with($real . '/', CAMINHO_RAIZ . '/')) {
        throw new InvalidArgumentException(
            "O relatorio deve ser gravado dentro do projeto: \"{$caminho}\" sai da pasta raiz."
        );
    }

    return $real . '/' . basename($completo);
}
