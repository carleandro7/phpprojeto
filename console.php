<?php

if (PHP_SAPI !== 'cli') {
    exit("Este arquivo so pode ser executado pelo terminal.\n");
}

require_once __DIR__ . '/nucleo/bootstrap.php';

use Nucleo\Config;
use Nucleo\Database;
use Nucleo\RelatorioPdf;

$comando = $argv[1] ?? '';

if ($comando === 'scaffold:crud') {
    gerarCrud(array_slice($argv, 2));
} elseif ($comando === 'auth:install') {
    gerarAutenticacao(array_slice($argv, 2));
} elseif ($comando === 'relatorio:pdf') {
    gerarRelatorioPdf(array_slice($argv, 2));
} else {
    echo "Uso:\n  php console.php scaffold:crud tabela campo:tipo ...\n  php console.php scaffold:crud filhos campo_id:belongs_to=tabela_pai\n  php console.php auth:install [Modelo]\n";
    echo "  php console.php relatorio:pdf modelo|tabela [arquivo.pdf]\n";
    exit($comando === '' ? 0 : 1);
}

function gerarCrud(array $argumentos): void
{
    if (count($argumentos) < 2) {
        throw new InvalidArgumentException('Uso: php console.php scaffold:crud tabela campo:tipo ...');
    }
    $tabela = strtolower($argumentos[0]);
    validarNome($tabela, 'tabela');
    $campos = [];
    foreach (array_slice($argumentos, 1) as $definicao) {
        [$nome, $tipo] = array_pad(explode(':', strtolower($definicao), 2), 2, 'string');
        $relacao = null;

        if (str_starts_with($tipo, 'belongs_to=')) {
            $relacao = substr($tipo, strlen('belongs_to='));
            validarNome($relacao, 'tabela relacionada');
            $tipo = 'integer';
        }

        validarNome($nome, 'campo');
        if (in_array($nome, ['id', 'criado_em'], true) || !in_array($tipo, ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'time'], true)) {
            throw new InvalidArgumentException("Campo ou tipo invalido: {$definicao}");
        }
        $campos[] = [$nome, $tipo, $relacao];
    }
    $classe = classeDaTabela($tabela);
    $recurso = pascal($tabela);
    $pasta = strtolower($recurso);
    $metodosRelacoes = metodosRelacoesModelo($campos);

    escrever(CAMINHO_MODELOS . "/{$classe}.php", "<?php\n\nnamespace Modelos;\n\nuse Nucleo\\Model;\n\nclass {$classe} extends Model\n{\n    protected string \$tabela = '{$tabela}';\n    protected array \$preenchiveis = [" . implode(', ', array_map(fn ($campo) => "'{$campo[0]}'", $campos)) . "];\n    protected string \$ordemPadrao = 'id DESC';\n\n{$metodosRelacoes}}\n");
    escrever(CAMINHO_CONTROLLERS . "/{$recurso}Controller.php", controllerGerado($classe, $recurso, $pasta, $campos));
    escrever(CAMINHO_VIEWS . "/{$pasta}/index.php", indexGerado($tabela, $campos));
    escrever(CAMINHO_VIEWS . "/{$pasta}/formulario.php", formularioGerado($tabela, $campos));
    escrever(CAMINHO_VIEWS . "/{$pasta}/ver.php", verGerado($tabela, $campos));
    escrever(CAMINHO_RAIZ . "/testes/modelos/{$classe}Test.php", testeModeloGerado($tabela, $classe, $campos));
    escrever(CAMINHO_RAIZ . "/testes/controllers/{$recurso}ControllerTest.php", testeControllerGerado($tabela, $classe, $recurso, $pasta, $campos));
    file_put_contents(CAMINHO_BANCO . '/esquema.sqlite.sql', "\n" . esquema($tabela, $campos, false), FILE_APPEND | LOCK_EX);
    file_put_contents(CAMINHO_BANCO . '/esquema.mysql.sql', "\n" . esquema($tabela, $campos, true), FILE_APPEND | LOCK_EX);
    Database::migrar();
    sincronizarColunas($tabela, $campos);
    echo "CRUD criado: /{$pasta}\n";
}

function sincronizarColunas(string $tabela, array $campos): void
{
    $pdo = Database::conexao();
    $driver = Config::obter('banco.driver', 'sqlite');
    $existentes = colunasDaTabela($tabela);

    foreach ($campos as [$nome, $tipo]) {
        if (!in_array($nome, $existentes, true)) {
            $definicao = $driver === 'mysql'
                ? tipoSql($tipo, true)
                : tipoSql($tipo, false);
            $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN {$nome} {$definicao} NULL");
        }
    }
}

function colunasDaTabela(string $tabela): array
{
    $pdo = Database::conexao();
    $driver = Config::obter('banco.driver', 'sqlite');
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

function tipoSql(string $tipo, bool $mysql): string
{
    if ($mysql) {
        return match ($tipo) {
            'integer' => 'INT',
            'decimal' => 'DECIMAL(12,2)',
            'boolean' => 'TINYINT(1)',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'time' => 'TIME',
            default => 'VARCHAR(255)',
        };
    }

    return match ($tipo) {
        'integer', 'boolean' => 'INTEGER',
        'decimal' => 'REAL',
        default => 'TEXT',
    };
}

function validarNome(string $nome, string $tipo): void
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $nome)) {
        throw new InvalidArgumentException("{$tipo} invalido: {$nome}");
    }
}

