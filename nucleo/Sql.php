<?php

namespace Nucleo;

use InvalidArgumentException;

/**
 * Protecoes contra SQL Injection.
 *
 * ------------------------------------------------------------------------
 * A REGRA DE OURO
 * ------------------------------------------------------------------------
 * Existem DOIS tipos de coisa que entram em um comando SQL:
 *
 *   1. VALORES  (o nome digitado, a nota, o id...)
 *      -> NUNCA entram no texto do SQL. Vao sempre como parametro:
 *
 *           $this->consultar('SELECT * FROM alunos WHERE nome = ?', [$nome]);
 *
 *         O PDO envia o comando e os valores separadamente, entao nao
 *         importa o que o usuario digitou: aquilo sera tratado como texto,
 *         nunca como comando.
 *
 *   2. IDENTIFICADORES  (nome de tabela, de coluna, ASC/DESC, operadores)
 *      -> Nao podem ser parametro do PDO; a linguagem SQL nao permite.
 *         Por isso eles passam por esta classe, que so aceita nomes com
 *         letras, numeros e "_", e operadores de uma lista fechada.
 *
 * Se voce seguir essas duas regras, o sistema esta protegido.
 * ------------------------------------------------------------------------
 */
class Sql
{
    /** Operadores aceitos em comparacoes (lista fechada). */
    public const OPERADORES = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    /**
     * Valida o nome de uma tabela ou coluna.
     *
     * Aceita: nome, nome_completo, alunos2
     * Recusa: "nome; DROP TABLE alunos", "nome--", "a b", "alunos.nome"
     */
    public static function identificador(string $nome, string $tipo = 'identificador'): string
    {
        $nome = trim($nome);

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $nome)) {
            throw new InvalidArgumentException(
                "Nome de {$tipo} invalido: \"{$nome}\". "
                . 'Use apenas letras, numeros e underline. '
                . 'Lembre-se: VALORES devem ir como parametro (?), nunca dentro do SQL.'
            );
        }

        return $nome;
    }

    /**
     * Valida uma clausula de ordenacao: "nome", "nota DESC", "criado_em ASC".
     */
    public static function ordenacao(string $ordem): string
    {
        $ordem = trim(preg_replace('/\s+/', ' ', $ordem));

        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]{0,63})( (ASC|DESC))?$/i', $ordem, $partes)) {
            throw new InvalidArgumentException(
                "Ordenacao invalida: \"{$ordem}\". Use por exemplo: 'nome' ou 'nota DESC'."
            );
        }

        $coluna  = $partes[1];
        $direcao = isset($partes[3]) ? ' ' . strtoupper($partes[3]) : '';

        return $coluna . $direcao;
    }

    /**
     * Valida um operador de comparacao.
     */
    public static function operador(string $operador): string
    {
        $operador = strtoupper(trim(preg_replace('/\s+/', ' ', $operador)));

        if (!in_array($operador, self::OPERADORES, true)) {
            throw new InvalidArgumentException(
                "Operador invalido: \"{$operador}\". Permitidos: " . implode(' ', self::OPERADORES)
            );
        }

        return $operador;
    }

    /**
     * Monta a lista de marcadores "?" para um IN (...).
     *
     *     $marcadores = Sql::marcadores($ids);            // "?, ?, ?"
     *     $sql = "SELECT * FROM alunos WHERE id IN ({$marcadores})";
     *     $this->consultar($sql, $ids);
     */
    public static function marcadores(array $valores): string
    {
        if ($valores === []) {
            throw new InvalidArgumentException('A lista de valores para IN (...) esta vazia.');
        }

        return implode(', ', array_fill(0, count($valores), '?'));
    }

    /**
     * Caractere de escape usado nas buscas com LIKE.
     *
     * Nao usamos a barra invertida de proposito: no MySQL ela ja e especial
     * dentro de textos e quebraria o comando. O "!" funciona igual em
     * SQLite e MySQL.
     */
    public const CARACTERE_ESCAPE = '!';

    /** Trecho pronto para colar no SQL: ESCAPE '!' */
    public const ESCAPE_LIKE = "'" . self::CARACTERE_ESCAPE . "'";

    /**
     * Prepara um texto para busca com LIKE, neutralizando os curingas
     * % e _ que o usuario possa ter digitado.
     *
     * Sem isso, quem pesquisasse por "%" veria a tabela inteira.
     *
     *     $sql = 'SELECT * FROM alunos WHERE nome LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE;
     *     $this->consultar($sql, [Sql::comoLike($termo)]);
     *
     * @param string $posicao 'ambos' (padrao), 'inicio' ou 'fim'
     */
    public static function comoLike(string $termo, string $posicao = 'ambos'): string
    {
        $escape = self::CARACTERE_ESCAPE;

        // O proprio caractere de escape precisa ser escapado primeiro.
        $termo = str_replace(
            [$escape, '%', '_'],
            [$escape . $escape, $escape . '%', $escape . '_'],
            $termo
        );

        return match ($posicao) {
            'inicio' => $termo . '%',
            'fim'    => '%' . $termo,
            default  => '%' . $termo . '%',
        };
    }
}
