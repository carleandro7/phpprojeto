<?php

/**
 * Configuracoes do banco de dados.
 *
 * Vem configurado com MySQL (XAMPP). Para instalar:
 *   1. Inicie o MySQL no painel de controle do XAMPP
 *   2. Rode:  php instalar.php
 *
 * O instalador cria o banco, as tabelas e os dados de exemplo sozinho,
 * nao precisa criar nada na mao pelo phpMyAdmin.
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