function pascal(string $nome): string
{
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $nome)));
}

function classeDaTabela(string $tabela): string
{
    $singular = str_ends_with($tabela, 's') ? substr($tabela, 0, -1) : $tabela;

    return pascal($singular);
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

function nomeMetodoRelacao(string $tabela): string
{
    return $tabela;
}

function metodosRelacoesModelo(array $campos): string
{
    $metodos = [];

    foreach (relacoesUnicas($campos) as $campo) {
        $tabelaRelacionada = $campo[2];
        $classeRelacionada = classeDaTabela($tabelaRelacionada);
        $metodo = nomeMetodoRelacao($tabelaRelacionada);
        $metodos[] = "    public function {$metodo}(): array\n    {\n        return (new \\Modelos\\{$classeRelacionada}())->todos();\n    }";
    }

    return $metodos === [] ? '' : implode("\n\n", $metodos) . "\n";
}

function escrever(string $arquivo, string $conteudo): void
{
    if (is_file($arquivo)) {
        throw new RuntimeException("Arquivo ja existe: {$arquivo}");
    }
    if (!is_dir(dirname($arquivo))) {
        mkdir(dirname($arquivo), 0777, true);
    }
    file_put_contents($arquivo, $conteudo . "\n", LOCK_EX);
}

function controllerGerado(string $classe, string $recurso, string $pasta, array $campos): string
{
    $dados = implode("\n            ", array_map(fn ($campo) => "'{$campo[0]}' => \$this->post('{$campo[0]}'),", $campos));
    $relacoesView = '';

    foreach (relacoesUnicas($campos) as $campo) {
        $tabelaRelacionada = $campo[2];
        $metodo = nomeMetodoRelacao($tabelaRelacionada);
        $relacoesView .= "\n            '{$tabelaRelacionada}' => \$this->modelo->{$metodo}(),";
    }

    return "<?php\n\nnamespace Controllers;\n\nuse Modelos\\{$classe};\nuse Nucleo\\Controller;\n\nclass {$recurso}Controller extends Controller\n{\n    private {$classe} \$modelo;\n\n    public function __construct()\n    {\n        \$this->modelo = new {$classe}();\n    }\n\n    public function index(): void\n    {\n        \$this->view('{$pasta}/index', ['titulo' => '{$recurso}', 'registros' => \$this->modelo->todos()]);\n    }\n\n    public function criar(): void\n    {\n        \$this->view('{$pasta}/formulario', [\n            'titulo' => 'Novo {$classe}',\n            'registro' => null,{$relacoesView}\n        ]);\n    }\n\n    public function salvar(): void\n    {\n        \$id = \$this->modelo->criar([\n            {$dados}\n        ]);\n        \$this->mensagem('sucesso', '{$classe} criado com sucesso.');\n        \$this->redirecionar('{$pasta}/ver/' . \$id);\n    }\n\n    public function ver(string \$id): void\n    {\n        \$registro = \$this->modelo->buscar(\$id);\n        if (\$registro === null) { \$this->naoEncontrado(); }\n        \$this->view('{$pasta}/ver', ['titulo' => '{$classe}', 'registro' => \$registro]);\n    }\n\n    public function editar(string \$id): void\n    {\n        \$registro = \$this->modelo->buscar(\$id);\n        if (\$registro === null) { \$this->naoEncontrado(); }\n        \$this->view('{$pasta}/formulario', [\n            'titulo' => 'Editar {$classe}',\n            'registro' => \$registro,{$relacoesView}\n        ]);\n    }\n\n    public function atualizar(string \$id): void\n    {\n        \$this->modelo->atualizar(\$id, [\n            {$dados}\n        ]);\n        \$this->mensagem('sucesso', '{$classe} atualizado com sucesso.');\n        \$this->redirecionar('{$pasta}/ver/' . \$id);\n    }\n\n    public function excluir(string \$id): void\n    {\n        if (!\$this->modelo->excluir(\$id)) { \$this->naoEncontrado(); }\n        \$this->mensagem('sucesso', '{$classe} excluido com sucesso.');\n        \$this->redirecionar('{$pasta}');\n    }\n}\n";
}

function indexGerado(string $tabela, array $campos): string
{
    $cabecalhos = implode('', array_map(fn ($campo) => "        <th>{$campo[0]}</th>\n", $campos));
    $celulas = implode('', array_map(fn ($campo) => "            <td><?= e(\$registro['{$campo[0]}'] ?? '') ?></td>\n", $campos));
        return "<div class=\"d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4\"><div><h1 class=\"h3 mb-1\">{$tabela}</h1><p class=\"text-secondary mb-0\">Gerencie os registros cadastrados.</p></div><a class=\"btn btn-primary\" href=\"<?= url('{$tabela}/criar') ?>\">Novo registro</a></div>\n<div class=\"card border-0 shadow-sm\"><div class=\"table-responsive\"><table class=\"table table-hover align-middle mb-0\"><thead class=\"table-light\"><tr><th>ID</th>\n{$cabecalhos}</tr></thead><tbody>\n<?php foreach (\$registros as \$registro): ?>\n<tr><td><a href=\"<?= url('{$tabela}/ver/' . \$registro['id']) ?>\"><?= e(\$registro['id']) ?></a></td>\n{$celulas}</tr>\n<?php endforeach ?>\n</tbody></table></div></div>\n";
}

function formularioGerado(string $tabela, array $campos): string
{
    $inputs = [];

    foreach ($campos as $campo) {
        [$nome, $tipo, $tabelaRelacionada] = array_pad($campo, 3, null);

        if ($tabelaRelacionada !== null) {
            $inputs[] = "    <div class=\"col-md-6\"><label class=\"form-label\" for=\"{$nome}\">{$nome}</label><select class=\"form-select\" id=\"{$nome}\" name=\"{$nome}\"><option value=\"\">Selecione...</option><?php \$valorSelecionado = antigo('{$nome}', \$registro['{$nome}'] ?? ''); ?><?php foreach ((\$" . $tabelaRelacionada . " ?? []) as \$opcao): ?><option value=\"<?= e(\$opcao['id']) ?>\" <?= (string) \$valorSelecionado === (string) \$opcao['id'] ? 'selected' : '' ?>><?= e(\$opcao['nome'] ?? \$opcao['descricao'] ?? ('#' . \$opcao['id'])) ?></option><?php endforeach ?></select></div>";
            continue;
        }

        $tipoHtml = match ($tipo) {
            'integer', 'decimal' => 'number',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'time' => 'time',
            'boolean' => 'checkbox',
            default => 'text',
        };
        $inputs[] = "    <div class=\"col-md-6\"><label class=\"form-label\" for=\"{$nome}\">{$nome}</label><input class=\"form-control\" id=\"{$nome}\" type=\"{$tipoHtml}\" name=\"{$nome}\" value=\"<?= e(antigo('{$nome}', \$registro['{$nome}'] ?? '')) ?>\"></div>";
    }

    return "<div class=\"mb-4\"><h1 class=\"h3 mb-1\"><?= e(\$titulo) ?></h1><p class=\"text-secondary mb-0\">Preencha os dados abaixo.</p></div>\n<form class=\"card border-0 shadow-sm p-4\" method=\"post\" action=\"<?= url('{$tabela}/' . (\$registro ? 'atualizar/' . \$registro['id'] : 'salvar')) ?>\"><div class=\"row g-3\">\n" . implode("\n", $inputs) . "\n</div><div class=\"d-flex gap-2 mt-4\"><button class=\"btn btn-primary\" type=\"submit\">Salvar</button><a class=\"btn btn-outline-secondary\" href=\"<?= url('{$tabela}') ?>\">Cancelar</a></div>\n</form>\n";
}

function verGerado(string $tabela, array $campos): string
{
    $linhas = implode("\n", array_map(fn ($campo) => "<dt>{$campo[0]}</dt><dd><?= e(\$registro['{$campo[0]}'] ?? '') ?></dd>", $campos));
        return "<div class=\"d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4\"><h1 class=\"h3 mb-0\">Registro</h1><a class=\"btn btn-outline-secondary\" href=\"<?= url('{$tabela}') ?>\">Voltar</a></div><div class=\"card border-0 shadow-sm\"><dl class=\"row g-0 mb-0 p-4\">\n{$linhas}\n</dl></div>\n";
}

function testeModeloGerado(string $tabela, string $classe, array $campos): string
{
    $relacoes = relacoesUnicas($campos);
    $colunas = implode(",\n            ", array_map(fn ($campo) => "{$campo[0]} " . tipoSqlTeste($campo[1]), $campos));
    $chaves = array_map(fn ($campo) => "CONSTRAINT fk_{$tabela}_{$campo[0]} FOREIGN KEY ({$campo[0]}) REFERENCES {$campo[2]}(id)", array_filter($campos, fn ($campo) => ($campo[2] ?? null) !== null));
    $definicoes = implode(",\n            ", array_merge(array_map(fn ($campo) => "{$campo[0]} " . tipoSqlTeste($campo[1]), $campos), $chaves));
    $dados = implode(",\n            ", array_map(function ($campo) {
        $valor = ($campo[2] ?? null) !== null
            ? "\$this->idsRelacoes['{$campo[0]}']"
            : var_export(valorTeste($campo[1]), true);

        return "'{$campo[0]}' => {$valor}";
    }, $campos));
    $campo = $campos[0][0];
    $valorAtualizado = ($campos[0][2] ?? null) !== null
        ? "\$this->idsRelacoesAtualizadas['{$campo}']"
        : var_export(valorTeste($campos[0][1], true), true);
    $prepararRelacoes = '';
    $limparRelacoes = '';
    $idsRelacoes = '';

    foreach ($relacoes as $relacao) {
        $tabelaRelacionada = $relacao[2];
        $prepararRelacoes .= "        Database::conexao()->exec(\"CREATE TABLE IF NOT EXISTS {$tabelaRelacionada} (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NULL)\");\n";
        $limparRelacoes .= "        Database::conexao()->exec('DELETE FROM {$tabelaRelacionada}');\n";
        $idsRelacoes .= "        Database::conexao()->exec(\"INSERT INTO {$tabelaRelacionada} (nome) VALUES ('Opcao 1'), ('Opcao 2')\");\n        \$this->idsRelacoes['{$relacao[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$tabelaRelacionada} ORDER BY id ASC LIMIT 1')->fetchColumn();\n        \$this->idsRelacoesAtualizadas['{$relacao[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$tabelaRelacionada} ORDER BY id DESC LIMIT 1')->fetchColumn();\n";
    }

    $assertRelacoes = '';
    foreach ($relacoes as $relacao) {
        $tabelaRelacionada = $relacao[2];
        $assertRelacoes .= "        \$opcoes = \$this->modelo->" . nomeMetodoRelacao($tabelaRelacionada) . "();\n        \$this->assertTotal(2, \$opcoes);\n        \$this->assertVerdadeiro(in_array(\$this->idsRelacoes['{$relacao[0]}'], array_column(\$opcoes, 'id'), true));\n";
    }

    return "<?php\n\nnamespace Testes\\Modelos;\n\nuse Modelos\\{$classe};\nuse Nucleo\\Database;\nuse Testes\\Suporte\\TesteBase;\n\nclass {$classe}Test extends TesteBase\n{\n    private {$classe} \$modelo;\n    private array \$idsRelacoes = [];\n    private array \$idsRelacoesAtualizadas = [];\n\n    public function preparar(): void\n    {\n{$prepararRelacoes}        Database::conexao()->exec(\"CREATE TABLE IF NOT EXISTS {$tabela} (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            {$definicoes}\n        )\");\n        Database::conexao()->exec('DELETE FROM {$tabela}');\n{$limparRelacoes}{$idsRelacoes}        \$this->modelo = new {$classe}();\n    }\n\n    public function testeExecutaCrudCompleto(): void\n    {\n        \$dados = [\n            {$dados}\n        ];\n{$assertRelacoes}        \$id = \$this->modelo->criar(\$dados);\n        \$registro = \$this->modelo->buscar(\$id);\n\n        \$this->assertVerdadeiro(\$id > 0);\n        \$this->assertIgual(\$dados['{$campo}'], \$registro['{$campo}']);\n        \$this->assertIgual(1, \$this->modelo->contar());\n\n        \$this->assertVerdadeiro(\$this->modelo->atualizar(\$id, ['{$campo}' => {$valorAtualizado}]));\n        \$this->assertIgual({$valorAtualizado}, \$this->modelo->buscar(\$id)['{$campo}']);\n        \$this->assertVerdadeiro(\$this->modelo->excluir(\$id));\n        \$this->assertNulo(\$this->modelo->buscar(\$id));\n    }\n}\n";
}

function tipoSqlTeste(string $tipo): string
{
    return match ($tipo) {
        'integer', 'boolean' => 'INTEGER',
        'decimal' => 'REAL',
        default => 'TEXT',
    } . ' NULL';
}

function testeControllerGerado(string $tabela, string $classe, string $recurso, string $pasta, array $campos): string
{
    $relacoes = relacoesUnicas($campos);
    $chaves = array_map(fn ($campo) => "CONSTRAINT fk_{$tabela}_{$campo[0]} FOREIGN KEY ({$campo[0]}) REFERENCES {$campo[2]}(id)", array_filter($campos, fn ($campo) => ($campo[2] ?? null) !== null));
    $definicoes = implode(",\n            ", array_merge(array_map(fn ($campo) => "{$campo[0]} " . tipoSqlTeste($campo[1]), $campos), $chaves));
    $dados = implode(",\n            ", array_map(function ($campo) {
        $valor = ($campo[2] ?? null) !== null
            ? "\$this->idsRelacoes['{$campo[0]}']"
            : var_export(valorTeste($campo[1]), true);

        return "'{$campo[0]}' => {$valor}";
    }, $campos));
    $dadosAtualizados = implode(",\n            ", array_map(function ($campo) {
        $valor = ($campo[2] ?? null) !== null
            ? "\$this->idsRelacoesAtualizadas['{$campo[0]}']"
            : var_export(valorTeste($campo[1], true), true);

        return "'{$campo[0]}' => {$valor}";
    }, $campos));
    $campoPrincipal = $campos[0][0];
    $valorInicial = ($campos[0][2] ?? null) !== null
        ? "\$this->idsRelacoes['{$campoPrincipal}']"
        : var_export(valorTeste($campos[0][1]), true);
    $valorAtualizado = ($campos[0][2] ?? null) !== null
        ? "\$this->idsRelacoesAtualizadas['{$campoPrincipal}']"
        : var_export(valorTeste($campos[0][1], true), true);
    $prepararRelacoes = '';
    $limparRelacoes = '';
    $idsRelacoes = '';

    foreach ($relacoes as $relacao) {
        $tabelaRelacionada = $relacao[2];
        $prepararRelacoes .= "        Database::conexao()->exec(\"CREATE TABLE IF NOT EXISTS {$tabelaRelacionada} (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NULL)\");\n";
        $limparRelacoes .= "        \$this->limparTabela('{$tabelaRelacionada}');\n";
        $idsRelacoes .= "        Database::conexao()->exec(\"INSERT INTO {$tabelaRelacionada} (nome) VALUES ('Opcao 1'), ('Opcao 2')\");\n        \$this->idsRelacoes['{$relacao[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$tabelaRelacionada} ORDER BY id ASC LIMIT 1')->fetchColumn();\n        \$this->idsRelacoesAtualizadas['{$relacao[0]}'] = (int) Database::conexao()->query('SELECT id FROM {$tabelaRelacionada} ORDER BY id DESC LIMIT 1')->fetchColumn();\n";
    }

    return "<?php\n\nnamespace Testes\\Controllers;\n\nuse Modelos\\{$classe};\nuse Nucleo\\Database;\nuse Testes\\Suporte\\TesteBase;\n\nclass {$recurso}ControllerTest extends TesteBase\n{\n    private {$classe} \$modelo;\n    private array \$idsRelacoes = [];\n    private array \$idsRelacoesAtualizadas = [];\n\n    public function preparar(): void\n    {\n{$prepararRelacoes}        Database::conexao()->exec(\"CREATE TABLE IF NOT EXISTS {$tabela} (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            {$definicoes}\n        )\");\n        \$this->limparTabela('{$tabela}');\n{$limparRelacoes}{$idsRelacoes}        \$this->modelo = new {$classe}();\n    }\n\n    public function testeExecutaRotasDoCrud(): void\n    {\n        \$lista = \$this->requisitar('{$pasta}');\n        \$this->assertIgual(200, \$lista->status);\n        \$this->assertContem('{$campoPrincipal}', \$lista->html);\n\n        \$formulario = \$this->requisitar('{$pasta}/criar');\n        \$this->assertIgual(200, \$formulario->status);\n        \$this->assertContem('Salvar', \$formulario->html);\n\n        \$salvar = \$this->postar('{$pasta}/salvar', [\n            {$dados}\n        ]);\n        \$this->assertVerdadeiro(\$salvar->redirecionouPara('{$pasta}/ver/1'));\n\n        \$registro = \$this->modelo->todos()[0] ?? null;\n        \$this->assertNaoNulo(\$registro);\n        \$id = (int) \$registro['id'];\n        \$this->assertIgual({$valorInicial}, \$registro['{$campoPrincipal}']);\n\n        \$ver = \$this->requisitar('{$pasta}/ver/' . \$id);\n        \$this->assertIgual(200, \$ver->status);\n        \$this->assertContem((string) \$registro['{$campoPrincipal}'], \$ver->html);\n\n        \$editar = \$this->requisitar('{$pasta}/editar/' . \$id);\n        \$this->assertIgual(200, \$editar->status);\n        \$this->assertContem('Editar {$classe}', \$editar->html);\n\n        \$atualizar = \$this->postar('{$pasta}/atualizar/' . \$id, [\n            {$dadosAtualizados}\n        ]);\n        \$this->assertVerdadeiro(\$atualizar->redirecionouPara('{$pasta}/ver/' . \$id));\n        \$registroAtualizado = \$this->modelo->buscar(\$id);\n        \$this->assertIgual({$valorAtualizado}, \$registroAtualizado['{$campoPrincipal}']);\n\n        \$excluir = \$this->requisitar('{$pasta}/excluir/' . \$id);\n        \$this->assertVerdadeiro(\$excluir->redirecionouPara('{$pasta}'));\n        \$this->assertNulo(\$this->modelo->buscar(\$id));\n    }\n}\n";
}

function valorTeste(string $tipo, bool $atualizado = false): mixed
{
    return match ($tipo) {
        'integer' => $atualizado ? 2 : 1,
        'decimal' => $atualizado ? 20.5 : 10.5,
        'boolean' => $atualizado ? 0 : 1,
        'date' => $atualizado ? '2026-02-02' : '2026-01-01',
        'datetime' => $atualizado ? '2026-02-02 12:00:00' : '2026-01-01 10:00:00',
        'time' => $atualizado ? '12:00:00' : '10:00:00',
        default => $atualizado ? 'Atualizado' : 'Teste',
    };
}

function esquema(string $tabela, array $campos, bool $mysql): string
{
    $colunas = array_map(fn ($campo) => "{$campo[0]} " . tipoSql($campo[1], $mysql) . ' NULL', $campos);
    $chaves = array_map(fn ($campo) => "CONSTRAINT fk_{$tabela}_{$campo[0]} FOREIGN KEY ({$campo[0]}) REFERENCES {$campo[2]}(id)", array_filter($campos, fn ($campo) => ($campo[2] ?? null) !== null));
    $definicoes = implode(",\n    ", array_merge($colunas, $chaves));

    return $mysql ? "CREATE TABLE IF NOT EXISTS {$tabela} (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    {$definicoes}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n" : "CREATE TABLE IF NOT EXISTS {$tabela} (\n    id INTEGER PRIMARY KEY AUTOINCREMENT,\n    {$definicoes}\n);\n";
}

function gerarRelatorioPdf(array $argumentos): void
{
    if (count($argumentos) < 1 || count($argumentos) > 2) {
        throw new InvalidArgumentException('Uso: php console.php relatorio:pdf modelo|tabela [arquivo.pdf]');
    }

    $modelo = resolverModeloRelatorio($argumentos[0]);
    $registros = $modelo['instancia']->todos();
    $colunas = $registros === [] ? colunasDaTabela($modelo['tabela']) : array_keys($registros[0]);
    $arquivo = caminhoRelatorioPdf($argumentos[1] ?? "relatorios/{$modelo['tabela']}.pdf");

    RelatorioPdf::gerar("Relatorio de {$modelo['tabela']}", $colunas, $registros, $arquivo);

    echo "Relatorio PDF criado: {$arquivo}\n";
}

function resolverModeloRelatorio(string $alvo): array
{
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $alvo)) {
        throw new InvalidArgumentException("Modelo ou tabela invalido: {$alvo}");
    }

    $candidatos = array_unique([
        pascal($alvo),
        classeDaTabela(strtolower($alvo)),
    ]);

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
        if (!$instancia instanceof \Nucleo\Model) {
            throw new RuntimeException("O modelo {$nomeCompleto} deve herdar de Nucleo\\Model.");
        }

        return [
            'classe' => $classe,
            'tabela' => $instancia->tabela(),
            'instancia' => $instancia,
        ];
    }

    throw new RuntimeException("Modelo nao encontrado: {$alvo}. Gere-o antes com scaffold:crud.");
}

