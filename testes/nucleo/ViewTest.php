<?php

namespace Testes\Nucleo;

use Nucleo\View;
use RuntimeException;
use Testes\Suporte\TesteBase;

/**
 * Testes das VIEWS e das funcoes de atalho usadas dentro delas.
 *
 * Rode so estes:  php testes/executar.php ViewTest
 */
class ViewTest extends TesteBase
{
    // ------------------------------------------------------------------
    // Renderizacao
    // ------------------------------------------------------------------

    public function testeRenderizaViewDentroDoTemplate(): void
    {
        $html = View::capturar('home/sobre', ['titulo' => 'Sobre']);

        $this->assertContem('<!DOCTYPE html>', $html, 'Deve vir dentro do template');
        $this->assertContem('Como funciona o framework', $html);
    }

    public function testeRenderizaViewSemTemplate(): void
    {
        $html = View::capturar('home/sobre', ['titulo' => 'Sobre'], null);

        $this->assertNaoContem('<!DOCTYPE html>', $html, 'Sem layout nao deve ter o HTML da pagina');
        $this->assertContem('Como funciona o framework', $html);
    }

    public function testeViewInexistenteDisparaErroExplicativo(): void
    {
        $this->assertExcecao(RuntimeException::class, function () {
            View::capturar('pasta/que-nao-existe');
        });
    }

    public function testeVariaveisChegamNaView(): void
    {
        $html = View::capturar('erros/404', [
            'titulo'   => 'Teste',
            'mensagem' => 'mensagem-de-teste',
            'rota'     => 'rota-de-teste',
        ]);

        $this->assertContem('rota-de-teste', $html);
    }

    // ------------------------------------------------------------------
    // Funcoes de atalho (helpers)
    // ------------------------------------------------------------------

    public function testeEscapaHtmlContraXss(): void
    {
        $perigoso = '<script>alert("invadido")</script>';

        $seguro = e($perigoso);

        $this->assertNaoContem('<script>', $seguro);
        $this->assertContem('&lt;script&gt;', $seguro);
    }

    public function testeEscapaAspas(): void
    {
        $this->assertContem('&quot;', e('texto com "aspas"'));
    }

    public function testeMontaUrlInterna(): void
    {
        $this->assertContem('/alunos/ver/7', url('alunos/ver/7'));
        $this->assertContem('/alunos', url('/alunos/'));
    }

    public function testeMontaCaminhoDeArquivoEstatico(): void
    {
        $this->assertContem('/views/css/estilo.css', asset('css/estilo.css'));
        $this->assertContem('/views/imagens/logo.png', asset('imagens/logo.png'));
        $this->assertContem('/views/javascripts/app.js', asset('javascripts/app.js'));
    }

    public function testeFormataDataNoPadraoBrasileiro(): void
    {
        $this->assertIgual('12/08/2026', data_br('2026-08-12'));
        $this->assertIgual('12/08/2026 09:30', data_br('2026-08-12 09:30:00', true));
        $this->assertIgual('', data_br(null));
    }

    public function testeFormataNumeroNoPadraoBrasileiro(): void
    {
        $this->assertIgual('7,50', moeda_br(7.5));
        $this->assertIgual('1.234,56', moeda_br(1234.56));
        $this->assertIgual('0,00', moeda_br(null));
    }

    // ------------------------------------------------------------------
    // Seguranca nas telas
    // ------------------------------------------------------------------

    public function testeNomeDeAlunoComHtmlNaoEhExecutado(): void
    {
        $this->limparTabela('alunos');

        (new \Modelos\Aluno())->criar([
            'nome'  => '<script>alert(1)</script>',
            'email' => 'xss@escola.br',
            'curso' => 'Informatica',
        ]);

        $html = $this->requisitar('alunos')->html;

        $this->assertNaoContem('<script>alert(1)</script>', $html, 'A view deve escapar o conteudo com e()');
        $this->assertContem('&lt;script&gt;', $html);
    }
}
