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
    $driver = Config::obter('banco.driver');

    $escrever("Instalando o banco de dados ({$driver})...");

    if ($driver === 'sqlite') {
        $escrever('Arquivo: ' . Config::obter('banco.sqlite.arquivo'));
    }

    if ($driver === 'mysql') {
        $escrever('Servidor: ' . Config::obter('banco.mysql.host')
            . ' | Banco: ' . Config::obter('banco.mysql.banco'));

        Database::criarBancoSeNaoExistir();
        $escrever('[ok] Banco de dados pronto.');
    }

    Database::migrar();
    $escrever('[ok] Banco de dados pronto. Nenhuma tabela padrao foi criada.');
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

    // Atencao aos parenteses: sem eles o PHP leria "$driver ?? ('' === 'mysql')".
    if (($driver ?? '') === 'mysql') {
        $escrever('');
        $escrever('Checklist do MySQL:');
        $escrever('  1. O MySQL esta iniciado no painel do XAMPP?');
        $escrever('  2. Usuario e senha em configuracoes/banco.php estao corretos?');
        $escrever('     (no XAMPP o padrao e usuario "root" e senha vazia)');
    }

    if (!$noTerminal) {
        echo '</pre>';
    }

    exit(1);
}

if (!$noTerminal) {
    echo '</pre><p><a href="' . url() . '">Abrir o sistema</a></p>';
}
