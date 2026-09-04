<?php

/**
 * Configuracoes do banco de dados (MySQL / MariaDB).
 *
 * Para instalar:
 *   1. Inicie o MySQL no painel de controle do XAMPP
 *   2. Rode:  php instalar.php
 *
 * O instalador cria os dois bancos e executa o esquema gerado pelo console
 * (banco/esquema.sql). O projeto comeca sem tabelas ou dados de exemplo.
 *
 * Sao dois bancos de proposito:
 *   'banco'        guarda os dados do sistema;
 *   'banco_testes' e recriado do zero a cada "php testes/executar.php",
 *                  entao rodar os testes nunca encosta nos seus dados.
 */

return [
    'mysql' => [
        'host'         => 'localhost',
        'porta'        => 3306,
        'banco'        => 'framework_aula',
        'banco_testes' => 'framework_aula_testes',
        'usuario'      => 'root',
        'senha'        => '',
        'charset'      => 'utf8mb4',
    ],
];
