<?php

namespace Testes\Controllers;

use Modelos\Aluno;
use Nucleo\Autenticacao;
use Testes\Suporte\TesteBase;

/**
 * Testes da tela de login (teste de integracao).
 *
 * Aqui o teste "usa o sistema": pede a tela, envia o formulario, confere para
 * onde o sistema mandou o navegador e o que ficou anotado na sessao.
 *
 * Repare que varios testes conferem coisas que NAO devem acontecer — a senha
 * aparecer no HTML, a mensagem de erro entregar se o e-mail existe, o painel
 * abrir sem login. Em tela de acesso, o que o sistema se recusa a fazer vale
 * tanto quanto o que ele faz.
 *
 * Rode so estes:  php testes/executar.php LoginControllerTest
 */
class LoginControllerTest extends TesteBase
{
    private Aluno $alunos;

    public function preparar(): void
    {
        $this->limparTabela('alunos');
        $this->limparSessao();

        $this->alunos = new Aluno();
    }

    /**
     * Depois de CADA teste: derruba o login.
     *
     * Sem isto, o aluno que entrou aqui continuaria logado nos testes
     * seguintes — a sessao e uma variavel global e nao se desfaz sozinha ao
     * fim do metodo. Todo teste que mexe em estado global tem que limpar
     * o que sujou.
     */
    public function finalizar(): void
    {
        $this->limparSessao();
    }

    // ------------------------------------------------------------------
    // Formulario  ->  GET /login
    // ------------------------------------------------------------------

    public function testeTelaDeLoginMostraOFormulario(): void
    {
        $resposta = $this->requisitar('login');

        $this->assertIgual(200, $resposta->status);
        $this->assertContem('name="email"', $resposta->html);
        $this->assertContem('type="password"', $resposta->html);
        $this->assertContem('login/entrar', $resposta->html);
    }

    public function testeQuemJaEstaLogadoVaiDiretoParaOPainel(): void
    {
        $this->entrarComo('Ana Souza', 'ana@escola.br', '123456');

        $this->assertVerdadeiro($this->requisitar('login')->redirecionouPara('login/painel'));
    }

    public function testeEntrarPelaBarraDeEnderecoVoltaAoFormulario(): void
    {
        // /login/entrar e um destino de POST. Digitado no navegador (GET) nao
        // pode tentar conferir nada.
        $this->assertVerdadeiro($this->requisitar('login/entrar')->redirecionouPara('login'));
    }

    // ------------------------------------------------------------------
    // Login  ->  POST /login/entrar
    // ------------------------------------------------------------------

    public function testeLoginComDadosCorretosEntraNoSistema(): void
    {
        $id = $this->criarAluno('Ana Souza', 'ana@escola.br', '123456');

        $resposta = $this->postar('login/entrar', [
            'email' => 'ana@escola.br',
            'senha' => '123456',
        ]);

        $this->assertVerdadeiro($resposta->redirecionouPara('login/painel'));
        $this->assertVerdadeiro(Autenticacao::verificar(), 'O aluno deveria ficar anotado na sessao');
        $this->assertIgual($id, Autenticacao::id());
    }

    public function testeLoginComSenhaErradaNaoEntra(): void
    {
        $this->criarAluno('Ana Souza', 'ana@escola.br', '123456');

        $resposta = $this->postar('login/entrar', [
            'email' => 'ana@escola.br',
            'senha' => 'chutando',
        ]);

        $this->assertVerdadeiro($resposta->redirecionouPara('login'));
        $this->assertFalso(Autenticacao::verificar());
    }

    public function testeLoginComEmailInexistenteNaoEntra(): void
    {
        $resposta = $this->postar('login/entrar', [
            'email' => 'ninguem@escola.br',
            'senha' => '123456',
        ]);

        $this->assertVerdadeiro($resposta->redirecionouPara('login'));
        $this->assertFalso(Autenticacao::verificar());
    }

    public function testeAMensagemDeErroNaoDizQualDosDoisEstavaErrado(): void
    {
        $this->criarAluno('Ana Souza', 'ana@escola.br', '123456');

        // Senha errada em um e-mail que EXISTE.
        $this->postar('login/entrar', ['email' => 'ana@escola.br', 'senha' => 'errada']);
        $comEmailReal = $this->requisitar('login')->html;

        $this->limparSessao();

        // E-mail que NAO existe.
        $this->postar('login/entrar', ['email' => 'ninguem@escola.br', 'senha' => 'errada']);
        $comEmailFalso = $this->requisitar('login')->html;

        // As duas telas tem que dizer exatamente a mesma coisa. Se uma delas
        // dissesse "e-mail nao cadastrado", bastaria ir testando enderecos
        // para descobrir quem tem conta no sistema.
        $this->assertContem('E-mail ou senha incorretos', $comEmailReal);
        $this->assertContem('E-mail ou senha incorretos', $comEmailFalso);
        $this->assertNaoContem('nao cadastrado', $comEmailReal);
        $this->assertNaoContem('nao existe', $comEmailFalso);
    }