function caminhoRelatorioPdf(string $caminho): string
{
    if ($caminho === '') {
        throw new InvalidArgumentException('O arquivo do relatorio nao pode ser vazio.');
    }

    if ($caminho[0] === '/') {
        return $caminho;
    }

    return CAMINHO_RAIZ . '/' . ltrim($caminho, '/');
}

function gerarAutenticacao(array $argumentos): void
{
    if (count($argumentos) > 1) {
        throw new InvalidArgumentException('Uso: php console.php auth:install [Modelo]');
    }

    $modelo = resolverModeloAutenticacao($argumentos[0] ?? null);
    validarArquivosAutenticacaoLivres();

    $campos = $modelo['novo']
        ? [['nome', 'string', null], ['email', 'string', null], ['senha', 'string', null]]
        : [['email', 'string', null], ['senha', 'string', null]];

    foreach ([false, true] as $mysql) {
        $fallback = $modelo['novo'] ? esquemaAutenticacaoPadrao($mysql) : null;
        atualizarEsquemaAutenticacao($modelo['tabela'], $campos, $mysql, $fallback);
    }

    Database::migrar();
    sincronizarColunas($modelo['tabela'], $campos);
    $colunas = colunasDaTabela($modelo['tabela']);

    foreach (['email', 'senha'] as $campo) {
        if (!in_array($campo, $colunas, true)) {
            throw new RuntimeException("Nao foi possivel criar a coluna {$campo} na tabela {$modelo['tabela']}.");
        }
    }

    $temNome = in_array('nome', $colunas, true);

    if ($modelo['novo']) {
        escrever(
            CAMINHO_MODELOS . "/{$modelo['classe']}.php",
            "<?php\n\nnamespace Modelos;\n\nuse Nucleo\\Autenticavel;\nuse Nucleo\\Model;\n\nclass {$modelo['classe']} extends Model\n{\n    use Autenticavel;\n\n    protected string \$tabela = '{$modelo['tabela']}';\n    protected array \$preenchiveis = ['nome', 'email', 'senha'];\n}\n"
        );
    } else {
        tornarModeloAutenticavel($modelo['arquivo'], $temNome ? ['nome'] : []);
    }

    escrever(CAMINHO_CONTROLLERS . '/AuthController.php', controllerAutenticacaoGerado($modelo['classe'], $temNome));
    escrever(CAMINHO_VIEWS . '/auth/login.php', viewLoginAutenticacaoGerada());
    escrever(CAMINHO_VIEWS . '/auth/registrar.php', viewRegistroAutenticacaoGerada($temNome));

    echo "Autenticacao aplicada ao modelo {$modelo['classe']}: /auth/login\n";
}

