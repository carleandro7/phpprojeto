<?php

namespace Testes\Suporte;

/**
 * Representa o resultado de uma requisicao simulada dentro dos testes.
 *
 * Devolvida por TesteBase::requisitar():
 *
 *     $resposta = $this->requisitar('produtos');
 *     $resposta->html                 -> o HTML gerado
 *     $resposta->status               -> 200, 302, 404...
 *     $resposta->redirecionamento     -> destino, quando houve redirect
 */
class Resposta
{
    public function __construct(
        public readonly string $html = '',
        public readonly int $status = 200,
        public readonly ?string $redirecionamento = null,
    ) {
    }

    /**
     * O HTML contem este texto?
     */
    public function contem(string $texto): bool
    {
        return str_contains($this->html, $texto);
    }

    /**
     * A requisicao terminou em redirecionamento?
     */
    public function foiRedirecionado(): bool
    {
        return $this->redirecionamento !== null;
    }

    /**
     * O destino do redirecionamento termina com este caminho?
     * Assim o teste nao precisa saber a URL base completa.
     */
    public function redirecionouPara(string $caminho): bool
    {
        if ($this->redirecionamento === null) {
            return false;
        }

        return str_ends_with(rtrim($this->redirecionamento, '/'), '/' . trim($caminho, '/'));
    }

    /**
     * Interpreta o corpo da resposta como JSON.
     */
    public function json(): mixed
    {
        return json_decode($this->html, true);
    }
}
