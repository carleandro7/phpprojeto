<?php

namespace Nucleo;

/**
 * Classe base de todos os controladores.
 *
 * O aluno cria uma classe em "controllers/", herda desta e ja tem pronto:
 * carregar view, carregar modelo, ler POST/GET, redirecionar, mensagens flash,
 * resposta em JSON e a trava de login (exigirLogin).
 *
 * Exemplo:
 *
 *     class HomeController extends \Nucleo\Controller
 *     {
 *         public function index(): void
 *         {
 *             $this->view('home/index', ['titulo' => 'Inicio']);
 *         }
 *     }
 */
abstract class Controller
{
    /**
     * Desenha uma view dentro do template padrao.
     */
    protected function view(string $view, array $dados = [], ?string $layout = 'template/layout'): void
    {
        View::renderizar($view, $dados, $layout);
    }

    /**
     * Desenha uma view sem template (util para conteudo carregado via AJAX).
     */
    protected function viewSemLayout(string $view, array $dados = []): void
    {
        View::renderizar($view, $dados, null);
    }

    /**
     * Instancia um modelo da pasta "modelos".
     *
     *     $alunos = $this->modelo('Aluno');
     */
    protected function modelo(string $nome): Model
    {
        $classe = 'Modelos\\' . str_replace('/', '\\', $nome);

        if (!class_exists($classe)) {
            throw new \RuntimeException("Modelo nao encontrado: {$classe}");
        }

        return new $classe();
    }

    // ------------------------------------------------------------------
    // Entrada de dados
    // ------------------------------------------------------------------

    /**
     * Le um campo enviado por POST (formulario), ja com trim().
     */
    protected function post(string $campo, mixed $padrao = null): mixed
    {
        $valor = $_POST[$campo] ?? $padrao;

        return is_string($valor) ? trim($valor) : $valor;
    }

    /**
     * Le um campo da query string (?pagina=2).
     */
    protected function get(string $campo, mixed $padrao = null): mixed
    {
        $valor = $_GET[$campo] ?? $padrao;

        return is_string($valor) ? trim($valor) : $valor;
    }

    /**
     * Devolve todo o POST, com trim em cada campo de texto.
     */
    protected function todosOsCampos(): array
    {
        return array_map(
            fn ($valor) => is_string($valor) ? trim($valor) : $valor,
            $_POST
        );
    }

    /**
     * Informa se a requisicao atual e um envio de formulario (POST).
     */
    protected function ehPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Cria um validador ja preenchido com os dados recebidos.
     */
    protected function validador(?array $dados = null): Validador
    {
        return new Validador($dados ?? $this->todosOsCampos());
    }

    // ------------------------------------------------------------------
    // Saida
    // ------------------------------------------------------------------

    /**
     * Manda o navegador para outra rota interna.
     *
     *     $this->redirecionar('alunos');
     */
    protected function redirecionar(string $rota = ''): never
    {
        throw new RedirecionamentoException(url($rota));
    }

    /**
     * Responde em JSON (para exercicios de AJAX / API).
     */
    protected function json(mixed $dados, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Guarda uma mensagem para aparecer na proxima tela.
     */
    protected function mensagem(string $tipo, string $texto): void
    {
        Sessao::flash($tipo, $texto);
    }

    /**
     * Interrompe mostrando a tela 404.
     */
    protected function naoEncontrado(string $mensagem = 'Pagina nao encontrada'): never
    {
        throw new NaoEncontradoException($mensagem);
    }

    // ------------------------------------------------------------------
    // Controle de acesso
    // ------------------------------------------------------------------

    /**
     * Trava de acesso: so continua quem estiver logado. Quem nao estiver e
     * mandado para a tela de login com um aviso.
     *
     * Chame na PRIMEIRA linha de todo metodo que nao pode ser aberto por
     * qualquer um:
     *
     *     public function painel(): void
     *     {
     *         $logado = $this->exigirLogin();
     *         ...
     *     }
     *
     * Nao basta esconder o link no menu: o endereco continua funcionando se
     * alguem digitar na barra do navegador. A trava tem que estar aqui, no
     * servidor.
     *
     * @return array{id:int,nome:string,email:string} os dados de quem esta logado
     */
    protected function exigirLogin(): array
    {
        $logado = Autenticacao::aluno();

        if ($logado === null) {
            $this->mensagem('aviso', 'Faca login para acessar esta pagina.');
            $this->redirecionar('login');
        }

        return $logado;
    }
}
