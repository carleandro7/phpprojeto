<?php

namespace Nucleo;

/**
 * Classe base de todos os controladores.
 *
 * O programador cria uma classe em "controllers/", herda desta e ja tem pronto:
 * carregar view, carregar modelo, ler POST/GET, redirecionar, mensagens flash
 * e resposta em JSON.
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
    *     $produtos = $this->modelo('Produto');
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

    /**
     * Pega um arquivo enviado pelo formulario.
     *
     * Devolve null quando ninguem escolheu arquivo — o caso normal em uma
     * edicao onde so os outros campos mudaram.
     *
     *     $anexo = $this->arquivo('contrato');
     *
     *     if ($anexo !== null && ($problema = $anexo->problema()) !== null) {
     *         $erros['contrato'] = $problema;
     *     }
     *
     *     $dados['contrato'] = $anexo->salvar('contratos');
     *
     * O formulario precisa ter enctype="multipart/form-data", senao o
     * navegador manda so o nome do arquivo e nada chega aqui.
     */
    protected function arquivo(
        string $campo,
        array $extensoes = Arquivo::DOCUMENTOS,
        int $maximoKb = Arquivo::MAXIMO_KB
    ): ?Arquivo {
        return Arquivo::de($campo, $extensoes, $maximoKb);
    }

    /**
     * Igual ao arquivo(), mas so aceita imagem — e confere o conteudo, nao
     * so a extensao.
     *
     *     $foto = $this->imagem('foto');
     */
    protected function imagem(string $campo, int $maximoKb = 2048): ?Arquivo
    {
        return Arquivo::de($campo, Arquivo::IMAGENS, $maximoKb, true);
    }

    // ------------------------------------------------------------------
    // Saida
    // ------------------------------------------------------------------

    /**
     * Manda o navegador para outra rota interna.
     *
    *     $this->redirecionar('produtos');
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

    protected function pdf(string $conteudo, string $arquivo = 'relatorio.pdf'): void
    {
        $arquivo = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($arquivo)) ?: 'relatorio.pdf';

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $arquivo . '"');
            header('Content-Length: ' . strlen($conteudo));
            header('X-Content-Type-Options: nosniff');
        }

        echo $conteudo;
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

    /**
     * Redireciona visitantes sem sessao para a tela de login.
     *
     *     $this->exigirAutenticacao();             // provider padrao (/auth)
     *     $this->exigirAutenticacao('professor');  // provider nomeado
     *
     * Se a tela de login informada nao existir, o erro aponta o comando que
     * falta rodar em vez de mandar o visitante para uma pagina 404.
     */
    protected function exigirAutenticacao(?string $provider = null): void
    {
        $provider = Autenticacao::resolver($provider);

        if (\autenticado($provider)) {
            return;
        }

        $this->mensagem('aviso', 'Entre para continuar.');
        $this->redirecionar(Autenticacao::rotaLogin($provider));
    }

    /**
     * Exige que quem esta logado tenha um destes perfis.
     *
     *     $this->exigirPerfil('admin');
     *     $this->exigirPerfil(['admin', 'coordenador']);
     *
     * Ela ja chama exigirAutenticacao() antes: quem nem entrou vai para a
     * tela de login, e nao para a mensagem de "sem permissao".
     *
     * Como exigirAutenticacao(), vale so na acao onde estiver escrita. Para
     * o controller inteiro, chame no construtor.
     */
    protected function exigirPerfil(string|array $perfis, ?string $provider = null): void
    {
        $this->exigirAutenticacao($provider);

        if (Perfis::tem($perfis, $provider)) {
            return;
        }

        $this->mensagem('erro', 'Voce nao tem permissao para acessar esta area.');
        $this->redirecionar();
    }

    /**
     * Garante que a acao so aceite POST.
     * Use em tudo que grava ou apaga: um link ou um <img> nao podem
     * disparar uma exclusao.
     */
    protected function exigirPost(): void
    {
        if (!$this->ehPost()) {
            $this->naoEncontrado('Esta acao so aceita envio de formulario (POST).');
        }
    }

    /**
     * Confere o token anti-CSRF enviado pelo formulario.
     * Nas views, gere o campo com <?= campo_csrf() ?>.
     */
    protected function exigirTokenValido(): void
    {
        if (Sessao::tokenValido($_POST['_token'] ?? null)) {
            return;
        }

        $this->mensagem('erro', 'Formulario expirado. Envie novamente.');
        $this->redirecionar($this->rotaAnterior());
    }

    /**
     * Atalho para acoes de gravacao: exige POST e token valido.
     */
    protected function exigirFormularioValido(): void
    {
        $this->exigirPost();
        $this->exigirTokenValido();
    }

    /**
     * Devolve o visitante ao formulario mantendo o que ele digitou e
     * mostrando os erros campo a campo.
     *
     *     $erros = $this->modelo->validar($dados);
     *     if ($erros !== []) {
     *         $this->voltarComErros($erros, 'produtos/criar');
     *     }
     */
    protected function voltarComErros(array $erros, string $rota): never
    {
        $entrada = $this->todosOsCampos();
        unset($entrada['senha'], $entrada['senha_confirmacao'], $entrada['_token']);

        Sessao::guardarErros($erros);
        Sessao::guardarEntrada($entrada);
        $this->mensagem('erro', 'Verifique os campos destacados.');

        $this->redirecionar($rota);
    }

    /**
     * Rota de onde veio a requisicao, para voltar depois de um erro.
     */
    protected function rotaAnterior(string $padrao = ''): string
    {
        $referencia = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referencia === '') {
            return $padrao;
        }

        $caminho = (string) (parse_url($referencia, PHP_URL_PATH) ?: '');
        $base    = (string) (parse_url(url_base(), PHP_URL_PATH) ?: '');

        if ($base !== '' && $base !== '/' && str_starts_with($caminho, $base)) {
            $caminho = substr($caminho, strlen($base));
        }

        return trim($caminho, '/');
    }
}
