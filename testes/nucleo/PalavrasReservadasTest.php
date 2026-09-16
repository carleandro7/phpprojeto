<?php

namespace Testes\Nucleo;

use Nucleo\Database;
use Nucleo\Model;
use Nucleo\Sql;
use Testes\Suporte\TesteBase;

/**
 * Uma tabela com nome reservado pelo MySQL, e colunas idem.
 *
 * "rank", "system", "groups" e "lead" sao palavras reservadas em versoes
 * recentes do MySQL. Nomes assim aparecem naturalmente em qualquer sistema,
 * entao eles nao podem quebrar o framework.
 */
class ModeloReservadoTeste extends Model
{
    protected string $tabela = 'rank';
    protected array $preenchiveis = ['nome', 'system', 'groups', 'lead'];
    protected string $ordemPadrao = 'id';
}

/**
 * Testes das PALAVRAS RESERVADAS do MySQL.
 *
 * A lista cresce a cada versao do banco: "manual" virou reservada no MySQL 9,
 * e antes dela vieram "rank", "system", "groups". Sem crases em volta dos
 * identificadores, um campo chamado "rank" derruba o CREATE TABLE, o INSERT,
 * o UPDATE e o WHERE.
 *
 * Rode so estes:  php testes/executar.php PalavrasReservadasTest
 */
class PalavrasReservadasTest extends TesteBase
{
    private ModeloReservadoTeste $modelo;

    public function preparar(): void
    {
        $this->recriarTabelas([
            'rank' => 'CREATE TABLE `rank` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nome` VARCHAR(255) NULL,
                `system` VARCHAR(255) NULL,
                `groups` INT NULL,
                `lead` VARCHAR(255) NULL
            )',
        ]);

        $this->modelo = new ModeloReservadoTeste();
    }

    public function testeProtegerPoeAsCrases(): void
    {
        $this->assertIgual('`rank`', Sql::proteger('rank'));
        $this->assertIgual('`nome` DESC', Sql::ordenacao('nome DESC'));
        $this->assertIgual('`nome`', Sql::ordenacao('nome'));
    }

    /** As crases nao podem virar uma porta para injecao. */
    public function testeProtegerContinuaRecusandoNomeInvalido(): void
    {
        $this->assertExcecao(\InvalidArgumentException::class, function (): void {
            Sql::proteger('nome`; DROP TABLE alunos; --');
        });

        $this->assertExcecao(\InvalidArgumentException::class, function (): void {
            Sql::proteger('a b');
        });
    }

    public function testeCrudCompletoComNomesReservados(): void
    {
        $id = $this->modelo->criar([
            'nome'   => 'Primeiro',
            'system' => 'alfa',
            'groups' => 3,
            'lead'   => 'Ana',
        ]);

        $this->assertVerdadeiro($id > 0);

        $registro = $this->modelo->buscar($id);

        $this->assertIgual('alfa', $registro['system']);
        $this->assertIgual(3, (int) $registro['groups']);

        $this->assertVerdadeiro($this->modelo->atualizar($id, ['groups' => 9, 'system' => 'beta']));
        $this->assertIgual('beta', $this->modelo->buscar($id)['system']);

        $this->assertIgual(1, $this->modelo->contar());
        $this->assertVerdadeiro($this->modelo->existe($id));

        $this->assertVerdadeiro($this->modelo->excluir($id));
        $this->assertNulo($this->modelo->buscar($id));
    }

    public function testeConsultasPorColunaReservada(): void
    {
        $this->modelo->criar(['nome' => 'A', 'system' => 'alfa', 'groups' => 1, 'lead' => 'Ana']);
        $this->modelo->criar(['nome' => 'B', 'system' => 'beta', 'groups' => 2, 'lead' => 'Bruno']);

        $this->assertTotal(1, $this->modelo->onde('system', 'alfa'));
        $this->assertTotal(1, $this->modelo->onde('groups', 1, '>'));
        $this->assertTotal(2, $this->modelo->onde('lead', '%', 'LIKE'));

        $this->assertIgual('A', $this->modelo->primeiroOnde('lead', 'Ana')['nome']);
    }

    public function testeOrdenacaoPorColunaReservada(): void
    {
        $this->modelo->criar(['nome' => 'A', 'groups' => 2]);
        $this->modelo->criar(['nome' => 'B', 'groups' => 1]);

        $ordenados = $this->modelo->todos('groups ASC');

        $this->assertIgual('B', $ordenados[0]['nome']);
    }

    public function testePaginacaoEmTabelaReservada(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->modelo->criar(['nome' => "Registro {$i}", 'groups' => $i]);
        }

        $pagina = $this->modelo->paginar(2, 3);

        $this->assertTotal(3, $pagina->registros);
        $this->assertIgual(7, $pagina->total);
        $this->assertIgual(3, $pagina->paginas());
    }

    /** O contarConsulta() envolve o SELECT em uma subconsulta. */
    public function testeContagemFiltradaEmTabelaReservada(): void
    {
        $this->modelo->criar(['nome' => 'A', 'system' => 'alfa']);
        $this->modelo->criar(['nome' => 'B', 'system' => 'beta']);

        $total = $this->modelo->contarConsulta(
            'SELECT * FROM `rank` WHERE `system` = ?',
            ['alfa']
        );

        $this->assertIgual(1, $total);
    }

    /** O suporte de testes tambem monta SQL com o nome da tabela. */
    public function testeSuporteDeTestesLidaComTabelaReservada(): void
    {
        $this->modelo->criar(['nome' => 'A']);

        $this->assertIgual(1, $this->contarNaTabela('rank'));

        $this->limparTabela('rank');

        $this->assertIgual(0, $this->contarNaTabela('rank'));

        // Depois do limparTabela() o id volta a contar do 1.
        $this->assertIgual(1, $this->modelo->criar(['nome' => 'Nova']));
    }

    public function testeSemeaduraEmTabelaReservada(): void
    {
        \Nucleo\Semeador::criar('rank', [
            ['nome' => 'Uma', 'system' => 'alfa'],
            ['nome' => 'Outra', 'system' => 'beta'],
        ], 'nome');

        $this->assertIgual(2, $this->contarNaTabela('rank'));

        \Nucleo\Semeador::inventar('rank', 4);

        $this->assertIgual(6, $this->contarNaTabela('rank'));

        \Nucleo\Semeador::limpar(['rank']);

        $this->assertIgual(0, $this->contarNaTabela('rank'));
    }

    /** O DROP/CREATE do recriarTabelas() ja passou por aqui no preparar(). */
    public function testeTabelaFoiRecriadaDeVerdade(): void
    {
        $colunas = [];

        foreach (Database::conexao()->query('SHOW COLUMNS FROM `rank`') as $coluna) {
            $colunas[] = $coluna['Field'];
        }

        $this->assertTemValor('system', $colunas);
        $this->assertTemValor('groups', $colunas);
        $this->assertTemValor('lead', $colunas);
    }
}
