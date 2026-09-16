<?php

namespace Nucleo;

/**
 * Perfis de acesso: quem pode fazer o que.
 *
 * ------------------------------------------------------------------------
 * A DIFERENCA ENTRE ESTAR LOGADO E TER PERMISSAO
 * ------------------------------------------------------------------------
 * exigirAutenticacao() responde "quem e voce?". Perfil responde "voce pode?".
 * Sao perguntas diferentes: em um sistema escolar, o aluno e o coordenador
 * estao os dois logados, e so um deles pode apagar uma turma.
 * ------------------------------------------------------------------------
 *
 * A lista de perfis de cada tela de login fica em configuracoes/perfis.php,
 * escrita pelo comando:
 *
 *     php console.php auth:perfis admin,coordenador,professor
 *
 * No controller:
 *
 *     $this->exigirPerfil('admin');                 // so admin
 *     $this->exigirPerfil(['admin', 'coordenador']); // qualquer um dos dois
 *
 * Na view:
 *
 *     <?php if (tem_perfil('admin')): ?>
 *         <a href="...">Area restrita</a>
 *     <?php endif ?>
 *
 * O perfil e lido do banco, nao da sessao. E um acesso a mais por
 * requisicao (guardado em memoria ate o fim dela), e em troca tirar o
 * "admin" de alguem passa a valer na hora — e nao so no proximo login.
 */
class Perfis
{
    /** Coluna que guarda o perfil na tabela do login. */
    public const COLUNA = 'perfil';

    /** Perfil ja lido nesta requisicao, por provider. */
    private static array $lembrados = [];

    /**
     * Perfis configurados para um provider.
     *
     * @return array<string,string> chave => rotulo ('admin' => 'Administrador')
     */
    public static function configurados(?string $provider = null): array
    {
        $configuracao = self::configuracao($provider);

        return is_array($configuracao['perfis'] ?? null) ? $configuracao['perfis'] : [];
    }

    /** As chaves aceitas, para a regra dentroDe() do model. */
    public static function chaves(?string $provider = null): array
    {
        return array_keys(self::configurados($provider));
    }

    /** O provider tem perfis configurados? */
    public static function ativo(?string $provider = null): bool
    {
        return self::configurados($provider) !== [];
    }

    /** Nome do model que guarda as contas desse provider. */
    public static function modelo(?string $provider = null): ?string
    {
        $modelo = self::configuracao($provider)['modelo'] ?? null;

        return is_string($modelo) && $modelo !== '' ? $modelo : null;
    }

    /**
     * Perfil de quem esta logado agora, ou null.
     *
     * Devolve null quando ninguem entrou, quando o provider nao usa perfis
     * ou quando a conta esta sem perfil definido.
     */
    public static function atual(?string $provider = null): ?string
    {
        $provider = Autenticacao::normalizar($provider);

        if (array_key_exists($provider, self::$lembrados)) {
            return self::$lembrados[$provider];
        }

        return self::$lembrados[$provider] = self::buscarNoBanco($provider);
    }

    /**
     * Quem esta logado tem algum destes perfis?
     *
     *     Perfis::tem('admin')
     *     Perfis::tem(['admin', 'coordenador'])
     */
    public static function tem(string|array $perfis, ?string $provider = null): bool
    {
        $atual = self::atual($provider);

        if ($atual === null) {
            return false;
        }

        return in_array($atual, (array) $perfis, true);
    }

    /** Nome legivel de um perfil: 'admin' -> 'Administrador'. */
    public static function rotulo(?string $perfil, ?string $provider = null): string
    {
        if ($perfil === null || $perfil === '') {
            return '';
        }

        return self::configurados($provider)[$perfil] ?? $perfil;
    }

    /**
     * Esquece o perfil lido nesta requisicao.
     *
     * Usado pelos testes, que trocam de usuario logado varias vezes dentro
     * do mesmo processo.
     */
    public static function esquecer(): void
    {
        self::$lembrados = [];
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    /** O bloco de configuracoes/perfis.php referente ao provider. */
    private static function configuracao(?string $provider = null): array
    {
        $provider = Autenticacao::normalizar($provider);
        $todos    = (array) Config::obter('perfis', []);

        return is_array($todos[$provider] ?? null) ? $todos[$provider] : [];
    }

    private static function buscarNoBanco(string $provider): ?string
    {
        if (!self::ativo($provider)) {
            return null;
        }

        $id = \usuario_id($provider);

        if ($id === null || $id === '') {
            return null;
        }

        // Normalmente e so o nome ('Usuario'), mas um nome completo com
        // namespace tambem serve — util para um model fora de modelos/.
        $nome   = (string) self::modelo($provider);
        $classe = str_contains($nome, '\\') ? ltrim($nome, '\\') : 'Modelos\\' . $nome;

        if (!class_exists($classe)) {
            return null;
        }

        $conta = (new $classe())->buscar($id);
        $perfil = $conta[self::COLUNA] ?? null;

        return is_string($perfil) && $perfil !== '' ? $perfil : null;
    }
}
