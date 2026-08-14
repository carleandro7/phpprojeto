<?php

namespace Nucleo;

/**
 * Guarda as configuracoes da aplicacao.
 *
 * Cada arquivo dentro da pasta "configuracoes" vira uma chave.
 * O arquivo configuracoes/app.php vira o grupo "app", entao para ler o
 * campo "debug" usamos Config::obter('app.debug').
 */
class Config
{
    /** @var array<string,mixed> */
    private static array $itens = [];

    /**
     * Le todos os arquivos .php da pasta de configuracoes.
     */
    public static function carregar(string $diretorio): void
    {
        foreach (glob($diretorio . '/*.php') as $arquivo) {
            self::$itens[basename($arquivo, '.php')] = require $arquivo;
        }
    }

    /**
     * Busca um valor usando "ponto" para navegar: 'banco.mysql.host'.
     */
    public static function obter(string $chave, mixed $padrao = null): mixed
    {
        $atual = self::$itens;

        foreach (explode('.', $chave) as $parte) {
            if (!is_array($atual) || !array_key_exists($parte, $atual)) {
                return $padrao;
            }
            $atual = $atual[$parte];
        }

        return $atual;
    }

    /**
     * Altera (ou cria) um valor em tempo de execucao.
     * Muito usado nos testes para trocar o banco de dados.
     */
    public static function definir(string $chave, mixed $valor): void
    {
        $partes = explode('.', $chave);
        $atual  = &self::$itens;

        foreach ($partes as $parte) {
            if (!isset($atual[$parte]) || !is_array($atual[$parte])) {
                $atual[$parte] = [];
            }
            $atual = &$atual[$parte];
        }

        $atual = $valor;
    }

    /**
     * Devolve tudo que esta carregado (util para depuracao).
     */
    public static function tudo(): array
    {
        return self::$itens;
    }
}