function resolverModeloAutenticacao(?string $alvo): array
{
    if ($alvo === null) {
        $alvo = 'Usuario';
    }

    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $alvo)) {
        throw new InvalidArgumentException("Modelo invalido: {$alvo}");
    }

    $arquivoUsuario = CAMINHO_MODELOS . '/Usuario.php';
    if (in_array(strtolower($alvo), ['usuario', 'usuarios'], true) && !is_file($arquivoUsuario)) {
        return [
            'classe' => 'Usuario',
            'tabela' => 'usuarios',
            'arquivo' => $arquivoUsuario,
            'novo' => true,
        ];
    }

    $candidatos = array_unique([
        pascal($alvo),
        classeDaTabela(strtolower($alvo)),
    ]);

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
        if (!$instancia instanceof \Nucleo\Model) {
            throw new RuntimeException("O modelo {$nomeCompleto} deve herdar de Nucleo\\Model.");
        }

        return [
            'classe' => $classe,
            'tabela' => $instancia->tabela(),
            'arquivo' => $arquivo,
            'novo' => false,
        ];
    }

    throw new RuntimeException("Modelo nao encontrado: {$alvo}. Gere-o antes com scaffold:crud.");
}

function validarArquivosAutenticacaoLivres(): void
{
    $arquivos = [
        CAMINHO_CONTROLLERS . '/AuthController.php',
        CAMINHO_VIEWS . '/auth/login.php',
        CAMINHO_VIEWS . '/auth/registrar.php',
    ];

    foreach ($arquivos as $arquivo) {
        if (is_file($arquivo)) {
            throw new RuntimeException("Arquivo ja existe: {$arquivo}. A autenticacao ja foi instalada.");
        }
    }
}

