<?php

namespace Nucleo;

use RuntimeException;

/**
 * Monta as telas (HTML) da aplicacao.
 *
 * A view fica em views/<pasta>/<arquivo>.php e e desenhada "dentro" de um
 * template (views/template/layout.php). O conteudo da view chega no template
 * atraves da variavel $conteudo.
 */
class View
{
    /**
     * Renderiza a view e imprime o resultado.
     *
     * @param string      $view   caminho relativo dentro de views, sem .php. Ex.: 'alunos/index'
     * @param array       $dados  variaveis disponiveis dentro da view
     * @param string|null $layout template a usar, ou null para nao usar nenhum
     */
    public static function renderizar(string $view, array $dados = [], ?string $layout = 'template/layout'): void
    {
        echo self::capturar($view, $dados, $layout);
    }

    /**
     * Igual ao renderizar(), mas devolve o HTML como string em vez de imprimir.
     * Muito util nos testes.
     */
    public static function capturar(string $view, array $dados = [], ?string $layout = 'template/layout'): string
    {
        $conteudo = self::processar(self::caminho($view), $dados);

        if ($layout === null) {
            return $conteudo;
        }

        return self::processar(
            self::caminho($layout),
            array_merge($dados, ['conteudo' => $conteudo])
        );
    }

    /**
     * Inclui um pedaco de tela reaproveitavel (parcial), ex.: template/mensagens.
     */
    public static function parcial(string $view, array $dados = []): string
    {
        return self::processar(self::caminho($view), $dados);
    }

    /**
     * Transforma 'alunos/index' no caminho absoluto do arquivo.
     */
    private static function caminho(string $view): string
    {
        $arquivo = CAMINHO_VIEWS . '/' . trim(str_replace('.', '/', $view), '/') . '.php';

        if (!is_file($arquivo)) {
            throw new RuntimeException("View nao encontrada: {$view} (esperado em {$arquivo})");
        }

        return $arquivo;
    }

    /**
     * Executa o arquivo PHP da view com as variaveis recebidas
     * e captura tudo que ele imprimir.
     */
    private static function processar(string $arquivo, array $dados): string
    {
        // extract() transforma ['nome' => 'Ana'] na variavel $nome dentro da view.
        extract($dados, EXTR_SKIP);

        ob_start();

        try {
            require $arquivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }
}
