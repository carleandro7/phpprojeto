<?php

namespace Nucleo;

use Exception;

/**
 * Disparada quando a rota pedida (controlador/metodo) nao existe.
 * O App captura esta excecao e mostra a tela de erro 404.
 */
class NaoEncontradoException extends Exception
{
}
