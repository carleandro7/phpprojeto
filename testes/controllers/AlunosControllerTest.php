<?php

namespace Testes\Controllers;

use Modelos\Aluno;
use Testes\Suporte\TesteBase;

/**
 * Testes do CONTROLADOR (teste de integracao).
 *
 * Aqui simulamos o acesso do navegador com $this->requisitar() e conferimos
 * o que o sistema respondeu: o HTML gerado, o redirecionamento e o que ficou
 * gravado no banco. E o teste que mais se parece com "usar o sistema".
 *
 * Rode so estes:  php testes/executar.php Controllers
 */
class AlunosControllerTest extends TesteBase
{
    private Aluno $alunos;

    public function preparar(): void
    {
        $this->limparTabela('alunos');
        $this->limparSessao();

        $this->alunos = new Aluno();
    }

    // ------------------------------------------------------------------
    // Listagem  ->  GET /alunos
    // ------------------------------------------------------------------

    public function testeListaMostraOsAlunosCadastrados(): void
    {
        $this->criarAluno('Ana Souza', 'ana@escola.br');
        $this->criarAluno('Bruno Lima', 'bruno@escola.br');

        $resposta = $this->requisitar('alunos');

        $this->assertIgual(200, $resposta->status);
        $this->assertContem('Ana Souza', $resposta->html);
        $this->assertContem('Bruno Lima', $resposta->html);
    }

    public function testeListaVaziaMostraAvisoAmigavel(): void
    {
        $resposta = $this->requisitar('alunos');

        $this->assertContem('Ainda nao ha alunos cadastrados', $resposta->html);
    }

    public function testeBuscaFiltraALista(): void
    {
        $this->criarAluno('Ana Souza', 'ana@escola.br');
        $this->criarAluno('Bruno Lima', 'bruno@escola.br');

        $resposta = $this->requisitar('alunos', 'GET', ['busca' => 'Souza']);

        $this->assertContem('Ana Souza', $resposta->html);
        $this->assertNaoContem('Bruno Lima', $resposta->html);
    }

    // ------------------------------------------------------------------
    // Detalhe  ->  GET /alunos/ver/{id}
    // ------------------------------------------------------------------

    public function testeVerMostraOsDadosDoAluno(): void
    {
        $id = $this->criarAluno('Carla Menezes', 'carla@escola.br', 'Enfermagem', 9.0);

        $resposta = $this->requisitar('alunos/ver/' . $id);

        $this->assertIgual(200, $resposta->status);
        $this->assertContem('Carla Menezes', $resposta->html);
        $this->assertContem('carla@escola.br', $resposta->html);
        $this->assertContem('Enfermagem', $resposta->html);
    }

    public function testeVerAlunoInexistenteRetorna404(): void
    {
        $resposta = $this->requisitar('alunos/ver/9999');

        $this->assertIgual(404, $resposta->status);
        $this->assertContem('Pagina nao encontrada', $resposta->html);
    }

    // ------------------------------------------------------------------
    // Cadastro  ->  GET /alunos/criar  +  POST /alunos/salvar
    // ------------------------------------------------------------------

    public function testeTelaDeCadastroMostraOFormulario(): void
    {
        $resposta = $this->requisitar('alunos/criar');

        $this->assertIgual(200, $resposta->status);
        $this->assertContem('<form', $resposta->html);
        $this->assertContem('name="nome"', $resposta->html);
        $this->assertContem('name="email"', $resposta->html);
        $this->assertContem('alunos/salvar', $resposta->html);
    }

    public function testeSalvarGravaNoBancoERedireciona(): void
    {
        $resposta = $this->postar('alunos/salvar', [
            'nome'              => 'Diego Fontes',
            'email'             => 'diego@escola.br',
            'senha'             => 'segredo123',
            'senha_confirmacao' => 'segredo123',
            'curso'             => 'Edificacoes',
            'nota'              => '7.5',
        ]);

        $this->assertVerdadeiro($resposta->foiRedirecionado(), 'Depois de salvar deve redirecionar');
        $this->assertIgual(1, $this->contarNaTabela('alunos'));

        $aluno = $this->alunos->primeiroOnde('email', 'diego@escola.br');
        $this->assertNaoNulo($aluno);
        $this->assertIgual('Diego Fontes', $aluno['nome']);
        $this->assertRedirecionouParaODetalhe($resposta->redirecionamento, $aluno['id']);
    }

    public function testeSalvarComDadosInvalidosNaoGrava(): void
    {
        $resposta = $this->postar('alunos/salvar', [
            'nome'  => 'A',                 // curto demais
            'email' => 'nao-e-email',       // invalido
            'curso' => '',                  // obrigatorio
        ]);

        $this->assertIgual(0, $this->contarNaTabela('alunos'), 'Dados invalidos nao podem ser gravados');
        $this->assertVerdadeiro($resposta->redirecionouPara('alunos/criar'), 'Deve voltar ao formulario');
    }

