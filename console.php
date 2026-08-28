<?php

if (PHP_SAPI !== 'cli') {
    exit("Este arquivo so pode ser executado pelo terminal.\n");
}

require_once __DIR__ . '/nucleo/bootstrap.php';

use Nucleo\Database;

$comando = $argv[1] ?? '';

if ($comando === 'scaffold:crud') {
    gerarCrud(array_slice($argv, 2));
} elseif ($comando === 'auth:install') {
    gerarAutenticacao();
} else {
    echo "Uso:\n  php console.php scaffold:crud tabela campo:tipo ...\n  php console.php auth:install\n";
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
        validarNome($nome, 'campo');
        if (in_array($nome, ['id', 'criado_em'], true) || !in_array($tipo, ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'time'], true)) {
            throw new InvalidArgumentException("Campo ou tipo invalido: {$definicao}");
        }
        $campos[] = [$nome, $tipo];
    }
    $singular = str_ends_with($tabela, 's') ? substr($tabela, 0, -1) : $tabela;
    $classe = pascal($singular);
    $recurso = pascal($tabela);
    $pasta = strtolower($recurso);

    escrever(CAMINHO_MODELOS . "/{$classe}.php", "<?php\n\nnamespace Modelos;\n\nuse Nucleo\\Model;\n\nclass {$classe} extends Model\n{\n    protected string \$tabela = '{$tabela}';\n    protected array \$preenchiveis = [" . implode(', ', array_map(fn ($campo) => "'{$campo[0]}'", $campos)) . "];\n    protected string \$ordemPadrao = 'id DESC';\n}\n");
    escrever(CAMINHO_CONTROLLERS . "/{$recurso}Controller.php", controllerGerado($classe, $recurso, $pasta, $campos));
    escrever(CAMINHO_VIEWS . "/{$pasta}/index.php", indexGerado($tabela, $campos));
    escrever(CAMINHO_VIEWS . "/{$pasta}/formulario.php", formularioGerado($tabela, $campos));
    escrever(CAMINHO_VIEWS . "/{$pasta}/ver.php", verGerado($tabela, $campos));
    file_put_contents(CAMINHO_BANCO . '/esquema.sqlite.sql', "\n" . esquema($tabela, $campos, false), FILE_APPEND | LOCK_EX);
    file_put_contents(CAMINHO_BANCO . '/esquema.mysql.sql', "\n" . esquema($tabela, $campos, true), FILE_APPEND | LOCK_EX);
    Database::migrar();
    echo "CRUD criado: /{$pasta}\n";
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
    return "<?php\n\nnamespace Controllers;\n\nuse Modelos\\{$classe};\nuse Nucleo\\Controller;\n\nclass {$recurso}Controller extends Controller\n{\n    private {$classe} \$modelo;\n\n    public function __construct()\n    {\n        \$this->modelo = new {$classe}();\n    }\n\n    public function index(): void\n    {\n        \$this->view('{$pasta}/index', ['titulo' => '{$recurso}', 'registros' => \$this->modelo->todos()]);\n    }\n\n    public function criar(): void\n    {\n        \$this->view('{$pasta}/formulario', ['titulo' => 'Novo {$classe}', 'registro' => null]);\n    }\n\n    public function salvar(): void\n    {\n        \$id = \$this->modelo->criar([\n            {$dados}\n        ]);\n        \$this->mensagem('sucesso', '{$classe} criado com sucesso.');\n        \$this->redirecionar('{$pasta}/ver/' . \$id);\n    }\n\n    public function ver(string \$id): void\n    {\n        \$registro = \$this->modelo->buscar(\$id);\n        if (\$registro === null) { \$this->naoEncontrado(); }\n        \$this->view('{$pasta}/ver', ['titulo' => '{$classe}', 'registro' => \$registro]);\n    }\n\n    public function editar(string \$id): void\n    {\n        \$registro = \$this->modelo->buscar(\$id);\n        if (\$registro === null) { \$this->naoEncontrado(); }\n        \$this->view('{$pasta}/formulario', ['titulo' => 'Editar {$classe}', 'registro' => \$registro]);\n    }\n\n    public function atualizar(string \$id): void\n    {\n        \$this->modelo->atualizar(\$id, [\n            {$dados}\n        ]);\n        \$this->mensagem('sucesso', '{$classe} atualizado com sucesso.');\n        \$this->redirecionar('{$pasta}/ver/' . \$id);\n    }\n\n    public function excluir(string \$id): void\n    {\n        if (!\$this->modelo->excluir(\$id)) { \$this->naoEncontrado(); }\n        \$this->mensagem('sucesso', '{$classe} excluido com sucesso.');\n        \$this->redirecionar('{$pasta}');\n    }\n}\n";
}

function indexGerado(string $tabela, array $campos): string
{
    $cabecalhos = implode('', array_map(fn ($campo) => "        <th>{$campo[0]}</th>\n", $campos));
    $celulas = implode('', array_map(fn ($campo) => "            <td><?= e(\$registro['{$campo[0]}'] ?? '') ?></td>\n", $campos));
    return "<h1>{$tabela}</h1>\n<a href=\"<?= url('{$tabela}/criar') ?>\">Novo registro</a>\n<table><thead><tr><th>ID</th>\n{$cabecalhos}</tr></thead><tbody>\n<?php foreach (\$registros as \$registro): ?>\n<tr><td><a href=\"<?= url('{$tabela}/ver/' . \$registro['id']) ?>\"><?= e(\$registro['id']) ?></a></td>\n{$celulas}</tr>\n<?php endforeach ?>\n</tbody></table>\n";
}

