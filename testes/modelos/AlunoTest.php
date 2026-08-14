<?php

namespace Testes\Modelos;

use Testes\Suporte\TesteBase;

use InvalidArgumentException;
use Modelos\Aluno;

/**
 * Testes do MODELO: verificam o CRUD herdado de Nucleo\Model
 * e as consultas proprias de Modelos\Aluno.
 *
 * Rode so estes:  php testes/executar.php Modelos
 */
class AlunoTest extends TesteBase
{
    private Aluno $alunos;

    /**
     * Antes de cada teste: tabela limpa e modelo novo.
     * Assim um teste nunca interfere no outro.
     */
    public function preparar(): void
    {
        $this->limparTabela('alunos');
        $this->alunos = new Aluno();
    }

    // ------------------------------------------------------------------
    // CREATE
    // ------------------------------------------------------------------

    public function testeCriaAlunoEDevolveOId(): void
    {
        $id = $this->alunos->criar([
            'nome'  => 'Ana Souza',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
            'nota'  => 9.5,
        ]);

        $this->assertVerdadeiro($id > 0, 'O metodo criar() deve devolver o id gerado');
        $this->assertIgual(1, $this->contarNaTabela('alunos'));
    }

    public function testeIgnoraCamposQueNaoSaoPreenchiveis(): void
    {
        // "id" e "campo_inventado" nao estao em $preenchiveis: devem ser descartados.
        $id = $this->alunos->criar([
            'nome'            => 'Bruno Lima',
            'email'           => 'bruno@escola.br',
            'curso'           => 'Informatica',
            'nota'            => 7,
            'id'              => 999,
            'campo_inventado' => 'xxx',
        ]);

        $this->assertDiferente(999, $id, 'O id enviado pelo formulario nao pode ser aceito');

        $aluno = $this->alunos->buscar($id);
        $this->assertNaoNulo($aluno);
        $this->assertFalso(array_key_exists('campo_inventado', $aluno));
    }

    // ------------------------------------------------------------------
    // READ
    // ------------------------------------------------------------------

    public function testeBuscaAlunoPeloId(): void
    {
        $id = $this->criarAlunoDeTeste('Carla Menezes', 'carla@escola.br');

        $aluno = $this->alunos->buscar($id);

        $this->assertNaoNulo($aluno);
        $this->assertIgual('Carla Menezes', $aluno['nome']);
        $this->assertIgual('carla@escola.br', $aluno['email']);
    }

    public function testeBuscaDevolveNuloQuandoNaoExiste(): void
    {
        $this->assertNulo($this->alunos->buscar(12345));
    }

    public function testeListaTodosOsAlunosEmOrdemAlfabetica(): void
    {
        $this->criarAlunoDeTeste('Zuleica Dias', 'zuleica@escola.br');
        $this->criarAlunoDeTeste('Ana Souza', 'ana@escola.br');
        $this->criarAlunoDeTeste('Marcos Reis', 'marcos@escola.br');

        $lista = $this->alunos->todos();

        $this->assertTotal(3, $lista);
        $this->assertIgual('Ana Souza', $lista[0]['nome'], 'A ordem padrao do modelo e nome ASC');
        $this->assertIgual('Zuleica Dias', $lista[2]['nome']);
    }

    public function testeFiltraPorColuna(): void
    {
        $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica');
        $this->criarAlunoDeTeste('Bruno', 'bruno@escola.br', 'Informatica');
        $this->criarAlunoDeTeste('Carla', 'carla@escola.br', 'Enfermagem');

        $this->assertTotal(2, $this->alunos->onde('curso', 'Informatica'));
        $this->assertTotal(1, $this->alunos->onde('curso', 'Enfermagem'));
        $this->assertTotal(0, $this->alunos->onde('curso', 'Direito'));
    }

    public function testeContaRegistros(): void
    {
        $this->assertIgual(0, $this->alunos->contar());

        $this->criarAlunoDeTeste('Ana', 'ana@escola.br');
        $this->criarAlunoDeTeste('Bruno', 'bruno@escola.br');

        $this->assertIgual(2, $this->alunos->contar());
    }

    // ------------------------------------------------------------------
    // UPDATE
    // ------------------------------------------------------------------

    public function testeAtualizaOsDadosDoAluno(): void
    {
        $id = $this->criarAlunoDeTeste('Nome Antigo', 'antigo@escola.br');

        $mudou = $this->alunos->atualizar($id, [
            'nome' => 'Nome Novo',
            'nota' => 10,
        ]);

        $this->assertVerdadeiro($mudou);

        $aluno = $this->alunos->buscar($id);
        $this->assertIgual('Nome Novo', $aluno['nome']);
        $this->assertIgual(10, $aluno['nota']);
        $this->assertIgual('antigo@escola.br', $aluno['email'], 'O e-mail nao foi enviado, deve continuar igual');
    }

    // ------------------------------------------------------------------
    // DELETE
    // ------------------------------------------------------------------

    public function testeExcluiAluno(): void
    {
        $id = $this->criarAlunoDeTeste('Para Excluir', 'excluir@escola.br');

        $this->assertVerdadeiro($this->alunos->excluir($id));
        $this->assertIgual(0, $this->contarNaTabela('alunos'));
        $this->assertNulo($this->alunos->buscar($id));
    }

    public function testeExcluirIdInexistenteDevolveFalso(): void
    {
        $this->assertFalso($this->alunos->excluir(9999));
    }

