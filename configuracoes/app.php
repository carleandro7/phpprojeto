<?php

/**
 * Configuracoes gerais da aplicacao.
 * Leia com Config::obter('app.nome').
 */

return [
    // Nome que aparece no titulo e no cabecalho das paginas.
    'nome' => 'Framework MVC - Curso Tecnico',

    // Endereco raiz do sistema.
    // Deixe vazio para o framework detectar sozinho.
    // Exemplo se precisar fixar: 'http://localhost/framework'
    'url_base' => '',

    // true  = mostra os detalhes dos erros (use enquanto desenvolve)
    // false = mostra uma tela generica (use quando publicar)
    'debug' => true,

    'timezone' => 'America/Sao_Paulo',

    // Para onde vai quem acessa a raiz do site: /  ->  HomeController::index()
    'controlador_padrao' => 'home',
    'metodo_padrao'      => 'index',
];