function atualizarEsquemaAutenticacao(string $tabela, array $campos, bool $mysql, ?string $fallback): void
{
    $arquivo = CAMINHO_BANCO . '/esquema.' . ($mysql ? 'mysql' : 'sqlite') . '.sql';
    if (!is_file($arquivo)) {
        throw new RuntimeException("Arquivo de esquema nao encontrado: {$arquivo}");
    }

    $conteudo = file_get_contents($arquivo);
    $padrao = '/(CREATE\\s+TABLE(?:\\s+IF\\s+NOT\\s+EXISTS)?\\s+' . preg_quote($tabela, '/') . '\\s*\\()(.+?)(\\)\\s*(?:ENGINE\\s*=\\s*[^;]+)?;)/is';
    $encontrou = false;

    $atualizado = preg_replace_callback($padrao, function (array $correspondencia) use ($campos, &$encontrou, $mysql): string {
        $encontrou = true;
        $definicoes = rtrim($correspondencia[2]);
        $adicoes = [];

        foreach ($campos as [$nome, $tipo]) {
            if (!preg_match('/\\b' . preg_quote($nome, '/') . '\\b/i', $definicoes)) {
                $adicoes[] = "{$nome} " . tipoSql($tipo, $mysql) . ' NULL';
            }
        }

        if ($adicoes === []) {
            return $correspondencia[0];
        }

        return $correspondencia[1]
            . $definicoes
            . ",\n    " . implode(",\n    ", $adicoes)
            . "\n"
            . $correspondencia[3];
    }, $conteudo, 1);

    if ($atualizado === null) {
        throw new RuntimeException("Nao foi possivel atualizar o esquema da tabela {$tabela}.");
    }

    if (!$encontrou) {
        if ($fallback === null) {
            throw new RuntimeException("Tabela {$tabela} nao encontrada no esquema do banco.");
        }
        $atualizado = rtrim($atualizado) . "\n\n" . $fallback;
    }

    file_put_contents($arquivo, $atualizado, LOCK_EX);
}

