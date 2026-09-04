<?php

/**
 * Preparacao do ambiente de testes.
 *
 * Ponto importante: os testes NAO usam o banco do sistema. Eles rodam no
 * banco indicado por 'banco_testes' em configuracoes/banco.php, que e
 * APAGADO E RECRIADO a cada execucao. Assim voce pode rodar os testes
 * quantas vezes quiser sem estragar os dados da aplicacao.
 */

use Nucleo\Config;
use Nucleo\Database;
use Nucleo\Sql;

require_once dirname(__DIR__) . '/nucleo/bootstrap.php';

// ---------------------------------------------------------------------
// Banco de dados descartavel
// ---------------------------------------------------------------------

$mysql       = (array) Config::obter('banco.mysql', []);
$daAplicacao = (string) ($mysql['banco'] ?? '');
$dosTestes   = (string) ($mysql['banco_testes'] ?? ($daAplicacao . '_testes'));

// Trava de seguranca: como este banco e apagado a cada execucao, ele nunca
// pode ser o mesmo da aplicacao.
if ($dosTestes === '' || $dosTestes === $daAplicacao) {
    fwrite(STDERR, "O banco de testes nao pode ser o mesmo da aplicacao.\n"
        . "Ajuste 'banco_testes' em configuracoes/banco.php.\n");
    exit(1);
}

$nome    = Sql::identificador($dosTestes, 'banco de testes');
$charset = Sql::identificador((string) ($mysql['charset'] ?? 'utf8mb4'), 'charset');

try {
    $servidor = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', $mysql['host'], $mysql['porta'], $charset),
        $mysql['usuario'],
        $mysql['senha'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Recriar do zero garante que o resultado nao dependa do que a execucao
    // anterior deixou para tras.
    $servidor->exec("DROP DATABASE IF EXISTS `{$nome}`");
    $servidor->exec("CREATE DATABASE `{$nome}` CHARACTER SET {$charset}");
} catch (PDOException $e) {
    fwrite(STDERR, "Nao foi possivel preparar o banco de testes \"{$nome}\":\n"
        . '  ' . $e->getMessage() . "\n\n"
        . "Checklist:\n"
        . "  1. O MySQL esta iniciado no painel do XAMPP?\n"
        . "  2. Usuario e senha em configuracoes/banco.php estao corretos?\n");
    exit(1);
}

Config::definir('banco.mysql.banco', $dosTestes);

// Se alguma conexao ja tinha sido aberta, descarta: a proxima nasce
// apontando para o banco de testes.
Database::desconectar();

// ---------------------------------------------------------------------
// Ambiente de requisicao simulado
// ---------------------------------------------------------------------

Config::definir('app.url_base', 'http://localhost');
Config::definir('app.debug', true);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/index.php';
$_SERVER['HTTP_HOST']      = 'localhost';
$_SESSION                  = [];
