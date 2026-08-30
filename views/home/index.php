<?php
/**
 * Tela inicial (dashboard).
 *
 * A tela inicial nao depende de nenhuma tabela da aplicacao.
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Bem-vindo ao framework MVC</h1>
        <p class="text-secondary mb-0">Sua base administrativa esta pronta para evoluir.</p>
    </div>
    <a class="btn btn-primary" href="<?= url('home/sobre') ?>">Como funciona</a>
</div>

<div class="row g-4">
<div class="col-12 col-lg-8"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><p class="texto-apoio">
    Esta e a estrutura base da disciplina de Desenvolvimento Web.
    Tudo ja vem pronto por <strong>heranca</strong>: seus controladores herdam de
    <code>Nucleo\Controller</code> e seus modelos de <code>Nucleo\Model</code>.
</p>

<h2 class="h5 mt-4">Por onde comecar</h2>

<ol class="lista-passos">
    <li>Crie sua primeira tabela com <code>php console.php scaffold:crud produto nome:string</code>.</li>
    <li>Abra a rota gerada e personalize o modelo, controlador e as telas.</li>
    <li>Adicione login com <code>php console.php auth:install</code> quando precisar.</li>
    <li>Rode os testes com <code>php testes/executar.php</code>.</li>
</ol>

</div></div></div>
<div class="col-12 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="text-primary fs-2 mb-3">&lt;/&gt;</div><h2 class="h5">Projeto sem dados predefinidos</h2><p class="text-secondary mb-0">Gere seus recursos com CRUD, testes e esquemas de banco pelo terminal.</p></div></div></div>
</div>
