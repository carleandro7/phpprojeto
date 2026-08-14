<?php

/**
 * Ponto de entrada dos testes.
 *
 * Rodar todos:
 *     php testes/executar.php
 *
 * Rodar apenas uma pasta, um arquivo ou um metodo (filtro por texto):
 *     php testes/executar.php Modelos            (pasta testes/modelos)
 *     php testes/executar.php Controllers        (pasta testes/controllers)
 *     php testes/executar.php AlunoTest
 *     php testes/executar.php Controllers\AlunosControllerTest::testeLista
 *     php testes/executar.php validacao
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Os testes so podem ser executados pelo terminal: php testes/executar.php');
}

require_once __DIR__ . '/bootstrap.php';

$filtro = $argv[1] ?? null;

$executor = new Testes\Suporte\Executor(__DIR__, $filtro);

exit($executor->executar());
