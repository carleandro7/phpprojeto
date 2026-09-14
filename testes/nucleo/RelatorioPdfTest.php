<?php

namespace Testes\Nucleo;

use Nucleo\RelatorioPdf;
use Testes\Suporte\TesteBase;

/**
 * Testes do gerador de relatorios em PDF.
 *
 * Rode so estes:  php testes/executar.php RelatorioPdfTest
 */
class RelatorioPdfTest extends TesteBase
{
    private function gerar(): string
    {
        return RelatorioPdf::conteudo('Relatorio de livros', ['id', 'nome'], [
            ['id' => 1, 'nome' => 'Dom Casmurro'],
        ]);
    }

    public function testeCelulasUsamQuebraDeLinhaDeVerdade(): void
    {
        $pdf = $this->gerar();

        // Um '\n' literal (barra + n) quebra os comandos Tf/Tm e o PDF abre em branco.
        $this->assertNaoContem('\n', $pdf, 'O conteudo do PDF nao pode ter "\n" literal');
        $this->assertContem("/F1 8 Tf\n1 0 0 1 ", $pdf);
        $this->assertContem(" Tm\n(Dom Casmurro) Tj", $pdf);
    }

    public function testeTextoDasCelulasEhPintadoDePreto(): void
    {
        $pdf = $this->gerar();

        // Sem voltar para o preto, o texto herda a cor clara do fundo da linha.
        $this->assertContem("BT\n0 0 0 rg\n/F1 8 Tf\n", $pdf);
    }
}
