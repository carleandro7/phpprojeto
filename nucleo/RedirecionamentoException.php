<?php

namespace Nucleo;

use Exception;

/**
 * Nao representa um erro: e a forma que o framework usa para interromper a
 * execucao quando um controlador manda redirecionar o navegador.
 *
 * Vantagem: nos testes conseguimos "capturar" o redirecionamento e verificar
 * para onde o sistema mandou o usuario, em vez de o script simplesmente
 * encerrar com exit().
 */
class RedirecionamentoException extends Exception
{
    public function __construct(
        public readonly string $destino,
        public readonly int $status = 302
    ) {
        parent::__construct("Redirecionando para {$destino}");
    }
}
