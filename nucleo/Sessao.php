<?php

namespace Nucleo;

/**
 * Facilita o uso da sessao ($_SESSION) e das "mensagens rapidas" (flash).
 *
 * Mensagem flash e aquela que aparece uma unica vez, tipicamente depois de
 * salvar um formulario: "Aluno cadastrado com sucesso!".
 *
 * Como funciona o ciclo:
 *   1. O controlador chama Sessao::flash('sucesso', '...') e redireciona.
 *   2. Na requisicao seguinte, iniciarRequisicao() retira as mensagens da
 *      sessao e guarda em memoria.
 *   3. A view chama mensagens() e as exibe. Depois disso elas somem.
 */
class Sessao
{
    /** Mensagens que vieram da requisicao anterior. */
    private static array $flashCarregado = [];

    /** Dados de formulario que vieram da requisicao anterior. */
    private static array $entradaAntiga = [];

    /** Erros de validacao que vieram da requisicao anterior. */
    private static array $errosAntigos = [];

    public static function iniciar(): void
    {
        // Na linha de comando (testes) nao existe sessao de verdade:
        // usamos um array simples para o codigo continuar funcionando igual.
        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Chamado pelo App no inicio de cada requisicao: transfere o que ficou
     * guardado na sessao para a memoria desta requisicao.
     */
    public static function iniciarRequisicao(): void
    {
        self::$flashCarregado = $_SESSION['_flash'] ?? [];
        self::$entradaAntiga  = $_SESSION['_entrada'] ?? [];
        self::$errosAntigos   = $_SESSION['_erros'] ?? [];

        unset($_SESSION['_flash'], $_SESSION['_entrada'], $_SESSION['_erros']);
    }

    // ------------------------------------------------------------------
    // Uso geral
    // ------------------------------------------------------------------

    public static function definir(string $chave, mixed $valor): void
    {
        $_SESSION[$chave] = $valor;
    }

    public static function obter(string $chave, mixed $padrao = null): mixed
    {
        return $_SESSION[$chave] ?? $padrao;
    }

    public static function tem(string $chave): bool
    {
        return isset($_SESSION[$chave]);
    }

    public static function remover(string $chave): void
    {
        unset($_SESSION[$chave]);
    }

    public static function limpar(): void
    {
        $_SESSION             = [];
        self::$flashCarregado = [];
        self::$entradaAntiga  = [];
        self::$errosAntigos   = [];
    }

    // ------------------------------------------------------------------
    // Mensagens rapidas (flash)
    // ------------------------------------------------------------------

    /**
     * Guarda uma mensagem para ser exibida na proxima tela.
     *
     * @param string $tipo sucesso | erro | aviso | info
     */
    public static function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['_flash'][] = ['tipo' => $tipo, 'texto' => $mensagem];
    }

    /**
     * Le e apaga as mensagens. Junta as que vieram da requisicao anterior
     * com as que foram criadas agora (caso nao tenha havido redirecionamento).
     */
    public static function lerFlash(): array
    {
        $mensagens = array_merge(self::$flashCarregado, $_SESSION['_flash'] ?? []);

        self::$flashCarregado = [];
        unset($_SESSION['_flash']);

        return $mensagens;
    }

    // ------------------------------------------------------------------
    // Repopular formulario apos erro de validacao
    // ------------------------------------------------------------------

    public static function guardarEntrada(array $dados): void
    {
        $_SESSION['_entrada'] = $dados;
    }

    /**
     * Guarda os erros de validacao para a tela do formulario exibir.
     *
     * @param array<string,string> $erros campo => mensagem
     */
    public static function guardarErros(array $erros): void
    {
        $_SESSION['_erros'] = $erros;
    }

    /**
     * Erros de validacao da requisicao anterior.
     * Pode ser lido varias vezes (uma para cada campo do formulario).
     *
     * @return array<string,string>
     */
    public static function erros(): array
    {
        return self::$errosAntigos;
    }

    /**
     * Valores digitados na requisicao anterior. Pode ser lido varias vezes
     * (uma para cada campo do formulario).
     */
    public static function entradaAntiga(): array
    {
        return self::$entradaAntiga;
    }
}
