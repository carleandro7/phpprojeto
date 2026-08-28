<?php

/**
 * Configuracoes do banco de dados.
 *
 * Vem configurado com MySQL (XAMPP). Para instalar:
 *   1. Inicie o MySQL no painel de controle do XAMPP
 *   2. Rode:  php instalar.php
 *
 * O instalador cria o banco e executa os esquemas gerados pelo console.
 * O projeto comeca sem tabelas ou dados de exemplo.
 *
 * Para voltar ao SQLite (nao precisa de servidor nenhum, o banco e um unico
 * arquivo em banco/dados.sqlite): troque 'driver' para 'sqlite' e rode o
 * instalador de novo.
 */

return [
    // 'sqlite' ou 'mysql'
    'driver' => 'mysql',

    'sqlite' => [
        'arquivo' => CAMINHO_RAIZ . '/banco/dados.sqlite',
    ],

    'mysql' => [
        'host'    => 'localhost',
        'porta'   => 3306,
        'banco'   => 'framework_aula',
        'usuario' => 'root',
        'senha'   => '',
        'charset' => 'utf8mb4',
    ],
];
