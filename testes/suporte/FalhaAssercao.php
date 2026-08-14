<?php

namespace Testes\Suporte;

use Exception;

/**
 * Disparada quando uma verificacao (assercao) de teste nao se confirma.
 * O Executor captura esta excecao e marca o teste como FALHOU.
 */
class FalhaAssercao extends Exception
{
}
