<?php

namespace Nucleo;

use ReflectionMethod;
use Throwable;

/**
 * O "coracao" do framework: recebe a URL, descobre qual controlador e qual
 * metodo devem responder e executa.
 *
 * Padrao de rota (roteamento por convencao):
 *
 *     /                          -> HomeController::index()
 *     /alunos                    -> AlunosController::index()
 *     /alunos/ver/7              -> AlunosController::ver(7)
 *     /alunos/editar/7           -> AlunosController::editar(7)
 *
 * Ou seja: /controlador/metodo/parametro1/parametro2...
 */
class App
{
    /**
     * Executa a aplicacao e imprime o resultado na tela.
     * E o que o index.php chama.
     */
    public function executar(?string $url = null): void
    {
        try {
            echo $this->despachar($url);
        } catch (RedirecionamentoException $e) {
            $this->enviarRedirecionamento($e);
        } catch (NaoEncontradoException $e) {
            http_response_code(404);
            echo View::capturar('erros/404', [
                'titulo'   => 'Pagina nao encontrada',
                'mensagem' => $e->getMessage(),
                'rota'     => $this->urlAtual($url),
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo View::capturar('erros/erro', [
                'titulo' => 'Erro na aplicacao',
                'erro'   => $e,
                'debug'  => (bool) Config::obter('app.debug', false),
            ]);
        }
    }

    /**
     * Faz o trabalho de verdade e DEVOLVE o HTML (nao imprime).
     * Os testes chamam este metodo para inspecionar a resposta.
     */
    public function despachar(?string $url = null): string
    {
        // Traz para a memoria as mensagens/dados deixados pela requisicao anterior.
        Sessao::iniciarRequisicao();

        [$controlador, $metodo, $parametros] = $this->analisar($this->urlAtual($url));

        $classe = 'Controllers\\' . $controlador . 'Controller';

        if (!class_exists($classe)) {
            throw new NaoEncontradoException("Controlador nao encontrado: {$classe}");
        }

        if (!method_exists($classe, $metodo)) {
            throw new NaoEncontradoException("Metodo nao encontrado: {$classe}::{$metodo}()");
        }

        $reflexao = new ReflectionMethod($classe, $metodo);

        // So metodos publicos viram rota — metodos de apoio ficam protegidos.
        if (!$reflexao->isPublic() || $reflexao->isStatic() || str_starts_with($metodo, '__')) {
            throw new NaoEncontradoException("Metodo nao acessivel: {$classe}::{$metodo}()");
        }

        // Confere se a quantidade de parametros da URL atende a assinatura.
        if (count($parametros) < $reflexao->getNumberOfRequiredParameters()) {
            throw new NaoEncontradoException(
                "Faltam parametros para {$classe}::{$metodo}()"
            );
        }

        $instancia = new $classe();

        ob_start();

        try {
            $reflexao->invokeArgs($instancia, $parametros);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * Quebra a URL em controlador, metodo e parametros.
     *
     * @return array{0:string,1:string,2:array<int,string>}
     */
    public function analisar(string $url): array
    {
        $partes = array_values(array_filter(
            explode('/', trim($url, '/')),
            fn ($parte) => $parte !== ''
        ));

        $controlador = $partes ? array_shift($partes) : Config::obter('app.controlador_padrao', 'home');
        $metodo      = $partes ? array_shift($partes) : Config::obter('app.metodo_padrao', 'index');

        return [
            $this->normalizarNome($controlador),
            $this->normalizarMetodo($metodo),
            array_map('urldecode', $partes),
        ];
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    private function urlAtual(?string $url): string
    {
        return $url ?? (string) ($_GET['url'] ?? '');
    }

    /**
     * 'alunos'     -> 'Alunos'
     * 'nota-final' -> 'NotaFinal'
     */
    private function normalizarNome(string $nome): string
    {
        $nome  = preg_replace('/[^A-Za-z0-9_\-]/', '', $nome);
        $nome  = str_replace('-', ' ', strtolower($nome));

        return str_replace(' ', '', ucwords($nome));
    }

    /**
     * 'ver'        -> 'ver'
     * 'nota-final' -> 'notaFinal'
     */
    private function normalizarMetodo(string $metodo): string
    {
        $metodo = preg_replace('/[^A-Za-z0-9_\-]/', '', $metodo);
        $partes = explode('-', strtolower($metodo));
        $nome   = array_shift($partes);

        foreach ($partes as $parte) {
            $nome .= ucfirst($parte);
        }

        return $nome;
    }

    private function enviarRedirecionamento(RedirecionamentoException $e): void
    {
        if (!headers_sent()) {
            header('Location: ' . $e->destino, true, $e->status);
        }

        // Fallback caso o cabecalho ja tenha sido enviado.
        echo '<p>Redirecionando para <a href="' . e($e->destino) . '">' . e($e->destino) . '</a></p>';
    }
}
