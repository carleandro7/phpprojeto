<?php

/**
 * Configuracoes do banco de dados.
 *
 * Vem configurado com SQLite: nao precisa instalar nem configurar servidor,
 * o banco e um unico arquivo em banco/dados.sqlite.
 *
 * Para usar MySQL (XAMPP / phpMyAdmin):
 *   1. Crie o banco no phpMyAdmin (ex.: framework_aula)
 *   2. Importe o arquivo banco/esquema.mysql.sql
 *   3. Troque 'driver' para 'mysql' e ajuste usuario/senha abaixo
 */

return [
    // 'sqlite' ou 'mysql'
    'driver' => 'sqlite',

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
