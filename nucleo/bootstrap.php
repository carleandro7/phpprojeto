<?php

/**
 * Arquivo de partida do framework.
 *
 * Ele e chamado tanto pelo index.php (navegador) quanto pelos testes.
 * Responsavel por: definir os caminhos, ligar o autoloader, carregar as
 * configuracoes, os helpers e iniciar a sessao.
 */

use Nucleo\Autoloader;
use Nucleo\Config;
use Nucleo\Sessao;

// ---------------------------------------------------------------------
// 1. Caminhos das pastas (constantes globais)
// ---------------------------------------------------------------------

define('CAMINHO_RAIZ',          dirname(__DIR__));
define('CAMINHO_NUCLEO',        CAMINHO_RAIZ . '/nucleo');
define('CAMINHO_CONFIGURACOES', CAMINHO_RAIZ . '/configuracoes');
define('CAMINHO_CONTROLLERS',   CAMINHO_RAIZ . '/controllers');
define('CAMINHO_MODELOS',       CAMINHO_RAIZ . '/modelos');
define('CAMINHO_VIEWS',         CAMINHO_RAIZ . '/views');
define('CAMINHO_TESTES',        CAMINHO_RAIZ . '/testes');
define('CAMINHO_BANCO',         CAMINHO_RAIZ . '/banco');

// ---------------------------------------------------------------------
// 2. Autoloader: liga cada namespace a uma pasta
// ---------------------------------------------------------------------

require_once CAMINHO_NUCLEO . '/Autoloader.php';

Autoloader::adicionar('Nucleo',      CAMINHO_NUCLEO);
Autoloader::adicionar('Controllers', CAMINHO_CONTROLLERS);
Autoloader::adicionar('Modelos',     CAMINHO_MODELOS);
Autoloader::adicionar('Testes',      CAMINHO_TESTES);
Autoloader::registrar();

// ---------------------------------------------------------------------
// 3. Configuracoes e funcoes de atalho
// ---------------------------------------------------------------------

Config::carregar(CAMINHO_CONFIGURACOES);

require_once CAMINHO_NUCLEO . '/helpers.php';

date_default_timezone_set(Config::obter('app.timezone', 'America/Sao_Paulo'));

// ---------------------------------------------------------------------
// 4. Exibicao de erros conforme o modo de desenvolvimento
// ---------------------------------------------------------------------

if (Config::obter('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// ---------------------------------------------------------------------
// 5. Sessao (mensagens de sucesso/erro, login, etc.)
// ---------------------------------------------------------------------

Sessao::iniciar();
