<?php

/**
 * Preparacao do ambiente de testes.
 *
 * Ponto importante: os testes NAO usam o banco de verdade.
 * Trocamos a configuracao para um SQLite em memoria, que nasce vazio e
 * desaparece quando o processo termina. Assim voce pode rodar os testes
 * quantas vezes quiser sem estragar os dados do sistema.
 */

use Nucleo\Config;
use Nucleo\Database;

require_once dirname(__DIR__) . '/nucleo/bootstrap.php';

// ---------------------------------------------------------------------
// Banco de dados descartavel
// ---------------------------------------------------------------------

Config::definir('banco.driver', 'sqlite');
Config::definir('banco.sqlite.arquivo', ':memory:');

// Se alguma conexao ja tinha sido aberta, descarta.
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
