<?php

namespace Testes\Nucleo;

use InvalidArgumentException;
use Modelos\Aluno;
use Nucleo\Sql;
use Testes\Suporte\TesteBase;

/**
 * Testes de SEGURANCA contra SQL INJECTION.
 *
 * Cada teste aqui simula um ataque real e prova que o framework resiste.
 * Use este arquivo em aula para mostrar por que prepared statements
 * (os "?" no SQL) sao obrigatorios.
 *
 * Rode so estes:  php testes/executar.php SegurancaSql
 */
class SegurancaSqlTest extends TesteBase
{
    private Aluno $alunos;

    public function preparar(): void
    {
        $this->limparTabela('alunos');
        $this->alunos = new Aluno();

        $this->alunos->criar([
            'nome'  => 'Ana Souza',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
            'nota'  => 9,
        ]);
    }

    // ------------------------------------------------------------------
    // Ataque 1: encerrar o comando e emendar outro (;)
    // ------------------------------------------------------------------

    public function testeAtaqueDropTableNaoApagaATabela(): void
    {
        $ataque = "x'; DROP TABLE alunos; --";

        // Passa pelo caminho normal do sistema: uma busca de usuario.
        $this->alunos->procurar($ataque);
        $this->alunos->primeiroOnde('email', $ataque);

        // A tabela continua de pe e com o registro intacto.
        $this->assertIgual(1, $this->contarNaTabela('alunos'), 'A tabela nao pode ser apagada');
    }

    public function testeAtaqueDeleteNaoRemoveRegistros(): void
    {
        $this->alunos->buscar("1; DELETE FROM alunos");
        $this->alunos->onde('curso', "Informatica'; DELETE FROM alunos; --");

        $this->assertIgual(1, $this->contarNaTabela('alunos'));
    }

    // ------------------------------------------------------------------
    // Ataque 2: burlar uma condicao com OR 1=1
    // ------------------------------------------------------------------

    public function testeAtaqueOrSempreVerdadeiroNaoVazaRegistros(): void
    {
        $this->alunos->criar([
            'nome'  => 'Bruno Lima',
            'email' => 'bruno@escola.br',
            'curso' => 'Enfermagem',
        ]);

        // Em um sistema vulneravel, isto listaria TODOS os alunos.
        $resultado = $this->alunos->onde('curso', "Informatica' OR '1'='1");

        $this->assertTotal(0, $resultado, "O texto foi comparado literalmente, como deve ser");
    }

    public function testeAtaqueOrSempreVerdadeiroPelaUrlNaoVazaDados(): void
    {
        // /alunos/ver/1 OR 1=1  -> o id chega como texto e nao encontra nada.
        $resposta = $this->requisitar('alunos/ver/' . rawurlencode('1 OR 1=1'));

        $this->assertIgual(404, $resposta->status);
        $this->assertNaoContem('Ana Souza', $resposta->html);
    }

    public function testeAtaquePeloCampoDeBuscaNaoVazaDados(): void
    {
        $resposta = $this->requisitar('alunos', 'GET', [
            'busca' => "' OR 1=1 --",
        ]);

        $this->assertIgual(200, $resposta->status);
        $this->assertNaoContem('Ana Souza', $resposta->html, 'A busca nao pode ser burlada');
        $this->assertIgual(1, $this->contarNaTabela('alunos'));
    }

    // ------------------------------------------------------------------
    // Ataque 3: comentario para anular o resto do comando (--)
    // ------------------------------------------------------------------

    public function testeAtaqueComComentarioNaoAlteraOComando(): void
    {
        $id = $this->alunos->primeiroOnde('email', 'ana@escola.br')['id'];

        // Tenta fazer o UPDATE atingir todas as linhas.
        $this->alunos->atualizar("{$id} OR 1=1", ['nota' => 0]);

        $aluno = $this->alunos->buscar($id);
        $this->assertIgual(9, $aluno['nota'], 'A nota original nao pode ser alterada');
    }

    // ------------------------------------------------------------------
    // Ataque 4: injetar pelo NOME DA COLUNA (nao protegido por "?")
    // ------------------------------------------------------------------

    public function testeColunaMaliciosaEhRecusada(): void
    {
        $this->assertExcecao(InvalidArgumentException::class, function () {
            $this->alunos->onde('nome; DROP TABLE alunos; --', 'x');
        });

        $this->assertExcecao(InvalidArgumentException::class, function () {
            $this->alunos->onde('1=1 OR nome', 'x');
        });

        $this->assertIgual(1, $this->contarNaTabela('alunos'));
    }

    public function testeOrdenacaoMaliciosaEhRecusada(): void
    {
        $this->assertExcecao(InvalidArgumentException::class, function () {
            $this->alunos->todos('nome; DELETE FROM alunos');
        });

        $this->assertExcecao(InvalidArgumentException::class, function () {
            $this->alunos->todos('(SELECT 1)');
        });
    }

