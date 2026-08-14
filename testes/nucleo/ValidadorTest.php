<?php

namespace Testes\Nucleo;

use Nucleo\Validador;
use Testes\Suporte\TesteBase;

/**
 * Testes do VALIDADOR de formularios.
 *
 * Rode so estes:  php testes/executar.php ValidadorTest
 */
class ValidadorTest extends TesteBase
{
    public function testeAceitaDadosCorretos(): void
    {
        $validador = new Validador(['nome' => 'Ana Souza', 'email' => 'ana@escola.br']);

        $validador->obrigatorio('nome')->email('email');

        $this->assertVerdadeiro($validador->passou());
        $this->assertVazio($validador->erros());
    }

    public function testeCampoObrigatorioVazio(): void
    {
        $validador = new Validador(['nome' => '   ']);

        $validador->obrigatorio('nome', 'Nome');

        $this->assertVerdadeiro($validador->falhou());
        $this->assertContem('obrigatorio', $validador->erroDe('nome'));
    }

    public function testeCampoObrigatorioAusente(): void
    {
        $validador = new Validador([]);

        $validador->obrigatorio('nome');

        $this->assertVerdadeiro($validador->falhou());
    }

    public function testeTamanhoMinimo(): void
    {
        $validador = new Validador(['nome' => 'Jo']);
        $validador->minimo('nome', 3);

        $this->assertVerdadeiro($validador->falhou());
        $this->assertContem('pelo menos 3', $validador->erroDe('nome'));
    }

    public function testeTamanhoMaximo(): void
    {
        $validador = new Validador(['nome' => str_repeat('a', 120)]);
        $validador->maximo('nome', 100);

        $this->assertVerdadeiro($validador->falhou());
    }

    public function testeEmailValidoEInvalido(): void
    {
        $bom = new Validador(['email' => 'aluno@escola.br']);
        $bom->email('email');
        $this->assertVerdadeiro($bom->passou());

        $ruim = new Validador(['email' => 'aluno-arroba-escola']);
        $ruim->email('email');
        $this->assertVerdadeiro($ruim->falhou());
    }

    public function testeCampoNumerico(): void
    {
        $bom = new Validador(['nota' => '7.5']);
        $bom->numerico('nota');
        $this->assertVerdadeiro($bom->passou());

        $ruim = new Validador(['nota' => 'dez']);
        $ruim->numerico('nota');
        $this->assertVerdadeiro($ruim->falhou());
    }

    public function testeFaixaDeValores(): void
    {
        $dentro = new Validador(['nota' => 8]);
        $dentro->entre('nota', 0, 10);
        $this->assertVerdadeiro($dentro->passou());

        $fora = new Validador(['nota' => 11]);
        $fora->entre('nota', 0, 10);
        $this->assertVerdadeiro($fora->falhou());
        $this->assertContem('entre 0 e 10', $fora->erroDe('nota'));
    }

    public function testeListaDeOpcoesAceitas(): void
    {
        $validador = new Validador(['curso' => 'Medicina']);
        $validador->dentroDe('curso', ['Informatica', 'Enfermagem']);

        $this->assertVerdadeiro($validador->falhou());
    }

    public function testeRegraPersonalizada(): void
    {
        $idade = 15;

        $validador = new Validador(['idade' => $idade]);
        $validador->personalizada('idade', $idade >= 16, 'O aluno precisa ter 16 anos ou mais.');

        $this->assertVerdadeiro($validador->falhou());
        $this->assertIgual('O aluno precisa ter 16 anos ou mais.', $validador->erroDe('idade'));
    }

    public function testeGuardaApenasOPrimeiroErroDeCadaCampo(): void
    {
        $validador = new Validador(['email' => '']);

        $validador->obrigatorio('email')->email('email')->minimo('email', 5);

        $this->assertTotal(1, $validador->erros(), 'Cada campo mostra so a primeira falha');
        $this->assertContem('obrigatorio', $validador->erroDe('email'));
    }

    public function testeRegrasSaoEncadeaveis(): void
    {
        $validador = new Validador(['nome' => '', 'email' => 'x', 'nota' => 'abc']);

        $validador
            ->obrigatorio('nome')
            ->email('email')
            ->numerico('nota');

        $this->assertTotal(3, $validador->erros());
    }

    public function testeCampoOpcionalVazioNaoGeraErro(): void
    {
        // Sem obrigatorio(), um campo em branco passa pelas demais regras.
        $validador = new Validador(['nota' => '']);

        $validador->numerico('nota')->entre('nota', 0, 10);

        $this->assertVerdadeiro($validador->passou());
    }

    public function testeErroDeCampoSemProblemaEhNulo(): void
    {
        $validador = new Validador(['nome' => 'Ana']);
        $validador->obrigatorio('nome');

        $this->assertNulo($validador->erroDe('nome'));
    }
}
