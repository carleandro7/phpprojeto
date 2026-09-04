<?php

/**
 * Instalador do framework: prepara o banco sem criar tabelas da aplicacao.
 *
 * Pelo terminal:      php instalar.php
 * Pelo navegador:     http://localhost/framework/instalar.php
 *
 * O esquema da aplicacao deve ser criado pelos comandos do console.
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
    $banco  = (string) Config::obter('banco.mysql.banco');
    $testes = (string) Config::obter('banco.mysql.banco_testes', $banco . '_testes');

    $escrever('Instalando o banco de dados (MySQL)...');
    $escrever('Servidor: ' . Config::obter('banco.mysql.host') . ' | Banco: ' . $banco);

    Database::criarBancoSeNaoExistir($banco);
    Database::migrar();
    $escrever('[ok] Banco de dados pronto. Nenhuma tabela padrao foi criada.');

    // O banco dos testes e recriado a cada execucao da suite; aqui so
    // garantimos que ele exista, para "php testes/executar.php" ja funcionar.
    Database::criarBancoSeNaoExistir($testes);
    $escrever("[ok] Banco de testes pronto: {$testes}");
    $escrever('');
    $escrever('Pronto! Agora rode o servidor:');
    $escrever('    php -S localhost:8000 roteador.php');
    $escrever('');
    $escrever('Para criar a tela de login:');
    $escrever('    php console.php auth:install');
    $escrever('');
    $escrever('Para criar sua primeira tabela:');
    $escrever('    php console.php scaffold:crud produtos nome:string preco:decimal --auth');
    $escrever('');
    $escrever('E os testes:');
    $escrever('    php testes/executar.php');
} catch (Throwable $e) {
    $escrever('[ERRO] ' . $e->getMessage());

    $escrever('');
    $escrever('Checklist do MySQL:');
    $escrever('  1. O MySQL esta iniciado no painel do XAMPP?');
    $escrever('  2. Usuario e senha em configuracoes/banco.php estao corretos?');
    $escrever('     (no XAMPP o padrao e usuario "root" e senha vazia)');

    if (!$noTerminal) {
        echo '</pre>';
    }

    exit(1);
}

if (!$noTerminal) {
    echo '</pre><p><a href="' . url() . '">Abrir o sistema</a></p>';
}