    public function testeFormularioMostraOsErrosDeValidacao(): void
    {
        // 1o passo: envio invalido (guarda erros e dados na sessao).
        $this->postar('alunos/salvar', [
            'nome'  => 'A',
            'email' => 'nao-e-email',
            'curso' => '',
        ]);

        // 2o passo: o navegador segue o redirecionamento e volta ao formulario.
        $resposta = $this->requisitar('alunos/criar');

        $this->assertContem('Corrija os campos destacados', $resposta->html);
        $this->assertContem('campo--erro', $resposta->html);
        $this->assertContem('e-mail valido', $resposta->html);
        $this->assertContem('value="A"', $resposta->html, 'O que foi digitado deve voltar preenchido');
    }

    public function testeNaoAceitaEmailRepetido(): void
    {
        $this->criarAluno('Ana Souza', 'ana@escola.br');

        $this->postar('alunos/salvar', [
            'nome'  => 'Outra Ana',
            'email' => 'ana@escola.br',
            'curso' => 'Informatica',
        ]);

        $this->assertIgual(1, $this->contarNaTabela('alunos'), 'O e-mail duplicado deve ser recusado');
    }

    // ------------------------------------------------------------------
    // Edicao  ->  GET /alunos/editar/{id}  +  POST /alunos/atualizar/{id}
    // ------------------------------------------------------------------

    public function testeTelaDeEdicaoVemPreenchida(): void
    {
        $id = $this->criarAluno('Ana Souza', 'ana@escola.br');

        $resposta = $this->requisitar('alunos/editar/' . $id);

        $this->assertIgual(200, $resposta->status);
        $this->assertContem('value="Ana Souza"', $resposta->html);
        $this->assertContem('value="ana@escola.br"', $resposta->html);
        $this->assertContem('alunos/atualizar/' . $id, $resposta->html);
    }

    public function testeAtualizarGravaAsAlteracoes(): void
    {
        $id = $this->criarAluno('Nome Antigo', 'antigo@escola.br');

        $resposta = $this->postar('alunos/atualizar/' . $id, [
            'nome'  => 'Nome Novo',
            'email' => 'antigo@escola.br',
            'curso' => 'Informatica',
            'nota'  => '9',
        ]);

        $this->assertVerdadeiro($resposta->foiRedirecionado());

        $aluno = $this->alunos->buscar($id);
        $this->assertIgual('Nome Novo', $aluno['nome']);
        $this->assertIgual(9, $aluno['nota']);
    }

    public function testeEditarAlunoInexistenteRetorna404(): void
    {
        $this->assertIgual(404, $this->requisitar('alunos/editar/9999')->status);
    }

    // ------------------------------------------------------------------
    // Exclusao  ->  POST /alunos/excluir/{id}
    // ------------------------------------------------------------------

    public function testeExcluirRemoveDoBanco(): void
    {
        $id = $this->criarAluno('Para Excluir', 'excluir@escola.br');

        $resposta = $this->postar('alunos/excluir/' . $id);

        $this->assertIgual(0, $this->contarNaTabela('alunos'));
        $this->assertVerdadeiro($resposta->redirecionouPara('alunos'));
    }

    public function testeExcluirAlunoInexistenteRetorna404(): void
    {
        $this->assertIgual(404, $this->postar('alunos/excluir/9999')->status);
    }

    // ------------------------------------------------------------------
    // Mensagens de sucesso (flash)
    // ------------------------------------------------------------------

    public function testeMostraMensagemDeSucessoAposCadastrar(): void
    {
        $this->postar('alunos/salvar', [
            'nome'              => 'Elisa Prado',
            'email'             => 'elisa@escola.br',
            'senha'             => 'segredo123',
            'senha_confirmacao' => 'segredo123',
            'curso'             => 'Enfermagem',
            'nota'              => '10',
        ]);

        // A mensagem foi guardada e deve aparecer na tela seguinte.
        $resposta = $this->requisitar('alunos');

        $this->assertContem('Aluno cadastrado com sucesso', $resposta->html);
        $this->assertContem('alerta--sucesso', $resposta->html);
    }

    // ------------------------------------------------------------------
    // API em JSON  ->  GET /alunos/api
    // ------------------------------------------------------------------

    public function testeApiDevolveJsonComOsAlunos(): void
    {
        $this->criarAluno('Ana Souza', 'ana@escola.br', 'Informatica', 8);

        $dados = $this->requisitar('alunos/api')->json();

        $this->assertNaoNulo($dados, 'A resposta deveria ser um JSON valido');
        $this->assertIgual(1, $dados['total']);
        $this->assertIgual(8, $dados['media']);
        $this->assertIgual('Ana Souza', $dados['alunos'][0]['nome']);
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    private function criarAluno(
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

    private function assertRedirecionouParaODetalhe(?string $destino, int|string $id): void
    {
        $this->assertContem('alunos/ver/' . $id, (string) $destino);
    }
}
