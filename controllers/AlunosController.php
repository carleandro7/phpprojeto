<?php

namespace Controllers;

use Modelos\Aluno;
use Nucleo\Controller;
use Nucleo\Sessao;

/**
 * CRUD completo de alunos — o exemplo principal da disciplina.
 *
 * Rotas atendidas:
 *   /alunos                  -> index()      lista
 *   /alunos/ver/5            -> ver(5)       detalhe
 *   /alunos/criar            -> criar()      formulario de cadastro
 *   /alunos/salvar           -> salvar()     grava o cadastro (POST)
 *   /alunos/editar/5         -> editar(5)    formulario de edicao
 *   /alunos/atualizar/5      -> atualizar(5) grava a edicao (POST)
 *   /alunos/excluir/5        -> excluir(5)   apaga
 *   /alunos/api              -> api()        exemplo de resposta JSON
 */
class AlunosController extends Controller
{
    private Aluno $alunos;

    public function __construct()
    {
        // Instancia o modelo uma vez para todos os metodos usarem.
        $this->alunos = new Aluno();
    }

    // ------------------------------------------------------------------
    // Listar
    // ------------------------------------------------------------------

    public function index(): void
    {
        $busca = (string) $this->get('busca', '');

        $lista = $busca !== ''
            ? $this->alunos->procurar($busca)
            : $this->alunos->todos();

        $this->view('alunos/index', [
            'titulo' => 'Alunos',
            'alunos' => $lista,
            'busca'  => $busca,
        ]);
    }

    // ------------------------------------------------------------------
    // Ver um registro
    // ------------------------------------------------------------------

    public function ver(string $id): void
    {
        $aluno = $this->alunos->buscar($id);

        if ($aluno === null) {
            $this->naoEncontrado("Aluno {$id} nao existe.");
        }

        $this->view('alunos/ver', [
            'titulo' => $aluno['nome'],
            'aluno'  => $aluno,
        ]);
    }

    // ------------------------------------------------------------------
    // Criar
    // ------------------------------------------------------------------

    public function criar(): void
    {
        $this->view('alunos/formulario', [
            'titulo' => 'Novo aluno',
            'aluno'  => null,
            'acao'   => url('alunos/salvar'),
        ]);
    }

    public function salvar(): void
    {
        if (!$this->ehPost()) {
            $this->redirecionar('alunos/criar');
        }

        $dados = $this->todosOsCampos();
        $erros = $this->alunos->validar($dados);

        if ($erros !== []) {
            // Guarda o que foi digitado e a lista de erros para a proxima tela.
            Sessao::guardarEntrada($dados);
            Sessao::guardarErros($erros);

            $this->mensagem('erro', 'Corrija os campos destacados.');
            $this->redirecionar('alunos/criar');
        }

        $id = $this->alunos->criar($dados);

        $this->mensagem('sucesso', 'Aluno cadastrado com sucesso!');
        $this->redirecionar('alunos/ver/' . $id);
    }

    // ------------------------------------------------------------------
    // Editar
    // ------------------------------------------------------------------

    public function editar(string $id): void
    {
        $aluno = $this->alunos->buscar($id);

        if ($aluno === null) {
            $this->naoEncontrado("Aluno {$id} nao existe.");
        }

        $this->view('alunos/formulario', [
            'titulo' => 'Editar aluno',
            'aluno'  => $aluno,
            'acao'   => url('alunos/atualizar/' . $aluno['id']),
        ]);
    }

    public function atualizar(string $id): void
    {
        if (!$this->ehPost()) {
            $this->redirecionar('alunos/editar/' . $id);
        }

        if (!$this->alunos->existe($id)) {
            $this->naoEncontrado("Aluno {$id} nao existe.");
        }

        $dados = $this->todosOsCampos();
        $erros = $this->alunos->validar($dados, $id);

        if ($erros !== []) {
            Sessao::guardarEntrada($dados);
            Sessao::guardarErros($erros);

            $this->mensagem('erro', 'Corrija os campos destacados.');
            $this->redirecionar('alunos/editar/' . $id);
        }

        $this->alunos->atualizar($id, $dados);

        $this->mensagem('sucesso', 'Dados atualizados com sucesso!');
        $this->redirecionar('alunos/ver/' . $id);
    }

    // ------------------------------------------------------------------
    // Excluir
    // ------------------------------------------------------------------

    public function excluir(string $id): void
    {
        if (!$this->alunos->existe($id)) {
            $this->naoEncontrado("Aluno {$id} nao existe.");
        }

        $this->alunos->excluir($id);

        $this->mensagem('sucesso', 'Aluno removido.');
        $this->redirecionar('alunos');
    }

    // ------------------------------------------------------------------
    // Exemplo de API / AJAX
    // ------------------------------------------------------------------

    public function api(): void
    {
        $this->json([
            'total'  => $this->alunos->contar(),
            'media'  => $this->alunos->mediaGeral(),
            'alunos' => $this->alunos->todos(),
        ]);
    }
}
