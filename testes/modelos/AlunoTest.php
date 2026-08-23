<?php

namespace Testes\Modelos;

use Testes\Suporte\TesteBase;

use InvalidArgumentException;
use Modelos\Aluno;
use Nucleo\Database;

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
            'nome'              => 'Ana Souza',
            'email'             => 'ana@escola.br',
            'senha'             => 'segredo123',
            'senha_confirmacao' => 'segredo123',
            'curso'             => 'Informatica',
            'nota'              => 8,
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
    // Validacao da senha
    // ------------------------------------------------------------------

    public function testeValidacaoExigeSenhaNoCadastro(): void
    {
        $erros = $this->alunos->validar([
            'nome'  => 'Ana Souza',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
        ]);

        $this->assertTemChave('senha', $erros, 'Sem senha o aluno nunca conseguiria entrar');
    }

    public function testeValidacaoNaoExigeSenhaNaEdicao(): void
    {
        $id = $this->criarAlunoDeTeste('Ana', 'ana@escola.br');

        // Campo em branco na edicao = "mantenha a senha atual".
        $erros = $this->alunos->validar([
            'nome'  => 'Ana Souza',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
            'senha' => '',
        ], $id);

        $this->assertVazio($erros);
    }

    public function testeValidacaoRecusaSenhaCurta(): void
    {
        $erros = $this->alunos->validar([
            'nome'              => 'Ana Souza',
            'email'             => 'ana@escola.br',
            'curso'             => 'Informatica',
            'senha'             => '123',
            'senha_confirmacao' => '123',
        ]);

        $this->assertTemChave('senha', $erros);
    }

    public function testeValidacaoRecusaConfirmacaoDiferente(): void
    {
        $erros = $this->alunos->validar([
            'nome'              => 'Ana Souza',
            'email'             => 'ana@escola.br',
            'curso'             => 'Informatica',
            'senha'             => 'segredo123',
            'senha_confirmacao' => 'segredo124',
        ]);

        $this->assertTemChave('senha_confirmacao', $erros);
    }

    // ------------------------------------------------------------------
    // Senha no banco: hash, e nunca texto puro
    // ------------------------------------------------------------------

    public function testeGravaOHashDaSenhaENaoASenhaDigitada(): void
    {
        $id = $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', null, '123456');

        // Le direto do banco, sem passar pelo modelo: e a unica forma de
        // conferir o que ficou GRAVADO de verdade na coluna.
        $gravado = $this->senhaGravada($id);

        $this->assertDiferente('123456', $gravado, 'A senha nao pode ir para o banco como foi digitada');
        $this->assertVerdadeiro(
            password_verify('123456', $gravado),
            'O que esta gravado tem que ser o hash da senha digitada'
        );
    }

    public function testeAMesmaSenhaGeraHashesDiferentes(): void
    {
        $ana   = $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', null, '123456');
        $bruno = $this->criarAlunoDeTeste('Bruno', 'bruno@escola.br', 'Informatica', null, '123456');

        // Duas pessoas com a MESMA senha nao podem ter o mesmo hash: cada
        // chamada de password_hash() sorteia um sal novo. E o que impede
        // alguem de olhar a tabela e descobrir quem repetiu a senha.
        $this->assertDiferente(
            $this->senhaGravada($ana),
            $this->senhaGravada($bruno),
            'Cada hash tem que ter o proprio sal'
        );
    }

    public function testeASenhaNaoSaiNasConsultas(): void
    {
        $id = $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', null, '123456');

        // $ocultos = ['senha'] no modelo: nenhuma consulta devolve a coluna.
        $this->assertFalso(array_key_exists('senha', $this->alunos->buscar($id)));
        $this->assertFalso(array_key_exists('senha', $this->alunos->todos()[0]));
        $this->assertFalso(array_key_exists('senha', $this->alunos->onde('email', 'ana@escola.br')[0]));
        $this->assertFalso(array_key_exists('senha', $this->alunos->procurar('Ana')[0]));
    }

    public function testeAtualizarSemSenhaMantemAAtual(): void
    {
        $id   = $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', null, '123456');
        $hash = $this->senhaGravada($id);

        $this->alunos->atualizar($id, ['nome' => 'Ana Maria', 'senha' => '']);

        $this->assertIgual('Ana Maria', $this->alunos->buscar($id)['nome']);
        $this->assertIgual($hash, $this->senhaGravada($id), 'Campo em branco nao pode apagar a senha');
    }

    public function testeAtualizarComSenhaNovaTrocaOHash(): void
    {
        $id   = $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', null, '123456');
        $hash = $this->senhaGravada($id);

        $this->alunos->atualizar($id, ['senha' => 'outrasenha']);

        $this->assertDiferente($hash, $this->senhaGravada($id));
        $this->assertVerdadeiro(password_verify('outrasenha', $this->senhaGravada($id)));
        $this->assertFalso(password_verify('123456', $this->senhaGravada($id)), 'A senha antiga nao vale mais');
    }

    // ------------------------------------------------------------------
    // Login  ->  autenticar()
    // ------------------------------------------------------------------

    public function testeAutenticaComEmailESenhaCorretos(): void
    {
        $id = $this->criarAlunoDeTeste('Ana Souza', 'ana@escola.br', 'Informatica', null, '123456');

        $aluno = $this->alunos->autenticar('ana@escola.br', '123456');

        $this->assertNaoNulo($aluno);
        $this->assertIgual($id, $aluno['id']);
        $this->assertIgual('Ana Souza', $aluno['nome']);
    }

    public function testeAutenticarNaoDevolveOHash(): void
    {
        $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', null, '123456');

        $aluno = $this->alunos->autenticar('ana@escola.br', '123456');

        $this->assertFalso(
            array_key_exists('senha', $aluno),
            'Depois de conferida, a senha nao tem mais serventia nenhuma'
        );
    }

    public function testeNaoAutenticaComSenhaErrada(): void
    {
        $this->criarAlunoDeTeste('Ana', 'ana@escola.br', 'Informatica', null, '123456');

        $this->assertNulo($this->alunos->autenticar('ana@escola.br', '654321'));
    }

    public function testeNaoAutenticaEmailInexistente(): void
    {
        $this->assertNulo($this->alunos->autenticar('ninguem@escola.br', '123456'));
    }

    public function testeNaoAutenticaAlunoSemSenhaCadastrada(): void
    {
        // Registro antigo, criado antes de a tela de login existir.
        $this->criarAlunoDeTeste('Sem Senha', 'sem.senha@escola.br');

        $this->assertNulo($this->alunos->autenticar('sem.senha@escola.br', ''));
        $this->assertNulo($this->alunos->autenticar('sem.senha@escola.br', '123456'));
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
        ?float $nota = null,
        ?string $senha = null
    ): int {
        return $this->alunos->criar([
            'nome'  => $nome,
            'email' => $email,
            'senha' => $senha,
            'curso' => $curso,
            'nota'  => $nota,
        ]);
    }

    /**
     * Le a coluna senha DIRETO do banco, sem passar pelo modelo.
     *
     * O modelo esconde essa coluna de proposito ($ocultos), entao esta e a
     * unica forma de o teste conferir o que ficou gravado de verdade. Um
     * teste que so olhasse pelo modelo nunca perceberia uma senha em texto
     * puro na tabela.
     */
    private function senhaGravada(int $id): string
    {
        $stmt = Database::conexao()->prepare('SELECT senha FROM alunos WHERE id = ?');
        $stmt->execute([$id]);

        return (string) $stmt->fetchColumn();
    }
}
