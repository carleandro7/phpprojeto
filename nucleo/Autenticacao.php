<?php

namespace Nucleo;

use RuntimeException;

/**
 * Descobre quais telas de login existem no projeto.
 *
 * Cada tela de login e um "provider", criado por:
 *
 *     php console.php auth:install [Modelo] [Prefixo]
 *
 * O provider padrao (sem prefixo) e representado por uma string vazia e
 * responde em /auth. Um provider chamado "professor" responde em
 * /auth-professor e usa chaves de sessao proprias.
 *
 * Esta classe existe para que exigirAutenticacao() nunca mande o visitante
 * para uma tela de login que nao foi instalada.
 */
class Autenticacao
{
    /** Prefixo do provider padrao (rotas em /auth). */
    public const PADRAO = '';

    /** Tamanho minimo aceito para uma senha em texto puro. */
    public const SENHA_MINIMA = 6;

    /**
     * Prefixos de todos os providers instalados.
     *
     * @return list<string> ex.: ['', 'professor']
     */
    public static function providers(): array
    {
        $prefixos = [];

        foreach (glob(CAMINHO_CONTROLLERS . '/Auth*Controller.php') ?: [] as $arquivo) {
            $nome = basename($arquivo, 'Controller.php');

            $prefixos[] = $nome === 'Auth'
                ? self::PADRAO
                : strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', substr($nome, 4)));
        }

        sort($prefixos);

        return $prefixos;
    }

    /**
     * O texto ja e um hash gerado por password_hash()?
     *
     * Serve para nao aplicar hash duas vezes quando a senha ja vem
     * cifrada (um seed, uma importacao de outro sistema).
     */
    public static function ehHash(mixed $senha): bool
    {
        if (!is_string($senha) || $senha === '') {
            return false;
        }

        return password_get_info($senha)['algoName'] !== 'unknown';
    }

    /** O provider informado ja foi instalado? */
    public static function instalado(?string $provider = null): bool
    {
        return is_file(CAMINHO_CONTROLLERS . '/' . self::controlador($provider) . '.php');
    }

    /**
     * Decide qual provider usar em exigirAutenticacao().
     *
     * Sem argumento usa o padrao (/auth). Se ele nao existir mas houver
     * exatamente um provider instalado, usa esse: assim um projeto que so
     * rodou "auth:install Professor professor" nao redireciona para uma
     * tela /auth/login inexistente.
     */
    public static function resolver(?string $provider = null): string
    {
        $provider = self::normalizar($provider);

        if (self::instalado($provider)) {
            return $provider;
        }

        $instalados = self::providers();

        if ($provider === self::PADRAO && count($instalados) === 1) {
            return $instalados[0];
        }

        if ($instalados === []) {
            throw new RuntimeException(
                'Nenhuma tela de login foi instalada. Rode: php console.php auth:install'
            );
        }

        throw new RuntimeException(sprintf(
            'A tela de login "%s" nao existe. Providers instalados: %s. '
            . 'Rode: php console.php auth:install [Modelo] %s',
            self::rotaBase($provider),
            implode(', ', array_map(fn (string $p): string => self::rotaBase($p), $instalados)),
            $provider === self::PADRAO ? '' : $provider
        ));
    }

    /** Normaliza o prefixo: null/'' viram o provider padrao. */
    public static function normalizar(?string $provider): string
    {
        if ($provider === null || trim($provider) === '') {
            return self::PADRAO;
        }

        return str_replace('-', '_', strtolower(trim($provider)));
    }

    /** '' -> 'auth' | 'professor' -> 'auth-professor' */
    public static function rotaBase(?string $provider = null): string
    {
        $provider = self::normalizar($provider);

        return $provider === self::PADRAO
            ? 'auth'
            : 'auth-' . str_replace('_', '-', $provider);
    }

    public static function rotaLogin(?string $provider = null): string
    {
        return self::rotaBase($provider) . '/login';
    }

    public static function rotaSair(?string $provider = null): string
    {
        return self::rotaBase($provider) . '/sair';
    }

    public static function rotaRegistrar(?string $provider = null): string
    {
        return self::rotaBase($provider) . '/registrar';
    }

    /** '' -> 'AuthController' | 'professor' -> 'AuthProfessorController' */
    public static function controlador(?string $provider = null): string
    {
        $provider = self::normalizar($provider);

        if ($provider === self::PADRAO) {
            return 'AuthController';
        }

        return 'Auth' . str_replace(' ', '', ucwords(str_replace('_', ' ', $provider))) . 'Controller';
    }

    /** Pasta das views: '' -> 'auth' | 'professor' -> 'auth/professor' */
    public static function pastaViews(?string $provider = null): string
    {
        $provider = self::normalizar($provider);

        return $provider === self::PADRAO ? 'auth' : 'auth/' . $provider;
    }

    /**
     * Providers em que o visitante esta logado agora.
     *
     * @return list<string>
     */
    public static function conectados(): array
    {
        return array_values(array_filter(
            self::providers(),
            fn (string $provider): bool => \autenticado($provider)
        ));
    }
}