function formularioGerado(string $tabela, array $campos): string
{
    $inputs = implode("\n", array_map(fn ($campo) => "    <label>{$campo[0]} <input type=\"" . match ($campo[1]) { 'integer', 'decimal' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'time' => 'time', 'boolean' => 'checkbox', default => 'text' } . "\" name=\"{$campo[0]}\" value=\"<?= e(antigo('{$campo[0]}', \$registro['{$campo[0]}'] ?? '')) ?>\"></label>", $campos));
    return "<h1><?= e(\$titulo) ?></h1>\n<form method=\"post\" action=\"<?= url('{$tabela}/' . (\$registro ? 'atualizar/' . \$registro['id'] : 'salvar')) ?>\">\n{$inputs}\n    <button type=\"submit\">Salvar</button>\n</form>\n";
}

function verGerado(string $tabela, array $campos): string
{
    $linhas = implode("\n", array_map(fn ($campo) => "<dt>{$campo[0]}</dt><dd><?= e(\$registro['{$campo[0]}'] ?? '') ?></dd>", $campos));
    return "<h1>Registro</h1><dl>\n{$linhas}\n</dl><a href=\"<?= url('{$tabela}') ?>\">Voltar</a>\n";
}

function esquema(string $tabela, array $campos, bool $mysql): string
{
    $colunas = implode(",\n    ", array_map(fn ($campo) => "{$campo[0]} " . ($mysql ? match ($campo[1]) { 'integer' => 'INT', 'decimal' => 'DECIMAL(12,2)', 'boolean' => 'TINYINT(1)', 'date' => 'DATE', 'datetime' => 'DATETIME', 'time' => 'TIME', default => 'VARCHAR(255)' } : match ($campo[1]) { 'integer' => 'INTEGER', 'decimal' => 'REAL', 'boolean' => 'INTEGER', default => 'TEXT' }) . ' NULL', $campos));
    return $mysql ? "CREATE TABLE IF NOT EXISTS {$tabela} (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    {$colunas}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n" : "CREATE TABLE IF NOT EXISTS {$tabela} (\n    id INTEGER PRIMARY KEY AUTOINCREMENT,\n    {$colunas}\n);\n";
}

function gerarAutenticacao(): void
{
    escrever(CAMINHO_MODELOS . '/Usuario.php', "<?php\n\nnamespace Modelos;\n\nuse Nucleo\\Model;\n\nclass Usuario extends Model\n{\n    protected string \$tabela = 'usuarios';\n    protected array \$preenchiveis = ['nome', 'email', 'senha'];\n}\n");
    escrever(CAMINHO_CONTROLLERS . '/AuthController.php', <<<'PHP'
<?php

namespace Controllers;

use Modelos\Usuario;
use Nucleo\Controller;
use Nucleo\Sessao;

class AuthController extends Controller
{
    private Usuario $usuarios;

    public function __construct()
    {
        $this->usuarios = new Usuario();
    }

    public function login(): void
    {
        if ($this->ehPost()) {
            $usuario = $this->usuarios->primeiroOnde('email', $this->post('email', ''));
            if ($usuario !== null && password_verify((string) $this->post('senha', ''), $usuario['senha'])) {
                Sessao::definir('usuario_id', $usuario['id']);
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
            if ($this->post('nome', '') !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($senha) >= 6 && $this->usuarios->primeiroOnde('email', $email) === null) {
                $this->usuarios->criar(['nome' => $this->post('nome'), 'email' => $email, 'senha' => password_hash($senha, PASSWORD_DEFAULT)]);
                $this->mensagem('sucesso', 'Conta criada. Agora entre com seus dados.');
                $this->redirecionar('auth/login');
            }
            $this->mensagem('erro', 'Preencha os dados corretamente.');
        }
        $this->view('auth/registrar', ['titulo' => 'Criar conta']);
    }

    public function sair(): void
    {
        Sessao::remover('usuario_id');
        $this->redirecionar('auth/login');
    }
}
PHP);
    escrever(CAMINHO_VIEWS . '/auth/login.php', "<h1>Entrar</h1>\n<form method=\"post\" action=\"<?= url('auth/login') ?>\"><label>E-mail <input type=\"email\" name=\"email\" required></label><label>Senha <input type=\"password\" name=\"senha\" required></label><button type=\"submit\">Entrar</button></form>\n<p><a href=\"<?= url('auth/registrar') ?>\">Criar uma conta</a></p>\n");
    escrever(CAMINHO_VIEWS . '/auth/registrar.php', "<h1>Criar conta</h1>\n<form method=\"post\" action=\"<?= url('auth/registrar') ?>\"><label>Nome <input type=\"text\" name=\"nome\" required></label><label>E-mail <input type=\"email\" name=\"email\" required></label><label>Senha <input type=\"password\" name=\"senha\" minlength=\"6\" required></label><button type=\"submit\">Criar conta</button></form>\n");
    $sqlite = "CREATE TABLE IF NOT EXISTS usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, email TEXT NOT NULL UNIQUE, senha TEXT NOT NULL, criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);\n";
    $mysql = "CREATE TABLE IF NOT EXISTS usuarios (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, senha VARCHAR(255) NOT NULL, criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
    file_put_contents(CAMINHO_BANCO . '/esquema.sqlite.sql', "\n{$sqlite}", FILE_APPEND | LOCK_EX);
    file_put_contents(CAMINHO_BANCO . '/esquema.mysql.sql', "\n{$mysql}", FILE_APPEND | LOCK_EX);
    Database::migrar();
    echo "Autenticacao criada: /auth/login\n";
}
