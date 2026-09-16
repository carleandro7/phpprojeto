<?php

/**
 * Console do framework.
 *
 *     php console.php scaffold:crud tabela campo:tipo ...
 *     php console.php scaffold:campo tabela campo:tipo ...
 *     php console.php scaffold:pesquisa tabela campo ...
 *     php console.php scaffold:paginacao tabela [--por-pagina=N]
 *     php console.php auth:install [Modelo] [Prefixo]
 *     php console.php auth:perfis perfil1,perfil2 [Modelo|prefixo]
 *     php console.php relatorio:pdf modelo|tabela [arquivo.pdf]
 *     php console.php db:semear                  (roda banco/semear.php)
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

const TIPOS_ACEITOS = [
    'string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'time',
    'arquivo', 'imagem',
];

/** Tipos em que a coluna guarda o caminho de um arquivo enviado. */
const TIPOS_ARQUIVO = ['arquivo', 'imagem'];
const CAMPOS_RESERVADOS = ['id', 'criado_em'];

$comando    = $argv[1] ?? '';
$argumentos = array_slice($argv, 2);
$detalhado  = in_array('-v', $argumentos, true);
$argumentos = array_values(array_filter($argumentos, fn (string $a): bool => $a !== '-v'));

try {
    match ($comando) {
        'scaffold:crud'     => gerarCrud($argumentos),
        'scaffold:campo'    => gerarCampo($argumentos),
        'scaffold:pesquisa' => gerarPesquisa($argumentos),
        'scaffold:paginacao' => gerarPaginacao($argumentos),
        'auth:install'      => gerarAutenticacao($argumentos),
        'auth:perfis'       => gerarPerfis($argumentos),
        'relatorio:pdf'     => gerarRelatorioPdf($argumentos),
        'db:semear'         => semearBanco($argumentos),
        default             => ajuda($comando),
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
      php console.php scaffold:campo <tabela> <campo:tipo> ... [--remover]
      php console.php scaffold:pesquisa <tabela> <campo> ... [--remover]
      php console.php scaffold:paginacao <tabela> [--por-pagina=N] [--remover]
      php console.php auth:install [Modelo|tabela] [Prefixo]
      php console.php auth:perfis <perfil1,perfil2,...> [Modelo|prefixo]
      php console.php relatorio:pdf <modelo|tabela> [arquivo.pdf]
      php console.php db:semear                      roda banco/semear.php
      php console.php db:semear <tabela> [quantidade]  atalho: enche uma tabela

    Tipos de campo:
      string  text  integer  decimal  boolean  date  datetime  time

    Relacao 1:N (a tabela pai precisa existir antes):
      php console.php scaffold:crud matriculas nome:string turma_id:belongs_to=turmas

    Telas de login (o prefixo sai do proprio modelo):
      php console.php auth:install                cria o model Usuario e o login unico em /auth
      php console.php auth:install Professor      usa o model Professor no login /auth-professor
      php console.php auth:install Cliente auth   usa o model Cliente no login unico em /auth

    Opcoes do scaffold:crud:
      --auth[=prefixo]   exige login em todas as rotas do recurso
      --modelo=Nome      define o nome da classe do model
      --sem-menu         nao adiciona o recurso a configuracoes/menu.php

    Opcoes do scaffold:campo (altera um CRUD que ja existe):
      --obrigatorio      o campo novo nasce com a regra obrigatorio()
      --remover          tira o campo do CRUD e APAGA a coluna do banco
      --forcar           nao pergunta antes de apagar a coluna

    Opcao do scaffold:pesquisa:
      --remover          tira o formulario de pesquisa do index

    Opcoes do scaffold:paginacao (quebra a listagem em paginas):
      --por-pagina=N     quantos registros por pagina (padrao 20)
      --remover          volta a listar tudo de uma vez

    Opcoes do auth:perfis (quem pode o que, dentro de uma tela de login):
      --remover          tira os perfis e APAGA a coluna perfil do banco
      --forcar           nao pergunta antes de apagar

    Opcoes do db:semear (os dados ficam em banco/semear.php):
      --limpar           apaga os registros atuais antes de semear
      --tudo             no atalho, enche todas as tabelas
      --semente=N        repete sempre os mesmos dados (util em sala)

    Opcao geral:
      -v                 mostra os detalhes tecnicos quando algo falha

    Exemplos:
      php console.php scaffold:crud produtos nome:string preco:decimal --auth
      php console.php scaffold:campo produtos peso:decimal
      php console.php scaffold:campo produtos categoria_id:belongs_to=categorias
      php console.php scaffold:pesquisa produtos nome preco
      php console.php scaffold:paginacao produtos --por-pagina=15
      php console.php db:semear
      php console.php db:semear produtos 30
      php console.php auth:install Professor
      php console.php auth:perfis admin,coordenador,professor
      php console.php scaffold:crud aulas titulo:string --auth=professor

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

    // O primeiro campo vira o titulo da listagem, a regra obrigatoria do
    // model e o valor conferido pelos testes gerados. Um caminho de arquivo
    // nao serve para nada disso.
    if (in_array($campos[0][1], TIPOS_ARQUIVO, true) && $campos[0][2] === null) {
        throw new InvalidArgumentException(
            "O primeiro campo nao pode ser do tipo {$campos[0][1]}: e dele que saem o titulo da\n"
            . "listagem, a regra obrigatoria do model e as asercoes dos testes.\n"
            . "Comece por um texto:\n"
            . "  php console.php scaffold:crud {$tabela} nome:string {$campos[0][0]}:{$campos[0][1]}"
        );
    }

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
    if ($provider !== false) {
        $provider = resolverProviderDoCrud($provider);
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
        registrarEsquema($tabela, esquema($tabela, $campos));

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

    echo '  ~ banco/esquema.sql' . "\n";

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

    $arquivo = arquivoEsquema();

    if (is_file($arquivo) && preg_match(padraoCreateTable($tabela), (string) file_get_contents($arquivo))) {
        return true;
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

    // "auth" ja volta como provider padrao de normalizarPrefixoAutenticacao().
    return normalizarPrefixoAutenticacao((string) $valor);
}

/**
 * Confere se a tela de login pedida por --auth existe.
 *
 * "--auth" sozinho se ajusta ao unico login instalado, do mesmo jeito que
 * exigirAutenticacao() sem argumento: um projeto que so rodou
 * "auth:install Professor" nao precisa escrever --auth=professor.
 */
function resolverProviderDoCrud(string $provider): string
{
    if (Nucleo\Autenticacao::instalado($provider)) {
        return $provider;
    }

    $instalados = Nucleo\Autenticacao::providers();

    if ($provider === Nucleo\Autenticacao::PADRAO && count($instalados) === 1) {
        return $instalados[0];
    }

    $rota     = fn (string $p): string => '/' . Nucleo\Autenticacao::rotaBase($p);
    $sugestao = '  php console.php auth:install'
        . ($provider === Nucleo\Autenticacao::PADRAO ? '' : ' ' . pascal($provider));

    if ($instalados === []) {
        throw new RuntimeException(
            'A tela de login ' . $rota($provider) . " ainda nao existe.\n"
            . "Instale-a antes:\n" . $sugestao
        );
    }

    throw new RuntimeException(
        'A tela de login ' . $rota($provider) . " nao existe.\n"
        . 'Telas instaladas: ' . implode(', ', array_map($rota, $instalados)) . ".\n"
        . "Use --auth=<prefixo> com uma delas ou instale a nova:\n" . $sugestao
    );
}

// =====================================================================
// Banco de dados
// =====================================================================

function sincronizarColunas(string $tabela, array $campos): void
{
    $pdo        = Database::conexao();
    $existentes = colunasDaTabela($tabela);

    foreach ($campos as [$nome, $tipo]) {
        if (in_array($nome, $existentes, true)) {
            continue;
        }

        // Colunas novas entram como NULL: a tabela pode ja ter registros.
        $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$nome}` " . tipoSql($tipo) . ' NULL');
    }
}

function colunasDaTabela(string $tabela): array
{
    $colunas = [];

    foreach (Database::conexao()->query("SHOW COLUMNS FROM `{$tabela}`") as $coluna) {
        $colunas[] = $coluna['Field'];
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
    $indice = "idx_{$tabela}_{$coluna}_unico";

    try {
        if ($pdo->query("SHOW INDEX FROM `{$tabela}` WHERE Key_name = '{$indice}'")->fetch() !== false) {
            return true;
        }

        $pdo->exec("CREATE UNIQUE INDEX `{$indice}` ON `{$tabela}` (`{$coluna}`)");

        return true;
    } catch (Throwable $e) {
        echo "AVISO: nao foi possivel criar o indice unico de {$tabela}.{$coluna}.\n";
        echo '       ' . $e->getMessage() . "\n";
        echo "       Provavelmente ja existem valores repetidos. Corrija-os e rode de novo.\n";

        return false;
    }
}

/**
 * Nome de tabela ou coluna entre crases, para o SQL gerado.
 *
 * A lista de palavras reservadas do MySQL cresce a cada versao, e nela cabem
 * nomes que qualquer sistema usa — "rank", "grupo", "manual", "order". Sem as
 * crases, um campo com um desses nomes quebraria o CREATE TABLE, o INSERT e
 * o WHERE.
 */
function sqlNome(string $nome): string
{
    return Nucleo\Sql::proteger($nome, 'identificador');
}

function tipoSql(string $tipo): string
{
    return match ($tipo) {
        'integer'  => 'INT',
        'decimal'  => 'DECIMAL(12,2)',
        'boolean'  => 'TINYINT(1)',
        'date'     => 'DATE',
        'datetime' => 'DATETIME',
        'time'     => 'TIME',
        'text'     => 'TEXT',
        // arquivo e imagem: a coluna guarda o CAMINHO do arquivo dentro de
        // views/uploads. O arquivo em si vai para o disco, nunca para o banco.
        default    => 'VARCHAR(255)',
    };
}

function esquema(string $tabela, array $campos): string
{
    $colunas = array_map(
        fn (array $campo): string => sqlNome($campo[0]) . ' ' . tipoSql($campo[1]) . ' NULL',
        $campos
    );

    $chaves = array_map(
        fn (array $campo): string => "CONSTRAINT fk_{$tabela}_{$campo[0]} FOREIGN KEY ("
            . sqlNome($campo[0]) . ') REFERENCES ' . sqlNome($campo[2]) . '(`id`)',
        array_filter($campos, fn (array $campo): bool => ($campo[2] ?? null) !== null)
    );

    $definicoes = implode(",\n    ", array_merge($colunas, $chaves));

    return 'CREATE TABLE IF NOT EXISTS ' . sqlNome($tabela) . " (\n"
        . "    `id` INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "    {$definicoes}\n"
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
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
function registrarEsquema(string $tabela, string $definicao): void
{
    $arquivo  = arquivoEsquema();
    $conteudo = is_file($arquivo) ? (string) file_get_contents($arquivo) : '';
    $padrao   = padraoCreateTable($tabela);

    if (preg_match($padrao, $conteudo)) {
        $conteudo = preg_replace_callback($padrao, fn (): string => rtrim($definicao), $conteudo, 1);
    } else {
        $conteudo = rtrim($conteudo) . "\n\n" . rtrim($definicao) . "\n";
    }

    file_put_contents($arquivo, ltrim((string) $conteudo, "\n"), LOCK_EX);
}

function arquivoEsquema(): string
{
    return CAMINHO_BANCO . '/esquema.sql';
}

/**
 * Copia do esquema atual, para desfazer se algo falhar no meio do comando.
 *
 * @return array<string,string>
 */
function lerEsquemas(): array
{
    $arquivo = arquivoEsquema();

    return is_file($arquivo) ? [$arquivo => (string) file_get_contents($arquivo)] : [];
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
        // O valor de um campo de arquivo nao vem do formulario: quem confere
        // o upload e a classe Nucleo\Arquivo, dentro do controller.
        if ($relacao === null && in_array($tipo, TIPOS_ARQUIVO, true)) {
            continue;
        }

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

/** Os campos do recurso que guardam o caminho de um arquivo enviado. */
function camposDeArquivo(array $campos): array
{
    return array_values(array_filter(
        $campos,
        fn (array $campo): bool => ($campo[2] ?? null) === null
            && in_array($campo[1], TIPOS_ARQUIVO, true)
    ));
}

/**
 * Nome da variavel que guarda o upload de uma coluna: foto -> $arquivoFoto.
 *
 * O prefixo existe para o nome da coluna nunca esbarrar em uma variavel que
 * o metodo ja usa ($dados, $erros, $registro, $id...).
 */
function variavelDoArquivo(string $nome): string
{
    return 'arquivo' . pascal($nome);
}

/**
 * Os trechos que o controller ganha quando o recurso tem campo de arquivo.
 *
 * Sao cinco pedacos, em ordem de execucao:
 *
 *   receber     pega o arquivo do formulario (null quando ninguem escolheu)
 *   conferir    junta o problema do upload aos erros da validacao
 *   gravar      move para o disco, so depois que tudo passou
 *   substituir  igual ao gravar, e ainda apaga o arquivo anterior
 *   apagar      tira do disco o arquivo do registro excluido
 *
 * Sem campo de arquivo, todos voltam vazios e o controller sai exatamente
 * como sempre foi.
 */
function trechoDeArquivos(array $arquivos, string $parte, string $pasta): string
{
    if ($arquivos === []) {
        return '';
    }

    $blocos = [];

    foreach ($arquivos as [$nome, $tipo]) {
        $var = '$' . variavelDoArquivo($nome);

        $blocos[] = match ($parte) {
            'receber' => sprintf(
                "        %s = \$this->%s('%s');",
                $var,
                $tipo === 'imagem' ? 'imagem' : 'arquivo',
                $nome
            ),
            'conferir' => "        if ({$var} !== null && (\$problema = {$var}->problema()) !== null) {\n"
                . "            \$erros['{$nome}'] = \$problema;\n"
                . '        }',
            'gravar' => "        if ({$var} !== null) {\n"
                . "            \$dados['{$nome}'] = {$var}->salvar('{$pasta}');\n"
                . '        }',
            'substituir' => "        if ({$var} !== null) {\n"
                . "            \$dados['{$nome}'] = {$var}->salvar('{$pasta}');\n"
                . "            Arquivo::apagar(\$registro['{$nome}'] ?? null);\n"
                . '        }',
            'apagar' => "        Arquivo::apagar(\$registro['{$nome}'] ?? null);",
            default  => '',
        };
    }

    // Os "receber" e os "apagar" sao uma linha cada e ficam juntos; os
    // outros sao blocos if e ganham uma linha em branco entre eles.
    $juntos = in_array($parte, ['receber', 'apagar'], true) ? "\n" : "\n\n";
    $texto  = implode($juntos, $blocos);

    $comentario = match ($parte) {
        'gravar'     => "        // O arquivo so vai para o disco depois que o resto passou.\n",
        'substituir' => "        // O arquivo novo substitui o anterior, que sai do disco.\n",
        'apagar'     => "        // O registro saiu; o arquivo dele nao fica ocupando disco.\n",
        default      => '',
    };

    return "\n" . $comentario . $texto . "\n";
}

function controllerGerado(
    string $tabela,
    string $classe,
    string $recurso,
    string $pasta,
    array $campos,
    string|false $provider
): string {
    // Campo de arquivo nao entra no $dados: o valor dele nao vem do $_POST,
    // e sim do $_FILES, depois de conferido e gravado em disco.
    $arquivos = camposDeArquivo($campos);

    $dados = implode("\n", array_map(
        fn (array $c): string => "            '{$c[0]}' => \$this->post('{$c[0]}'),",
        array_values(array_filter(
            $campos,
            fn (array $c): bool => !in_array($c, $arquivos, true)
        ))
    ));

    $importarArquivo = $arquivos === [] ? '' : "\nuse Nucleo\\Arquivo;";
    $receber         = trechoDeArquivos($arquivos, 'receber', $pasta);
    $conferir        = trechoDeArquivos($arquivos, 'conferir', $pasta);
    $gravarNovo      = trechoDeArquivos($arquivos, 'gravar', $pasta);
    $gravarEdicao    = trechoDeArquivos($arquivos, 'substituir', $pasta);
    $apagarArquivos  = trechoDeArquivos($arquivos, 'apagar', $pasta);

    // Com arquivo, o atualizar() e o excluir() precisam do registro antigo
    // para saber qual arquivo sai do disco.
    $buscarAtual = $arquivos === []
        ? "        if (!\$this->modelo->existe(\$id)) {\n            \$this->naoEncontrado();\n        }"
        : "        \$registro = \$this->modelo->buscar(\$id);\n\n        if (\$registro === null) {\n            \$this->naoEncontrado();\n        }";

    $buscarParaExcluir = $arquivos === [] ? '' : "\n        \$registro = \$this->modelo->buscar(\$id);\n";

    $relacoes = '';

    foreach (relacoesUnicas($campos) as $campo) {
        $relacoes .= "\n            '{$campo[2]}' => \$this->modelo->{$campo[2]}(),";
    }

    $guarda = $provider === false
        ? ''
        : '        $this->exigirAutenticacao(' . ($provider === '' ? '' : "'{$provider}'") . ");\n\n";

    $filtros = '';

    foreach (array_merge([['id', 'integer', null]], $campos) as [$nome, $tipo, $relacao]) {
        $filtros .= filtroRelatorioGerado($nome, $tipo, $relacao);
    }

    $colunas = implode(', ', array_merge(["'id'"], array_map(fn (array $c): string => "'{$c[0]}'", $campos)));

    return strtr(<<<'PHP'
        <?php

        namespace Controllers;

        use Modelos\{{CLASSE}};{{IMPORTAR_ARQUIVO}}
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
        {{RECEBER}}
                $erros = $this->modelo->validar($dados);
        {{CONFERIR}}
                if ($erros !== []) {
                    $this->voltarComErros($erros, '{{PASTA}}/criar');
                }
        {{GRAVAR_NOVO}}
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

        {{BUSCAR_ATUAL}}

                $dados = [
        {{DADOS}}
                ];
        {{RECEBER}}
                $erros = $this->modelo->validar($dados, $id);
        {{CONFERIR}}
                if ($erros !== []) {
                    $this->voltarComErros($erros, '{{PASTA}}/editar/' . $id);
                }
        {{GRAVAR_EDICAO}}
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

        {{FILTROS}}        $sql = 'SELECT * FROM ' . $this->modelo->tabelaProtegida();

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
        {{BUSCAR_PARA_EXCLUIR}}
                if (!$this->modelo->excluir($id)) {
                    $this->naoEncontrado();
                }
        {{APAGAR_ARQUIVOS}}
                $this->mensagem('sucesso', '{{CLASSE}} excluido com sucesso.');
                $this->redirecionar('{{PASTA}}');
            }
        }
        PHP, [
        '{{CLASSE}}'              => $classe,
        '{{RECURSO}}'             => $recurso,
        '{{PASTA}}'               => $pasta,
        '{{TABELA}}'              => $tabela,
        '{{GUARDA}}'              => $guarda,
        '{{DADOS}}'               => $dados,
        '{{RELACOES}}'            => $relacoes,
        '{{FILTROS}}'             => $filtros,
        '{{COLUNAS}}'             => $colunas,
        '{{CAMPO}}'               => $campos[0][0],
        '{{IMPORTAR_ARQUIVO}}'    => $importarArquivo,
        '{{RECEBER}}'             => $receber,
        '{{CONFERIR}}'            => $conferir,
        '{{GRAVAR_NOVO}}'         => $gravarNovo,
        '{{GRAVAR_EDICAO}}'       => $gravarEdicao,
        '{{BUSCAR_ATUAL}}'        => $buscarAtual,
        '{{BUSCAR_PARA_EXCLUIR}}' => $buscarParaExcluir,
        '{{APAGAR_ARQUIVOS}}'     => $apagarArquivos,
    ]);
}

/**
 * Um filtro do relatorio: le a coluna da query string e, se veio alguma
 * coisa, acrescenta a condicao ao WHERE.
 *
 * Texto procura pelo trecho (LIKE); numero, data e chave estrangeira
 * comparam o valor exato. O scaffold:crud e o scaffold:campo geram por
 * aqui, entao o filtro sai igual nos dois.
 */
function filtroRelatorioGerado(string $nome, string $tipo, ?string $relacao): string
{
    $exato = $nome === 'id' || $relacao !== null || in_array($tipo, ['integer', 'decimal', 'boolean'], true);

    $coluna = sqlNome($nome);

    return "        \$filtro = \$this->get('{$nome}');\n"
        . "        if (is_scalar(\$filtro) && (string) \$filtro !== '') {\n"
        . ($exato
            ? "            \$condicoes[] = '{$coluna} = ?';\n            \$parametros[] = \$filtro;\n"
            : "            \$condicoes[] = '{$coluna} LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE;\n            \$parametros[] = Sql::comoLike((string) \$filtro);\n")
        . "        }\n\n";
}

// =====================================================================
// Geradores: views
// =====================================================================

/**
 * Como o valor de uma coluna aparece na listagem e na tela de detalhe.
 *
 * Texto passa por e() contra XSS; boolean vira Sim/Nao; arquivo vira link e
 * imagem vira miniatura — esses dois ja devolvem HTML pronto, entao nao
 * passam por e() de novo.
 *
 * O scaffold:crud e o scaffold:campo geram por aqui, para a coluna sair
 * igual tendo sido criada de um jeito ou do outro.
 */
function valorNaTela(string $nome, string $tipo, bool $detalhe = false): string
{
    $campo = "\$registro['{$nome}'] ?? null";

    return match ($tipo) {
        'boolean' => "<?= e(sim_nao({$campo})) ?>",
        'imagem'  => '<?= miniatura(' . $campo . ($detalhe ? ', 160' : '') . ') ?>',
        'arquivo' => "<?= link_arquivo({$campo}) ?>",
        default   => "<?= e(\$registro['{$nome}'] ?? '') ?>",
    };
}

function indexGerado(string $tabela, string $pasta, array $campos): string
{
    $cabecalhos = '';
    $celulas    = '';

    foreach ($campos as [$nome, $tipo, $relacao]) {
        $cabecalhos .= "                <th>{$nome}</th>\n";
        $celulas .= '                <td>' . valorNaTela($nome, $tipo) . "</td>\n";
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

    foreach ($campos as $campo) {
        $blocos[] = campoFormularioGerado($campo);
    }

    // Sem enctype o navegador manda so o NOME do arquivo, e nada chega ao
    // $_FILES. E o erro mais comum de formulario com upload.
    $enctype = temCampoDeArquivo($campos) ? ' enctype="multipart/form-data"' : '';

    return strtr(<<<'HTML'
        <div class="mb-4">
            <h1 class="h3 mb-1"><?= e($titulo) ?></h1>
            <p class="text-secondary mb-0">Preencha os dados abaixo.</p>
        </div>

        <form class="card border-0 shadow-sm p-4" method="post"{{ENCTYPE}} action="<?= url('{{PASTA}}/' . ($registro ? 'atualizar/' . $registro['id'] : 'salvar')) ?>">
            <?= campo_csrf() ?>
            <div class="row g-3">
        {{CAMPOS}}    </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-outline-secondary" href="<?= url('{{PASTA}}') ?>">Cancelar</a>
            </div>
        </form>
        HTML, [
        '{{PASTA}}'   => $pasta,
        '{{ENCTYPE}}' => $enctype,
        '{{CAMPOS}}'  => implode('', $blocos),
    ]);
}

/** Algum campo do recurso guarda arquivo? */
function temCampoDeArquivo(array $campos): bool
{
    foreach ($campos as [$nome, $tipo, $relacao]) {
        if ($relacao === null && in_array($tipo, TIPOS_ARQUIVO, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Escolhe o campo do formulario conforme o tipo da coluna.
 *
 * @param array{0:string,1:string,2:?string} $campo nome, tipo e tabela pai
 */
function campoFormularioGerado(array $campo): string
{
    [$nome, $tipo, $relacao] = $campo;

    if ($relacao !== null) {
        return campoRelacao($nome, $relacao);
    }

    if (in_array($tipo, TIPOS_ARQUIVO, true)) {
        return campoArquivo($nome, $tipo);
    }

    return $tipo === 'boolean' ? campoBoolean($nome) : campoSimples($nome, $tipo);
}

/**
 * Campo de envio de arquivo.
 *
 * Na edicao ele nasce vazio de proposito: um <input type="file"> nao pode ser
 * preenchido pelo servidor (seria um jeito de o site ler arquivos da maquina
 * de quem visita). Por isso a tela mostra o arquivo atual ao lado e so troca
 * quando alguem escolhe outro.
 */
function campoArquivo(string $nome, string $tipo): string
{
    $imagem = $tipo === 'imagem';

    return strtr(<<<'HTML'
        <div class="col-md-6">
            <label class="form-label" for="{{NOME}}">{{NOME}}</label>
            <input class="form-control <?= tem_erro('{{NOME}}') ? 'is-invalid' : '' ?>" id="{{NOME}}" type="file" name="{{NOME}}"{{ACEITA}}>
            <?php if ($registro && ($registro['{{NOME}}'] ?? '') !== ''): ?>
                <div class="form-text d-flex align-items-center gap-2">{{ATUAL}} Envie outro para substituir.</div>
            <?php endif ?>
            <?php if ($mensagem = erro_de('{{NOME}}')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
        </div>

    HTML, [
        '{{NOME}}'   => $nome,
        '{{ACEITA}}' => $imagem ? ' accept="image/*"' : '',
        '{{ATUAL}}'  => $imagem
            ? "<?= miniatura(\$registro['{$nome}'], 32) ?>"
            : "Atual: <?= link_arquivo(\$registro['{$nome}']) ?> —",
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
        $linhas .= "        <dt class=\"col-sm-3\">{$nome}</dt>\n"
            . '        <dd class="col-sm-9">' . valorNaTela($nome, $tipo, true) . "</dd>\n";
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

function valorTeste(string $tipo, bool $atualizado = false, string $nome = ''): mixed
{
    // Um campo chamado "email" ganha a regra email() no model gerado: com o
    // "Teste" generico o proprio teste gerado nasceria falhando na validacao.
    if ($nome === 'email') {
        return $atualizado ? 'maria@example.com' : 'ana@example.com';
    }

    // A coluna guarda o caminho do arquivo; nos testes basta um caminho.
    if (in_array($tipo, TIPOS_ARQUIVO, true)) {
        return $atualizado ? 'uploads/teste/depois.png' : 'uploads/teste/antes.png';
    }

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

    return var_export(valorTeste($campo[1], $atualizado, $campo[0]), true);
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

        $linhas[] = "            '{$campo[2]}' => 'CREATE TABLE " . sqlNome($campo[2])
            . " (`id` INT AUTO_INCREMENT PRIMARY KEY, `nome` VARCHAR(255) NULL)',";
    }

    $linhas[] = "            '{$tabela}' => \"CREATE TABLE " . sqlNome($tabela) . " (\n"
        . "                `id` INT AUTO_INCREMENT PRIMARY KEY,\n"
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

        $tabelaPai = sqlNome($pai);

        $ids .= "\n        Database::conexao()->exec(\"INSERT INTO {$tabelaPai} (`nome`) VALUES ('Opcao 1'), ('Opcao 2')\");\n"
            . "        \$this->idsRelacoes['{$campo[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$tabelaPai} ORDER BY id ASC LIMIT 1')->fetchColumn();\n"
            . "        \$this->idsRelacoesAtualizadas['{$campo[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$tabelaPai} ORDER BY id DESC LIMIT 1')->fetchColumn();";
    }

    return $ids;
}

function definicoesDaTabelaDeTeste(string $tabela, array $campos): string
{
    $colunas = array_map(
        fn (array $campo): string => sqlNome($campo[0]) . ' ' . tipoSql($campo[1]) . ' NULL',
        $campos
    );

    $chaves = array_map(
        fn (array $campo): string => "CONSTRAINT fk_{$tabela}_{$campo[0]} FOREIGN KEY ("
            . sqlNome($campo[0]) . ') REFERENCES ' . sqlNome($campo[2]) . '(`id`)',
        array_filter($campos, fn (array $campo): bool => ($campo[2] ?? null) !== null)
    );

    return implode(",\n                ", array_merge($colunas, $chaves));
}

/**
 * As linhas que conferem se o metodo da tabela pai devolve as opcoes do
 * <select>. O scaffold:crud e o scaffold:campo geram por aqui.
 */
function assercoesDeRelacaoGeradas(array $campos): string
{
    $linhas = '';

    foreach (relacoesUnicas($campos) as $campo) {
        $linhas .= "        \$opcoes = \$this->modelo->{$campo[2]}();\n"
            . "        \$this->assertTotal(2, \$opcoes);\n"
            . "        \$this->assertVerdadeiro(in_array(\$this->idsRelacoes['{$campo[0]}'], array_column(\$opcoes, 'id'), true));\n\n";
    }

    return $linhas;
}

function testeModeloGerado(string $tabela, string $classe, array $campos): string
{
    $principal = $campos[0][0];
    $conferirRelacoes = assercoesDeRelacaoGeradas($campos);

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

    $modelo    = resolverModeloAutenticacao($posicionais[0] ?? null);
    $escolhido = array_key_exists(1, $posicionais);
    $provider  = $escolhido
        ? normalizarPrefixoAutenticacao($posicionais[1])
        : prefixoDoModelo($posicionais[0] ?? null, $modelo['classe']);

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
        if ($modelo['novo']) {
            registrarEsquema($modelo['tabela'], esquemaAutenticacaoPadrao());
        } else {
            acrescentarColunasAoEsquema($modelo['tabela'], ['email', 'senha']);
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
    //    Se o model ja tinha CRUD, ele passa a receber e-mail e senha.
    // -------------------------------------------------------------
    $modeloOriginal = $modelo['novo'] ? null : (string) file_get_contents($modelo['arquivo']);
    $crud           = null;
    $crudOriginais  = [];
    $avisos         = [];

    try {
        if (!$modelo['novo']) {
            $avisos = tornarModeloAutenticavel($modelo['arquivo']);
            $crud   = crudComCredenciais($modelo['classe'], $modelo['tabela'], $avisos);

            foreach (array_keys($crud['arquivos'] ?? []) as $caminho) {
                $crudOriginais[$caminho] = lerArquivo($caminho);
            }

            regravarArquivos($crud['arquivos'] ?? []);
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
                $colunas,
                $crud
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

        foreach ($crudOriginais as $caminho => $conteudo) {
            file_put_contents($caminho, $conteudo, LOCK_EX);
        }

        restaurarEsquemas($esquemas);

        throw $e;
    }

    echo "Autenticacao aplicada ao modelo {$modelo['classe']}.\n";

    foreach (array_keys($arquivos) as $caminho) {
        echo '  + ' . caminhoRelativo($caminho) . "\n";
    }

    if (!$modelo['novo']) {
        echo '  ~ ' . caminhoRelativo($modelo['arquivo']) . "\n";
    }

    foreach (array_keys($crudOriginais) as $caminho) {
        echo '  ~ ' . caminhoRelativo($caminho) . "\n";
    }

    echo '  ~ banco/esquema.sql' . "\n\n";

    if ($crud !== null) {
        echo "O CRUD /{$crud['pasta']} agora recebe email e senha. Os dois sao opcionais:\n"
            . "sem eles o registro so ainda nao tem conta.\n\n";
    }

    foreach ($avisos as $aviso) {
        echo "AVISO: {$aviso}\n\n";
    }

    if ($provider === Nucleo\Autenticacao::PADRAO) {
        echo "Login em /{$rota}: e o login unico do projeto.\n\n";
    } else {
        echo "Login em /{$rota}: prefixo \"{$provider}\""
            . ($escolhido ? ".\n" : ", vindo do modelo {$modelo['classe']}.\n")
            . "Para deixa-lo no login unico /auth: php console.php auth:install {$modelo['classe']} auth\n\n";
    }

    echo "Rotas:\n";
    echo "  /{$rota}/registrar   cria uma conta\n";
    echo "  /{$rota}/login       entra\n";
    echo "  /{$rota}/sair        encerra a sessao\n\n";
    echo "Para exigir esse login:\n";
    echo '  em um CRUD novo    php console.php scaffold:crud <tabela> <campo:tipo> ... --auth'
        . ($provider === Nucleo\Autenticacao::PADRAO ? '' : "={$provider}") . "\n";
    echo '  em um controller   $this->exigirAutenticacao('
        . ($provider === Nucleo\Autenticacao::PADRAO ? '' : "'{$provider}'") . ");\n\n";
    echo "Rode os testes com: php testes/executar.php {$controlador}\n";
}

/**
 * Prefixo do provider quando o comando recebeu so o modelo.
 *
 *     auth:install               -> /auth             (login unico do projeto)
 *     auth:install Usuario       -> /auth             (o model padrao do framework)
 *     auth:install Professor     -> /auth-professor
 *     auth:install professores   -> /auth-professor   (o nome da tabela tambem serve)
 *
 * O prefixo nasce do proprio modelo porque digitar "auth:install Professor
 * professor" repetia a mesma informacao duas vezes. Para escolher outro nome,
 * informe o segundo argumento; "auth" nele devolve o login unico em /auth.
 */
function prefixoDoModelo(?string $alvo, string $classe): string
{
    if ($alvo === null || $classe === 'Usuario') {
        return Nucleo\Autenticacao::PADRAO;
    }

    return normalizarPrefixoAutenticacao(
        strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $classe))
    );
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
 * e, no CRUD, e-mail e senha sao opcionais (sem eles o registro so ainda nao
 * tem conta). Quem exige credenciais e a tela de cadastro (criarComSenha).
 */
function acrescentarColunasAoEsquema(string $tabela, array $colunas): void
{
    $arquivo  = arquivoEsquema();
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

        $definicao = sqlNome($coluna) . ' ' . tipoSql('string') . ' NULL';

        // Uma coluna nova nunca pode cair depois de um CONSTRAINT: e assim
        // que se le um CREATE TABLE, e era exatamente isso que quebrava ao
        // dar login a um model com belongs_to ("auth:install Aluno" com a
        // chave turma_id).
        if (preg_match('/\bCONSTRAINT\b/i', $novo, $achado, PREG_OFFSET_CAPTURE)) {
            $posicao = (int) $achado[0][1];
            $novo    = substr($novo, 0, $posicao) . $definicao . ",\n    " . substr($novo, $posicao);

            continue;
        }

        // Sem restricoes, entra logo antes do parentese que fecha a tabela.
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

function esquemaAutenticacaoPadrao(): string
{
    return "CREATE TABLE IF NOT EXISTS `usuarios` (\n"
        . "    `id` INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "    `nome` VARCHAR(100) NOT NULL,\n"
        . "    `email` VARCHAR(150) NOT NULL UNIQUE,\n"
        . "    `senha` VARCHAR(255) NOT NULL,\n"
        . "    `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n"
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
}

/**
 * Acrescenta o trait Autenticavel a um model que ja existe, preservando a
 * indentacao e a ordem dos "use" do arquivo.
 *
 * @return list<string> avisos do que precisa ser feito a mao
 */
function tornarModeloAutenticavel(string $arquivo): array
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

    // 4. validar() ganha as regras de e-mail e senha usadas pelo CRUD.
    $avisos    = [];
    $validacao = validacaoComCredenciais((string) $conteudo);

    if ($validacao === null) {
        $avisos[] = 'Nao encontrei o validar() com Validador em ' . caminhoRelativo($arquivo) . ".\n"
            . '       Confira e-mail e senha a mao; emailEmUso() diz se o e-mail ja tem dono.';
    } else {
        $conteudo = $validacao;
    }

    file_put_contents($arquivo, (string) $conteudo, LOCK_EX);

    return $avisos;
}

/**
 * Acrescenta ao validar() do model as regras de e-mail e senha: e-mail
 * valido e sem dono, senha com o tamanho minimo. As tres so atuam quando o
 * campo vem preenchido, entao o CRUD continua aceitando registros sem conta.
 *
 * Devolve null quando o model nao tem um validar() terminado em ->erros().
 */
function validacaoComCredenciais(string $conteudo): ?string
{
    $padrao = '/(public\s+function\s+validar\s*\(([^)]*)\)(?:(?!\bfunction\b).)*?)^([ \t]*)->erros\(\);/ms';

    if (!preg_match($padrao, $conteudo, $encontrado)) {
        return null;
    }

    $ignorarId = str_contains($encontrado[2], '$ignorarId') ? '$ignorarId' : 'null';
    $regras    = [
        "->email('email'"  => "->email('email')",
        'emailEmUso('      => "->personalizada('email', !\$this->emailEmUso(\$dados['email'] ?? null, {$ignorarId}), 'Este e-mail ja esta cadastrado.')",
        "->minimo('senha'" => "->minimo('senha', " . Nucleo\Autenticacao::SENHA_MINIMA . ')',
    ];

    $novas = '';

    foreach ($regras as $existente => $regra) {
        if (!str_contains($encontrado[1], $existente)) {
            $novas .= $encontrado[3] . $regra . "\n";
        }
    }

    return (string) preg_replace_callback(
        $padrao,
        fn (array $m): string => $m[1] . $novas . $m[3] . '->erros();',
        $conteudo,
        1
    );
}

/**
 * Leva e-mail e senha para o CRUD que o scaffold:crud gerou para o model.
 *
 * Sem isso o login ficaria instalado, mas o formulario do CRUD nao teria os
 * campos e o salvar()/atualizar() do controller nunca os gravaria:
 *
 *   - o controller passa a receber email e senha nos dois $dados;
 *   - o formulario ganha os campos (a senha num input password, sem value);
 *   - a tela ver mostra o e-mail;
 *   - o teste do controller recria a tabela ja com as colunas novas.
 *
 * Nada e gravado aqui: devolve [caminho => conteudo novo] para o chamador
 * gravar junto com o resto. Devolve null quando o model nao tem CRUD ou o
 * controller mudou tanto que nao da para alterar (o que fazer vai para $avisos).
 *
 * @return array{arquivos:array<string,string>,pasta:string,guarda:string|false,campos:list<array{0:string,1:string}>}|null
 */
function crudComCredenciais(string $classe, string $tabela, array &$avisos): ?array
{
    $recurso    = pascal($tabela);
    $pasta      = strtolower($recurso);
    $controller = CAMINHO_CONTROLLERS . "/{$recurso}Controller.php";

    if (!is_file($controller)) {
        return null;
    }

    $conteudo = lerArquivo($controller);

    // Um controller com o mesmo nome, mas de outro model, nao e o CRUD dele.
    if (!preg_match('/^use\s+Modelos\\\\' . preg_quote($classe, '/') . ';/m', $conteudo)) {
        return null;
    }

    $novoController = controllerComCredenciais($conteudo);

    if ($novoController === null) {
        $avisos[] = 'Nao encontrei o $dados = [...] do salvar()/atualizar() em ' . caminhoRelativo($controller) . ".\n"
            . "       Inclua a mao: 'email' => \$this->post('email'), 'senha' => \$this->post('senha'),";

        return null;
    }

    $arquivos = $novoController === $conteudo ? [] : [$controller => $novoController];
    $alvos    = [
        CAMINHO_VIEWS . "/{$pasta}/formulario.php" => 'formularioComCredenciais',
        CAMINHO_VIEWS . "/{$pasta}/ver.php"        => 'verComCredenciais',
        CAMINHO_RAIZ . "/testes/controllers/{$recurso}ControllerTest.php"
            => fn (string $texto): ?string => testeComColunasDeCredenciais($texto, $tabela),
    ];

    foreach ($alvos as $caminho => $alterar) {
        if (!is_file($caminho)) {
            continue;
        }

        $original = lerArquivo($caminho);
        $novo     = $alterar($original);

        if ($novo === null) {
            $avisos[] = 'Nao encontrei onde incluir email e senha em ' . caminhoRelativo($caminho) . '. Inclua a mao.';
        } elseif ($novo !== $original) {
            $arquivos[$caminho] = $novo;
        }
    }

    // O teste gerado pelo auth:install usa estes campos para postar no CRUD.
    $campos = [];

    foreach (colunasDoEsquema($tabela) as $nome => [$tipo]) {
        if (!in_array($nome, ['id', 'email', 'senha', 'criado_em'], true)) {
            $campos[] = [$nome, $tipo];
        }
    }

    preg_match('/exigirAutenticacao\(\s*(?:\'([a-z0-9_]*)\')?\s*\)/', $conteudo, $guarda);

    return [
        'arquivos' => $arquivos,
        'pasta'    => $pasta,
        'guarda'   => $guarda === [] ? false : ($guarda[1] ?? ''),
        'campos'   => $campos,
    ];
}

/**
 * Os "$dados = [...]" do salvar() e do atualizar() ganham email e senha.
 * Devolve null se o controller nao tiver nenhum desses blocos.
 */
function controllerComCredenciais(string $conteudo): ?string
{
    $blocos = 0;

    $novo = preg_replace_callback(
        '/^([ \t]*)\$dados = \[\R((?:[ \t]+.*\$this->post\(.*\R)+)\1\];/m',
        function (array $m) use (&$blocos): string {
            $blocos++;

            $linhas = $m[2];
            $recuo  = preg_match('/^[ \t]+/', $linhas, $r) ? $r[0] : $m[1] . '    ';

            foreach (['email', 'senha'] as $campo) {
                if (!preg_match("/['\"]{$campo}['\"]\s*=>/", $linhas)) {
                    $linhas .= "{$recuo}'{$campo}' => \$this->post('{$campo}'),\n";
                }
            }

            return $m[1] . "\$dados = [\n" . $linhas . $m[1] . '];';
        },
        $conteudo
    );

    return $blocos > 0 ? (string) $novo : null;
}

/**
 * O formulario ganha os campos que ainda nao tem, logo antes dos botoes.
 * Devolve null se nao achar o fim da grade de campos.
 */
function formularioComCredenciais(string $conteudo): ?string
{
    $campos = (str_contains($conteudo, 'name="email"') ? '' : campoEmail())
        . (str_contains($conteudo, 'name="senha"') ? '' : campoSenha());

    if ($campos === '') {
        return $conteudo;
    }

    $padrao = '/^[ \t]*<\/div>\R[ \t]*<div class="d-flex gap-2 mt-4">/m';

    if (!preg_match($padrao, $conteudo)) {
        return null;
    }

    return (string) preg_replace_callback($padrao, fn (array $m): string => $campos . $m[0], $conteudo, 1);
}

function campoEmail(): string
{
    return <<<'HTML'
            <div class="col-md-6">
                <label class="form-label" for="email">email</label>
                <input class="form-control <?= tem_erro('email') ? 'is-invalid' : '' ?>" id="email" type="email" name="email" value="<?= e(antigo('email', $registro['email'] ?? '')) ?>">
                <?php if ($mensagem = erro_de('email')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
            </div>

        HTML;
}

/**
 * A senha nunca volta para a tela: o input nao tem value. Em branco na
 * edicao, o trait Autenticavel mantem a senha atual.
 */
function campoSenha(): string
{
    return strtr(<<<'HTML'
            <div class="col-md-6">
                <label class="form-label" for="senha">senha</label>
                <input class="form-control <?= tem_erro('senha') ? 'is-invalid' : '' ?>" id="senha" type="password" name="senha" autocomplete="new-password" minlength="{{SENHA_MINIMA}}">
                <?php if ($registro): ?><div class="form-text">Deixe em branco para manter a senha atual.</div><?php endif ?>
                <?php if ($mensagem = erro_de('senha')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
            </div>

        HTML, ['{{SENHA_MINIMA}}' => (string) Nucleo\Autenticacao::SENHA_MINIMA]);
}

/** A tela ver mostra o e-mail (a senha, nunca). */
function verComCredenciais(string $conteudo): ?string
{
    if (str_contains($conteudo, "\$registro['email']")) {
        return $conteudo;
    }

    if (!preg_match('/^([ \t]*)<\/dl>/m', $conteudo, $fim)) {
        return null;
    }

    $recuo = preg_match('/^([ \t]*)<dt\b/m', $conteudo, $dt) ? $dt[1] : $fim[1] . '    ';
    $linha = "{$recuo}<dt class=\"col-sm-3\">email</dt>\n"
        . "{$recuo}<dd class=\"col-sm-9\"><?= e(\$registro['email'] ?? '') ?></dd>\n";

    return (string) preg_replace_callback('/^[ \t]*<\/dl>/m', fn (array $m): string => $linha . $m[0], $conteudo, 1);
}

/**
 * O teste do controller recria a tabela com as colunas da epoca do
 * scaffold. Como o salvar() agora grava o e-mail, ela precisa das colunas
 * novas. Devolve null se nao achar o CREATE TABLE da tabela.
 */
function testeComColunasDeCredenciais(string $conteudo, string $tabela): ?string
{
    $nome   = preg_quote($tabela, '/');
    $padrao = "/('{$nome}'\s*=>\s*\"CREATE TABLE `?{$nome}`? \(\R)(.*?)(\R[ \t]*\)\",)/s";

    if (!preg_match($padrao, $conteudo)) {
        return null;
    }

    return (string) preg_replace_callback($padrao, function (array $m): string {
        $recuo      = preg_match('/^[ \t]*/', $m[2], $r) ? $r[0] : '';
        $definicoes = array_map('trim', explode(",\n", str_replace("\r\n", "\n", $m[2])));
        $restricao  = null;

        foreach ($definicoes as $indice => $definicao) {
            if (preg_match('/^CONSTRAINT\b/i', $definicao)) {
                $restricao = $indice;
                break;
            }
        }

        $novas = [];

        foreach (['email', 'senha'] as $coluna) {
            if (preg_grep('/^' . $coluna . '\b/i', $definicoes) === []) {
                $novas[] = sqlNome($coluna) . ' VARCHAR(255) NULL';
            }
        }

        // Coluna nunca depois de CONSTRAINT, como em acrescentarColunasAoEsquema().
        array_splice($definicoes, $restricao ?? count($definicoes), 0, $novas);

        return $m[1] . $recuo . implode(",\n{$recuo}", $definicoes) . $m[3];
    }, $conteudo, 1);
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

                // Template proprio: a tela de login nao mostra o menu lateral.
                $this->view('{{VISTA}}/login', ['titulo' => 'Entrar'], 'template/layout-login');
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

                $this->view('{{VISTA}}/registrar', ['titulo' => 'Criar conta'], 'template/layout-login');
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
        <?php
        /**
         * Tela de entrada. Desenhada dentro de views/template/layout-login.php,
         * que nao tem menu lateral: quem ainda nao entrou nao usaria nenhum
         * daqueles atalhos.
         */
        ?>
        <h1 class="h4 mb-4">Entrar</h1>

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
        HTML, ['{{ROTA}}' => $rota]);
}

function viewRegistroGerada(bool $temNome, string $rota): string
{
    // O "\n\n    " final e a linha em branco mais a indentacao do campo
    // seguinte: sem ele o proximo <div> sairia colado na margem.
    $campoNome = $temNome ? <<<'HTML'
        <div class="mb-3">
                <label class="form-label" for="nome">Nome</label>
                <input class="form-control <?= tem_erro('nome') ? 'is-invalid' : '' ?>" id="nome" type="text" name="nome" autocomplete="name" value="<?= e(antigo('nome')) ?>" required>
                <?php if ($mensagem = erro_de('nome')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
            </div>
        HTML . "\n\n    " : '';

    return strtr(<<<'HTML'
        <?php
        /**
         * Tela de cadastro. Assim como a de entrada, e desenhada dentro de
         * views/template/layout-login.php, sem o menu lateral.
         */
        ?>
        <h1 class="h4 mb-4">Criar conta</h1>

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
    array $colunas,
    ?array $crud = null
): string {
    $definicoes = implode(",\n                        ", array_map(
        fn (string $coluna): string => "{$coluna} TEXT NULL",
        array_values(array_filter($colunas, fn (string $c): bool => $c !== 'id'))
    ));

    $argumentoProvider = $provider === '' ? '' : "'{$provider}'";
    $testeCrud         = $crud === null ? '' : testeCrudComCredenciais($crud, $rota, $argumentoProvider);

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
                    '{{TABELA}}' => "CREATE TABLE `{{TABELA}}` (
                        id INT AUTO_INCREMENT PRIMARY KEY,
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
            }{{TESTE_CRUD}}
        }
        PHP, [
        '{{CLASSE}}'      => $classe,
        '{{CONTROLADOR}}' => $controlador,
        '{{TABELA}}'      => $tabela,
        '{{DEFINICOES}}'  => $definicoes,
        '{{ROTA}}'        => $rota,
        '{{PROVIDER}}'    => $argumentoProvider,
        '{{DADO_NOME}}'   => $temNome ? "\n            'nome'  => 'Ana'," : '',
        '{{TESTE_CRUD}}'  => $testeCrud,
    ]);
}

/**
 * Teste gerado quando o model ja tinha CRUD: o formulario do CRUD grava
 * e-mail e senha (com hash), senha em branco na edicao mantem a atual,
 * e-mail repetido volta para o formulario e a conta criada ali entra.
 */
function testeCrudComCredenciais(array $crud, string $rota, string $argumentoProvider): string
{
    $entrar = $crud['guarda'] === false
        ? ''
        : '        Sessao::definir(Sessao::chaveAutenticacao(\Nucleo\Autenticacao::resolver('
            . ($crud['guarda'] === '' ? '' : "'{$crud['guarda']}'") . ")), 1);\n\n";

    $dados = function (string $senha) use ($crud): string {
        $linhas = [];

        foreach ($crud['campos'] as [$nome, $tipo]) {
            $linhas[] = "            '{$nome}' => " . var_export(valorTeste($tipo, false, $nome), true) . ',';
        }

        $linhas[] = "            'email' => 'bia@example.com',";
        $linhas[] = "            'senha' => " . var_export($senha, true) . ',';

        return implode("\n", $linhas);
    };

    return strtr(<<<'PHP'


            /**
             * O CRUD /{{PASTA}} tambem recebe e-mail e senha: grava o hash,
             * mantem a senha quando o campo vem em branco, recusa e-mail
             * repetido, e a conta cadastrada ali consegue entrar.
             */
            public function testeCrudGravaEmailESenha(): void
            {
        {{ENTRAR}}        $salvar = $this->postar('{{PASTA}}/salvar', [
        {{DADOS_SALVAR}}
                ]);

                $registro = $this->modelo->buscarPorEmail('bia@example.com');
                $this->assertNaoNulo($registro, 'O salvar() do CRUD deve gravar o e-mail');
                $this->assertVerdadeiro($salvar->redirecionouPara('{{PASTA}}/ver/' . $registro['id']));
                $this->assertDiferente('segredo123', $registro['senha']);
                $this->assertVerdadeiro(password_verify('segredo123', (string) $registro['senha']));

                $atualizar = $this->postar('{{PASTA}}/atualizar/' . $registro['id'], [
        {{DADOS_ATUALIZAR}}
                ]);
                $this->assertVerdadeiro($atualizar->redirecionouPara('{{PASTA}}/ver/' . $registro['id']));
                $this->assertIgual($registro['senha'], $this->modelo->buscar($registro['id'])['senha']);

                $repetido = $this->postar('{{PASTA}}/salvar', [
        {{DADOS_REPETIDO}}
                ]);
                $this->assertVerdadeiro($repetido->redirecionouPara('{{PASTA}}/criar'));
                $this->assertIgual(1, $this->modelo->contar());

                $this->limparSessao();

                $login = $this->postar('{{ROTA}}/login', [
                    'email' => 'bia@example.com',
                    'senha' => 'segredo123',
                ]);
                $this->assertVerdadeiro($login->foiRedirecionado());
                $this->assertVerdadeiro(autenticado({{PROVIDER}}));
            }
        PHP, [
        '{{PASTA}}'           => $crud['pasta'],
        '{{ROTA}}'            => $rota,
        '{{PROVIDER}}'        => $argumentoProvider,
        '{{ENTRAR}}'          => $entrar,
        '{{DADOS_SALVAR}}'    => $dados('segredo123'),
        '{{DADOS_ATUALIZAR}}' => $dados(''),
        '{{DADOS_REPETIDO}}'  => $dados('outrasenha'),
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

// =====================================================================
// scaffold:pesquisa
// =====================================================================

/**
 * Coloca um formulario de pesquisa acima da tabela do index.
 *
 *     php console.php scaffold:pesquisa produtos nome preco disponivel
 *     php console.php scaffold:pesquisa produtos --remover
 *
 * O comando edita dois arquivos que ja existem — o controller e a view
 * index — sempre dentro de marcadores. Rodar de novo troca o trecho
 * anterior em vez de empilhar um segundo formulario.
 */
function gerarPesquisa(array $argumentos): void
{
    [$posicionais, $opcoes] = separarOpcoes($argumentos, ['remover']);

    $remover = array_key_exists('remover', $opcoes);

    if ($posicionais === [] || (!$remover && count($posicionais) < 2)) {
        throw new InvalidArgumentException(
            "Uso: php console.php scaffold:pesquisa <tabela> <campo> [campo2 ...]\n"
            . "Exemplo: php console.php scaffold:pesquisa produtos nome preco\n"
            . 'Para tirar o formulario: php console.php scaffold:pesquisa produtos --remover'
        );
    }

    $modelo     = resolverModeloRelatorio($posicionais[0]);
    $tabela     = $modelo['tabela'];
    $recurso    = pascal($tabela);
    $pasta      = strtolower($recurso);
    $controller = CAMINHO_CONTROLLERS . "/{$recurso}Controller.php";
    $view       = CAMINHO_VIEWS . "/{$pasta}/index.php";

    foreach ([$controller, $view] as $arquivo) {
        if (!is_file($arquivo)) {
            throw new RuntimeException(
                'Arquivo do CRUD nao encontrado: ' . caminhoRelativo($arquivo) . "\n"
                . "Gere o CRUD antes:\n  php console.php scaffold:crud {$tabela} nome:string"
            );
        }
    }

    // -------------------------------------------------------------
    // 1. Monta os dois arquivos na memoria. Nada e gravado ainda.
    // -------------------------------------------------------------
    $campos = $remover
        ? []
        : camposPesquisados($tabela, array_slice($posicionais, 1), $modelo['classe']);

    $novos = $remover
        ? [
            $controller => controllerSemPesquisa(lerArquivo($controller), $controller),
            $view       => indexSemPesquisa(lerArquivo($view)),
        ]
        : [
            $controller => controllerComPesquisa(lerArquivo($controller), $controller, $campos, $modelo['classe'], $pasta),
            $view       => indexComPesquisa(lerArquivo($view), $view, $pasta, $campos),
        ];

    // -------------------------------------------------------------
    // 2. Grava os dois de uma vez, com volta atras se algo falhar.
    // -------------------------------------------------------------
    regravarArquivos($novos);

    if ($remover) {
        echo "Pesquisa removida de /{$pasta}\n";
        echo '  ~ ' . caminhoRelativo($controller) . "\n";
        echo '  ~ ' . caminhoRelativo($view) . "\n";

        return;
    }

    echo "Pesquisa criada em /{$pasta}\n";
    echo '  ~ ' . caminhoRelativo($controller) . "\n";
    echo '  ~ ' . caminhoRelativo($view) . "\n\n";
    echo "Campos pesquisaveis:\n";

    foreach ($campos as [$nome, $tipo, $relacao]) {
        printf(
            "  %-18s %-13s %s\n",
            $nome,
            rotuloTipoPesquisa($tipo, $relacao),
            explicacaoPesquisa($tipo, $relacao)
        );
    }

    echo "\nO formulario aparece acima da tabela em /{$pasta} e envia os campos\n";
    echo "pela query string: /{$pasta}?{$campos[0][0]}=...\n";
    echo "\nPara desfazer: php console.php scaffold:pesquisa {$tabela} --remover\n";
}

/**
 * Confere os campos pedidos contra as colunas registradas no esquema.
 *
 * @return list<array{0:string,1:string,2:?string}>
 */
function camposPesquisados(string $tabela, array $pedidos, string $classe): array
{
    $colunas = colunasDoEsquema($tabela);

    if ($colunas === []) {
        throw new RuntimeException(
            "Nao encontrei a tabela \"{$tabela}\" em banco/esquema.sql.\n"
            . "Gere o CRUD antes:\n  php console.php scaffold:crud {$tabela} nome:string"
        );
    }

    $campos = [];

    foreach ($pedidos as $pedido) {
        $nome = strtolower(trim($pedido));

        validarNome($nome, 'nome de campo');

        // O roteador usa $_GET['url'], entao uma coluna com esse nome nunca
        // receberia o que o visitante digitou no formulario.
        if ($nome === 'url') {
            throw new InvalidArgumentException(
                'O campo "url" nao pode ser pesquisado: o roteador ja usa esse nome na query string.'
            );
        }

        if (!isset($colunas[$nome])) {
            throw new InvalidArgumentException(
                "A tabela {$tabela} nao tem a coluna \"{$nome}\".\n"
                . 'Colunas disponiveis: ' . implode(', ', array_keys($colunas))
            );
        }

        foreach ($campos as $escolhido) {
            if ($escolhido[0] === $nome) {
                throw new InvalidArgumentException("Campo repetido: {$nome}.");
            }
        }

        [$tipo, $relacao] = $colunas[$nome];

        // Sem o metodo da tabela pai no model nao da para montar o <select>;
        // nesse caso o campo vira uma caixa de numero comum.
        if ($relacao !== null && !modeloTemMetodo($classe, $relacao)) {
            $relacao = null;
            $tipo    = 'integer';
        }

        $campos[] = [$nome, $tipo, $relacao];
    }

    return $campos;
}

/**
 * Le as colunas de uma tabela direto de banco/esquema.sql, sem precisar de
 * banco ligado.
 *
 * @return array<string,array{0:string,1:?string}> nome => [tipo, tabela pai]
 */
function colunasDoEsquema(string $tabela): array
{
    $arquivo = arquivoEsquema();

    if (!is_file($arquivo)) {
        return [];
    }

    if (!preg_match(padraoCreateTable($tabela), (string) file_get_contents($arquivo), $encontrado)) {
        return [];
    }

    return interpretarCreateTable($encontrado[0]);
}

/**
 * Traduz um CREATE TABLE em colunas com tipo e relacao.
 *
 * @return array<string,array{0:string,1:?string}>
 */
function interpretarCreateTable(string $sql): array
{
    $abre  = strpos($sql, '(');
    $fecha = strrpos($sql, ')');

    if ($abre === false || $fecha === false || $fecha <= $abre) {
        return [];
    }

    $corpo   = substr($sql, $abre + 1, $fecha - $abre - 1);
    $colunas = ['id' => ['integer', null]];
    $pais    = [];

    preg_match_all(
        '/FOREIGN\s+KEY\s*\(\s*`?(\w+)`?\s*\)\s*REFERENCES\s+`?(\w+)`?/i',
        $corpo,
        $chaves,
        PREG_SET_ORDER
    );

    foreach ($chaves as $chave) {
        $pais[strtolower($chave[1])] = strtolower($chave[2]);
    }

    foreach (explode(',', $corpo) as $linha) {
        $linha = trim($linha);

        if ($linha === '' || preg_match('/^(CONSTRAINT|FOREIGN|PRIMARY|UNIQUE|KEY|INDEX)\b/i', $linha)) {
            continue;
        }

        if (!preg_match('/^`?([A-Za-z_]\w*)`?\s+([A-Za-z]+\s*(?:\([^)]*\))?)/', $linha, $partes)) {
            continue;
        }

        $nome = strtolower($partes[1]);

        if ($nome === 'id') {
            continue;
        }

        $colunas[$nome] = [tipoDoEsquema($partes[2]), $pais[$nome] ?? null];
    }

    return $colunas;
}

/**
 * Caminho inverso do tipoSql(): do tipo SQL de volta para o tipo do scaffold.
 *
 * E o que permite ao scaffold:pesquisa descobrir sozinho que "ativo" e um
 * boolean (TINYINT(1)) e "nascimento" uma data, sem pedir os tipos de novo.
 */
function tipoDoEsquema(string $sql): string
{
    $sql = strtoupper((string) preg_replace('/\s+/', '', $sql));

    return match (true) {
        str_starts_with($sql, 'TINYINT(1)') => 'boolean',
        str_starts_with($sql, 'DATETIME'),
        str_starts_with($sql, 'TIMESTAMP')  => 'datetime',
        str_starts_with($sql, 'DATE')       => 'date',
        str_starts_with($sql, 'TIME')       => 'time',
        str_starts_with($sql, 'DECIMAL'),
        str_starts_with($sql, 'NUMERIC'),
        str_starts_with($sql, 'DOUBLE'),
        str_starts_with($sql, 'FLOAT'),
        str_starts_with($sql, 'REAL')       => 'decimal',
        str_starts_with($sql, 'TINYINT'),
        str_starts_with($sql, 'SMALLINT'),
        str_starts_with($sql, 'MEDIUMINT'),
        str_starts_with($sql, 'BIGINT'),
        str_starts_with($sql, 'INT')        => 'integer',
        str_starts_with($sql, 'TEXT')       => 'text',
        default                             => 'string',
    };
}

/**
 * Como cada tipo e pesquisado:
 *   'contem' -> LIKE %termo%  (texto)
 *   'comeca' -> LIKE termo%   (datetime: a data casa com qualquer horario)
 *   'exato'  -> =             (numero, boolean, data, hora, chave estrangeira)
 */
function modoPesquisa(string $tipo, ?string $relacao): string
{
    if ($relacao !== null) {
        return 'exato';
    }

    return match ($tipo) {
        'string', 'text' => 'contem',
        'datetime'       => 'comeca',
        default          => 'exato',
    };
}

function rotuloTipoPesquisa(string $tipo, ?string $relacao): string
{
    if ($relacao !== null) {
        return $relacao;
    }

    return match ($tipo) {
        'boolean'            => 'Sim/Nao',
        'integer', 'decimal' => 'numero',
        'date'               => 'data',
        'datetime'           => 'data e hora',
        'time'               => 'hora',
        default              => 'texto',
    };
}

function explicacaoPesquisa(string $tipo, ?string $relacao): string
{
    if ($relacao !== null) {
        return 'lista suspensa com os registros de ' . $relacao;
    }

    return match (modoPesquisa($tipo, $relacao)) {
        'contem' => 'contem o trecho digitado (LIKE)',
        'comeca' => 'todos os horarios da data escolhida (LIKE)',
        default  => 'valor exato',
    };
}

// ---------------------------------------------------------------------
// scaffold:pesquisa - arquivos
// ---------------------------------------------------------------------

function lerArquivo(string $caminho): string
{
    $conteudo = file_get_contents($caminho);

    if ($conteudo === false) {
        throw new RuntimeException('Nao foi possivel ler: ' . caminhoRelativo($caminho));
    }

    return $conteudo;
}

/**
 * Regrava arquivos que ja existiam. Se um deles falhar, o conteudo
 * anterior de todos volta — ao contrario de escreverArquivos(), que apaga
 * os arquivos criados porque la eles ainda nao existiam.
 *
 * @param array<string,string> $arquivos caminho => conteudo novo
 */
function regravarArquivos(array $arquivos): void
{
    $originais = [];

    foreach (array_keys($arquivos) as $caminho) {
        $originais[$caminho] = lerArquivo($caminho);
    }

    $gravados = [];

    try {
        foreach ($arquivos as $caminho => $conteudo) {
            if (file_put_contents($caminho, rtrim($conteudo, "\n") . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Nao foi possivel gravar: ' . caminhoRelativo($caminho));
            }

            $gravados[] = $caminho;
        }
    } catch (Throwable $e) {
        foreach ($gravados as $caminho) {
            file_put_contents($caminho, $originais[$caminho], LOCK_EX);
        }

        throw $e;
    }
}

/** @return array{0:string,1:string} marcadores do trecho gerado no controller */
function marcadoresPesquisaPhp(): array
{
    return [
        '        // ----- scaffold:pesquisa inicio -----',
        '        // ----- scaffold:pesquisa fim -----',
    ];
}

/** @return array{0:string,1:string} marcadores do trecho gerado na view */
function marcadoresPesquisaHtml(): array
{
    return ['<!-- scaffold:pesquisa inicio -->', '<!-- scaffold:pesquisa fim -->'];
}

/** Texto do "nenhum registro" nas telas que ja tem pesquisa. */
function vazioComPesquisa(): string
{
    return '<?= ($pesquisa ?? []) === []'
        . " ? 'Nenhum registro cadastrado.'"
        . " : 'Nenhum registro encontrado para a pesquisa.' ?>";
}

function modeloTemMetodo(string $classe, string $metodo): bool
{
    $arquivo = CAMINHO_MODELOS . "/{$classe}.php";

    return is_file($arquivo)
        && preg_match('/function\s+' . preg_quote($metodo, '/') . '\s*\(/', lerArquivo($arquivo)) === 1;
}

// ---------------------------------------------------------------------
// scaffold:pesquisa - controller
// ---------------------------------------------------------------------

/** Recorta o metodo index() inteiro de dentro do controller. */
function blocoIndexDoController(string $conteudo, string $arquivo): string
{
    $padrao = '/\n[ ]{4}public function index\(\): void\n[ ]{4}\{\n[\s\S]*?\n[ ]{4}\}\n/';

    if (!preg_match($padrao, $conteudo, $encontrado)) {
        throw new RuntimeException(
            'Nao encontrei o metodo index() em ' . caminhoRelativo($arquivo) . ".\n"
            . 'Ele precisa comecar exatamente com "    public function index(): void".'
        );
    }

    return $encontrado[0];
}

/**
 * Faz o index() filtrar pelos campos pedidos. O trecho gerado fica entre
 * marcadores, para poder ser trocado ou retirado depois.
 */
function controllerComPesquisa(
    string $conteudo,
    string $arquivo,
    array $campos,
    string $classe,
    string $pasta
): string {
    $conteudo = garantirImportacaoSql($conteudo, $arquivo);
    $antigo   = blocoIndexDoController($conteudo, $arquivo);

    // Comeca sempre do index() limpo: rodar o comando de novo com menos
    // campos nao pode deixar sobras da pesquisa anterior.
    $bloco = blocoIndexLimpo($antigo);

    [$inicioDaPaginacao] = marcadoresPaginacaoPhp();

    $comPaginacao = str_contains($bloco, $inicioDaPaginacao);

    if ($comPaginacao) {
        // A tela ja e paginada: quem passa a receber o WHERE e o paginador,
        // para o total de paginas sair do resultado do filtro.
        $bloco = str_replace(
            "\$pagina = \$this->modelo->paginar(",
            "\$pagina = \$this->modelo->paginarConsulta(\$sql, \$parametros, ",
            $bloco
        );
    } else {
        // A listagem passa a vir de uma consulta com WHERE.
        $bloco = str_replace(
            "'registros' => \$this->modelo->todos(),",
            "'registros' => \$this->modelo->consultar(\$sql, \$parametros),",
            $bloco
        );

        if (!str_contains($bloco, "'registros' => \$this->modelo->consultar(\$sql, \$parametros),")) {
            throw new RuntimeException(
                'Nao encontrei a linha "\'registros\' => $this->modelo->todos()," no index() de '
                . caminhoRelativo($arquivo) . ".\n"
                . 'Reponha essa linha (ou gere o CRUD de novo) antes de acrescentar a pesquisa.'
            );
        }
    }

    // O que a view precisa para redesenhar o formulario ja preenchido.
    if (!str_contains($bloco, "'pesquisa'")) {
        $bloco = (string) preg_replace(
            "/([ ]*)('registros'[^\n]*\n)/",
            "\${1}\${2}\${1}'pesquisa'  => \$pesquisa,\n",
            $bloco,
            1
        );
    }

    // Os <select> das chaves estrangeiras recebem a lista da tabela pai,
    // pelo mesmo caminho que criar() e editar() ja usam.
    foreach (relacoesUnicas($campos) as $relacao) {
        $pai = $relacao[2];

        if (str_contains($bloco, "'{$pai}'")) {
            continue;
        }

        $bloco = (string) preg_replace(
            "/([ ]*)('pesquisa'[^\n]*\n)/",
            "\${1}\${2}\${1}" . str_pad("'{$pai}'", 11) . " => \\\$this->modelo->{$pai}(),\n",
            $bloco,
            1
        );
    }

    $filtros = filtrosPesquisaGerados($campos, ordemPadraoDoModelo($classe), $pasta);

    if ($comPaginacao) {
        // Antes do paginador: ele recebe o $sql que o filtro acabou de montar.
        $bloco = str_replace($inicioDaPaginacao, $filtros . "\n\n" . $inicioDaPaginacao, $bloco);

        return str_replace($antigo, $bloco, $conteudo);
    }

    // E o trecho gerado entra logo antes da chamada da view.
    $bloco = (string) preg_replace(
        '/(\n+)([ ]*)\$this->view\(/',
        '${1}' . preg_quote_replace($filtros) . "\n\n\${2}\$this->view(",
        $bloco,
        1,
        $trocas
    );

    if ($trocas !== 1) {
        throw new RuntimeException(
            'O index() de ' . caminhoRelativo($arquivo) . " nao chama \$this->view().\n"
            . 'Deixe a chamada la (ou gere o CRUD de novo) antes de acrescentar a pesquisa.'
        );
    }

    return str_replace($antigo, $bloco, $conteudo);
}

/** Devolve o index() ao estado sem pesquisa. */
function controllerSemPesquisa(string $conteudo, string $arquivo): string
{
    $antigo = blocoIndexDoController($conteudo, $arquivo);

    return str_replace($antigo, blocoIndexLimpo($antigo), $conteudo);
}

/**
 * Tira do index() tudo que o scaffold:pesquisa tinha colocado: o trecho
 * entre marcadores, a consulta filtrada e os dados extras da view.
 */
function blocoIndexLimpo(string $bloco): string
{
    [$inicio, $fim] = marcadoresPesquisaPhp();

    $bloco = (string) preg_replace(
        '/' . preg_quote($inicio, '/') . '[\s\S]*?' . preg_quote($fim, '/') . "\n\n?/",
        '',
        $bloco,
        1
    );

    $bloco = str_replace(
        "'registros' => \$this->modelo->consultar(\$sql, \$parametros),",
        "'registros' => \$this->modelo->todos(),",
        $bloco
    );

    // Se a tela tambem e paginada, o paginador volta a ler a tabela inteira.
    $bloco = str_replace(
        "\$pagina = \$this->modelo->paginarConsulta(\$sql, \$parametros, ",
        "\$pagina = \$this->modelo->paginar(",
        $bloco
    );

    // Sai o 'pesquisa' e saem as listas das tabelas pai, que so o
    // formulario de pesquisa usava nesta tela.
    return (string) preg_replace(
        [
            "/^[ ]*'pesquisa'[^\n]*\n/m",
            "/^[ ]*'(\w+)'[ ]*=>[ ]*\\\$this->modelo->\\1\(\),\n/m",
        ],
        '',
        $bloco
    );
}

/** Escapa "$" e "\" para o texto ir literal na substituicao do preg_replace(). */
function preg_quote_replace(string $texto): string
{
    return str_replace(['\\', '$'], ['\\\\', '\\$'], $texto);
}

/** Monta o trecho que le a query string e arma o SELECT. */
function filtrosPesquisaGerados(array $campos, string $ordem, string $pasta): string
{
    [$inicio, $fim] = marcadoresPesquisaPhp();

    $linhas = [
        $inicio,
        '        // O formulario acima da tabela manda os campos pela query string:',
        "        //     /{$pasta}?{$campos[0][0]}=...",
        '        // Campo em branco e ignorado, entao a lista completa continua',
        '        // aparecendo enquanto ninguem pesquisar nada.',
        '        //',
        '        // Os VALORES vao como "?" (parametros do PDO). So os nomes de',
        '        // coluna entram no texto do SQL, e eles sao fixos aqui.',
        '        $pesquisa   = [];',
        '        $condicoes  = [];',
        '        $parametros = [];',
    ];

    foreach ($campos as [$nome, $tipo, $relacao]) {
        $linhas[] = '';
        $linhas[] = "        \$termo = \$this->get('{$nome}');";
        $linhas[] = '';
        $linhas[] = "        if (is_scalar(\$termo) && (string) \$termo !== '') {";
        $linhas[] = "            \$pesquisa['{$nome}'] = (string) \$termo;";

        $coluna = sqlNome($nome);

        $linhas[] = match (modoPesquisa($tipo, $relacao)) {
            'contem', 'comeca' => "            \$condicoes[] = '{$coluna} LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE;",
            default            => "            \$condicoes[] = '{$coluna} = ?';",
        };

        $linhas[] = match (modoPesquisa($tipo, $relacao)) {
            'contem' => '            $parametros[] = Sql::comoLike((string) $termo);',
            'comeca' => "            \$parametros[] = Sql::comoLike((string) \$termo, 'inicio');",
            default  => '            $parametros[] = $termo;',
        };

        $linhas[] = '        }';
    }

    $linhas[] = '';
    $linhas[] = "        \$sql = 'SELECT * FROM ' . \$this->modelo->tabelaProtegida();";
    $linhas[] = '';
    $linhas[] = '        if ($condicoes !== []) {';
    $linhas[] = "            \$sql .= ' WHERE ' . implode(' AND ', \$condicoes);";
    $linhas[] = '        }';

    if ($ordem !== '') {
        $linhas[] = '';
        $linhas[] = "        \$sql .= ' ORDER BY {$ordem}';";
    }

    $linhas[] = $fim;

    return implode("\n", $linhas);
}

/**
 * Ordenacao declarada no model, para a lista filtrada sair na mesma ordem
 * em que o todos() a devolvia.
 */
function ordemPadraoDoModelo(string $classe): string
{
    $arquivo = CAMINHO_MODELOS . "/{$classe}.php";

    if (!is_file($arquivo) || !preg_match('/\$ordemPadrao\s*=\s*\'([^\']*)\'/', lerArquivo($arquivo), $achado)) {
        return 'id DESC';
    }

    return $achado[1] === '' ? '' : Nucleo\Sql::ordenacao($achado[1]);
}

/** O trecho gerado usa Sql::comoLike(), entao o import precisa existir. */
function garantirImportacaoSql(string $conteudo, string $arquivo): string
{
    if (preg_match('/^use\s+Nucleo\\\\Sql;/m', $conteudo)) {
        return $conteudo;
    }

    $novo = preg_replace(
        '/^use\s+Nucleo\\\\Controller;/m',
        "use Nucleo\\Controller;\nuse Nucleo\\Sql;",
        $conteudo,
        1,
        $trocas
    );

    if ($trocas !== 1 || $novo === null) {
        throw new RuntimeException(
            'Nao encontrei "use Nucleo\\Controller;" em ' . caminhoRelativo($arquivo)
            . " para acrescentar \"use Nucleo\\Sql;\".\n"
            . 'Escreva essa linha a mao e rode o comando de novo.'
        );
    }

    return $novo;
}

// ---------------------------------------------------------------------
// scaffold:pesquisa - view
// ---------------------------------------------------------------------

/** Coloca (ou troca) o formulario de pesquisa acima da tabela do index. */
function indexComPesquisa(string $conteudo, string $arquivo, string $pasta, array $campos): string
{
    $conteudo   = indexSemPesquisa($conteudo);
    $formulario = formularioPesquisaGerado($pasta, $campos) . "\n\n";

    // Com pesquisa, "nenhum registro" pode significar duas coisas bem
    // diferentes; a view passa a distinguir as duas.
    $conteudo = str_replace('Nenhum registro cadastrado.', vazioComPesquisa(), $conteudo);

    foreach (['<div class="card border-0 shadow-sm">', '<div class="table-responsive">', '<table'] as $ancora) {
        $posicao = strpos($conteudo, $ancora);

        if ($posicao === false) {
            continue;
        }

        // Entra no comeco da linha em que a tabela comeca.
        $quebra = strrpos(substr($conteudo, 0, $posicao), "\n");
        $corte  = $quebra === false ? 0 : $quebra + 1;

        return substr($conteudo, 0, $corte) . $formulario . substr($conteudo, $corte);
    }

    throw new RuntimeException(
        'Nao encontrei a tabela em ' . caminhoRelativo($arquivo) . ".\n"
        . 'A view precisa manter o card da listagem gerado pelo scaffold:crud.'
    );
}

/** Tira o formulario de pesquisa da view. */
function indexSemPesquisa(string $conteudo): string
{
    [$inicio, $fim] = marcadoresPesquisaHtml();

    $padrao = '/' . preg_quote($inicio, '/') . '[\s\S]*?' . preg_quote($fim, '/') . '\n*/';

    return str_replace(
        vazioComPesquisa(),
        'Nenhum registro cadastrado.',
        (string) preg_replace($padrao, '', $conteudo, 1)
    );
}

function formularioPesquisaGerado(string $pasta, array $campos): string
{
    [$inicio, $fim] = marcadoresPesquisaHtml();

    $blocos = '';

    foreach ($campos as [$nome, $tipo, $relacao]) {
        $blocos .= $relacao !== null
            ? filtroRelacao($nome, $relacao)
            : ($tipo === 'boolean' ? filtroBoolean($nome) : filtroSimples($nome, $tipo));
    }

    return strtr(<<<'HTML'
        {{INICIO}}
        <form class="card border-0 shadow-sm p-3 mb-3" method="get" action="<?= url('{{PASTA}}') ?>">
            <div class="row g-2 align-items-end">
        {{CAMPOS}}        <div class="col-12 col-lg-auto d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Pesquisar</button>
                    <a class="btn btn-outline-secondary" href="<?= url('{{PASTA}}') ?>">Limpar</a>
                </div>
            </div>
        </form>
        {{FIM}}
        HTML, [
        '{{INICIO}}' => $inicio,
        '{{FIM}}'    => $fim,
        '{{PASTA}}'  => $pasta,
        '{{CAMPOS}}' => $blocos,
    ]);
}

function filtroSimples(string $nome, string $tipo): string
{
    $tipoHtml = match ($tipo) {
        'integer', 'decimal' => 'number',
        'date', 'datetime'   => 'date',
        'time'               => 'time',
        default              => 'text',
    };

    $extra = $tipo === 'decimal' ? ' step="0.01"' : '';

    return strtr(<<<'HTML'
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small text-secondary mb-1" for="pesquisa_{{NOME}}">{{NOME}}</label>
                    <input class="form-control" id="pesquisa_{{NOME}}" type="{{TIPO}}"{{EXTRA}} name="{{NOME}}" value="<?= e($pesquisa['{{NOME}}'] ?? '') ?>">
                </div>

        HTML, [
        '{{NOME}}'  => $nome,
        '{{TIPO}}'  => $tipoHtml,
        '{{EXTRA}}' => $extra,
    ]);
}

/**
 * Boolean vira uma lista de tres estados: "Todos" (nao filtra), Sim e Nao.
 * Uma caixa de marcar nao daria conta — desmarcada, ela nao diz se o
 * visitante quer os "Nao" ou quer todo mundo.
 */
function filtroBoolean(string $nome): string
{
    return strtr(<<<'HTML'
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small text-secondary mb-1" for="pesquisa_{{NOME}}">{{NOME}}</label>
                    <?php $escolhido = (string) ($pesquisa['{{NOME}}'] ?? ''); ?>
                    <select class="form-select" id="pesquisa_{{NOME}}" name="{{NOME}}">
                        <option value="">Todos</option>
                        <option value="1" <?= $escolhido === '1' ? 'selected' : '' ?>>Sim</option>
                        <option value="0" <?= $escolhido === '0' ? 'selected' : '' ?>>Nao</option>
                    </select>
                </div>

        HTML, ['{{NOME}}' => $nome]);
}

function filtroRelacao(string $nome, string $tabelaPai): string
{
    return strtr(<<<'HTML'
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small text-secondary mb-1" for="pesquisa_{{NOME}}">{{NOME}}</label>
                    <?php $escolhido = (string) ($pesquisa['{{NOME}}'] ?? ''); ?>
                    <select class="form-select" id="pesquisa_{{NOME}}" name="{{NOME}}">
                        <option value="">Todos</option>
                        <?php foreach ((${{PAI}} ?? []) as $opcao): ?>
                            <option value="<?= e($opcao['id']) ?>" <?= $escolhido === (string) $opcao['id'] ? 'selected' : '' ?>><?= e($opcao['nome'] ?? $opcao['descricao'] ?? ('#' . $opcao['id'])) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

        HTML, [
        '{{NOME}}' => $nome,
        '{{PAI}}'  => $tabelaPai,
    ]);
}

// =====================================================================
// scaffold:campo
// =====================================================================

/**
 * Acrescenta (ou tira) campos de um CRUD que ja existe.
 *
 * O scaffold:crud se recusa a sobrescrever arquivos. Ate aqui, para ganhar
 * mais uma coluna em um recurso pronto era preciso apagar os sete arquivos
 * gerados — e junto com eles tudo que ja tinha sido escrito a mao.
 *
 * Este comando altera no lugar: esquema, banco, model, controller, as tres
 * views e os dois testes gerados.
 */
function gerarCampo(array $argumentos): void
{
    [$posicionais, $opcoes] = separarOpcoes($argumentos, ['remover', 'obrigatorio', 'forcar']);

    $remover = array_key_exists('remover', $opcoes);

    if (count($posicionais) < 2) {
        throw new InvalidArgumentException(
            "Uso: php console.php scaffold:campo <tabela> <campo:tipo> [campo2:tipo ...]\n"
            . "Exemplo: php console.php scaffold:campo produtos peso:decimal\n"
            . 'Para tirar um campo: php console.php scaffold:campo produtos peso --remover'
        );
    }

    $modelo  = resolverModeloRelatorio($posicionais[0]);
    $classe  = $modelo['classe'];
    $tabela  = $modelo['tabela'];
    $recurso = pascal($tabela);
    $pasta   = strtolower($recurso);

    $colunas = colunasDoEsquema($tabela);

    if ($colunas === []) {
        throw new RuntimeException(
            "Nao encontrei a tabela \"{$tabela}\" em " . caminhoRelativo(arquivoEsquema()) . ".\n"
            . "Gere o CRUD antes:\n  php console.php scaffold:crud {$tabela} nome:string"
        );
    }

    $pedidos = array_slice($posicionais, 1);

    $campos = $remover
        ? camposParaRemover($pedidos, $colunas, $tabela)
        : camposParaAcrescentar($pedidos, $colunas, $tabela);

    $arquivos = arquivosDoRecurso($classe, $recurso, $pasta);

    if (!is_file($arquivos['modelo'])) {
        throw new RuntimeException(
            'Model nao encontrado: ' . caminhoRelativo($arquivos['modelo']) . ".\n"
            . 'O scaffold:campo altera um CRUD que ja existe.'
        );
    }

    // -------------------------------------------------------------
    // 1. Monta todos os arquivos na memoria. Nada e gravado ainda,
    //    entao um anexo que nao case aborta antes de mexer no banco.
    // -------------------------------------------------------------
    $avisos = [];
    $novos  = [];

    $transformar = [
        'modelo'           => fn (string $t): ?string => $remover
            ? modeloSemCampos($t, $campos, $colunas)
            : modeloComCampos($t, $campos, array_key_exists('obrigatorio', $opcoes)),
        'controller'       => fn (string $t): ?string => $remover
            ? controllerSemCampos($t, $campos, $colunas)
            : controllerComCampos($t, $campos, $pasta),
        'formulario'       => fn (string $t): ?string => $remover
            ? formularioSemCampos($t, $campos)
            : formularioComCampos($t, $campos),
        'index'            => fn (string $t): ?string => $remover
            ? indexSemCampos($t, $campos)
            : indexComCampos($t, $campos),
        'ver'              => fn (string $t): ?string => $remover
            ? verSemCampos($t, $campos)
            : verComCampos($t, $campos),
        'teste_modelo'     => fn (string $t): ?string => testeComCampos($t, $tabela, $campos, $colunas, $remover),
        'teste_controller' => fn (string $t): ?string => testeComCampos($t, $tabela, $campos, $colunas, $remover),
    ];

    foreach ($transformar as $chave => $alterar) {
        $caminho = $arquivos[$chave];

        if (!is_file($caminho)) {
            continue;
        }

        $original = lerArquivo($caminho);
        $novo     = $alterar($original);

        if ($novo === null) {
            $avisos[] = 'Nao consegui alterar ' . caminhoRelativo($caminho) . ' — ajuste a mao.';
        } elseif ($novo !== $original) {
            $novos[$caminho] = $novo;
        }
    }

    // O auth:install gera um terceiro teste que tambem recria a tabela do
    // recurso. Sem acertar o CREATE TABLE dele, o campo novo derruba a suite.
    foreach (testesQueRecriamATabela($tabela, $arquivos) as $caminho) {
        $original = lerArquivo($caminho);
        $novo     = testeExtraComCampos($original, $tabela, $campos, $colunas, $remover);

        if ($novo === null) {
            $avisos[] = 'Nao consegui alterar ' . caminhoRelativo($caminho) . ' — ajuste a mao.';
        } elseif ($novo !== $original) {
            $novos[$caminho] = $novo;
        }
    }

    // O model e o unico arquivo sem o qual o campo nao funciona: sem ele na
    // lista de $preenchiveis, o formulario grava e o valor some.
    if (!isset($novos[$arquivos['modelo']])) {
        throw new RuntimeException(
            'Nao encontrei a propriedade $preenchiveis em ' . caminhoRelativo($arquivos['modelo']) . ".\n"
            . 'Ela precisa continuar no formato gerado: protected array $preenchiveis = [...];'
        );
    }

    // -------------------------------------------------------------
    // 2. Apagar coluna apaga dado. Confirma antes.
    // -------------------------------------------------------------
    if ($remover && !array_key_exists('forcar', $opcoes)) {
        $nomes = implode(', ', array_map(fn (array $c): string => $c[0], $campos));

        echo "Isto vai APAGAR a(s) coluna(s) {$nomes} da tabela {$tabela}, com os dados que estiverem la.\n";

        if (!confirmar('Continuar?')) {
            echo "Nada foi alterado.\n";

            return;
        }
    }

    // -------------------------------------------------------------
    // 3. Esquema e banco, com volta atras se algo falhar.
    // -------------------------------------------------------------
    $esquemas = lerEsquemas();

    try {
        esquemaComCampos($tabela, $campos, $remover);

        if ($remover) {
            bancoSemCampos($tabela, $campos);
        } else {
            bancoComCampos($tabela, $campos);
        }
    } catch (Throwable $e) {
        restaurarEsquemas($esquemas);

        throw $e;
    }

    // -------------------------------------------------------------
    // 4. So agora os arquivos.
    // -------------------------------------------------------------
    regravarArquivos($novos);

    $verbo = count($campos) === 1
        ? ($remover ? 'Campo removido de' : 'Campo acrescentado em')
        : ($remover ? 'Campos removidos de' : 'Campos acrescentados em');

    echo "{$verbo} /{$pasta}\n";

    foreach ($campos as [$nome, $tipo, $relacao]) {
        printf("  %-18s %s\n", $nome, $relacao !== null ? "belongs_to={$relacao}" : $tipo);
    }

    echo "\n";

    foreach (array_keys($novos) as $caminho) {
        echo '  ~ ' . caminhoRelativo($caminho) . "\n";
    }

    echo '  ~ ' . caminhoRelativo(arquivoEsquema()) . "\n";
    echo "  ~ tabela {$tabela} no banco\n";

    foreach ($avisos as $aviso) {
        echo "\nAVISO: {$aviso}\n";
    }

    if (!$remover && is_file($arquivos['index']) && str_contains(lerArquivo($arquivos['index']), 'scaffold:pesquisa')) {
        $pesquisados = implode(' ', array_map(fn (array $c): string => $c[0], $campos));

        echo "\nO formulario de pesquisa continua com os campos antigos. Para incluir os novos:\n";
        echo "  php console.php scaffold:pesquisa {$tabela} <campos de antes> {$pesquisados}\n";
    }

    if (!$remover) {
        $nomes = implode(' ', array_map(fn (array $c): string => $c[0], $campos));

        echo "\nPara desfazer: php console.php scaffold:campo {$tabela} {$nomes} --remover\n";
    }

    echo "\nRode os testes com: php testes/executar.php {$classe}\n";
}

/**
 * Campos novos: valida tipo e nome e recusa o que ja existe na tabela.
 *
 * @return list<array{0:string,1:string,2:?string}>
 */
function camposParaAcrescentar(array $pedidos, array $colunas, string $tabela): array
{
    $campos = interpretarCampos($pedidos);

    foreach ($campos as [$nome, , $relacao]) {
        if (isset($colunas[$nome])) {
            throw new InvalidArgumentException(
                "A tabela {$tabela} ja tem a coluna \"{$nome}\".\n"
                . 'Colunas atuais: ' . implode(', ', array_keys($colunas))
            );
        }

        // O roteador usa $_GET['url']: uma coluna com esse nome nunca
        // receberia o valor digitado no filtro do relatorio.
        if ($nome === 'url') {
            throw new InvalidArgumentException(
                'O campo "url" nao pode ser usado: o roteador ja ocupa esse nome na query string.'
            );
        }
    }

    validarRelacoes($campos, $tabela);

    return $campos;
}

/**
 * Campos a remover: o tipo vem do proprio esquema, entao aqui so se informa
 * o nome. O primeiro campo do CRUD nao sai — ele e quem sustenta a
 * validacao e os testes gerados.
 *
 * @return list<array{0:string,1:string,2:?string}>
 */
function camposParaRemover(array $pedidos, array $colunas, string $tabela): array
{
    $primeiro = campoPrincipal($colunas);
    $campos   = [];

    foreach ($pedidos as $pedido) {
        $nome = strtolower(trim(explode(':', $pedido)[0]));

        validarNome($nome, 'nome de campo');

        if (in_array($nome, CAMPOS_RESERVADOS, true)) {
            throw new InvalidArgumentException("O campo \"{$nome}\" e do framework e nao pode ser removido.");
        }

        if (!isset($colunas[$nome])) {
            throw new InvalidArgumentException(
                "A tabela {$tabela} nao tem a coluna \"{$nome}\".\n"
                . 'Colunas disponiveis: ' . implode(', ', array_keys($colunas))
            );
        }

        if ($nome === $primeiro) {
            throw new InvalidArgumentException(
                "\"{$nome}\" e o primeiro campo de {$tabela}: e dele que saem a regra obrigatoria\n"
                . "do model e as asercoes dos testes gerados.\n"
                . 'Para trocar o campo principal do recurso, gere o CRUD de novo.'
            );
        }

        foreach ($campos as $escolhido) {
            if ($escolhido[0] === $nome) {
                throw new InvalidArgumentException("Campo repetido: {$nome}.");
            }
        }

        [$tipo, $relacao] = $colunas[$nome];

        $campos[] = [$nome, $tipo, $relacao];
    }

    return $campos;
}

/** @return array<string,string> os arquivos que o scaffold:crud gerou para o recurso */
function arquivosDoRecurso(string $classe, string $recurso, string $pasta): array
{
    return [
        'modelo'           => CAMINHO_MODELOS . "/{$classe}.php",
        'controller'       => CAMINHO_CONTROLLERS . "/{$recurso}Controller.php",
        'formulario'       => CAMINHO_VIEWS . "/{$pasta}/formulario.php",
        'index'            => CAMINHO_VIEWS . "/{$pasta}/index.php",
        'ver'              => CAMINHO_VIEWS . "/{$pasta}/ver.php",
        'teste_modelo'     => CAMINHO_RAIZ . "/testes/modelos/{$classe}Test.php",
        'teste_controller' => CAMINHO_RAIZ . "/testes/controllers/{$recurso}ControllerTest.php",
    ];
}

/** Pergunta no terminal antes de uma acao que apaga dados. */
function confirmar(string $pergunta): bool
{
    echo $pergunta . ' [s/N]: ';

    $resposta = fgets(STDIN);

    if ($resposta === false) {
        echo "\nSem terminal para responder. Use --forcar se tem certeza.\n";

        return false;
    }

    return in_array(strtolower(trim($resposta)), ['s', 'sim', 'y', 'yes'], true);
}

// ---------------------------------------------------------------------
// scaffold:campo - model
// ---------------------------------------------------------------------

/**
 * O model ganha o campo em $preenchiveis, a regra de validacao do tipo e,
 * quando for uma relacao, o metodo que alimenta o <select>.
 */
function modeloComCampos(string $conteudo, array $campos, bool $obrigatorio): ?string
{
    $novo = preenchiveisComCampos($conteudo, $campos);

    if ($novo === null) {
        return null;
    }

    // As regras entram antes do ->erros(), que fecha a corrente do Validador.
    if (preg_match('/^([ \t]*)->erros\(\);/m', $novo, $fim)) {
        $regras = '';

        foreach ($campos as [$nome, $tipo, $relacao]) {
            foreach (regrasDoCampo($nome, $tipo, $relacao, $obrigatorio) as $regra) {
                if (!str_contains($novo, $regra)) {
                    $regras .= $fim[1] . $regra . "\n";
                }
            }
        }

        if ($regras !== '') {
            $novo = (string) preg_replace_callback(
                '/^[ \t]*->erros\(\);/m',
                fn (array $m): string => $regras . $m[0],
                $novo,
                1
            );
        }
    }

    return modeloComRelacoes($novo, $campos);
}

/** Tira o campo do model: $preenchiveis, regras e metodo da relacao. */
function modeloSemCampos(string $conteudo, array $campos, array $colunas): ?string
{
    $novo = preenchiveisSemCampos($conteudo, $campos);

    if ($novo === null) {
        return null;
    }

    foreach ($campos as [$nome, , $relacao]) {
        // Sai qualquer linha da corrente do Validador que fale desse campo.
        $novo = (string) preg_replace(
            "/^[ \t]*->\w+\('" . preg_quote($nome, '/') . "'[^\n]*\n/m",
            '',
            $novo
        );

        if ($relacao !== null && !outraColunaUsaRelacao($colunas, $relacao, $campos)) {
            // Sai o metodo que alimentava o <select>, com o comentario dele.
            $novo = (string) preg_replace(
                '/\n(?:[ \t]*\/\*\*(?:(?!\*\/)[\s\S])*\*\/\n)?[ \t]*public function '
                    . preg_quote($relacao, '/') . '\(\): array\n[ \t]*\{\n[\s\S]*?\n[ \t]*\}\n/',
                '',
                $novo,
                1
            );
        }
    }

    return $novo;
}

/** Acrescenta os metodos das tabelas pai antes do fecha-chaves da classe. */
function modeloComRelacoes(string $conteudo, array $campos): string
{
    $metodos = '';

    foreach (relacoesUnicas($campos) as $campo) {
        if (preg_match('/function\s+' . preg_quote($campo[2], '/') . '\s*\(/', $conteudo)) {
            continue;
        }

        $metodos .= metodosRelacoesModelo([$campo]);
    }

    if ($metodos === '') {
        return $conteudo;
    }

    return (string) preg_replace('/\n\}\s*$/', "\n" . rtrim($metodos, "\n") . "\n}\n", $conteudo, 1);
}

/** As regras que um campo novo ganha, no mesmo criterio do scaffold:crud. */
function regrasDoCampo(string $nome, string $tipo, ?string $relacao, bool $obrigatorio): array
{
    $regras = [];

    if ($obrigatorio || $relacao !== null) {
        $regras[] = "->obrigatorio('{$nome}')";
    }

    if ($nome === 'email') {
        $regras[] = "->email('{$nome}')";
    }

    $doTipo = match ($tipo) {
        'integer', 'decimal' => "->numerico('{$nome}')",
        'string'             => "->maximo('{$nome}', 255)",
        default              => null,
    };

    if ($doTipo !== null) {
        $regras[] = $doTipo;
    }

    return $regras;
}

/** Acrescenta os nomes na lista $preenchiveis do model. */
function preenchiveisComCampos(string $conteudo, array $campos): ?string
{
    $padrao = '/(protected\s+array\s+\$preenchiveis\s*=\s*\[)([^\]]*)(\]\s*;)/';

    if (!preg_match($padrao, $conteudo)) {
        return null;
    }

    return (string) preg_replace_callback($padrao, function (array $m) use ($campos): string {
        $lista = trim($m[2]);

        foreach ($campos as [$nome]) {
            if (preg_match("/'" . preg_quote($nome, '/') . "'/", $lista)) {
                continue;
            }

            $lista = $lista === '' ? "'{$nome}'" : $lista . ", '{$nome}'";
        }

        return $m[1] . $lista . $m[3];
    }, $conteudo, 1);
}

/** Tira os nomes da lista $preenchiveis do model. */
function preenchiveisSemCampos(string $conteudo, array $campos): ?string
{
    $padrao = '/(protected\s+array\s+\$preenchiveis\s*=\s*\[)([^\]]*)(\]\s*;)/';

    if (!preg_match($padrao, $conteudo)) {
        return null;
    }

    return (string) preg_replace_callback($padrao, function (array $m) use ($campos): string {
        $remover = array_map(fn (array $c): string => $c[0], $campos);

        $itens = array_filter(
            array_map('trim', explode(',', $m[2])),
            fn (string $item): bool => $item !== '' && !in_array(trim($item, "'\" "), $remover, true)
        );

        return $m[1] . implode(', ', $itens) . $m[3];
    }, $conteudo, 1);
}

/** Alguma outra coluna da tabela ainda aponta para essa tabela pai? */
function outraColunaUsaRelacao(array $colunas, string $pai, array $saindo): bool
{
    $nomesSaindo = array_map(fn (array $c): string => $c[0], $saindo);

    foreach ($colunas as $nome => [, $relacao]) {
        if ($relacao === $pai && !in_array($nome, $nomesSaindo, true)) {
            return true;
        }
    }

    return false;
}

// ---------------------------------------------------------------------
// scaffold:campo - controller
// ---------------------------------------------------------------------

/**
 * Recorta um metodo inteiro do controller, para alterar so o que esta
 * dentro dele. Devolve null quando o metodo nao existe.
 *
 * E o que impede, por exemplo, que o filtro do relatorio() va parar no
 * index() — os dois tem uma linha "$sql = 'SELECT * FROM '".
 */
function blocoMetodoDoController(string $conteudo, string $metodo): ?string
{
    $padrao = '/\n[ ]{4}public function ' . preg_quote($metodo, '/')
        . '\([^)]*\)(?:\s*:\s*\w+)?\n[ ]{4}\{\n[\s\S]*?\n[ ]{4}\}\n/';

    return preg_match($padrao, $conteudo, $encontrado) ? $encontrado[0] : null;
}

/**
 * O controller passa a ler o campo do formulario, a filtrar por ele no
 * relatorio e a mandar a lista da tabela pai para o <select>.
 */
function controllerComCampos(string $conteudo, array $campos, string $pasta): ?string
{
    $novo = dadosComCampos($conteudo, $campos);

    if ($novo === null) {
        return null;
    }

    $novo = controllerComArquivos($novo, camposDeArquivo($campos), $pasta);
    $novo = relatorioComCampos($novo, $campos);

    // criar() e editar() desenham o mesmo formulario: as duas precisam das
    // opcoes da tabela pai.
    foreach (['criar', 'editar'] as $metodo) {
        $bloco = blocoMetodoDoController($novo, $metodo);

        if ($bloco === null) {
            continue;
        }

        $alterado = $bloco;

        foreach (relacoesUnicas($campos) as $campo) {
            $pai = $campo[2];

            if (str_contains($alterado, "'{$pai}'")) {
                continue;
            }

            // Sem str_pad: o scaffold:crud escreve essa linha sem alinhar,
            // e as duas formas de gerar precisam sair iguais.
            $alterado = (string) preg_replace(
                "/^([ \t]*)('registro'\s*=>[^\n]*,)$/m",
                "\${1}\${2}\n\${1}'{$pai}' => " . '\\$this->modelo->' . $pai . '(),',
                $alterado,
                1
            );
        }

        $novo = str_replace($bloco, $alterado, $novo);
    }

    return $novo;
}

/** Desfaz o que o controllerComCampos() tinha colocado. */
function controllerSemCampos(string $conteudo, array $campos, array $colunas): ?string
{
    $novo = dadosSemCampos($conteudo, $campos);

    if ($novo === null) {
        return null;
    }

    $novo = controllerSemArquivos($novo, $campos);
    $novo = relatorioSemCampos($novo, $campos);

    foreach ($campos as [, , $relacao]) {
        if ($relacao === null || outraColunaUsaRelacao($colunas, $relacao, $campos)) {
            continue;
        }

        $novo = (string) preg_replace(
            "/^[ \t]*'" . preg_quote($relacao, '/') . "'\s*=>\s*\\\$this->modelo->"
                . preg_quote($relacao, '/') . "\(\),\n/m",
            '',
            $novo
        );
    }

    return $novo;
}

/**
 * Poe no controller o tratamento de upload dos campos de arquivo.
 *
 * Gera exatamente os mesmos trechos do scaffold:crud (trechoDeArquivos()),
 * so que encaixados em um controller que ja existe.
 */
function controllerComArquivos(string $conteudo, array $arquivos, string $pasta): string
{
    if ($arquivos === []) {
        return $conteudo;
    }

    $conteudo = comImportacaoDeArquivo($conteudo);

    foreach (['salvar' => 'gravar', 'atualizar' => 'substituir'] as $metodo => $gravacao) {
        $bloco = blocoMetodoDoController($conteudo, $metodo);

        if ($bloco === null) {
            continue;
        }

        $novo = $metodo === 'atualizar' ? comRegistroAntigo($bloco) : $bloco;

        // Pegar o arquivo, conferir junto com a validacao e so entao gravar.
        $novo = inserirDepois($novo, '/(\$dados = \[\n(?:[^\n]*\n)*?[ \t]*\];\n)/', trechoDeArquivos($arquivos, 'receber', $pasta));
        $novo = inserirDepois($novo, '/(\$erros = \$this->modelo->validar\([^\n]*\n)/', trechoDeArquivos($arquivos, 'conferir', $pasta));
        $novo = inserirDepois($novo, '/(if \(\$erros !== \[\]\) \{\n(?:[^\n]*\n)*?[ \t]*\}\n)/', trechoDeArquivos($arquivos, $gravacao, $pasta));

        $conteudo = str_replace($bloco, $novo, $conteudo);
    }

    $bloco = blocoMetodoDoController($conteudo, 'excluir');

    if ($bloco === null) {
        return $conteudo;
    }

    $novo = $bloco;

    if (!str_contains($novo, '$registro = $this->modelo->buscar($id);')) {
        $novo = str_replace(
            '        if (!$this->modelo->excluir($id)) {',
            "        \$registro = \$this->modelo->buscar(\$id);\n\n        if (!\$this->modelo->excluir(\$id)) {",
            $novo
        );
    }

    $novo = inserirDepois(
        $novo,
        '/(if \(!\$this->modelo->excluir\(\$id\)\) \{\n(?:[^\n]*\n)*?[ \t]*\}\n)/',
        trechoDeArquivos($arquivos, 'apagar', $pasta)
    );

    return str_replace($bloco, $novo, $conteudo);
}

/**
 * Tira do controller o tratamento de upload dos campos que sairam.
 *
 * Se ainda restar outro campo de arquivo, a estrutura em volta (o $registro
 * do atualizar(), o import) fica como esta — ela continua sendo usada.
 */
function controllerSemArquivos(string $conteudo, array $campos): string
{
    foreach ($campos as [$nome]) {
        $var = preg_quote('$' . variavelDoArquivo($nome), '/');

        $conteudo = (string) preg_replace([
            // a linha que pega o arquivo
            "/^[ \t]*{$var} = \\\$this->(?:imagem|arquivo)\([^\n]*\n/m",
            // os blocos "if (\$arquivoX !== null ...) { ... }"
            "/^[ \t]*if \({$var} !== null[^\n]*\{\n(?:[^\n]*\n)*?[ \t]*\}\n/m",
            // e o apagar do excluir()
            "/^[ \t]*Arquivo::apagar\(\\\$registro\['" . preg_quote($nome, '/') . "'\][^\n]*\n/m",
        ], '', $conteudo);
    }

    // Sobrou algum campo de arquivo? Entao a estrutura continua necessaria.
    if (preg_match('/\$this->(?:imagem|arquivo)\(/', $conteudo)) {
        return juntarLinhasEmBranco(limparComentariosDeArquivo($conteudo));
    }

    $conteudo = semRegistroAntigo($conteudo);
    $conteudo = (string) preg_replace('/^use Nucleo\\\\Arquivo;\n/m', '', $conteudo, 1);

    return juntarLinhasEmBranco(limparComentariosDeArquivo($conteudo));
}

/**
 * Junta as linhas em branco que sobraram no lugar dos trechos removidos.
 *
 * Tirar linhas vizinhas deixa buracos de tamanhos diferentes; o controller
 * gerado nunca tem duas linhas em branco seguidas, entao encostar tudo em
 * uma so devolve o arquivo ao formato original.
 */
function juntarLinhasEmBranco(string $conteudo): string
{
    return (string) preg_replace("/\n{3,}/", "\n\n", $conteudo);
}

/** Os comentarios dos trechos de upload saem junto com o ultimo bloco deles. */
function limparComentariosDeArquivo(string $conteudo): string
{
    $orfaos = [
        '        // O arquivo so vai para o disco depois que o resto passou.',
        '        // O arquivo novo substitui o anterior, que sai do disco.',
        '        // O registro saiu; o arquivo dele nao fica ocupando disco.',
    ];

    foreach ($orfaos as $comentario) {
        // So sai quando nao sobrou codigo embaixo dele.
        $conteudo = (string) preg_replace(
            '/\n?' . preg_quote($comentario, '/') . '\n(?=\n|[ \t]*\}|[ \t]*\$(?:id|this)\b)/',
            "\n",
            $conteudo
        );
    }

    return $conteudo;
}

/** O atualizar() passa a carregar o registro antigo, para saber o arquivo atual. */
function comRegistroAntigo(string $bloco): string
{
    return str_replace(
        "        if (!\$this->modelo->existe(\$id)) {\n            \$this->naoEncontrado();\n        }",
        "        \$registro = \$this->modelo->buscar(\$id);\n\n        if (\$registro === null) {\n            \$this->naoEncontrado();\n        }",
        $bloco
    );
}

/** Caminho inverso: sem arquivo, basta saber se o registro existe. */
function semRegistroAntigo(string $conteudo): string
{
    foreach (['atualizar', 'excluir'] as $metodo) {
        $bloco = blocoMetodoDoController($conteudo, $metodo);

        if ($bloco === null) {
            continue;
        }

        $novo = str_replace(
            "        \$registro = \$this->modelo->buscar(\$id);\n\n        if (\$registro === null) {\n            \$this->naoEncontrado();\n        }",
            "        if (!\$this->modelo->existe(\$id)) {\n            \$this->naoEncontrado();\n        }",
            $bloco
        );

        $novo = (string) preg_replace(
            "/^[ \t]*\\\$registro = \\\$this->modelo->buscar\(\\\$id\);\n\n(?=[ \t]*if \(!)/m",
            '',
            $novo,
            1
        );

        $conteudo = str_replace($bloco, $novo, $conteudo);
    }

    return $conteudo;
}

/** O trecho de upload usa Arquivo::apagar(), entao o import precisa existir. */
function comImportacaoDeArquivo(string $conteudo): string
{
    if (preg_match('/^use\s+Nucleo\\Arquivo;/m', $conteudo)) {
        return $conteudo;
    }

    return (string) preg_replace(
        '/^(use\s+Modelos\\\\\w+;)$/m',
        "$1\nuse Nucleo\\Arquivo;",
        $conteudo,
        1
    );
}

/** Insere um trecho logo depois do que o padrao encontrar. */
function inserirDepois(string $texto, string $padrao, string $trecho): string
{
    if ($trecho === '') {
        return $texto;
    }

    return (string) preg_replace(
        $padrao,
        '$1' . preg_quote_replace($trecho),
        $texto,
        1
    );
}

/**
 * Os "$dados = [...]" do salvar() e do atualizar() ganham o campo novo.
 * Mesmo criterio do auth:install, que ja fazia isso com email e senha.
 */
function dadosComCampos(string $conteudo, array $campos): ?string
{
    $blocos = 0;

    $novo = preg_replace_callback(
        '/^([ \t]*)\$dados = \[\R((?:[ \t]+.*\$this->post\(.*\R)+)\1\];/m',
        function (array $m) use (&$blocos, $campos): string {
            $blocos++;

            $linhas = $m[2];
            $recuo  = preg_match('/^[ \t]+/', $linhas, $r) ? $r[0] : $m[1] . '    ';

            foreach ($campos as [$nome, $tipo, $relacao]) {
                // Campo de arquivo nao vem do $_POST: quem cuida dele e a
                // classe Nucleo\Arquivo, mais abaixo no mesmo metodo.
                if ($relacao === null && in_array($tipo, TIPOS_ARQUIVO, true)) {
                    continue;
                }

                if (!preg_match("/['\"]" . preg_quote($nome, '/') . "['\"]\s*=>/", $linhas)) {
                    $linhas .= "{$recuo}'{$nome}' => \$this->post('{$nome}'),\n";
                }
            }

            return $m[1] . "\$dados = [\n" . $linhas . $m[1] . '];';
        },
        $conteudo
    );

    return $blocos > 0 ? (string) $novo : null;
}

/** Tira o campo dos "$dados = [...]" do controller. */
function dadosSemCampos(string $conteudo, array $campos): ?string
{
    $novo = $conteudo;

    foreach ($campos as [$nome]) {
        $novo = (string) preg_replace(
            "/^[ \t]*'" . preg_quote($nome, '/') . "'\s*=>\s*\\\$this->post\('"
                . preg_quote($nome, '/') . "'\),\n/m",
            '',
            $novo
        );
    }

    return $novo;
}

/**
 * O relatorio() ganha o filtro do campo novo e a coluna na tabela do PDF.
 * Sem CRUD com relatorio o metodo simplesmente nao existe: nesse caso o
 * conteudo volta como estava.
 */
function relatorioComCampos(string $conteudo, array $campos): string
{
    $bloco = blocoMetodoDoController($conteudo, 'relatorio');

    if ($bloco === null) {
        return $conteudo;
    }

    $alterado = $bloco;

    // 1. Os filtros, logo antes da montagem do SELECT.
    $filtros = '';

    foreach ($campos as [$nome, $tipo, $relacao]) {
        if (str_contains($alterado, "\$this->get('{$nome}')")) {
            continue;
        }

        $filtros .= filtroRelatorioGerado($nome, $tipo, $relacao);
    }

    if ($filtros !== '') {
        $alterado = (string) preg_replace(
            "/^[ \t]*\\\$sql = 'SELECT \* FROM '/m",
            preg_quote_replace($filtros) . '$0',
            $alterado,
            1
        );
    }

    // 2. A lista de colunas que o PDF imprime.
    $alterado = (string) preg_replace_callback(
        '/(RelatorioPdf::conteudo\(\s*[^,]+,\s*\[)([^\]]*)(\])/',
        function (array $m) use ($campos): string {
            $lista = trim($m[2]);

            foreach ($campos as [$nome]) {
                if (preg_match("/'" . preg_quote($nome, '/') . "'/", $lista)) {
                    continue;
                }

                $lista = $lista === '' ? "'{$nome}'" : $lista . ", '{$nome}'";
            }

            return $m[1] . $lista . $m[3];
        },
        $alterado,
        1
    );

    return str_replace($bloco, $alterado, $conteudo);
}

/** Tira o filtro e a coluna do relatorio(). */
function relatorioSemCampos(string $conteudo, array $campos): string
{
    $bloco = blocoMetodoDoController($conteudo, 'relatorio');

    if ($bloco === null) {
        return $conteudo;
    }

    $alterado = $bloco;

    foreach ($campos as [$nome]) {
        $escapado = preg_quote($nome, '/');

        $alterado = (string) preg_replace(
            "/^[ \t]*\\\$filtro = \\\$this->get\('{$escapado}'\);\n[ \t]*if \([^\n]*\n(?:[^\n]*\n)*?[ \t]*\}\n\n?/m",
            '',
            $alterado,
            1
        );

        $alterado = (string) preg_replace_callback(
            '/(RelatorioPdf::conteudo\(\s*[^,]+,\s*\[)([^\]]*)(\])/',
            function (array $m) use ($nome): string {
                $itens = array_filter(
                    array_map('trim', explode(',', $m[2])),
                    fn (string $item): bool => $item !== '' && trim($item, "'\" ") !== $nome
                );

                return $m[1] . implode(', ', $itens) . $m[3];
            },
            $alterado,
            1
        );
    }

    return str_replace($bloco, $alterado, $conteudo);
}

// ---------------------------------------------------------------------
// scaffold:campo - views
// ---------------------------------------------------------------------

/** O formulario ganha o campo logo antes dos botoes. */
function formularioComCampos(string $conteudo, array $campos): ?string
{
    $blocos = '';

    foreach ($campos as $campo) {
        if (str_contains($conteudo, 'name="' . $campo[0] . '"')) {
            continue;
        }

        $blocos .= campoFormularioGerado($campo);
    }

    if ($blocos === '') {
        return $conteudo;
    }

    $padrao = '/^[ \t]*<\/div>\R[ \t]*<div class="d-flex gap-2 mt-4">/m';

    if (!preg_match($padrao, $conteudo)) {
        return null;
    }

    $conteudo = (string) preg_replace_callback($padrao, fn (array $m): string => $blocos . $m[0], $conteudo, 1);

    return formularioComEnctype($conteudo);
}

/**
 * Garante o enctype no <form> quando a tela passa a ter campo de arquivo.
 *
 * Sem ele o navegador manda so o NOME do arquivo e o $_FILES chega vazio —
 * o erro mais comum de formulario com upload.
 */
function formularioComEnctype(string $conteudo): string
{
    if (!str_contains($conteudo, 'type="file"') || str_contains($conteudo, 'enctype=')) {
        return $conteudo;
    }

    return (string) preg_replace(
        '/(<form\b[^>]*?)(\s+method="post")/',
        '$1$2 enctype="multipart/form-data"',
        $conteudo,
        1
    );
}

/** Tira o enctype quando nao sobrou nenhum campo de arquivo na tela. */
function formularioSemEnctype(string $conteudo): string
{
    if (str_contains($conteudo, 'type="file"')) {
        return $conteudo;
    }

    return (string) preg_replace('/\s+enctype="multipart\/form-data"/', '', $conteudo, 1);
}

/** Tira do formulario o bloco <div> inteiro do campo. */
function formularioSemCampos(string $conteudo, array $campos): ?string
{
    foreach ($campos as [$nome]) {
        $conteudo = removerBlocoDaView($conteudo, 'name="' . $nome . '"');
    }

    return formularioSemEnctype($conteudo);
}

/** A listagem ganha a coluna no cabecalho e na linha. */
function indexComCampos(string $conteudo, array $campos): ?string
{
    $cabecalhos = '';
    $celulas    = '';
    $novos      = 0;

    foreach ($campos as [$nome, $tipo]) {
        if (preg_match('/<th>' . preg_quote($nome, '/') . '<\/th>/', $conteudo)) {
            continue;
        }

        $novos++;
        $cabecalhos .= "<th>{$nome}</th>\n";
        $celulas .= '<td>' . valorNaTela($nome, $tipo) . "</td>\n";
    }

    if ($novos === 0) {
        return $conteudo;
    }

    $cabecalho = '/^([ \t]*)<th class="text-end">Acoes<\/th>/m';
    $celula    = '/^([ \t]*)<td class="text-end text-nowrap">/m';

    if (!preg_match($cabecalho, $conteudo) || !preg_match($celula, $conteudo)) {
        return null;
    }

    $conteudo = (string) preg_replace_callback(
        $cabecalho,
        fn (array $m): string => $m[1] . str_replace("\n", "\n" . $m[1], rtrim($cabecalhos, "\n")) . "\n" . $m[0],
        $conteudo,
        1
    );

    $conteudo = (string) preg_replace_callback(
        $celula,
        fn (array $m): string => $m[1] . str_replace("\n", "\n" . $m[1], rtrim($celulas, "\n")) . "\n" . $m[0],
        $conteudo,
        1
    );

    return colspanAjustado($conteudo, $novos);
}

/** Tira a coluna do cabecalho e da linha da listagem. */
function indexSemCampos(string $conteudo, array $campos): ?string
{
    $saiu = 0;

    foreach ($campos as [$nome]) {
        $antes = $conteudo;

        $conteudo = (string) preg_replace(
            '/^[ \t]*<th>' . preg_quote($nome, '/') . "<\/th>\n/m",
            '',
            $conteudo,
            1
        );

        // Casa com qualquer forma de mostrar a coluna: e(), sim_nao(),
        // miniatura() ou link_arquivo().
        $conteudo = (string) preg_replace(
            '/^[ \t]*<td><\?=[^\n]*\$registro\[\'' . preg_quote($nome, '/') . "'\][^\n]*\n/m",
            '',
            $conteudo,
            1
        );

        if ($antes !== $conteudo) {
            $saiu++;
        }
    }

    return $saiu === 0 ? $conteudo : colspanAjustado($conteudo, -$saiu);
}

/** Mantem o colspan do "nenhum registro" do tamanho da tabela. */
function colspanAjustado(string $conteudo, int $diferenca): string
{
    return (string) preg_replace_callback(
        '/colspan="(\d+)"/',
        fn (array $m): string => 'colspan="' . max(1, (int) $m[1] + $diferenca) . '"',
        $conteudo,
        1
    );
}

/** A tela de detalhe ganha mais uma linha na lista de definicoes. */
function verComCampos(string $conteudo, array $campos): ?string
{
    $linhas = '';
    $recuo  = preg_match('/^([ \t]*)<dt\b/m', $conteudo, $dt) ? $dt[1] : '        ';

    foreach ($campos as [$nome, $tipo]) {
        if (str_contains($conteudo, "\$registro['{$nome}']")) {
            continue;
        }

        $linhas .= "{$recuo}<dt class=\"col-sm-3\">{$nome}</dt>\n"
            . "{$recuo}<dd class=\"col-sm-9\">" . valorNaTela($nome, $tipo, true) . "</dd>\n";
    }

    if ($linhas === '') {
        return $conteudo;
    }

    if (!preg_match('/^[ \t]*<\/dl>/m', $conteudo)) {
        return null;
    }

    return (string) preg_replace_callback(
        '/^[ \t]*<\/dl>/m',
        fn (array $m): string => $linhas . $m[0],
        $conteudo,
        1
    );
}

/** Tira o <dt>/<dd> do campo da tela de detalhe. */
function verSemCampos(string $conteudo, array $campos): ?string
{
    foreach ($campos as [$nome]) {
        $conteudo = (string) preg_replace(
            '/^[ \t]*<dt[^\n]*>' . preg_quote($nome, '/') . "<\/dt>\n[ \t]*<dd[^\n]*\n/m",
            '',
            $conteudo,
            1
        );
    }

    return $conteudo;
}

/**
 * Recorta da view o <div> que contem uma marca, do <div> de abertura ate o
 * </div> que fecha ele — e como os campos do formulario sao gerados.
 */
function removerBlocoDaView(string $conteudo, string $marca): string
{
    $posicao = strpos($conteudo, $marca);

    if ($posicao === false) {
        return $conteudo;
    }

    $abertura = strrpos(substr($conteudo, 0, $posicao), '<div class="col');

    if ($abertura === false) {
        return $conteudo;
    }

    $inicio = strrpos(substr($conteudo, 0, $abertura), "\n");
    $inicio = $inicio === false ? 0 : $inicio + 1;

    // Anda pelo bloco contando <div> e </div> ate o que fecha a abertura.
    $nivel  = 0;
    $cursor = $abertura;
    $tamanho = strlen($conteudo);

    while ($cursor < $tamanho) {
        $proximoAbre  = strpos($conteudo, '<div', $cursor);
        $proximoFecha = strpos($conteudo, '</div>', $cursor);

        if ($proximoFecha === false) {
            return $conteudo;
        }

        if ($proximoAbre !== false && $proximoAbre < $proximoFecha) {
            $nivel++;
            $cursor = $proximoAbre + 4;

            continue;
        }

        $nivel--;
        $cursor = $proximoFecha + 6;

        if ($nivel === 0) {
            break;
        }
    }

    // Leva junto a linha em branco que separa um campo do outro.
    $fim = $cursor;

    while ($fim < $tamanho && ($conteudo[$fim] === "\n" || $conteudo[$fim] === "\r")) {
        $fim++;
    }

    return substr($conteudo, 0, $inicio) . substr($conteudo, $fim);
}

// ---------------------------------------------------------------------
// scaffold:campo - testes gerados
// ---------------------------------------------------------------------

/**
 * Os testes gerados recriam a tabela com as colunas da epoca do scaffold e
 * postam um array de dados fixo. Sem acertar os dois, acrescentar um campo
 * deixaria a suite vermelha na hora.
 */
function testeComCampos(string $conteudo, string $tabela, array $campos, array $colunas, bool $remover): ?string
{
    $principal = campoPrincipal($colunas);

    if ($principal === null) {
        return null;
    }

    if ($remover) {
        $novo = testeSemTabelasPai($conteudo, $campos, $colunas);
        $novo = removerEmListasDeDados($novo, $campos);

        return tabelaDoTesteComCampos($novo, $tabela, $campos, true);
    }

    $novo = testeComTabelasPai($conteudo, $tabela, $campos);
    $novo = tabelaDoTesteComCampos($novo, $tabela, $campos, false);

    if ($novo === null) {
        return null;
    }

    $linhas      = [];
    $atualizadas = [];

    foreach ($campos as $campo) {
        $linhas[]      = "'{$campo[0]}' => " . valorNoTeste($campo) . ',';
        $atualizadas[] = "'{$campo[0]}' => " . valorNoTeste($campo, true) . ',';
    }

    // O array que o teste usa no atualizar() leva os valores "depois", para
    // o arquivo ficar igual ao que o scaffold:crud geraria com o campo novo.
    $depois = valorNoTeste([$principal, ...$colunas[$principal]], true);

    return acrescentarEmListasDeDados($novo, $linhas, $atualizadas, $principal, $depois);
}

/** Primeira coluna depois do id: e ela que aparece em todo array de dados. */
function campoPrincipal(array $colunas): ?string
{
    foreach (array_keys($colunas) as $nome) {
        if ($nome !== 'id') {
            return $nome;
        }
    }

    return null;
}

/**
 * Acrescenta (ou tira) colunas do CREATE TABLE que o teste monta em
 * preparar(). Generaliza o que o auth:install ja fazia com email e senha.
 */
function tabelaDoTesteComCampos(string $conteudo, string $tabela, array $campos, bool $remover): ?string
{
    $nome   = preg_quote($tabela, '/');
    $padrao = "/('{$nome}'\s*=>\s*\"CREATE TABLE `?{$nome}`? \(\R)(.*?)(\R[ \t]*\)\",)/s";

    if (!preg_match($padrao, $conteudo)) {
        // Um teste que nao recria a tabela nao precisa de ajuste nenhum.
        return $conteudo;
    }

    return (string) preg_replace_callback($padrao, function (array $m) use ($campos, $tabela, $remover): string {
        $recuo      = preg_match('/^[ \t]*/', $m[2], $r) ? $r[0] : '';
        $definicoes = array_map('trim', explode(",\n", str_replace("\r\n", "\n", $m[2])));

        if ($remover) {
            foreach ($campos as [$coluna]) {
                $definicoes = array_values(array_filter(
                    $definicoes,
                    fn (string $definicao): bool => !preg_match('/^`?' . preg_quote($coluna, '/') . '`?\b/i', $definicao)
                        && !preg_match('/\(\s*`?' . preg_quote($coluna, '/') . '`?\s*\)/i', $definicao)
                ));
            }

            return $m[1] . $recuo . implode(",\n{$recuo}", $definicoes) . $m[3];
        }

        $novas      = [];
        $restricoes = [];

        foreach ($campos as [$coluna, $tipo, $relacao]) {
            if (preg_grep('/^`?' . preg_quote($coluna, '/') . '`?\b/i', $definicoes) !== []) {
                continue;
            }

            $novas[] = sqlNome($coluna) . ' ' . tipoSql($tipo) . ' NULL';

            if ($relacao !== null) {
                $restricoes[] = "CONSTRAINT fk_{$tabela}_{$coluna} FOREIGN KEY ("
                    . sqlNome($coluna) . ') REFERENCES ' . sqlNome($relacao) . '(`id`)';
            }
        }

        // Coluna nunca depois de CONSTRAINT: e assim que se le um CREATE TABLE.
        $primeiraRestricao = count($definicoes);

        foreach ($definicoes as $indice => $definicao) {
            if (preg_match('/^CONSTRAINT\b/i', $definicao)) {
                $primeiraRestricao = $indice;
                break;
            }
        }

        array_splice($definicoes, $primeiraRestricao, 0, $novas);

        return $m[1] . $recuo . implode(",\n{$recuo}", array_merge($definicoes, $restricoes)) . $m[3];
    }, $conteudo, 1);
}

/** O teste precisa da tabela pai e dos ids que o <select> vai usar. */
function testeComTabelasPai(string $conteudo, string $tabela, array $campos): string
{
    foreach (relacoesUnicas($campos) as $campo) {
        $pai = $campo[2];

        if ($pai === $tabela || str_contains($conteudo, "'{$pai}' => 'CREATE TABLE")) {
            continue;
        }

        $conteudo = (string) preg_replace_callback(
            "/^([ \t]*)'" . preg_quote($tabela, '/') . "'\s*=>\s*\"CREATE TABLE/m",
            fn (array $m): string => $m[1] . "'{$pai}' => 'CREATE TABLE " . sqlNome($pai)
                . " (`id` INT AUTO_INCREMENT PRIMARY KEY, `nome` VARCHAR(255) NULL)',\n" . $m[0],
            $conteudo,
            1
        );
    }

    foreach (relacoesUnicas($campos) as $campo) {
        if (str_contains($conteudo, "\$this->idsRelacoes['{$campo[0]}']")) {
            continue;
        }

        $conteudo = comIdsDaRelacao($conteudo, idsDasRelacoes([$campo]));
    }

    // No teste do model, a relacao ainda ganha as asercoes do <select>.
    $assercoes = assercoesDeRelacaoGeradas($campos);

    if ($assercoes !== '' && str_contains($conteudo, '$id = $this->modelo->criar($dados);')) {
        $novas = '';

        foreach (explode("\n\n", rtrim($assercoes, "\n")) as $bloco) {
            if ($bloco !== '' && !str_contains($conteudo, trim(explode("\n", $bloco)[0]))) {
                $novas .= $bloco . "\n\n";
            }
        }

        $conteudo = str_replace(
            '        $id = $this->modelo->criar($dados);',
            $novas . '        $id = $this->modelo->criar($dados);',
            $conteudo
        );
    }

    return $conteudo;
}

/**
 * Encaixa o trecho que popula a tabela pai dentro do preparar().
 *
 * Quando ja existe outra relacao, o trecho novo entra colado no dela; se for
 * a primeira, entra depois do recriarTabelas() — nos dois casos com o mesmo
 * espacamento que o scaffold:crud produziria.
 */
function comIdsDaRelacao(string $conteudo, string $ids): string
{
    $ultima = strrpos($conteudo, '$this->idsRelacoesAtualizadas[');

    if ($ultima !== false) {
        $fimDaLinha = strpos($conteudo, "\n", $ultima);
        $corte      = $fimDaLinha === false ? strlen($conteudo) : $fimDaLinha;

        return substr($conteudo, 0, $corte) . $ids . substr($conteudo, $corte);
    }

    return (string) preg_replace(
        '/(\$this->recriarTabelas\(\[[\s\S]*?\n[ \t]*\]\);)\n+/',
        '$1' . "\n" . preg_quote_replace($ids) . "\n\n",
        $conteudo,
        1
    );
}

/** Desfaz o testeComTabelasPai(). */
function testeSemTabelasPai(string $conteudo, array $campos, array $colunas): string
{
    foreach ($campos as [$nome, , $relacao]) {
        if ($relacao === null) {
            continue;
        }

        $conteudo = (string) preg_replace(
            "/^[ \t]*\\\$this->idsRelacoes(?:Atualizadas)?\['" . preg_quote($nome, '/') . "'\][^\n]*\n/m",
            '',
            $conteudo
        );

        if (outraColunaUsaRelacao($colunas, $relacao, $campos)) {
            continue;
        }

        $conteudo = (string) preg_replace(
            "/^[ \t]*'" . preg_quote($relacao, '/') . "'\s*=>\s*'CREATE TABLE[^\n]*\n/m",
            '',
            $conteudo,
            1
        );

        $conteudo = (string) preg_replace(
            '/^[ \t]*Database::conexao\(\)->exec\("INSERT INTO `?' . preg_quote($relacao, '/') . "`?[^\n]*\n/m",
            '',
            $conteudo,
            1
        );

        $conteudo = (string) preg_replace(
            '/^[ \t]*\$opcoes = \$this->modelo->' . preg_quote($relacao, '/') . "\(\);\n"
                . '[ \t]*\$this->assertTotal\(2, \$opcoes\);\n'
                . "[ \t]*\\\$this->assertVerdadeiro\(in_array[^\n]*\n\n?/m",
            '',
            $conteudo,
            1
        );
    }

    return $conteudo;
}

/**
 * Acrescenta linhas em todos os arrays de dados do teste.
 *
 * Um "array de dados" e uma sequencia de linhas 'campo' => valor, — e o que
 * o teste passa para criar(), para postar() e para validar(). A sequencia
 * que contem o campo principal e sempre uma delas.
 */
function acrescentarEmListasDeDados(
    string $conteudo,
    array $novas,
    array $atualizadas,
    string $principal,
    string $depois
): string {
    return percorrerListasDeDados(
        $conteudo,
        $principal,
        function (array $bloco, string $abertura) use ($novas, $atualizadas, $principal, $depois): array {
            // A lista de filtros do relatorio nao e um registro: acrescentar
            // campos ali so estreitaria a busca do teste sem motivo.
            if (str_contains($abertura, 'relatorio')) {
                return $bloco;
            }

            $recuo = preg_match('/^[ \t]*/', $bloco[0], $r) ? $r[0] : '            ';

            // Reconhece o array do atualizar() pelo valor do campo principal.
            $linhaPrincipal = preg_grep("/^[ \t]*'" . preg_quote($principal, '/') . "'\s*=>/", $bloco);
            $ehAtualizacao  = $linhaPrincipal !== [] && str_contains(reset($linhaPrincipal), $depois);

            foreach ($ehAtualizacao ? $atualizadas : $novas as $linha) {
                $campo = (string) preg_replace("/^'(\w+)'.*$/", '$1', $linha);

                if (preg_grep("/^[ \t]*'" . preg_quote($campo, '/') . "'\s*=>/", $bloco) !== []) {
                    continue;
                }

                $bloco[] = $recuo . $linha;
            }

            return $bloco;
        }
    );
}

/** Tira as linhas do campo de todos os arrays de dados do teste. */
function removerEmListasDeDados(string $conteudo, array $campos): string
{
    foreach ($campos as [$nome]) {
        $conteudo = (string) preg_replace(
            "/^[ \t]*'" . preg_quote($nome, '/') . "'\s*=>\s*(?!['\"]CREATE TABLE)[^\n]*,\n/m",
            '',
            $conteudo
        );
    }

    return $conteudo;
}

/**
 * Encontra cada sequencia de linhas "'campo' => valor," que contenha o
 * campo principal e entrega a sequencia — com a linha que abriu o array —
 * para a funcao decidir o que muda.
 */
function percorrerListasDeDados(string $conteudo, string $principal, callable $alterar): string
{
    $linhas    = explode("\n", $conteudo);
    $resultado = [];
    $bloco     = [];
    $achou     = false;
    $abertura  = '';

    $fechar = function () use (&$bloco, &$achou, &$abertura, &$resultado, $alterar): void {
        if ($bloco === []) {
            return;
        }

        $resultado = array_merge($resultado, $achou ? $alterar($bloco, $abertura) : $bloco);
        $bloco     = [];
        $achou     = false;
    };

    foreach ($linhas as $linha) {
        if (preg_match("/^[ \t]*'\w+'\s*=>\s*.+,$/", $linha)) {
            $bloco[] = $linha;

            if (preg_match("/^[ \t]*'" . preg_quote($principal, '/') . "'\s*=>/", $linha)) {
                $achou = true;
            }

            continue;
        }

        $fechar();

        // Guarda a linha que abre o array: e ela que diz para que ele serve.
        $abertura    = $linha;
        $resultado[] = $linha;
    }

    $fechar();

    return implode("\n", $resultado);
}

/**
 * Outros testes gerados que montam a mesma tabela — hoje, o do auth:install.
 *
 * @param array<string,string> $jaTratados arquivos que o comando ja alterou
 * @return list<string>
 */
function testesQueRecriamATabela(string $tabela, array $jaTratados): array
{
    $marca      = "'{$tabela}' => \"CREATE TABLE";
    $encontrados = [];

    foreach (['/testes/controllers/*.php', '/testes/modelos/*.php'] as $padrao) {
        foreach (glob(CAMINHO_RAIZ . $padrao) ?: [] as $caminho) {
            if (in_array($caminho, $jaTratados, true)) {
                continue;
            }

            if (str_contains(lerArquivo($caminho), $marca)) {
                $encontrados[] = $caminho;
            }
        }
    }

    return $encontrados;
}

/**
 * Acerta um teste que so recria a tabela de passagem.
 *
 * Diferente dos testes do recurso, este nao tem as propriedades $idsRelacoes:
 * a chave estrangeira aponta para o primeiro registro da tabela pai, criado
 * aqui mesmo, e o id vai literal no array de dados.
 */
function testeExtraComCampos(string $conteudo, string $tabela, array $campos, array $colunas, bool $remover): ?string
{
    $principal = campoPrincipal($colunas);

    if ($principal === null) {
        return null;
    }

    $novo = tabelaDoTesteComCampos($conteudo, $tabela, $campos, $remover);

    if ($novo === null) {
        return null;
    }

    foreach (relacoesUnicas($campos) as $campo) {
        $pai = $campo[2];

        if ($remover) {
            if (outraColunaUsaRelacao($colunas, $pai, $campos)) {
                continue;
            }

            $novo = (string) preg_replace(
                ["/^[ \t]*'" . preg_quote($pai, '/') . "'\s*=>\s*'CREATE TABLE[^\n]*\n/m",
                 '/^[ \t]*Database::conexao\(\)->exec\("INSERT INTO `?' . preg_quote($pai, '/') . "`?[^\n]*\n\n?/m"],
                '',
                $novo
            );

            continue;
        }

        if ($pai === $tabela || str_contains($novo, "'{$pai}' => 'CREATE TABLE")) {
            continue;
        }

        $tabelaPai = sqlNome($pai);

        $novo = (string) preg_replace_callback(
            "/^([ \t]*)'" . preg_quote($tabela, '/') . "'\s*=>\s*\"CREATE TABLE/m",
            fn (array $m): string => $m[1] . "'{$pai}' => 'CREATE TABLE " . $tabelaPai
                . " (`id` INT AUTO_INCREMENT PRIMARY KEY, `nome` VARCHAR(255) NULL)',\n" . $m[0],
            $novo,
            1
        );

        $novo = (string) preg_replace(
            '/(\$this->recriarTabelas\(\[[\s\S]*?\n[ \t]*\]\);)\n+/',
            '$1' . "\n\n        Database::conexao()->exec(\"INSERT INTO {$tabelaPai} (`nome`) VALUES ('Opcao 1')\");\n\n",
            $novo,
            1
        );
    }

    if ($remover) {
        return removerEmListasDeDados($novo, $campos);
    }

    $linhas = [];

    foreach ($campos as [$nome, $tipo, $relacao]) {
        // Sem $idsRelacoes aqui: o pai acabou de ser recriado, entao o
        // primeiro registro dele e sempre o id 1.
        $valor = $relacao !== null ? '1' : var_export(valorTeste($tipo, false, $nome), true);

        $linhas[] = "'{$nome}' => {$valor},";
    }

    return acrescentarEmListasDeDados($novo, $linhas, $linhas, $principal, "\x00sem-atualizacao");
}

// ---------------------------------------------------------------------
// scaffold:campo - esquema e banco
// ---------------------------------------------------------------------

/**
 * Reescreve o CREATE TABLE em banco/esquema.sql com as colunas novas,
 * mantendo as CONSTRAINT no fim e preservando ENGINE e CHARSET do original.
 */
function esquemaComCampos(string $tabela, array $campos, bool $remover): void
{
    $arquivo  = arquivoEsquema();
    $conteudo = is_file($arquivo) ? (string) file_get_contents($arquivo) : '';

    if (!preg_match(padraoCreateTable($tabela), $conteudo, $bloco)) {
        throw new RuntimeException(
            "A tabela \"{$tabela}\" nao esta em " . caminhoRelativo($arquivo) . '.'
        );
    }

    $original = $bloco[0];
    $abre     = strpos($original, '(');
    $fecha    = strrpos($original, ')');

    if ($abre === false || $fecha === false || $fecha <= $abre) {
        throw new RuntimeException("Nao consegui interpretar o CREATE TABLE de \"{$tabela}\".");
    }

    $definicoes = array_values(array_filter(
        array_map('trim', explode(",\n", substr($original, $abre + 1, $fecha - $abre - 1))),
        fn (string $definicao): bool => $definicao !== ''
    ));

    $colunas    = [];
    $restricoes = [];

    foreach ($definicoes as $definicao) {
        if (preg_match('/^(CONSTRAINT|FOREIGN|PRIMARY|UNIQUE|KEY|INDEX)\b/i', $definicao)) {
            $restricoes[] = $definicao;

            continue;
        }

        $colunas[] = $definicao;
    }

    foreach ($campos as [$nome, $tipo, $relacao]) {
        $escapado = preg_quote($nome, '/');

        if ($remover) {
            $colunas = array_values(array_filter(
                $colunas,
                fn (string $coluna): bool => !preg_match('/^`?' . $escapado . '`?\b/i', $coluna)
            ));

            $restricoes = array_values(array_filter(
                $restricoes,
                fn (string $restricao): bool => !preg_match('/\(\s*`?' . $escapado . '`?\s*\)/i', $restricao)
            ));

            continue;
        }

        if (preg_grep('/^`?' . $escapado . '`?\b/i', $colunas) !== []) {
            continue;
        }

        $colunas[] = sqlNome($nome) . ' ' . tipoSql($tipo) . ' NULL';

        if ($relacao !== null) {
            $restricoes[] = "CONSTRAINT fk_{$tabela}_{$nome} FOREIGN KEY ("
                . sqlNome($nome) . ') REFERENCES ' . sqlNome($relacao) . '(`id`)';
        }
    }

    $novo = substr($original, 0, $abre + 1) . "\n    "
        . implode(",\n    ", array_merge($colunas, $restricoes)) . "\n"
        . substr($original, $fecha);

    file_put_contents($arquivo, str_replace($original, $novo, $conteudo), LOCK_EX);
}

/** Cria as colunas (e as chaves estrangeiras) na tabela que ja esta no banco. */
function bancoComCampos(string $tabela, array $campos): void
{
    $pdo        = Database::conexao();
    $existentes = colunasDaTabela($tabela);

    foreach ($campos as [$nome, $tipo, $relacao]) {
        if (!in_array($nome, $existentes, true)) {
            // Coluna nova entra como NULL: a tabela pode ja ter registros.
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$nome}` " . tipoSql($tipo) . ' NULL');
        }

        if ($relacao !== null && chaveEstrangeiraDaColuna($tabela, $nome) === null) {
            $pdo->exec(
                "ALTER TABLE `{$tabela}` ADD CONSTRAINT `fk_{$tabela}_{$nome}` "
                . "FOREIGN KEY (`{$nome}`) REFERENCES `{$relacao}`(id)"
            );
        }
    }
}

/** Apaga as colunas do banco (a chave estrangeira sai primeiro). */
function bancoSemCampos(string $tabela, array $campos): void
{
    $pdo        = Database::conexao();
    $existentes = colunasDaTabela($tabela);

    foreach ($campos as [$nome]) {
        $chave = chaveEstrangeiraDaColuna($tabela, $nome);

        if ($chave !== null) {
            $pdo->exec("ALTER TABLE `{$tabela}` DROP FOREIGN KEY `{$chave}`");
        }

        if (in_array($nome, $existentes, true)) {
            $pdo->exec("ALTER TABLE `{$tabela}` DROP COLUMN `{$nome}`");
        }
    }
}

/** Nome da chave estrangeira de uma coluna, ou null se ela nao tiver. */
function chaveEstrangeiraDaColuna(string $tabela, string $coluna): ?string
{
    $consulta = Database::conexao()->prepare(
        'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
           AND REFERENCED_TABLE_NAME IS NOT NULL
         LIMIT 1'
    );

    $consulta->execute([$tabela, $coluna]);

    $nome = $consulta->fetchColumn();

    return $nome === false ? null : (string) $nome;
}

// =====================================================================
// auth:perfis
// =====================================================================

/**
 * Da perfis de acesso a uma tela de login.
 *
 * "Esta logado" e "pode fazer isso" sao perguntas diferentes. Ate aqui o
 * framework so respondia a primeira: qualquer conta que entrasse podia tudo.
 *
 * O comando escreve a lista em configuracoes/perfis.php, cria a coluna
 * perfil na tabela das contas e, quando o model tem CRUD, poe a lista no
 * formulario. Proteger a rota continua sendo uma linha no controller:
 *
 *     $this->exigirPerfil('admin');
 */
function gerarPerfis(array $argumentos): void
{
    [$posicionais, $opcoes] = separarOpcoes($argumentos, ['remover', 'forcar']);

    $remover = array_key_exists('remover', $opcoes);

    if (!$remover && $posicionais === []) {
        throw new InvalidArgumentException(
            "Uso: php console.php auth:perfis <perfil1,perfil2,...> [Modelo|prefixo]\n"
            . "Exemplo: php console.php auth:perfis admin,coordenador,professor\n"
            . 'Para tirar: php console.php auth:perfis --remover'
        );
    }

    $alvo     = $remover ? ($posicionais[0] ?? null) : ($posicionais[1] ?? null);
    $provider = providerDosPerfis($alvo);
    $classe   = modeloDoProvider($provider);
    $tabela   = (new ("Modelos\\" . $classe)())->tabela();

    $perfis = $remover ? [] : interpretarPerfis($posicionais[0]);
    $campo  = [[Nucleo\Perfis::COLUNA, 'string', null]];

    $arquivoModelo = CAMINHO_MODELOS . "/{$classe}.php";
    $recurso       = pascal($tabela);
    $pasta         = strtolower($recurso);

    // -------------------------------------------------------------
    // 1. Tudo na memoria antes de gravar qualquer coisa.
    // -------------------------------------------------------------
    $novos  = [];
    $avisos = [];

    $modelo = lerArquivo($arquivoModelo);

    $modeloNovo = $remover
        ? modeloSemPerfil($modelo)
        : modeloComPerfil($modelo);

    if ($modeloNovo === null) {
        throw new RuntimeException(
            'Nao encontrei a propriedade $preenchiveis em ' . caminhoRelativo($arquivoModelo) . '.'
        );
    }

    if ($modeloNovo !== $modelo) {
        $novos[$arquivoModelo] = $modeloNovo;
    }

    // As telas do CRUD, quando o model tiver um.
    $telas = [
        CAMINHO_VIEWS . "/{$pasta}/formulario.php" => $remover
            ? fn (string $t): string => removerBlocoDaView($t, 'name="' . Nucleo\Perfis::COLUNA . '"')
            : fn (string $t): ?string => formularioComPerfil($t, $provider),
        CAMINHO_VIEWS . "/{$pasta}/index.php" => $remover
            ? fn (string $t): ?string => indexSemCampos($t, $campo)
            : fn (string $t): ?string => indexComPerfil($t, $provider),
        CAMINHO_VIEWS . "/{$pasta}/ver.php" => $remover
            ? fn (string $t): ?string => verSemCampos($t, $campo)
            : fn (string $t): ?string => verComPerfil($t, $provider),
        CAMINHO_CONTROLLERS . "/{$recurso}Controller.php" => $remover
            ? fn (string $t): ?string => dadosSemCampos($t, $campo)
            : fn (string $t): ?string => dadosComCampos($t, $campo),
    ];

    foreach ($telas as $caminho => $alterar) {
        if (!is_file($caminho)) {
            continue;
        }

        $original = lerArquivo($caminho);
        $novo     = $alterar($original);

        if ($novo === null) {
            $avisos[] = 'Nao consegui alterar ' . caminhoRelativo($caminho) . ' — ajuste a mao.';
        } elseif ($novo !== $original) {
            $novos[$caminho] = $novo;
        }
    }

    // Os testes gerados recriam a tabela: a coluna nova precisa entrar la.
    foreach (testesQueRecriamATabela($tabela, []) as $caminho) {
        $original = lerArquivo($caminho);
        $novo     = tabelaDoTesteComCampos($original, $tabela, $campo, $remover);

        if ($novo !== null && $novo !== $original) {
            $novos[$caminho] = $novo;
        }
    }

    // -------------------------------------------------------------
    // 2. Tirar perfis apaga a coluna: confirma antes.
    // -------------------------------------------------------------
    if ($remover && !array_key_exists('forcar', $opcoes)) {
        echo "Isto vai APAGAR a coluna " . Nucleo\Perfis::COLUNA . " da tabela {$tabela}.\n";

        if (!confirmar('Continuar?')) {
            echo "Nada foi alterado.\n";

            return;
        }
    }

    // -------------------------------------------------------------
    // 3. Configuracao, esquema e banco.
    // -------------------------------------------------------------
    $esquemas = lerEsquemas();

    try {
        registrarPerfisNaConfiguracao($provider, $classe, $perfis, $remover);

        esquemaComCampos($tabela, $campo, $remover);

        if ($remover) {
            bancoSemCampos($tabela, $campo);
        } else {
            bancoComCampos($tabela, $campo);
        }
    } catch (Throwable $e) {
        restaurarEsquemas($esquemas);

        throw $e;
    }

    regravarArquivos($novos);

    // -------------------------------------------------------------
    // 4. Relatorio
    // -------------------------------------------------------------
    $tela = $provider === '' ? '/auth' : '/auth-' . str_replace('_', '-', $provider);

    if ($remover) {
        echo "Perfis removidos do login {$tela}\n";
    } else {
        echo "Perfis do login {$tela} ({$classe}):\n";

        foreach ($perfis as $chave => $rotulo) {
            printf("  %-18s %s\n", $chave, $rotulo);
        }
    }

    echo "\n  ~ configuracoes/perfis.php\n";

    foreach (array_keys($novos) as $caminho) {
        echo '  ~ ' . caminhoRelativo($caminho) . "\n";
    }

    echo '  ~ ' . caminhoRelativo(arquivoEsquema()) . "\n";
    echo "  ~ tabela {$tabela} no banco\n";

    foreach ($avisos as $aviso) {
        echo "\nAVISO: {$aviso}\n";
    }

    if ($remover) {
        return;
    }

    $primeiro = array_key_first($perfis);
    $argumento = $provider === '' ? '' : ", '{$provider}'";

    echo "\nProteja as rotas no controller:\n";
    echo "      \$this->exigirPerfil('{$primeiro}'{$argumento});\n";
    echo "\nE esconda o que a pessoa nao pode usar, nas views:\n";
    echo "      <?php if (tem_perfil('{$primeiro}'" . $argumento . ")): ?> ... <?php endif ?>\n";
    echo "\nNenhuma conta tem perfil ainda. Defina o de cada uma pelo CRUD,\n";
    echo "ou direto no banco:\n";
    echo "      UPDATE {$tabela} SET " . Nucleo\Perfis::COLUNA . " = '{$primeiro}' WHERE id = 1;\n";

    if (!is_file(CAMINHO_CONTROLLERS . "/{$recurso}Controller.php")) {
        echo "\nO model {$classe} nao tem CRUD, entao nao ha formulario para escolher o perfil.\n";
        echo "Para gerar um: php console.php scaffold:crud {$tabela} nome:string\n";
    }

    echo "\nPara desfazer: php console.php auth:perfis --remover"
        . ($provider === '' ? '' : ' ' . $provider) . "\n";
}

/** Descobre de qual tela de login o comando esta falando. */
function providerDosPerfis(?string $alvo): string
{
    if ($alvo === null || trim($alvo) === '') {
        return Nucleo\Autenticacao::resolver();
    }

    $alvo = trim($alvo);

    // Pode vir o prefixo do provider ("professor") ou o nome do model.
    $comoPrefixo = Nucleo\Autenticacao::normalizar($alvo);

    if (Nucleo\Autenticacao::instalado($comoPrefixo)) {
        return $comoPrefixo;
    }

    foreach (Nucleo\Autenticacao::providers() as $provider) {
        if (strcasecmp(modeloDoProvider($provider), $alvo) === 0) {
            return $provider;
        }
    }

    throw new RuntimeException(
        "Nao encontrei a tela de login \"{$alvo}\".\n"
        . 'Instaladas: ' . implode(', ', array_map(
            fn (string $p): string => Nucleo\Autenticacao::rotaBase($p),
            Nucleo\Autenticacao::providers()
        )) . "\n"
        . 'Para criar uma: php console.php auth:install'
    );
}

/** O model usado pela tela de login, lido do proprio controller dela. */
function modeloDoProvider(string $provider): string
{
    $arquivo = CAMINHO_CONTROLLERS . '/' . Nucleo\Autenticacao::controlador($provider) . '.php';

    if (!is_file($arquivo)) {
        throw new RuntimeException(
            'Tela de login nao encontrada: ' . caminhoRelativo($arquivo) . "\n"
            . 'Rode antes: php console.php auth:install'
        );
    }

    if (!preg_match('/^use\s+Modelos\\\\(\w+);/m', lerArquivo($arquivo), $achado)) {
        throw new RuntimeException(
            'Nao encontrei o "use Modelos\\..." em ' . caminhoRelativo($arquivo) . '.'
        );
    }

    return $achado[1];
}

/**
 * Le "admin,coordenador" e devolve chave => rotulo.
 *
 * @return array<string,string>
 */
function interpretarPerfis(string $lista): array
{
    $perfis = [];

    foreach (explode(',', $lista) as $bruto) {
        $chave = strtolower(trim($bruto));

        if ($chave === '') {
            continue;
        }

        if (!preg_match('/^[a-z][a-z0-9_]{0,29}$/', $chave)) {
            throw new InvalidArgumentException(
                "Perfil invalido: \"{$chave}\".\n"
                . 'Use letras minusculas, numeros e _, comecando por letra. Ex.: admin, coordenador_geral'
            );
        }

        if (isset($perfis[$chave])) {
            throw new InvalidArgumentException("Perfil repetido: {$chave}.");
        }

        $perfis[$chave] = ucfirst(str_replace('_', ' ', $chave));
    }

    if ($perfis === []) {
        throw new InvalidArgumentException('Informe ao menos um perfil. Ex.: admin,coordenador');
    }

    return $perfis;
}

/** Grava (ou tira) o bloco do provider em configuracoes/perfis.php. */
function registrarPerfisNaConfiguracao(string $provider, string $classe, array $perfis, bool $remover): void
{
    $arquivo  = CAMINHO_CONFIGURACOES . '/perfis.php';
    $conteudo = is_file($arquivo) ? lerArquivo($arquivo) : "<?php\n\nreturn [\n    // auth:perfis\n];\n";

    // Tira o bloco antigo deste provider, se existir.
    // O \1 amarra o fecha-colchete ao MESMO recuo do abre: sem isso o
    // padrao pararia no "]," interno, o da lista de perfis.
    $padrao = "/^([ \t]*)'" . preg_quote($provider, '/') . "'\s*=>\s*\[[\s\S]*?^\\1\],\n/m";
    $conteudo = (string) preg_replace($padrao, '', $conteudo, 1);

    if (!$remover) {
        $linhas = ["    '{$provider}' => ["];
        $linhas[] = "        'modelo' => '{$classe}',";
        $linhas[] = '        \'perfis\' => [';

        foreach ($perfis as $chave => $rotulo) {
            $linhas[] = "            '{$chave}' => '" . str_replace("'", "\\'", $rotulo) . "',";
        }

        $linhas[] = '        ],';
        $linhas[] = '    ],';

        $bloco = implode("\n", $linhas) . "\n";

        if (str_contains($conteudo, '    // auth:perfis')) {
            $conteudo = str_replace('    // auth:perfis', $bloco . '    // auth:perfis', $conteudo);
        } else {
            $conteudo = (string) preg_replace('/\n\];(\s*)$/', "\n" . $bloco . '];$1', $conteudo, 1);
        }
    }

    file_put_contents($arquivo, $conteudo, LOCK_EX);

    // O comando continua rodando depois disso (o model usa Perfis::chaves()
    // na mensagem final), entao a configuracao em memoria tambem muda.
    Nucleo\Config::carregar(CAMINHO_CONFIGURACOES);
}

// ---------------------------------------------------------------------
// auth:perfis - arquivos
// ---------------------------------------------------------------------

/** O model ganha a coluna perfil e a regra que limita os valores aceitos. */
function modeloComPerfil(string $conteudo): ?string
{
    $coluna = Nucleo\Perfis::COLUNA;
    $novo   = preenchiveisComCampos($conteudo, [[$coluna, 'string', null]]);

    if ($novo === null) {
        return null;
    }

    $regra = "->dentroDe('{$coluna}', \\Nucleo\\Perfis::chaves())";

    if (str_contains($novo, $regra)) {
        return $novo;
    }

    return (string) preg_replace_callback(
        '/^([ \t]*)->erros\(\);/m',
        fn (array $m): string => $m[1] . $regra . "\n" . $m[0],
        $novo,
        1
    );
}

/** Caminho inverso do modeloComPerfil(). */
function modeloSemPerfil(string $conteudo): ?string
{
    $coluna = Nucleo\Perfis::COLUNA;
    $novo   = preenchiveisSemCampos($conteudo, [[$coluna, 'string', null]]);

    if ($novo === null) {
        return null;
    }

    return (string) preg_replace(
        "/^[ \t]*->\w+\('" . preg_quote($coluna, '/') . "'[^\n]*\n/m",
        '',
        $novo
    );
}

/** O formulario ganha a lista de perfis, logo antes dos botoes. */
function formularioComPerfil(string $conteudo, string $provider): ?string
{
    $coluna = Nucleo\Perfis::COLUNA;

    if (str_contains($conteudo, 'name="' . $coluna . '"')) {
        return $conteudo;
    }

    $padrao = '/^[ \t]*<\/div>\R[ \t]*<div class="d-flex gap-2 mt-4">/m';

    if (!preg_match($padrao, $conteudo)) {
        return null;
    }

    return (string) preg_replace_callback(
        $padrao,
        fn (array $m): string => campoPerfilGerado($provider) . $m[0],
        $conteudo,
        1
    );
}

/** O <select> com os perfis configurados. */
function campoPerfilGerado(string $provider): string
{
    $coluna    = Nucleo\Perfis::COLUNA;
    $argumento = $provider === '' ? '' : "'{$provider}'";

    return strtr(<<<'HTML'
        <div class="col-md-6">
            <label class="form-label" for="{{COLUNA}}">{{COLUNA}}</label>
            <?php $escolhido = (string) antigo('{{COLUNA}}', $registro['{{COLUNA}}'] ?? ''); ?>
            <select class="form-select <?= tem_erro('{{COLUNA}}') ? 'is-invalid' : '' ?>" id="{{COLUNA}}" name="{{COLUNA}}">
                <option value="">Sem perfil</option>
                <?php foreach (Nucleo\Perfis::configurados({{PROVIDER}}) as $chave => $rotulo): ?>
                    <option value="<?= e($chave) ?>" <?= $escolhido === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach ?>
            </select>
            <?php if ($mensagem = erro_de('{{COLUNA}}')): ?><div class="invalid-feedback d-block"><?= e($mensagem) ?></div><?php endif ?>
        </div>

    HTML, [
        '{{COLUNA}}'   => $coluna,
        '{{PROVIDER}}' => $argumento,
    ]);
}

/** A listagem ganha a coluna do perfil, com o rotulo legivel. */
function indexComPerfil(string $conteudo, string $provider): ?string
{
    $coluna = Nucleo\Perfis::COLUNA;

    if (preg_match('/<th>' . $coluna . '<\/th>/', $conteudo)) {
        return $conteudo;
    }

    $cabecalho = '/^([ \t]*)<th class="text-end">Acoes<\/th>/m';
    $celula    = '/^([ \t]*)<td class="text-end text-nowrap">/m';

    if (!preg_match($cabecalho, $conteudo) || !preg_match($celula, $conteudo)) {
        return null;
    }

    $conteudo = (string) preg_replace_callback(
        $cabecalho,
        fn (array $m): string => $m[1] . "<th>{$coluna}</th>\n" . $m[0],
        $conteudo,
        1
    );

    $conteudo = (string) preg_replace_callback(
        $celula,
        fn (array $m): string => $m[1] . '<td>' . valorDoPerfil($provider) . "</td>\n" . $m[0],
        $conteudo,
        1
    );

    return colspanAjustado($conteudo, 1);
}

/** A tela de detalhe ganha a linha do perfil. */
function verComPerfil(string $conteudo, string $provider): ?string
{
    $coluna = Nucleo\Perfis::COLUNA;

    if (str_contains($conteudo, "\$registro['{$coluna}']")) {
        return $conteudo;
    }

    if (!preg_match('/^([ \t]*)<\/dl>/m', $conteudo, $fim)) {
        return null;
    }

    $recuo = preg_match('/^([ \t]*)<dt\b/m', $conteudo, $dt) ? $dt[1] : $fim[1] . '    ';

    $linha = "{$recuo}<dt class=\"col-sm-3\">{$coluna}</dt>\n"
        . "{$recuo}<dd class=\"col-sm-9\">" . valorDoPerfil($provider) . "</dd>\n";

    return (string) preg_replace_callback(
        '/^[ \t]*<\/dl>/m',
        fn (array $m): string => $linha . $m[0],
        $conteudo,
        1
    );
}

/** Como o perfil aparece na tela: a chave vira o rotulo configurado. */
function valorDoPerfil(string $provider): string
{
    $coluna    = Nucleo\Perfis::COLUNA;
    $argumento = $provider === '' ? '' : ", '{$provider}'";

    return "<?= e(rotulo_perfil(\$registro['{$coluna}'] ?? null{$argumento})) ?>";
}

// =====================================================================
// scaffold:paginacao
// =====================================================================

/**
 * Quebra a listagem em paginas.
 *
 * Sem isso o index() traz a tabela inteira: o banco devolve tudo, o PHP
 * guarda tudo e o navegador desenha tudo. Com trinta registros ninguem
 * percebe; com trinta mil, a tela nao abre.
 *
 * O comando altera o index() do controller e a view index.php, e funciona
 * antes ou depois do scaffold:pesquisa — os dois se encaixam.
 */
function gerarPaginacao(array $argumentos): void
{
    [$posicionais, $opcoes] = separarOpcoes($argumentos, ['remover', 'por-pagina']);

    $remover = array_key_exists('remover', $opcoes);

    if ($posicionais === []) {
        throw new InvalidArgumentException(
            "Uso: php console.php scaffold:paginacao <tabela> [--por-pagina=N]\n"
            . "Exemplo: php console.php scaffold:paginacao produtos --por-pagina=15\n"
            . 'Para tirar: php console.php scaffold:paginacao produtos --remover'
        );
    }

    $porPagina = interpretarPorPagina($opcoes);

    $modelo     = resolverModeloRelatorio($posicionais[0]);
    $tabela     = $modelo['tabela'];
    $recurso    = pascal($tabela);
    $pasta      = strtolower($recurso);
    $controller = CAMINHO_CONTROLLERS . "/{$recurso}Controller.php";
    $view       = CAMINHO_VIEWS . "/{$pasta}/index.php";

    foreach ([$controller, $view] as $arquivo) {
        if (!is_file($arquivo)) {
            throw new RuntimeException(
                'Arquivo do CRUD nao encontrado: ' . caminhoRelativo($arquivo) . "\n"
                . "Gere o CRUD antes:\n  php console.php scaffold:crud {$tabela} nome:string"
            );
        }
    }

    $novos = $remover
        ? [
            $controller => controllerSemPaginacao(lerArquivo($controller), $controller),
            $view       => indexSemPaginacao(lerArquivo($view)),
        ]
        : [
            $controller => controllerComPaginacao(lerArquivo($controller), $controller, $porPagina),
            $view       => indexComPaginacao(lerArquivo($view)),
        ];

    regravarArquivos($novos);

    if ($remover) {
        echo "Paginacao removida de /{$pasta}\n";
        echo '  ~ ' . caminhoRelativo($controller) . "\n";
        echo '  ~ ' . caminhoRelativo($view) . "\n";

        return;
    }

    echo "Paginacao criada em /{$pasta}\n";
    echo '  ~ ' . caminhoRelativo($controller) . "\n";
    echo '  ~ ' . caminhoRelativo($view) . "\n\n";
    echo "A listagem passa a mostrar {$porPagina} registros por vez, com a barra de\n";
    echo "navegacao abaixo da tabela: /{$pasta}?pagina=2\n";

    if (str_contains(lerArquivo($controller), marcadoresPesquisaPhp()[0])) {
        echo "\nA pesquisa continua valendo: o total de paginas e contado depois do filtro,\n";
        echo "e trocar de pagina nao perde o que foi digitado.\n";
    }

    echo "\nPara desfazer: php console.php scaffold:paginacao {$tabela} --remover\n";
}

/** Le e confere o --por-pagina=N. */
function interpretarPorPagina(array $opcoes): int
{
    if (!isset($opcoes['por-pagina'])) {
        return Nucleo\Paginacao::PADRAO;
    }

    $valor = (string) $opcoes['por-pagina'];

    if (!ctype_digit($valor) || (int) $valor < 1) {
        throw new InvalidArgumentException(
            "Valor invalido em --por-pagina={$valor}. Informe um numero maior que zero."
        );
    }

    if ((int) $valor > Nucleo\Paginacao::MAXIMO) {
        throw new InvalidArgumentException(
            '--por-pagina aceita no maximo ' . Nucleo\Paginacao::MAXIMO . ' registros por pagina.'
        );
    }

    return (int) $valor;
}

/** @return array{0:string,1:string} marcadores do trecho gerado no controller */
function marcadoresPaginacaoPhp(): array
{
    return [
        '        // ----- scaffold:paginacao inicio -----',
        '        // ----- scaffold:paginacao fim -----',
    ];
}

/** @return array{0:string,1:string} marcadores do trecho gerado na view */
function marcadoresPaginacaoHtml(): array
{
    return ['<!-- scaffold:paginacao inicio -->', '<!-- scaffold:paginacao fim -->'];
}

/**
 * Faz o index() pedir uma pagina em vez da tabela inteira.
 *
 * A chamada muda conforme a tela ja tenha pesquisa ou nao: com pesquisa, o
 * WHERE ja esta montado em $sql, e e ele que precisa ser paginado — senao o
 * total de paginas sairia da tabela toda, e nao do resultado do filtro.
 */
function controllerComPaginacao(string $conteudo, string $arquivo, int $porPagina): string
{
    $antigo = blocoIndexDoController($conteudo, $arquivo);

    // Comeca sempre do index() sem paginacao: rodar de novo com outro
    // --por-pagina troca o trecho em vez de empilhar dois.
    $bloco = blocoIndexSemPaginacao($antigo);

    [$inicio, $fim]     = marcadoresPaginacaoPhp();
    [, $fimDaPesquisa]  = marcadoresPesquisaPhp();

    $comPesquisa = str_contains($bloco, $fimDaPesquisa);

    $chamada = $comPesquisa
        ? "\$pagina = \$this->modelo->paginarConsulta(\$sql, \$parametros, \$this->get('pagina'), {$porPagina});"
        : "\$pagina = \$this->modelo->paginar(\$this->get('pagina'), {$porPagina});";

    $trecho = $inicio . "\n        " . $chamada . "\n" . $fim;

    // A listagem passa a vir da pagina.
    $bloco = (string) preg_replace_callback(
        "/^([ \t]*)'registros'(\s*)=>[^\n]*\n/m",
        fn (array $m): string => $m[1] . "'registros'" . $m[2] . "=> \$pagina->registros,\n",
        $bloco,
        1,
        $trocas
    );

    if ($trocas !== 1) {
        throw new RuntimeException(
            'Nao encontrei a linha "\'registros\' => ..." no index() de '
            . caminhoRelativo($arquivo) . ".\n"
            . 'Reponha essa linha (ou gere o CRUD de novo) antes de acrescentar a paginacao.'
        );
    }

    // E a view recebe a pagina inteira, para desenhar a barra de navegacao.
    // Ela entra no fim do array: assim o resultado e o mesmo tendo a pesquisa
    // chegado antes ou depois da paginacao.
    $bloco = (string) preg_replace_callback(
        '/(\$this->view\([\s\S]*?\n)([ \t]*)(\]\);)/',
        // Os itens do array ficam um nivel para dentro do "]);".
        fn (array $m): string => $m[1] . $m[2] . '    ' . str_pad("'pagina'", 11)
            . " => \$pagina,\n" . $m[2] . $m[3],
        $bloco,
        1
    );

    if ($comPesquisa) {
        // Depois do filtro: o $sql precisa estar pronto antes de paginar.
        return str_replace(
            $antigo,
            str_replace($fimDaPesquisa . "\n", $fimDaPesquisa . "\n\n" . $trecho . "\n", $bloco),
            $conteudo
        );
    }

    $bloco = (string) preg_replace(
        '/(\n+)([ ]*)\$this->view\(/',
        '${1}' . preg_quote_replace($trecho) . "\n\n\${2}\$this->view(",
        $bloco,
        1,
        $trocas
    );

    if ($trocas !== 1) {
        throw new RuntimeException(
            'O index() de ' . caminhoRelativo($arquivo) . " nao chama \$this->view().\n"
            . 'Deixe a chamada la (ou gere o CRUD de novo) antes de acrescentar a paginacao.'
        );
    }

    return str_replace($antigo, $bloco, $conteudo);
}

/** Devolve o index() ao estado sem paginacao. */
function controllerSemPaginacao(string $conteudo, string $arquivo): string
{
    $antigo = blocoIndexDoController($conteudo, $arquivo);

    return str_replace($antigo, blocoIndexSemPaginacao($antigo), $conteudo);
}

/**
 * Tira do index() tudo que o scaffold:paginacao tinha colocado, devolvendo a
 * listagem para a forma que ela tinha antes — com ou sem pesquisa.
 */
function blocoIndexSemPaginacao(string $bloco): string
{
    [$inicio, $fim]    = marcadoresPaginacaoPhp();
    [, $fimDaPesquisa] = marcadoresPesquisaPhp();

    $bloco = (string) preg_replace(
        '/\n?' . preg_quote($inicio, '/') . '[\s\S]*?' . preg_quote($fim, '/') . "\n/",
        '',
        $bloco,
        1
    );

    $fonte = str_contains($bloco, $fimDaPesquisa)
        ? '$this->modelo->consultar($sql, $parametros),'
        : '$this->modelo->todos(),';

    $bloco = (string) preg_replace(
        "/^([ \t]*)'registros'(\s*)=>\s*\\\$pagina->registros,\n/m",
        '${1}\'registros\'${2}=> ' . preg_quote_replace($fonte) . "\n",
        $bloco,
        1
    );

    return (string) preg_replace("/^[ \t]*'pagina'\s*=>\s*\\\$pagina,\n/m", '', $bloco, 1);
}

/** Coloca a barra de navegacao embaixo da tabela do index. */
function indexComPaginacao(string $conteudo): string
{
    [$inicio, $fim] = marcadoresPaginacaoHtml();

    $conteudo = indexSemPaginacao($conteudo);

    return rtrim($conteudo, "\n") . "\n\n"
        . $inicio . "\n"
        . '<?= paginacao($pagina ?? null) ?>' . "\n"
        . $fim . "\n";
}

/** Tira a barra de navegacao da view. */
function indexSemPaginacao(string $conteudo): string
{
    [$inicio, $fim] = marcadoresPaginacaoHtml();

    $padrao = '/\n*' . preg_quote($inicio, '/') . '[\s\S]*?' . preg_quote($fim, '/') . '\n*/';

    return rtrim((string) preg_replace($padrao, '', $conteudo, 1), "\n") . "\n";
}

// =====================================================================
// db:semear
// =====================================================================

/**
 * Executa o arquivo de semeadura, banco/semear.php.
 *
 * Semente e dado que voce escreve, nao dado inventado: as categorias do
 * catalogo, os status de um pedido, a conta de administrador. Por isso ele
 * mora em um arquivo do projeto, e nao na linha de comando — da para versionar
 * junto com o codigo e rodar igual em qualquer maquina.
 *
 * O atalho com nome de tabela continua existindo para quando voce so quer
 * encher uma tela depressa, sem abrir o arquivo.
 */
function semearBanco(array $argumentos): void
{
    [$posicionais, $opcoes] = separarOpcoes($argumentos, ['tudo', 'limpar', 'semente']);

    // Com --semente=N todo mundo da turma recebe exatamente os mesmos dados.
    if (isset($opcoes['semente'])) {
        if (!ctype_digit((string) $opcoes['semente'])) {
            throw new InvalidArgumentException('A semente precisa ser um numero inteiro: --semente=7');
        }

        mt_srand((int) $opcoes['semente']);
    }

    $limpar = array_key_exists('limpar', $opcoes);
    $tudo   = array_key_exists('tudo', $opcoes);

    if ($posicionais === [] && !$tudo) {
        semearPeloArquivo($limpar);

        return;
    }

    semearDepressa($posicionais, $tudo, $limpar);
}

/** O caminho normal: roda banco/semear.php. */
function semearPeloArquivo(bool $limpar): void
{
    $arquivo = CAMINHO_BANCO . '/semear.php';

    if (!is_file($arquivo)) {
        throw new RuntimeException(
            'Arquivo de semeadura nao encontrado: ' . caminhoRelativo($arquivo) . "\n\n"
            . "Crie-o com este conteudo e escreva os seus dados dentro:\n\n"
            . "  <?php\n"
            . "  semear('categorias', [\n"
            . "      ['nome' => 'Eletronicos'],\n"
            . "  ], 'nome');\n"
        );
    }

    if ($limpar) {
        Nucleo\Semeador::limpar();
        echo "Banco limpo.\n\n";
    }

    $contagens = Nucleo\Semeador::executar($arquivo);

    if ($contagens === []) {
        echo 'Nada foi criado: ' . caminhoRelativo($arquivo) . " nao chamou semear() nem falsos().\n\n";
        echo "Abra o arquivo, descomente os exemplos e troque pelos seus dados.\n";

        return;
    }

    echo 'Semeado a partir de ' . caminhoRelativo($arquivo) . ":\n";

    $total = 0;

    foreach ($contagens as $tabela => $quantos) {
        $total += $quantos;

        printf("  %-22s %d registro(s)\n", $tabela, $quantos);
    }

    echo "\n{$total} registro(s) inserido(s).\n";

    if (semeouSenha($contagens)) {
        echo "\nAs contas criadas por falsos() usam a senha: " . Nucleo\DadosFalsos::SENHA . "\n";
        echo "As criadas por semear() usam a senha que voce escreveu no arquivo.\n";
    }

    echo "\nPara comecar do zero: php console.php db:semear --limpar\n";
}

/** O atalho: enche uma tabela (ou todas) sem passar pelo arquivo. */
function semearDepressa(array $posicionais, bool $tudo, bool $limpar): void
{
    $quantidade = quantidadeSemeada($posicionais, $tudo);

    $tabelas = $tudo
        ? Nucleo\Semeador::tabelasEmOrdem()
        : [resolverModeloRelatorio($posicionais[0])['tabela']];

    if ($tabelas === []) {
        throw new RuntimeException(
            "Nenhuma tabela no banco.\n"
            . 'Gere um CRUD antes: php console.php scaffold:crud produtos nome:string'
        );
    }

    if ($limpar) {
        Nucleo\Semeador::limpar($tudo ? [] : $tabelas);
    }

    foreach ($tabelas as $tabela) {
        Nucleo\Semeador::inventar($tabela, $quantidade);
    }

    $contagens = Nucleo\Semeador::contagens();
    $total     = 0;

    foreach ($contagens as $tabela => $quantos) {
        $total += $quantos;

        printf("  %-22s %d registro(s)\n", $tabela, $quantos);
    }

    echo "\n{$total} registro(s) inserido(s).\n";

    if (semeouSenha($contagens)) {
        echo "\nAs contas criadas usam a senha: " . Nucleo\DadosFalsos::SENHA . "\n";
    }

    echo "\nIsto e um atalho. Os dados que o sistema precisa ter de verdade vao em\n";
    echo caminhoRelativo(CAMINHO_BANCO . '/semear.php') . ", que roda com: php console.php db:semear\n";
}

/** Alguma tabela semeada tem coluna de senha? */
function semeouSenha(array $contagens): bool
{
    foreach (array_keys($contagens) as $tabela) {
        try {
            foreach (Database::conexao()->query("SHOW COLUMNS FROM `{$tabela}`") as $coluna) {
                if (Nucleo\DadosFalsos::perfilDoCampo((string) $coluna['Field']) === 'senha') {
                    return true;
                }
            }
        } catch (Throwable $e) {
            continue;
        }
    }

    return false;
}

/** Quantidade pedida na linha de comando (padrao: 10). */
function quantidadeSemeada(array $posicionais, bool $tudo): int
{
    $pedida = $tudo ? ($posicionais[0] ?? null) : ($posicionais[1] ?? null);

    if ($pedida === null) {
        return 10;
    }

    if (!ctype_digit((string) $pedida) || (int) $pedida < 1) {
        throw new InvalidArgumentException("Quantidade invalida: \"{$pedida}\". Informe um numero maior que zero.");
    }

    if ((int) $pedida > 5000) {
        throw new InvalidArgumentException('Quantidade maxima: 5000 registros por tabela.');
    }

    return (int) $pedida;
}