    // ------------------------------------------------------------------
    // Validacao
    // ------------------------------------------------------------------

    public function testeValidacaoAceitaDadosCorretos(): void
    {
        $erros = $this->alunos->validar([
            'nome'  => 'Ana Souza',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
            'nota'  => 8,
        ]);

        $this->assertVazio($erros, 'Dados corretos nao deveriam gerar erros');
    }

    public function testeValidacaoRecusaCamposObrigatoriosVazios(): void
    {
        $erros = $this->alunos->validar(['nome' => '', 'email' => '', 'curso' => '']);

        $this->assertTemChave('nome', $erros);
        $this->assertTemChave('email', $erros);
        $this->assertTemChave('curso', $erros);
    }

    public function testeValidacaoRecusaEmailInvalido(): void
    {
        $erros = $this->alunos->validar([
            'nome'  => 'Ana Souza',
            'email' => 'isso-nao-e-email',
            'curso' => 'Informatica',
        ]);

        $this->assertTemChave('email', $erros);
    }

    public function testeValidacaoRecusaNotaForaDaFaixa(): void
    {
        $erros = $this->alunos->validar([
            'nome'  => 'Ana Souza',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
            'nota'  => 15,
        ]);

        $this->assertTemChave('nota', $erros, 'Nota 15 deveria ser recusada (faixa 0 a 10)');
    }

    public function testeValidacaoRecusaEmailRepetido(): void
    {
        $this->criarAlunoDeTeste('Ana', 'ana@escola.br');

        $erros = $this->alunos->validar([
            'nome'  => 'Outra Ana',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
        ]);

        $this->assertTemChave('email', $erros);
        $this->assertContem('ja esta cadastrado', $erros['email']);
    }

    public function testeValidacaoPermiteManterOProprioEmailNaEdicao(): void
    {
        $id = $this->criarAlunoDeTeste('Ana', 'ana@escola.br');

        // Ao editar, o proprio registro nao pode ser acusado de e-mail duplicado.
        $erros = $this->alunos->validar([
            'nome'  => 'Ana Maria',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
        ], $id);

        $this->assertVazio($erros);
    }

    // ------------------------------------------------------------------
    // Consultas proprias do modelo
    // ------------------------------------------------------------------

    public function testeProcuraPorNomeOuEmail(): void
    {
        $this->criarAlunoDeTeste('Ana Souza', 'ana@escola.br');
        $this->criarAlunoDeTeste('Bruno Lima', 'bruno@escola.br');

        $this->assertTotal(1, $this->alunos->procurar('Souza'));
        $this->assertTotal(1, $this->alunos->procurar('bruno@'));
        $this->assertTotal(0, $this->alunos->procurar('Zeca'));
    }

    public function testeCalculaAMediaDaTurma(): void
    {
        $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', 10);
        $this->criarAlunoDeTeste('Bruno', 'bruno@escola.br', 'Informatica', 5);

        $this->assertIgual(7.5, $this->alunos->mediaGeral());
    }

    public function testeListaSomenteOsAprovados(): void
    {
        $this->criarAlunoDeTeste('Aprovada', 'ap@escola.br', 'Informatica', 8);
        $this->criarAlunoDeTeste('Recuperacao', 'rec@escola.br', 'Informatica', 4);

        $aprovados = $this->alunos->aprovados();

        $this->assertTotal(1, $aprovados);
        $this->assertIgual('Aprovada', $aprovados[0]['nome']);
    }

    public function testeAgrupaTotalPorCurso(): void
    {
        $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica');
        $this->criarAlunoDeTeste('Bruno', 'bruno@escola.br', 'Informatica');
        $this->criarAlunoDeTeste('Carla', 'carla@escola.br', 'Enfermagem');

        $totais = $this->alunos->totalPorCurso();

        $this->assertTotal(2, $totais, 'Devem existir dois cursos diferentes');
        $this->assertIgual('Informatica', $totais[0]['curso']);
        $this->assertIgual(2, $totais[0]['total']);
    }

    // ------------------------------------------------------------------
    // Seguranca
    // ------------------------------------------------------------------

    public function testeRecusaNomeDeColunaSuspeito(): void
    {
        // Nomes de coluna nao passam por prepared statement, por isso o
        // modelo valida o formato antes de montar o SQL.
        $this->assertExcecao(InvalidArgumentException::class, function () {
            $this->alunos->onde('nome; DROP TABLE alunos', 'x');
        });
    }

    public function testeValorComAspasNaoQuebraOSql(): void
    {
        $nomePerigoso = "Robert'); DROP TABLE alunos;--";

        $id = $this->alunos->criar([
            'nome'  => $nomePerigoso,
            'email' => 'robert@escola.br',
            'curso' => 'Informatica',
        ]);

        $aluno = $this->alunos->buscar($id);

        $this->assertIgual($nomePerigoso, $aluno['nome'], 'O texto deve ser gravado literalmente');
        $this->assertIgual(1, $this->contarNaTabela('alunos'), 'A tabela deve continuar existindo');
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    private function criarAlunoDeTeste(
        string $nome,
        string $email,
        string $curso = 'Informatica',
        ?float $nota = null
    ): int {
        return $this->alunos->criar([
            'nome'  => $nome,
            'email' => $email,
            'curso' => $curso,
            'nota'  => $nota,
        ]);
    }
}
