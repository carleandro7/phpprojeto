<?php

namespace Nucleo;

/**
 * Uma pagina de resultados.
 *
 * Listar tudo funciona com trinta registros e trava com trinta mil: o banco
 * devolve tudo, o PHP guarda tudo na memoria e o navegador desenha tudo.
 * Paginar resolve os tres de uma vez, porque o LIMIT corta no banco.
 *
 * Quem cria esta classe e o model:
 *
 *     $pagina = $this->modelo->paginar($this->get('pagina'), 20);
 *
 *     $pagina->registros    os registros desta pagina
 *     $pagina->total        quantos registros existem ao todo
 *     $pagina->paginas()    quantas paginas dao os registros
 *
 * E a view desenha a barra de navegacao com um helper:
 *
 *     <?= paginacao($pagina) ?>
 */
class Paginacao
{
    /** Quantos registros por pagina quando ninguem escolhe. */
    public const PADRAO = 20;

    /**
     * Teto de registros por pagina.
     *
     * Existe porque o tamanho da pagina costuma vir da URL. Sem limite,
     * "?por_pagina=999999" pediria a tabela inteira — justamente o que a
     * paginacao esta aqui para evitar.
     */
    public const MAXIMO = 200;

    /**
     * @param list<array<string,mixed>> $registros registros desta pagina
     * @param int $pagina    numero da pagina atual (comeca em 1)
     * @param int $porPagina quantos registros cabem em uma pagina
     * @param int $total     quantos registros existem ao todo
     */
    public function __construct(
        public readonly array $registros,
        public readonly int $pagina,
        public readonly int $porPagina,
        public readonly int $total
    ) {
    }

    /**
     * Normaliza o numero de pagina que veio da URL.
     *
     * "?pagina=abc", "?pagina=-3" e "?pagina=" viram 1: um numero invalido
     * nao pode virar um OFFSET negativo no SQL.
     */
    public static function numero(mixed $valor): int
    {
        if (!is_scalar($valor) || !ctype_digit(ltrim((string) $valor, '+'))) {
            return 1;
        }

        return max(1, (int) $valor);
    }

    /** Mantem o tamanho da pagina entre 1 e Paginacao::MAXIMO. */
    public static function tamanho(mixed $valor, int $padrao = self::PADRAO): int
    {
        $tamanho = is_scalar($valor) && ctype_digit((string) $valor) ? (int) $valor : $padrao;

        return max(1, min(self::MAXIMO, $tamanho));
    }

    // ------------------------------------------------------------------
    // Contas da barra de navegacao
    // ------------------------------------------------------------------

    /** Quantas paginas os registros dao. Uma lista vazia ainda tem 1 pagina. */
    public function paginas(): int
    {
        return max(1, (int) ceil($this->total / $this->porPagina));
    }

    public function temAnterior(): bool
    {
        return $this->pagina > 1;
    }

    public function temProxima(): bool
    {
        return $this->pagina < $this->paginas();
    }

    public function anterior(): int
    {
        return max(1, $this->pagina - 1);
    }

    public function proxima(): int
    {
        return min($this->paginas(), $this->pagina + 1);
    }

    public function vazia(): bool
    {
        return $this->registros === [];
    }

    /** Posicao do primeiro registro da pagina na contagem geral. */
    public function primeiroDaPagina(): int
    {
        return $this->total === 0 ? 0 : ($this->pagina - 1) * $this->porPagina + 1;
    }

    /** Posicao do ultimo registro da pagina na contagem geral. */
    public function ultimoDaPagina(): int
    {
        return min($this->total, $this->primeiroDaPagina() + count($this->registros) - 1);
    }

    /**
     * Resumo em texto: "21 a 40 de 137".
     */
    public function resumo(): string
    {
        if ($this->total === 0) {
            return 'nenhum registro';
        }

        return $this->primeiroDaPagina() . ' a ' . $this->ultimoDaPagina() . ' de ' . $this->total;
    }

    /**
     * Os numeros que aparecem na barra, em uma janela em volta da pagina atual.
     *
     * Com 500 paginas nao da para desenhar 500 botoes; a barra mostra alguns
     * vizinhos e as setas cuidam do resto.
     *
     * @return list<int>
     */
    public function numeros(int $lados = 2): array
    {
        $ultima  = $this->paginas();
        $inicio  = max(1, $this->pagina - $lados);
        $fim     = min($ultima, $this->pagina + $lados);

        // Perto das pontas, a janela cresce para o outro lado e o tamanho
        // da barra nao fica variando a cada clique.
        $largura = $lados * 2 + 1;

        if ($fim - $inicio + 1 < $largura) {
            if ($inicio === 1) {
                $fim = min($ultima, $inicio + $largura - 1);
            } else {
                $inicio = max(1, $fim - $largura + 1);
            }
        }

        return range($inicio, $fim);
    }
}
