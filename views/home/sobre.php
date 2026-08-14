<?php
/**
 * Tela explicativa: serve como "cola" para os alunos durante as aulas.
 */
?>
<h1>Como funciona o framework</h1>

<h2>1. O caminho de uma requisicao</h2>

<pre class="codigo">
Navegador
   |
   v
.htaccess  (ou roteador.php no servidor embutido)
   |
   v
index.php  -----> nucleo/bootstrap.php  (autoloader, config, sessao)
   |
   v
Nucleo\App  ----> descobre o controlador e o metodo pela URL
   |
   v
Controllers\AlunosController::ver(5)
   |                |
   |                v
   |          Modelos\Aluno  -->  banco de dados
   v
views/alunos/ver.php  dentro de  views/template/layout.php
   |
   v
HTML pronto no navegador
</pre>

<h2>2. Regra das rotas</h2>

<p>O endereco segue sempre o padrao <code>/controlador/metodo/parametros</code>:</p>

<table class="tabela">
    <thead>
        <tr><th>URL</th><th>Executa</th></tr>
    </thead>
    <tbody>
        <tr><td><code>/</code></td><td><code>HomeController::index()</code></td></tr>
        <tr><td><code>/alunos</code></td><td><code>AlunosController::index()</code></td></tr>
        <tr><td><code>/alunos/criar</code></td><td><code>AlunosController::criar()</code></td></tr>
        <tr><td><code>/alunos/ver/7</code></td><td><code>AlunosController::ver(7)</code></td></tr>
    </tbody>
</table>

<h2>3. Criando um controlador novo</h2>

<pre class="codigo">
&lt;?php
namespace Controllers;

use Nucleo\Controller;

class ProfessoresController extends Controller
{
    public function index(): void
    {
        $professores = $this->modelo('Professor')->todos();

        $this->view('professores/index', [
            'titulo'      =&gt; 'Professores',
            'professores' =&gt; $professores,
        ]);
    }
}
</pre>

<p>
    Salve como <code>controllers/ProfessoresController.php</code>.
    A rota <code>/professores</code> passa a funcionar na hora — nao precisa
    registrar nada em lugar nenhum.
</p>

<h2>4. Criando um modelo novo</h2>

<pre class="codigo">
&lt;?php
namespace Modelos;

use Nucleo\Model;

class Professor extends Model
{
    protected string $tabela       = 'professores';
    protected array  $preenchiveis = ['nome', 'disciplina'];
}
</pre>

<p>Pronto: <code>todos()</code>, <code>buscar()</code>, <code>criar()</code>,
   <code>atualizar()</code>, <code>excluir()</code>, <code>onde()</code> e
   <code>contar()</code> ja existem, herdados de <code>Nucleo\Model</code>.</p>

<h2>5. Metodos que voce ganha de graca</h2>

<table class="tabela">
    <thead>
        <tr><th>No controlador</th><th>O que faz</th></tr>
    </thead>
    <tbody>
        <tr><td><code>$this->view()</code></td><td>desenha uma tela</td></tr>
        <tr><td><code>$this->modelo('Aluno')</code></td><td>instancia um modelo</td></tr>
        <tr><td><code>$this->post('nome')</code></td><td>le um campo do formulario</td></tr>
        <tr><td><code>$this->ehPost()</code></td><td>diz se o formulario foi enviado</td></tr>
        <tr><td><code>$this->validador()</code></td><td>valida os dados recebidos</td></tr>
        <tr><td><code>$this->redirecionar('alunos')</code></td><td>manda para outra rota</td></tr>
        <tr><td><code>$this->mensagem('sucesso', '...')</code></td><td>mensagem na proxima tela</td></tr>
        <tr><td><code>$this->json([...])</code></td><td>responde em JSON (AJAX)</td></tr>
        <tr><td><code>$this->naoEncontrado()</code></td><td>mostra a tela 404</td></tr>
    </tbody>
</table>

<p><a class="botao botao--secundario" href="<?= url() ?>">Voltar ao inicio</a></p>