function esquemaAutenticacaoPadrao(bool $mysql): string
{
    return $mysql
        ? "CREATE TABLE IF NOT EXISTS usuarios (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, senha VARCHAR(255) NOT NULL, criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n"
        : "CREATE TABLE IF NOT EXISTS usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, email TEXT NOT NULL UNIQUE, senha TEXT NOT NULL, criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);\n";
}

function tornarModeloAutenticavel(string $arquivo, array $camposAdicionais = []): void
{
    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        throw new RuntimeException("Nao foi possivel ler o modelo: {$arquivo}");
    }

    if (!str_contains($conteudo, 'Nucleo\\Autenticavel')) {
        $conteudo = preg_replace_callback(
            '/use\\s+Nucleo\\\\Model;\\s*/',
            fn (array $correspondencia): string => $correspondencia[0] . "use Nucleo\\Autenticavel;\n",
            $conteudo,
            1,
            $quantidade
        );

        if ($quantidade !== 1 || $conteudo === null) {
            throw new RuntimeException("Nao foi possivel preparar o modelo para autenticacao: {$arquivo}");
        }
    }

    if (!preg_match('/class\\s+[A-Za-z][A-Za-z0-9_]*\\s+extends\\s+[A-Za-z][A-Za-z0-9_]*\\s*\\{/', $conteudo)) {
        throw new RuntimeException("Nao foi possivel localizar a classe no modelo: {$arquivo}");
    }

    if (!preg_match('/class\\s+[A-Za-z][A-Za-z0-9_]*\\s+extends\\s+[A-Za-z][A-Za-z0-9_]*\\s*\\{[^}]*?\\buse\\s+Autenticavel\\s*;/s', $conteudo)) {
        $conteudo = preg_replace_callback(
            '/(class\\s+[A-Za-z][A-Za-z0-9_]*\\s+extends\\s+[A-Za-z][A-Za-z0-9_]*\\s*\\{\\s*)/',
            fn (array $correspondencia): string => $correspondencia[1] . "    use Autenticavel;\n\n",
            $conteudo,
            1,
            $quantidade
        );

        if ($quantidade !== 1 || $conteudo === null) {
            throw new RuntimeException("Nao foi possivel ativar a autenticacao no modelo: {$arquivo}");
        }
    }

    $padraoPreenchiveis = '/protected\\s+array\\s+\\$preenchiveis\\s*=\\s*\\[(.*?)\\];/s';
    if (preg_match($padraoPreenchiveis, $conteudo, $correspondencia)) {
        preg_match_all("/['\"]([a-z][a-z0-9_]*)['\"]/", $correspondencia[1], $encontrados);
        $campos = $encontrados[1] ?? [];

        foreach (array_merge($camposAdicionais, ['email', 'senha']) as $campo) {
            if (!in_array($campo, $campos, true)) {
                $campos[] = $campo;
            }
        }

        $declaracao = 'protected array $preenchiveis = ['
            . implode(', ', array_map(fn (string $campo): string => "'{$campo}'", $campos))
            . '];';
        $conteudo = preg_replace($padraoPreenchiveis, $declaracao, $conteudo, 1, $quantidade);

        if ($quantidade !== 1 || $conteudo === null) {
            throw new RuntimeException("Nao foi possivel atualizar os campos do modelo: {$arquivo}");
        }
    } else {
        $conteudo = preg_replace_callback(
            '/(class\\s+[A-Za-z][A-Za-z0-9_]*\\s+extends\\s+[A-Za-z][A-Za-z0-9_]*\\s*\\{\\s*)/',
            fn (array $correspondencia): string => $correspondencia[1] . "    protected array \$preenchiveis = ['" . implode("', '", array_merge($camposAdicionais, ['email', 'senha'])) . "'];\n\n",
            $conteudo,
            1,
            $quantidade
        );

        if ($quantidade !== 1 || $conteudo === null) {
            throw new RuntimeException("Nao foi possivel adicionar os campos ao modelo: {$arquivo}");
        }
    }

    file_put_contents($arquivo, $conteudo, LOCK_EX);
}