    public function testeOEmailVoltaPreenchidoMasASenhaNao(): void
    {
        $this->criarAluno('Ana Souza', 'ana@escola.br', '123456');

        $this->postar('login/entrar', ['email' => 'ana@escola.br', 'senha' => 'errada']);

        $html = $this->requisitar('login')->html;

        $this->assertContem('value="ana@escola.br"', $html, 'O e-mail nao precisa ser digitado de novo');
        $this->assertNaoContem('errada', $html, 'A senha digitada nao pode voltar para a tela');
    }

    // ------------------------------------------------------------------
    // Area restrita  ->  GET /login/painel
    // ------------------------------------------------------------------

    public function testePainelNaoAbreSemLogin(): void
    {
        $resposta = $this->requisitar('login/painel');

        $this->assertVerdadeiro($resposta->redirecionouPara('login'));
    }

    public function testePainelMostraOsDadosDeQuemEntrou(): void
    {
        $this->entrarComo('Ana Souza', 'ana@escola.br', '123456');

        $resposta = $this->requisitar('login/painel');

        $this->assertIgual(200, $resposta->status);
        $this->assertContem('Ana Souza', $resposta->html);
        $this->assertContem('ana@escola.br', $resposta->html);
    }

    public function testeOHashDaSenhaNaoApareceNoPainel(): void
    {
        $this->entrarComo('Ana Souza', 'ana@escola.br', '123456');

        $html = $this->requisitar('login/painel')->html;

        $this->assertNaoContem('123456', $html, 'A senha digitada nao existe mais em lugar nenhum');
        $this->assertNaoContem('$2y$', $html, 'Nem o hash pode vazar para a tela');
    }

    public function testePainelDerrubaASessaoSeOAlunoFoiExcluido(): void
    {
        $id = $this->entrarComo('Ana Souza', 'ana@escola.br', '123456');

        // O registro some enquanto a sessao continua aberta.
        $this->alunos->excluir($id);

        $resposta = $this->requisitar('login/painel');

        $this->assertVerdadeiro($resposta->redirecionouPara('login'));
        $this->assertFalso(Autenticacao::verificar());
    }

    // ------------------------------------------------------------------
    // Saida  ->  POST /login/sair
    // ------------------------------------------------------------------

    public function testeSairEncerraASessao(): void
    {
        $this->entrarComo('Ana Souza', 'ana@escola.br', '123456');

        $resposta = $this->postar('login/sair');

        $this->assertVerdadeiro($resposta->redirecionouPara('login'));
        $this->assertFalso(Autenticacao::verificar());

        // E depois de sair, a pagina protegida volta a ser inacessivel.
        $this->assertVerdadeiro($this->requisitar('login/painel')->redirecionouPara('login'));
    }

    // ------------------------------------------------------------------
    // Cabecalho
    // ------------------------------------------------------------------

    public function testeCabecalhoMostraEntrarQuandoNinguemEstaLogado(): void
    {
        $html = $this->requisitar('alunos')->html;

        // O link "Entrar" aponta para /login exato — as rotas de dentro
        // (login/painel, login/sair) so existem depois do login.
        $this->assertContem('href="' . url('login') . '"', $html);
        $this->assertNaoContem('login/sair', $html);
        $this->assertNaoContem('login/painel', $html);
    }

    public function testeCabecalhoMostraONomeEOBotaoSairDepoisDoLogin(): void
    {
        $this->entrarComo('Ana Souza', 'ana@escola.br', '123456');

        $html = $this->requisitar('alunos')->html;

        $this->assertContem('Ana Souza', $html);
        $this->assertContem('login/sair', $html);
        $this->assertContem('login/painel', $html);
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    private function criarAluno(string $nome, string $email, string $senha): int
    {
        return $this->alunos->criar([
            'nome'  => $nome,
            'email' => $email,
            'senha' => $senha,
            'curso' => 'Informatica',
            'nota'  => 8,
        ]);
    }

    /**
     * Cadastra o aluno e faz o login de verdade, pelo formulario.
     * Devolve o id.
     */
    private function entrarComo(string $nome, string $email, string $senha): int
    {
        $id = $this->criarAluno($nome, $email, $senha);

        $this->postar('login/entrar', ['email' => $email, 'senha' => $senha]);

        return $id;
    }
}
