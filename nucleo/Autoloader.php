<?php

namespace Nucleo;

/**
 * Carregador automatico de classes (padrao PSR-4 simplificado).
 *
 * Em vez de dar "require" em cada arquivo de classe, registramos aqui um mapa
 * de "namespace => pasta". Quando o PHP encontra uma classe desconhecida,
 * ele chama a funcao registrada em spl_autoload_register e nos localizamos
 * o arquivo correspondente.
 *
 * Exemplo: a classe Controllers\ProdutosController vira o arquivo
 *          controllers/ProdutosController.php
 */
class Autoloader
{
    /** @var array<string,string> namespace => diretorio */
    private static array $mapa = [];

    /**
     * Registra um namespace e a pasta onde ficam as classes dele.
     */
    public static function adicionar(string $namespace, string $diretorio): void
    {
        self::$mapa[trim($namespace, '\\') . '\\'] = rtrim($diretorio, '/');
    }

    /**
     * Liga o autoloader no PHP.
     */
    public static function registrar(): void
    {
        spl_autoload_register([self::class, 'carregar']);
    }

    /**
     * Recebe o nome completo da classe e tenta incluir o arquivo dela.
     */
    public static function carregar(string $classe): void
    {
        foreach (self::$mapa as $prefixo => $diretorio) {
            if (!str_starts_with($classe, $prefixo)) {
                continue;
            }

            $relativo = substr($classe, strlen($prefixo));
            $arquivo  = $diretorio . '/' . str_replace('\\', '/', $relativo) . '.php';

            if (is_file($arquivo)) {
                require_once $arquivo;
                return;
            }
        }
    }
}
