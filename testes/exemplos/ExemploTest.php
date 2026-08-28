<?php

namespace Testes\Exemplos;

use Testes\Suporte\TesteBase;

/**
 * COMECE POR AQUI.
 *
 * Este arquivo nao testa o sistema: ele mostra como escrever um teste e
 * quais verificacoes estao disponiveis. Use como consulta rapida.
 *
 * Regras:
 *   - o arquivo termina em "Test.php"
 *   - a classe tem o mesmo nome do arquivo e herda de TesteBase
 *   - cada metodo publico que comeca com "teste" e executado
 */
class ExemploTest extends TesteBase
{
    /**
     * Roda ANTES de cada metodo de teste desta classe.
     * Serve para preparar o cenario.
     */
    public function preparar(): void
    {
        // Ex.: $this->limparTabela('produtos');
    }

    /**
     * Roda DEPOIS de cada metodo de teste.
     */
    public function finalizar(): void
    {
        // Ex.: apagar arquivos temporarios.
    }

    // ------------------------------------------------------------------

    public function testeComparaValores(): void
    {
        // Iguais (comparacao solta: 5 == '5')
        $this->assertIgual(4, 2 + 2);
        $this->assertIgual('10', 10);

        // Iguais E do mesmo tipo (5 !== '5')
        $this->assertIdentico(4, 2 + 2);

        // Diferentes
        $this->assertDiferente(5, 2 + 2);
    }

    public function testeVerificaVerdadeiroFalsoNulo(): void
    {
        $this->assertVerdadeiro(10 > 5);
        $this->assertFalso(10 < 5);

        $this->assertNulo(null);
        $this->assertNaoNulo('qualquer coisa');

        $this->assertVazio([]);
        $this->assertVazio('');
        $this->assertNaoVazio('conteudo');
    }

    public function testeVerificaTextos(): void
    {
        $frase = 'Curso Tecnico em Informatica';

        $this->assertContem('Tecnico', $frase);
        $this->assertNaoContem('Medicina', $frase);
    }

    public function testeVerificaArrays(): void
    {
        $notas = ['ana' => 9.5, 'bruno' => 7.0, 'carla' => 8.25];

        $this->assertTotal(3, $notas);
        $this->assertTemChave('ana', $notas);
        $this->assertTemValor(7.0, $notas);
    }

    public function testeVerificaExcecoes(): void
    {
        // Confere que o codigo dispara o erro esperado.
        $this->assertExcecao(\DivisionByZeroError::class, function () {
            $resultado = 10 % 0;
        });
    }

    public function testeMensagemPersonalizada(): void
    {
        $idade = 17;

        // O segundo parametro de cada assert e uma mensagem sua,
        // que aparece quando o teste falha.
        $this->assertVerdadeiro(
            $idade < 18,
            'Um registro pode conter qualquer regra de negocio'
        );
    }
}
