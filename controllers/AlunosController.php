<?php

namespace Controllers;

use Modelos\Aluno;
use Nucleo\Controller;
use Nucleo\Sessao;

/**
 * CRUD completo de alunos — o exemplo principal da disciplina.
 *
 * ------------------------------------------------------------------------
 * O QUE E UM CONTROLLER?
 * ------------------------------------------------------------------------
 * E o maestro da aplicacao. Ele NAO escreve SQL e NAO escreve HTML.
 * O trabalho dele e apenas:
 *
 *   1. receber o que o usuario pediu (a URL e os campos do formulario);
 *   2. pedir os dados ao MODELO  (modelos/Aluno.php);
 *   3. entregar o resultado a VIEW (views/alunos/*.php).
 *
 * Se voce escrever SELECT aqui dentro, ou echo de HTML, quebrou a separacao.
 *
 * ------------------------------------------------------------------------
 * COMO A URL VIRA UM METODO
 * ------------------------------------------------------------------------
 * O nucleo (Nucleo\App) parte a URL em tres pedacos:
 *
 *     /alunos      /ver      /5
 *      ^controller  ^metodo   ^parametro
 *
 * ...e executa  (new AlunosController())->ver('5').
 * Por isso todo metodo publico desta classe vira uma pagina do sistema.
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
    /**
     * A "caixa de ferramentas" para falar com a tabela alunos.
     *
     * "private"     = so esta classe enxerga esta propriedade.
     * "Aluno $..."  = type hint; garante que aqui so cabe um objeto Aluno.
     */
    private Aluno $alunos;

    /**
     * Metodo magico do PHP: roda sozinho no "new AlunosController()", antes
     * de qualquer outro metodo.
     *
     * Serve para preparar o que todos os metodos vao precisar — assim nao
     * repetimos "new Aluno()" em cada funcao la embaixo.
     */
    public function __construct()
    {
        // Instancia o modelo uma vez para todos os metodos usarem.
        $this->alunos = new Aluno();
    }

    // ------------------------------------------------------------------
    // Listar
    // ------------------------------------------------------------------

    /**
     * Rota: GET /alunos  (e tambem /alunos?busca=ana)
     *
     * Unica tela com uma decisao: se o usuario digitou algo no campo de
     * busca, filtramos; se nao, mostramos a lista inteira.
     */
    public function index(): void
    {
        // get() le a query string (?busca=ana) ja com trim().
        // O segundo argumento e o valor padrao usado quando o campo nao veio.
        // O (string) garante que $busca seja texto mesmo se vier algo estranho.
        $busca = (string) $this->get('busca', '');

        // Operador ternario: "condicao ? valor_se_verdadeiro : valor_se_falso".
        // E a forma curta de escrever um if/else que so escolhe um valor.
        $lista = $busca !== ''
            ? $this->alunos->procurar($busca)   // consulta propria do modelo
            : $this->alunos->todos();           // CRUD herdado de Nucleo\Model

        // view() carrega views/alunos/index.php dentro do template padrao.
        // CADA CHAVE DO ARRAY VIRA UMA VARIAVEL DENTRO DA VIEW:
        //   'alunos' => $lista   ->   la dentro existe $alunos
        //   'busca'  => $busca   ->   la dentro existe $busca (repoe o campo)
        $this->view('alunos/index', [
            'titulo' => 'Alunos',
            'alunos' => $lista,
            'busca'  => $busca,
        ]);
    }

    // ------------------------------------------------------------------
    // Ver um registro
    // ------------------------------------------------------------------

    /**
     * Rota: GET /alunos/ver/5
     *
     * O "5" da URL chega automaticamente no parametro $id.
     * (Vem sempre como string, porque tudo na URL e texto.)
     */
    public function ver(string $id): void
    {
        // buscar() faz "SELECT * FROM alunos WHERE id = ? LIMIT 1"
        // e devolve NULL quando nao encontra nada.
        $aluno = $this->alunos->buscar($id);

        // Sempre confira o null ANTES de usar o resultado.
        // Sem este if, a linha $aluno['nome'] mais abaixo quebraria com erro
        // feio quando alguem digitasse /alunos/ver/9999 na barra do navegador.
        if ($aluno === null) {
            // naoEncontrado() mostra a tela 404 e ENCERRA a execucao aqui
            // (o retorno dele e "never"), por isso nao precisa de return.
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

    /**
     * Rota: GET /alunos/criar
     *
     * So MOSTRA o formulario em branco — nao grava nada.
     * Quem grava e o salvar(), chamado depois pelo POST do formulario.
     */
    public function criar(): void
    {
        // A MESMA view serve para cadastrar e para editar.
        // Sao estes dois valores que dizem a ela qual e o caso:
        //   'aluno' => null            -> campos vazios
        //   'acao'  => .../salvar      -> para onde o <form action=""> envia
        // url() monta o endereco completo respeitando a pasta do projeto.
        $this->view('alunos/formulario', [
            'titulo' => 'Novo aluno',
            'aluno'  => null,
            'acao'   => url('alunos/salvar'),
        ]);
    }

    /**
     * Rota: POST /alunos/salvar
     *
     * Recebe o formulario, valida e grava. E o metodo mais denso da classe;
     * leia como quatro etapas em sequencia.
     */
    public function salvar(): void
    {
        // ETAPA 1 - trava de seguranca.
        // ehPost() diz se a requisicao veio de um formulario enviado.
        // Sem isso, bastaria digitar /alunos/salvar no navegador (isso e GET)
        // para cair num INSERT. redirecionar() encerra a execucao.
        if (!$this->ehPost()) {
            $this->redirecionar('alunos/criar');
        }

        // ETAPA 2 - ler e validar.
        // todosOsCampos() devolve todo o $_POST com trim() em cada texto.
        $dados = $this->todosOsCampos();

        // As REGRAS ficam no modelo, nunca aqui. validar() devolve um array
        // "campo => mensagem". Array VAZIO significa que passou em tudo.
        $erros = $this->alunos->validar($dados);

        // ETAPA 3 - deu erro: devolve o usuario ao formulario.
        if ($erros !== []) {
            // Como a proxima linha e um redirect, a pagina recarrega do zero
            // e tudo que estava em memoria se perde. Estas duas chamadas sao
            // o que faz o formulario voltar PREENCHIDO e com as mensagens em
            // vermelho, em vez de o usuario ter que digitar tudo de novo.
            // Os dados ficam na sessao por apenas uma requisicao.
            Sessao::guardarEntrada($dados);
            Sessao::guardarErros($erros);

            // Aviso que aparece uma unica vez no topo da proxima tela (flash).
            $this->mensagem('erro', 'Corrija os campos destacados.');
            $this->redirecionar('alunos/criar');
        }

        // ETAPA 4 - passou: grava.
        // criar() monta o INSERT e devolve o ID gerado pelo banco.
        // Repare que mandamos $dados inteiro: o modelo descarta sozinho o que
        // nao estiver na lista $preenchiveis (protecao contra mass assignment).
        $id = $this->alunos->criar($dados);

        $this->mensagem('sucesso', 'Aluno cadastrado com sucesso!');

        // Por que redirecionar em vez de mostrar a tela direto?
        // E o padrao POST-Redirect-GET. Sem ele, apertar F5 depois de salvar
        // reenvia o POST e cadastra o mesmo aluno duas vezes.
        $this->redirecionar('alunos/ver/' . $id);
    }

    // ------------------------------------------------------------------
    // Editar
    // ------------------------------------------------------------------

    /**
     * Rota: GET /alunos/editar/5
     *
     * Igual ao criar(), com duas diferencas: busca o registro antes e passa
     * o aluno para a view, que entao preenche os campos com os valores atuais.
     */
    public function editar(string $id): void
    {
        $aluno = $this->alunos->buscar($id);

        if ($aluno === null) {
            $this->naoEncontrado("Aluno {$id} nao existe.");
        }

        $this->view('alunos/formulario', [
            'titulo' => 'Editar aluno',
            'aluno'  => $aluno,                              // campos preenchidos
            'acao'   => url('alunos/atualizar/' . $aluno['id']), // outro destino
        ]);
    }

    /**
     * Rota: POST /alunos/atualizar/5
     *
     * Espelho do salvar(), com DUAS conferencias a mais (marcadas abaixo).
     */
    public function atualizar(string $id): void
    {
        if (!$this->ehPost()) {
            $this->redirecionar('alunos/editar/' . $id);
        }

        // DIFERENCA 1: o id vem da URL, ou seja, do usuario. Antes de gastar
        // trabalho validando, confirme que esse registro realmente existe.
        if (!$this->alunos->existe($id)) {
            $this->naoEncontrado("Aluno {$id} nao existe.");
        }

        $dados = $this->todosOsCampos();

        // DIFERENCA 2 (a mais importante): o segundo argumento.
        // Ele diz ao validador "ignore ESTE registro na checagem de e-mail
        // unico". Sem ele, editar o aluno sem trocar o e-mail acusaria
        // "este e-mail ja esta cadastrado" — conflitando com ele mesmo.
        $erros = $this->alunos->validar($dados, $id);

        if ($erros !== []) {
            Sessao::guardarEntrada($dados);
            Sessao::guardarErros($erros);

            $this->mensagem('erro', 'Corrija os campos destacados.');
            $this->redirecionar('alunos/editar/' . $id);
        }

        // atualizar() monta o UPDATE ... WHERE id = ?
        $this->alunos->atualizar($id, $dados);

        $this->mensagem('sucesso', 'Dados atualizados com sucesso!');
        $this->redirecionar('alunos/ver/' . $id);
    }

    // ------------------------------------------------------------------
    // Excluir
    // ------------------------------------------------------------------

    /**
     * Rota: GET /alunos/excluir/5
     *
     * Nao tem tela propria: confere, apaga, avisa e volta para a lista.
     * (A confirmacao "tem certeza?" e feita no navegador, pela view.)
     */
    public function excluir(string $id): void
    {
        if (!$this->alunos->existe($id)) {
            $this->naoEncontrado("Aluno {$id} nao existe.");
        }

        // excluir() monta o DELETE ... WHERE id = ?
        $this->alunos->excluir($id);

        $this->mensagem('sucesso', 'Aluno removido.');

        // Sem parametro, redirecionar() volta para a raiz da rota: /alunos
        $this->redirecionar('alunos');
    }

    // ------------------------------------------------------------------
    // Exemplo de API / AJAX
    // ------------------------------------------------------------------

    /**
     * Rota: GET /alunos/api
     *
     * Mostra a OUTRA forma de resposta do controller. Repare que nao ha
     * view aqui: json() define o cabecalho "Content-Type: application/json"
     * e imprime o array convertido. E o que o JavaScript consome no fetch().
     */
    public function api(): void
    {
        $this->json([
            'total'  => $this->alunos->contar(),      // herdado de Nucleo\Model
            'media'  => $this->alunos->mediaGeral(),  // consulta propria do modelo
            'alunos' => $this->alunos->todos(),
        ]);
    }
}
