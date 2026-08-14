<?php

/**
 * Front Controller: TODAS as paginas do site passam por aqui.
 *
 * O arquivo .htaccess reescreve qualquer endereco para
 * index.php?url=<caminho>, e o App decide quem responde.
 */

require_once __DIR__ . '/nucleo/bootstrap.php';

(new Nucleo\App())->executar();
