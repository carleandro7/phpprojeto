<?php

namespace Controllers;

use Modelos\Aluno;
use Nucleo\Autenticacao;
use Nucleo\Controller;
use Nucleo\Sessao;

/**
 * Entrada e saida do aluno no sistema.
 *
 * ------------------------------------------------------------------------
 * O CAMINHO COMPLETO DE UM LOGIN
 * ------------------------------------------------------------------------
 *   1. O aluno abre /login                  -> index()   mostra o formulario
 *   2. Preenche e-mail + senha e envia      -> entrar()  confere e anota
 *   3. Passa a ver as paginas protegidas    -> painel()
 *   4. Clica em "Sair"                      -> sair()    apaga a anotacao
 *
 * Repare em quem faz o que, porque e a mesma divisao do resto do sistema:
 *
 *   - o MODELO (Modelos\Aluno::autenticar) e o unico que fala com o banco e
 *     o unico que sabe conferir uma senha;
 *   - a SESSAO (Nucleo\Autenticacao) e a unica que sabe onde fica anotado
 *     quem entrou;
 *   - este CONTROLLER so faz a ligacao entre os dois e decide para onde o
 *     navegador vai em seguida.
 *
 * Rotas atendidas:
 *   /login              -> index()    formulario de acesso
 *   /login/entrar       -> entrar()   confere e-mail e senha (POST)
 *   /login/painel       -> painel()   pagina que so quem entrou enxerga
 *   /login/sair         -> sair()     encerra a sessao (POST)
 */
class LoginController extends Controller
{
    private Aluno $alunos;

    public function __construct()
    {
        $this->alunos = new Aluno();
    }

    // ------------------------------------------------------------------
    // Formulario
    // ------------------------------------------------------------------

    /**
     * Rota: GET /login
     */
    public function index(): void
    {
        // Quem ja entrou nao precisa ver esta tela de novo.
        if (Autenticacao::verificar()) {
            $this->redirecionar('login/painel');
        }

        $this->view('login/index', [
            'titulo' => 'Entrar',
            'acao'   => url('login/entrar'),
        ]);
    }

    // ------------------------------------------------------------------
    // Conferencia
    // ------------------------------------------------------------------

    /**
     * Rota: POST /login/entrar
     */
    public function entrar(): void
    {
        // Mesma trava do salvar() dos alunos: sem POST nao ha o que conferir.
        if (!$this->ehPost()) {
            $this->redirecionar('login');
        }

        $email = (string) $this->post('email', '');
        $senha = (string) $this->post('senha', '');

        // Quem confere e o modelo. Ele devolve o registro do aluno quando o
        // e-mail existe E a senha bate com o hash guardado; null no resto.
        $aluno = $this->alunos->autenticar($email, $senha);

        if ($aluno === null) {
            // Uma mensagem so, de proposito, sem dizer se o errado foi o
            // e-mail ou a senha — veja o comentario em Aluno::autenticar().
            $this->mensagem('erro', 'E-mail ou senha incorretos.');

            // Devolve so o e-mail para o campo voltar preenchido. A senha
            // NUNCA volta: nao se repoe senha em tela nem se guarda na sessao.
            Sessao::guardarEntrada(['email' => $email]);

            $this->redirecionar('login');
        }

        // Acertou: fica anotado na sessao que este aluno entrou.
        Autenticacao::entrar($aluno);

        $this->mensagem('sucesso', 'Bem-vindo(a), ' . $aluno['nome'] . '!');

        // POST-Redirect-GET de novo: sem o redirect, um F5 reenviaria o
        // formulario de login.
        $this->redirecionar('login/painel');
    }

    // ------------------------------------------------------------------
    // Area restrita
    // ------------------------------------------------------------------

    /**
     * Rota: GET /login/painel
     *
     * Exemplo de pagina protegida — e o motivo de o login existir.
     */
    public function painel(): void
    {
        // A primeira linha e a trava. Sem ela, bastaria digitar o endereco
        // para ver a pagina, mesmo sem nunca ter feito login.
        $logado = $this->exigirLogin();

        // Na sessao guardamos so id, nome e e-mail. O resto (curso, nota)
        // continua no banco e e buscado agora, para a tela mostrar sempre o
        // dado atual — e nao uma copia velha de quando o aluno entrou.
        $aluno = $this->alunos->buscar($logado['id']);

        // Cinto de seguranca: o registro pode ter sido excluido enquanto a
        // sessao seguia aberta.
        if ($aluno === null) {
            Autenticacao::sair();

            $this->mensagem('erro', 'A sua conta nao esta mais disponivel.');
            $this->redirecionar('login');
        }

        $this->view('login/painel', [
            'titulo' => 'Minha area',
            'aluno'  => $aluno,
        ]);
    }

    // ------------------------------------------------------------------
    // Saida
    // ------------------------------------------------------------------

    /**
     * Rota: POST /login/sair
     *
     * E POST, e nao um link comum, pelo mesmo motivo do "Excluir" da lista de
     * alunos: sair e uma acao que MUDA alguma coisa. Se fosse um GET, bastaria
     * um <img src=".../login/sair"> em qualquer outro site para derrubar a
     * sessao de quem passasse por la.
     */
    public function sair(): void
    {
        Autenticacao::sair();

        $this->mensagem('info', 'Voce saiu do sistema.');
        $this->redirecionar('login');
    }
}
