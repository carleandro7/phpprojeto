<?php

namespace Nucleo;

/**
 * Validador de formularios.
 *
 * As regras sao encadeadas e cada uma guarda uma mensagem de erro por campo.
 *
 * Exemplo:
 *     $v = new Validador($_POST);
 *     $v->obrigatorio('nome', 'Nome')
 *       ->minimo('nome', 3)
 *       ->email('email');
 *
 *     if (!$v->passou()) {
 *         $erros = $v->erros();
 *     }
 */
class Validador
{
    /** @var array<string,string> campo => primeira mensagem de erro */
    private array $erros = [];

    public function __construct(private array $dados)
    {
    }

    // ------------------------------------------------------------------
    // Regras
    // ------------------------------------------------------------------

    public function obrigatorio(string $campo, ?string $rotulo = null): static
    {
        $valor = $this->valor($campo);

        if ($valor === null || trim((string) $valor) === '') {
            $this->erro($campo, 'O campo ' . $this->rotulo($campo, $rotulo) . ' e obrigatorio.');
        }

        return $this;
    }

    public function minimo(string $campo, int $tamanho, ?string $rotulo = null): static
    {
        $valor = (string) $this->valor($campo);

        if ($valor !== '' && mb_strlen(trim($valor)) < $tamanho) {
            $this->erro($campo, $this->rotulo($campo, $rotulo) . " deve ter pelo menos {$tamanho} caracteres.");
        }

        return $this;
    }

    public function maximo(string $campo, int $tamanho, ?string $rotulo = null): static
    {
        $valor = (string) $this->valor($campo);

        if (mb_strlen(trim($valor)) > $tamanho) {
            $this->erro($campo, $this->rotulo($campo, $rotulo) . " deve ter no maximo {$tamanho} caracteres.");
        }

        return $this;
    }

    public function email(string $campo, ?string $rotulo = null): static
    {
        $valor = (string) $this->valor($campo);

        if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $this->erro($campo, $this->rotulo($campo, $rotulo) . ' deve ser um e-mail valido.');
        }

        return $this;
    }

    public function numerico(string $campo, ?string $rotulo = null): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !is_numeric($valor)) {
            $this->erro($campo, $this->rotulo($campo, $rotulo) . ' deve ser um numero.');
        }

        return $this;
    }

    public function entre(string $campo, float $min, float $max, ?string $rotulo = null): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && is_numeric($valor)) {
            $numero = (float) $valor;

            if ($numero < $min || $numero > $max) {
                $this->erro($campo, $this->rotulo($campo, $rotulo) . " deve estar entre {$min} e {$max}.");
            }
        }

        return $this;
    }

    /**
     * Verifica se o valor esta em uma lista de opcoes aceitas.
     */
    public function dentroDe(string $campo, array $opcoes, ?string $rotulo = null): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !in_array($valor, $opcoes, true)) {
            $this->erro($campo, $this->rotulo($campo, $rotulo) . ' possui um valor invalido.');
        }

        return $this;
    }

    /**
     * Regra livre: o aluno passa a propria condicao.
     */
    public function personalizada(string $campo, bool $condicaoValida, string $mensagem): static
    {
        if (!$condicaoValida) {
            $this->erro($campo, $mensagem);
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Resultado
    // ------------------------------------------------------------------

    public function passou(): bool
    {
        return $this->erros === [];
    }

    public function falhou(): bool
    {
        return !$this->passou();
    }

    /** @return array<string,string> */
    public function erros(): array
    {
        return $this->erros;
    }

    public function erroDe(string $campo): ?string
    {
        return $this->erros[$campo] ?? null;
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    private function valor(string $campo): mixed
    {
        return $this->dados[$campo] ?? null;
    }

    private function erro(string $campo, string $mensagem): void
    {
        // Guarda apenas o primeiro erro de cada campo.
        if (!isset($this->erros[$campo])) {
            $this->erros[$campo] = $mensagem;
        }
    }

    private function rotulo(string $campo, ?string $rotulo): string
    {
        return $rotulo ?? ucfirst(str_replace('_', ' ', $campo));
    }
}
