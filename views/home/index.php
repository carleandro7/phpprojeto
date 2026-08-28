<?php
/**
 * Tela inicial (dashboard).
 *
 * A tela inicial nao depende de nenhuma tabela da aplicacao.
 */
?>
<h1>Bem-vindo ao framework MVC</h1>

<p class="texto-apoio">
    Esta e a estrutura base da disciplina de Desenvolvimento Web.
    Tudo ja vem pronto por <strong>heranca</strong>: seus controladores herdam de
    <code>Nucleo\Controller</code> e seus modelos de <code>Nucleo\Model</code>.
</p>

<h2>Por onde comecar</h2>

<ol class="lista-passos">
    <li>Crie sua primeira tabela com <code>php console.php scaffold:crud produto nome:string</code>.</li>
    <li>Abra a rota gerada e personalize o modelo, controlador e as telas.</li>
    <li>Adicione login com <code>php console.php auth:install</code> quando precisar.</li>
    <li>Rode os testes com <code>php testes/executar.php</code>.</li>
</ol>

<p>
    <a class="botao botao--secundario" href="<?= url('home/sobre') ?>">Como funciona</a>
</p>
