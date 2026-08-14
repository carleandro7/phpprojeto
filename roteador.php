<?php

/**
 * Roteador para o servidor embutido do PHP (nao precisa de XAMPP/Apache).
 *
 * Como usar, dentro da pasta do projeto:
 *
 *     php -S localhost:8000 roteador.php
 *
 * Depois abra http://localhost:8000 no navegador.
 *
 * Este arquivo faz o papel do .htaccess: entrega direto os arquivos estaticos
 * (css, imagens, javascripts) e manda todo o resto para o index.php.
 */

$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$arquivo = __DIR__ . urldecode($caminho);

// Nao deixa abrir as pastas internas do framework pelo navegador.
if (preg_match('#^/(nucleo|modelos|controllers|configuracoes|testes|banco)(/|$)#', $caminho)) {
    http_response_code(404);
    echo 'Acesso negado.';
    return true;
}

// Arquivo estatico existente (css, js, png...) e servido como esta.
if ($caminho !== '/' && is_file($arquivo) && !str_ends_with(strtolower($arquivo), '.php')) {
    return false;
}

$_GET['url'] = trim($caminho, '/');

// Com o servidor embutido, SCRIPT_NAME recebe a URL pedida. Corrigimos para
// que a funcao url_base() calcule os links corretamente.
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/index.php';