    public function testeOperadorForaDaListaEhRecusado(): void
    {
        $this->assertExcecao(InvalidArgumentException::class, function () {
            $this->alunos->onde('nome', 'x', 'UNION SELECT');
        });
    }

    public function testeCampoDeFormularioComNomeMaliciosoEhRecusado(): void
    {
        // Modelo sem $preenchiveis declarado: as chaves do $_POST virariam
        // nomes de coluna. O filtro precisa barrar mesmo assim.
        $modelo = new ModeloSemPreenchiveis();

        $this->assertExcecao(InvalidArgumentException::class, function () use ($modelo) {
            $modelo->criar(['nome) VALUES (1); DROP TABLE alunos; --' => 'x']);
        });

        $this->assertIgual(1, $this->contarNaTabela('alunos'));
    }

    // ------------------------------------------------------------------
    // Os valores continuam sendo gravados exatamente como vieram
    // ------------------------------------------------------------------

    public function testeTextoComAspasEGravadoELidoCorretamente(): void
    {
        $nome = "Robert'); DROP TABLE alunos;-- O'Brien \"aspas\"";

        $id = $this->alunos->criar([
            'nome'  => $nome,
            'email' => 'robert@escola.br',
            'curso' => 'Informatica',
        ]);

        $this->assertIgual($nome, $this->alunos->buscar($id)['nome']);
        $this->assertIgual(2, $this->contarNaTabela('alunos'));
    }

    // ------------------------------------------------------------------
    // Curingas do LIKE
    // ------------------------------------------------------------------

    public function testeCuringaDoLikeNaoVazaTodosOsRegistros(): void
    {
        // Sem tratamento, buscar por "%" traria a lista inteira.
        $this->assertTotal(0, $this->alunos->procurar('%'));
        $this->assertTotal(0, $this->alunos->procurar('_'));

        // A busca normal continua funcionando.
        $this->assertTotal(1, $this->alunos->procurar('Ana'));
    }

    // ------------------------------------------------------------------
    // A classe de apoio Nucleo\Sql
    // ------------------------------------------------------------------

    public function testeIdentificadorAceitaNomesValidos(): void
    {
        $this->assertIgual('nome', Sql::identificador('nome'));
        $this->assertIgual('criado_em', Sql::identificador('criado_em'));
        $this->assertIgual('coluna2', Sql::identificador('coluna2'));

        // Espaco sobrando nas pontas e apenas descuido de digitacao: e aparado.
        $this->assertIgual('nome', Sql::identificador('  nome  '));
    }

    public function testeIdentificadorRecusaNomesInvalidos(): void
    {
        $proibidos = [
            'nome; DROP TABLE alunos',
            'alunos.nome',
            'nome--',
            'no me',      // espaco no meio do nome
            '',
            '1coluna',
            'nome OR 1=1',
        ];

        foreach ($proibidos as $nome) {
            $this->assertExcecao(
                InvalidArgumentException::class,
                fn () => Sql::identificador($nome),
                "Deveria recusar: \"{$nome}\""
            );
        }
    }

    public function testeOrdenacaoAceitaFormatoValido(): void
    {
        $this->assertIgual('nome', Sql::ordenacao('nome'));
        $this->assertIgual('nota DESC', Sql::ordenacao('nota desc'));
        $this->assertIgual('nome ASC', Sql::ordenacao('  nome   ASC  '));
    }

    public function testeMarcadoresParaClausulaIn(): void
    {
        $this->assertIgual('?, ?, ?', Sql::marcadores([1, 2, 3]));

        $this->assertExcecao(InvalidArgumentException::class, fn () => Sql::marcadores([]));
    }

    public function testeComoLikeNeutralizaCuringas(): void
    {
        $this->assertIgual('%100!%%', Sql::comoLike('100%'));
        $this->assertIgual('%a!_b%', Sql::comoLike('a_b'));
        $this->assertIgual('%uau!!%', Sql::comoLike('uau!'), 'O proprio escape deve ser escapado');
        $this->assertIgual('Ana%', Sql::comoLike('Ana', 'inicio'));
    }

    public function testeBuscaComCaractereDeEscapeNaoQuebraOSql(): void
    {
        // Se o escape nao fosse tratado, isto geraria um erro de SQL.
        $this->assertTotal(0, $this->alunos->procurar('!'));
        $this->assertTotal(0, $this->alunos->procurar('100%!_'));
    }
}

/**
 * Modelo usado apenas por um dos testes acima: proposital sem $preenchiveis,
 * para provar que o framework se protege mesmo quando o programador esquece
 * de declarar os campos permitidos.
 */
class ModeloSemPreenchiveis extends \Nucleo\Model
{
    protected string $tabela = 'alunos';
}
