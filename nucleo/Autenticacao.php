<?php

namespace Nucleo;

/**
 * Guarda quem esta logado na sessao.
 *
 * ------------------------------------------------------------------------
 * QUAL E O PROBLEMA QUE ESTA CLASSE RESOLVE?
 * ------------------------------------------------------------------------
 * O HTTP nao tem memoria: cada clique do navegador chega ao servidor como um
 * pedido novo, sem nenhuma lembranca do anterior. Se o aluno acertou a senha
 * na tela de login, na proxima pagina o servidor ja nao sabe mais quem ele e.
 *
 * A SESSAO e o que costura os pedidos: o PHP guarda um arquivo no servidor e
 * manda ao navegador so um cracha (o cookie PHPSESSID). A cada pedido o
 * navegador devolve o cracha e o PHP reabre o arquivo certo.
 *
 * Entao "estar logado" e simplesmente: existe um aluno anotado nessa sessao.
 * Nada mais. Sair do sistema e apagar a anotacao.
 *
 * ------------------------------------------------------------------------
 * POR QUE UMA CLASSE SO PARA ISSO?
 * ------------------------------------------------------------------------
 * Porque a chave usada em $_SESSION vira uma combinacao espalhada pelo
 * sistema. Deixando tudo aqui, o controller, o cabecalho e as views nao
 * precisam saber onde nem como o dado esta guardado — perguntam a esta classe.
 *
 * Uso:
 *     Autenticacao::entrar($aluno);     // depois de conferir a senha
 *     Autenticacao::verificar();        // true/false
 *     Autenticacao::aluno()['nome'];    // quem esta logado
 *     Autenticacao::sair();
 */
class Autenticacao
{
    /** Chave usada dentro de $_SESSION. */
    private const CHAVE = '_aluno';

    /**
     * Marca o aluno como logado.
     *
     * Recebe o registro devolvido por Modelos\Aluno::autenticar() — ou seja,
     * so chame este metodo DEPOIS de a senha ter sido conferida. Ele nao
     * confere nada: quem confere e o modelo.
     */
    public static function entrar(array $aluno): void
    {
        // Trocar o numero da sessao no momento do login fecha a porta ao
        // "session fixation": o golpe em que alguem planta um PHPSESSID
        // conhecido no navegador da vitima e, quando ela faz login, passa a
        // ter uma sessao ja autenticada nas maos.
        self::renovarIdDaSessao();

        // Guardamos so o minimo necessario para o cabecalho e para as
        // checagens. A nota, o curso e o resto continuam onde devem estar:
        // no banco, buscados pelo modelo quando alguem precisar deles.
        Sessao::definir(self::CHAVE, [
            'id'    => (int) $aluno['id'],
            'nome'  => (string) $aluno['nome'],
            'email' => (string) $aluno['email'],
        ]);
    }

    /**
     * Encerra a sessao do aluno.
     */
    public static function sair(): void
    {
        Sessao::remover(self::CHAVE);

        // Numero novo tambem na saida: se o cracha antigo vazou, ele ja nao
        // serve para nada.
        self::renovarIdDaSessao();
    }

    /**
     * Tem alguem logado?
     */
    public static function verificar(): bool
    {
        return self::aluno() !== null;
    }

    /**
     * Dados de quem esta logado (id, nome, email) ou null.
     *
     * @return array{id:int,nome:string,email:string}|null
     */
    public static function aluno(): ?array
    {
        $aluno = Sessao::obter(self::CHAVE);

        return is_array($aluno) ? $aluno : null;
    }

    /**
     * Id de quem esta logado, ou null.
     */
    public static function id(): ?int
    {
        return self::aluno()['id'] ?? null;
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    /**
     * Gera um numero novo para a sessao, mantendo o conteudo dela.
     *
     * Na linha de comando (testes) nao existe sessao de verdade — o
     * Nucleo\Sessao usa um array simples — entao nao ha o que renovar.
     */
    private static function renovarIdDaSessao(): void
    {
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_regenerate_id(true);
    }
}
