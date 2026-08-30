<h1>Como funciona o framework</h1>

<h2>O caminho de uma requisicao</h2>

<pre class="codigo">
Navegador
   |
   v
index.php -> nucleo/bootstrap.php -> Nucleo\App
                                      |
                                      v
                         Controller -> Model -> banco de dados
                                      |
                                      v
                                  View -> HTML
</pre>

<h2>Comecar uma aplicacao</h2>

<p>O projeto inicia sem tabelas ou entidades de exemplo. Gere um recurso
completo pelo terminal:</p>

<pre class="codigo">php console.php scaffold:crud produtos nome:string preco:decimal</pre>

<p>Para adicionar login a um model existente, execute:</p>

<pre class="codigo">php console.php auth:install Cliente</pre>

<p>O comando adiciona os campos <code>email</code> e <code>senha</code> quando
necessario. Controllers podem exigir login chamando
<code>$this-&gt;exigirAutenticacao()</code>. Consulte
<code>documentacao/Tutorial-Comandos.md</code> para o fluxo completo.</p>

<p><a class="botao botao--secundario" href="<?= url() ?>">Voltar ao inicio</a></p>
