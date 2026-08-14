<?php

/**
 * Instalador do framework: cria as tabelas e insere os dados de exemplo.
 *
 * Pelo terminal:      php instalar.php
 * Pelo navegador:     http://localhost/framework/instalar.php
 *
 * Pode rodar quantas vezes quiser — o banco e recriado do zero.
 */

require_once __DIR__ . '/nucleo/bootstrap.php';

use Nucleo\Config;
use Nucleo\Database;

$noTerminal = PHP_SAPI === 'cli';
$quebra     = $noTerminal ? PHP_EOL : '<br>';

if (!$noTerminal) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font:14px/1.6 monospace;padding:20px">';
}

$escrever = function (string $texto) use ($quebra): void {
    echo $texto . $quebra;
};

try {
    $driver = Config::obter('banco.driver');

    $escrever("Instalando o banco de dados ({$driver})...");

    if ($driver === 'sqlite') {
        $escrever('Arquivo: ' . Config::obter('banco.sqlite.arquivo'));
    }

    Database::migrar();
    $escrever('[ok] Tabelas criadas.');

    Database::popular();
    $escrever('[ok] Dados de exemplo inseridos.');

    $total = Database::conexao()
        ->query('SELECT COUNT(*) FROM alunos')
        ->fetchColumn();

    $escrever("[ok] {$total} alunos cadastrados.");
    $escrever('');
    $escrever('Pronto! Agora rode o servidor:');
    $escrever('    php -S localhost:8000 roteador.php');
    $escrever('');
    $escrever('E os testes:');
    $escrever('    php testes/executar.php');
} catch (Throwable $e) {
    $escrever('[ERRO] ' . $e->getMessage());

    if ($driver ?? '' === 'mysql') {
        $escrever('Verifique se o banco existe e se usuario/senha estao corretos');
        $escrever('em configuracoes/banco.php.');
    }

    if (!$noTerminal) {
        echo '</pre>';
    }

    exit(1);
}

if (!$noTerminal) {
    echo '</pre><p><a href="' . url() . '">Abrir o sistema</a></p>';
}