function controllerAutenticacaoGerado(string $classe, bool $temNome): string
{
    $validacaoNome = $temNome ? "\$this->post('nome', '') !== '' && " : '';
    $dadosNome = $temNome ? "\n                    'nome' => \$this->post('nome')," : '';

    return str_replace(
        ['__CLASSE__', '__VALIDACAO_NOME__', '__DADOS_NOME__'],
        [$classe, $validacaoNome, $dadosNome],
        <<<'PHP'
<?php

namespace Controllers;

use Modelos\__CLASSE__;
use Nucleo\Controller;
use Nucleo\Sessao;

class AuthController extends Controller
{
    private __CLASSE__ $modelo;

    public function __construct()
    {
        $this->modelo = new __CLASSE__();
    }

    public function login(): void
    {
        if ($this->ehPost()) {
            $email = (string) $this->post('email', '');
            $senha = (string) $this->post('senha', '');
            $registro = $this->modelo->autenticar($email, $senha);
            if ($registro !== null) {
                Sessao::definir('autenticacao_id', $registro['id']);
                Sessao::definir('usuario_id', $registro['id']);
                $this->redirecionar();
            }
            $this->mensagem('erro', 'E-mail ou senha invalidos.');
        }
        $this->view('auth/login', ['titulo' => 'Entrar']);
    }

    public function registrar(): void
    {
        if ($this->ehPost()) {
            $senha = (string) $this->post('senha', '');
            $email = (string) $this->post('email', '');
            if (__VALIDACAO_NOME__filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($senha) >= 6 && $this->modelo->buscarPorEmail($email) === null) {
                $dados = [
                    'email' => $email,__DADOS_NOME__
                ];
                $this->modelo->criarComSenha($dados, $senha);
                $this->mensagem('sucesso', 'Conta criada. Agora entre com seus dados.');
                $this->redirecionar('auth/login');
            }
            $this->mensagem('erro', 'Preencha os dados corretamente.');
        }
        $this->view('auth/registrar', ['titulo' => 'Criar conta']);
    }

    public function sair(): void
    {
        Sessao::remover('autenticacao_id');
        Sessao::remover('usuario_id');
        $this->redirecionar('auth/login');
    }
}
PHP);
}

