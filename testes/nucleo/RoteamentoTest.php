<?php

namespace Testes\Nucleo;

use Nucleo\App;
use Testes\Suporte\TesteBase;

/**
 * Testes do ROTEADOR: garantem que a URL vira o controlador e o metodo certos.
 *
 * Rode so estes:  php testes/executar.php Nucleo\RoteamentoTest
 */
class RoteamentoTest extends TesteBase
{
    private App $app;

    public function preparar(): void
    {
        $this->app = new App();
    }

    // ------------------------------------------------------------------
    // Quebra da URL
    // ------------------------------------------------------------------

    public function testeUrlVaziaUsaHomeIndex(): void
    {
        [$controlador, $metodo, $parametros] = $this->app->analisar('');

        $this->assertIgual('Home', $controlador);
        $this->assertIgual('index', $metodo);
        $this->assertVazio($parametros);
    }

    public function testeUrlComApenasOControladorUsaIndex(): void
    {
        [$controlador, $metodo] = $this->app->analisar('produtos');

        $this->assertIgual('Produtos', $controlador);
        $this->assertIgual('index', $metodo);
    }

    public function testeUrlComControladorEMetodo(): void
    {
        [$controlador, $metodo] = $this->app->analisar('produtos/criar');

        $this->assertIgual('Produtos', $controlador);
        $this->assertIgual('criar', $metodo);
    }

    public function testeUrlComParametros(): void
    {
        [$controlador, $metodo, $parametros] = $this->app->analisar('produtos/ver/7');

        $this->assertIgual('Produtos', $controlador);
        $this->assertIgual('ver', $metodo);
        $this->assertTotal(1, $parametros);
        $this->assertIgual('7', $parametros[0]);
    }

    public function testeUrlComVariosParametros(): void
    {
        [, , $parametros] = $this->app->analisar('relatorio/notas/2026/1');

        $this->assertTotal(2, $parametros);
        $this->assertIgual('2026', $parametros[0]);
        $this->assertIgual('1', $parametros[1]);
    }

    public function testeIgnoraBarrasSobrandoNaUrl(): void
    {
        [$controlador, $metodo] = $this->app->analisar('///produtos//criar//');

        $this->assertIgual('Produtos', $controlador);
        $this->assertIgual('criar', $metodo);
    }

    public function testeConverteHifenParaOPadraoDeNomes(): void
    {
        // /nota-final/media-geral  ->  NotaFinalController::mediaGeral()
        [$controlador, $metodo] = $this->app->analisar('nota-final/media-geral');

        $this->assertIgual('NotaFinal', $controlador);
        $this->assertIgual('mediaGeral', $metodo);
    }

    public function testeRemoveCaracteresPerigososDoNome(): void
    {
        [$controlador] = $this->app->analisar('../../etc/passwd');

        $this->assertNaoContem('.', $controlador);
        $this->assertNaoContem('/', $controlador);
    }

    // ------------------------------------------------------------------
    // Execucao das rotas
    // ------------------------------------------------------------------

    public function testeRaizAbreAPaginaInicial(): void
    {
        $resposta = $this->requisitar('');

        $this->assertIgual(200, $resposta->status);
        $this->assertContem('Bem-vindo ao framework MVC', $resposta->html);
    }

    public function testeControladorInexistenteRetorna404(): void
    {
        $resposta = $this->requisitar('pagina-que-nao-existe');

        $this->assertIgual(404, $resposta->status);
    }

    public function testeMetodoInexistenteRetorna404(): void
    {
        $this->assertIgual(404, $this->requisitar('produtos/metodo-inventado')->status);
    }

    public function testeMetodoQueExigeParametroSemReceberRetorna404(): void
    {
        // ver() exige o id; sem ele a rota nao pode ser executada.
        $this->assertIgual(404, $this->requisitar('produtos/ver')->status);
    }

    public function testeMetodoPrivadoNaoViraRota(): void
    {
        // HomeController nao tem metodos privados expostos; ja o construtor
        // (__construct) jamais pode ser chamado pela URL.
        $this->assertIgual(404, $this->requisitar('produtos/__construct')->status);
    }

    // ------------------------------------------------------------------
    // Template
    // ------------------------------------------------------------------

    public function testeTodaPaginaVemDentroDoTemplate(): void
    {
        $html = $this->requisitar('')->html;

        $this->assertContem('<!DOCTYPE html>', $html);
        $this->assertContem('<html lang="pt-BR">', $html);
        $this->assertContem('css/estilo.css', $html, 'O CSS do template deve ser carregado');
        $this->assertContem('javascripts/app.js', $html, 'O JS do template deve ser carregado');
        $this->assertContem('</html>', $html);
    }

    public function testeTituloDaPaginaVemDoControlador(): void
    {
        $html = $this->requisitar('home')->html;

        $this->assertContem('<title>Inicio |', $html);
    }
}
