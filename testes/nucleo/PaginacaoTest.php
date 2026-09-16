<?php

namespace Testes\Nucleo;

use Nucleo\Database;
use Nucleo\Model;
use Nucleo\Paginacao;
use Testes\Suporte\TesteBase;

class ModeloPaginadoTeste extends Model
{
    protected string $tabela = 'paginacao_teste';
    protected array $preenchiveis = ['nome'];
    protected string $ordemPadrao = 'id';
}

/**
 * Testes da PAGINACAO.
 *
 * Rode so estes:  php testes/executar.php PaginacaoTest
 */
class PaginacaoTest extends TesteBase
{
    public function testeContaAsPaginas(): void
    {
        $pagina = new Paginacao([], 1, 20, 47);

        $this->assertIgual(3, $pagina->paginas());
        $this->assertIgual(1, (new Paginacao([], 1, 20, 20))->paginas());
        $this->assertIgual(2, (new Paginacao([], 1, 20, 21))->paginas());
    }

    public function testeListaVaziaTemUmaPagina(): void
    {
        $pagina = new Paginacao([], 1, 20, 0);

        $this->assertIgual(1, $pagina->paginas());
        $this->assertVerdadeiro($pagina->vazia());
        $this->assertFalso($pagina->temAnterior());
        $this->assertFalso($pagina->temProxima());
        $this->assertIgual('nenhum registro', $pagina->resumo());
    }

    public function testeSabeOndeEstaNaContagem(): void
    {
        $registros = array_fill(0, 20, ['id' => 1]);
        $pagina    = new Paginacao($registros, 2, 20, 47);

        $this->assertIgual(21, $pagina->primeiroDaPagina());
        $this->assertIgual(40, $pagina->ultimoDaPagina());
        $this->assertIgual('21 a 40 de 47', $pagina->resumo());

        $this->assertVerdadeiro($pagina->temAnterior());
        $this->assertVerdadeiro($pagina->temProxima());
        $this->assertIgual(1, $pagina->anterior());
        $this->assertIgual(3, $pagina->proxima());
    }

    /** A ultima pagina costuma vir incompleta. */
    public function testeUltimaPaginaIncompleta(): void
    {
        $pagina = new Paginacao(array_fill(0, 7, ['id' => 1]), 3, 20, 47);

        $this->assertIgual(41, $pagina->primeiroDaPagina());
        $this->assertIgual(47, $pagina->ultimoDaPagina());
        $this->assertFalso($pagina->temProxima());
        $this->assertIgual(3, $pagina->proxima());
    }

    public function testeNumeroDaPaginaVindoDaUrl(): void
    {
        $this->assertIgual(5, Paginacao::numero('5'));
        $this->assertIgual(1, Paginacao::numero('abc'));
        $this->assertIgual(1, Paginacao::numero('-3'));
        $this->assertIgual(1, Paginacao::numero(''));
        $this->assertIgual(1, Paginacao::numero(null));
        $this->assertIgual(1, Paginacao::numero(['7']));
    }

    public function testeTamanhoDaPaginaTemTeto(): void
    {
        $this->assertIgual(Paginacao::PADRAO, Paginacao::tamanho(null));
        $this->assertIgual(15, Paginacao::tamanho(15));
        $this->assertIgual(1, Paginacao::tamanho(0));
        $this->assertIgual(Paginacao::MAXIMO, Paginacao::tamanho(999999));
    }

    /** A barra mostra uma janela em volta da pagina atual, nao 500 botoes. */
    public function testeJanelaDeNumeros(): void
    {
        $meio = new Paginacao([], 10, 10, 1000);
        $this->assertIgual([8, 9, 10, 11, 12], $meio->numeros());

        $comeco = new Paginacao([], 1, 10, 1000);
        $this->assertIgual([1, 2, 3, 4, 5], $comeco->numeros());

        $fim = new Paginacao([], 100, 10, 1000);
        $this->assertIgual([96, 97, 98, 99, 100], $fim->numeros());

        $curta = new Paginacao([], 1, 10, 25);
        $this->assertIgual([1, 2, 3], $curta->numeros());
    }

    // ------------------------------------------------------------------
    // Integracao com o banco
    // ------------------------------------------------------------------

    public function preparar(): void
    {
        $this->recriarTabelas([
            'paginacao_teste' => 'CREATE TABLE paginacao_teste (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NULL
            )',
        ]);

        $valores = [];

        for ($i = 1; $i <= 47; $i++) {
            $valores[] = "('Registro {$i}')";
        }

        Database::conexao()->exec(
            'INSERT INTO paginacao_teste (nome) VALUES ' . implode(', ', $valores)
        );
    }

    public function testeModeloTrazSoUmaPagina(): void
    {
        $modelo = new ModeloPaginadoTeste();
        $pagina = $modelo->paginar(2, 20);

        $this->assertTotal(20, $pagina->registros);
        $this->assertIgual(47, $pagina->total);
        $this->assertIgual(3, $pagina->paginas());
        $this->assertIgual('Registro 21', $pagina->registros[0]['nome']);
    }

    /** Pedir uma pagina que nao existe devolve a ultima, nao uma tela vazia. */
    public function testePaginaAlemDoFimCaiNaUltima(): void
    {
        $pagina = (new ModeloPaginadoTeste())->paginar(99, 20);

        $this->assertIgual(3, $pagina->pagina);
        $this->assertTotal(7, $pagina->registros);
    }

    /** Com filtro, o total conta o resultado do filtro — nao a tabela toda. */
    public function testeTotalRespeitaOFiltro(): void
    {
        $modelo = new ModeloPaginadoTeste();

        $pagina = $modelo->paginarConsulta(
            'SELECT * FROM paginacao_teste WHERE nome LIKE ? ORDER BY id',
            ['Registro 1%'],
            1,
            5
        );

        // Registro 1 e de 10 a 19: onze no total.
        $this->assertIgual(11, $pagina->total);
        $this->assertIgual(3, $pagina->paginas());
        $this->assertTotal(5, $pagina->registros);
    }

    public function testeTamanhoDaPaginaNaoEstoura(): void
    {
        $pagina = (new ModeloPaginadoTeste())->paginar(1, 999999);

        $this->assertIgual(Paginacao::MAXIMO, $pagina->porPagina);
        $this->assertTotal(47, $pagina->registros);
    }
}