function viewLoginAutenticacaoGerada(): string
{
    return <<<'PHP'
<div class="row justify-content-center"><div class="col-12 col-md-7 col-lg-5"><div class="card border-0 shadow-sm p-4"><h1 class="h3 mb-4">Entrar</h1><form method="post" action="<?= url('auth/login') ?>"><div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" id="email" type="email" name="email" autocomplete="email" required></div><div class="mb-4"><label class="form-label" for="senha">Senha</label><input class="form-control" id="senha" type="password" name="senha" autocomplete="current-password" required></div><button class="btn btn-primary w-100" type="submit">Entrar</button></form><p class="text-center mt-4 mb-0"><a href="<?= url('auth/registrar') ?>">Criar uma conta</a></p></div></div></div>
PHP;
}

function viewRegistroAutenticacaoGerada(bool $temNome): string
{
    $campoNome = $temNome
        ? '<div class="mb-3"><label class="form-label" for="nome">Nome</label><input class="form-control" id="nome" type="text" name="nome" autocomplete="name" required></div>'
        : '';

    return str_replace('__CAMPO_NOME__', $campoNome, <<<'PHP'
<div class="row justify-content-center"><div class="col-12 col-md-7 col-lg-5"><div class="card border-0 shadow-sm p-4"><h1 class="h3 mb-4">Criar conta</h1><form method="post" action="<?= url('auth/registrar') ?>">__CAMPO_NOME__<div class="mb-3"><label class="form-label" for="email">E-mail</label><input class="form-control" id="email" type="email" name="email" autocomplete="email" required></div><div class="mb-4"><label class="form-label" for="senha">Senha</label><input class="form-control" id="senha" type="password" name="senha" autocomplete="new-password" minlength="6" required></div><button class="btn btn-primary w-100" type="submit">Criar conta</button></form></div></div></div>
PHP);
}
