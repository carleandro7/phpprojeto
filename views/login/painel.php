<?php
/**
 * Area restrita: so aparece para quem passou pelo login.
 *
 * Recebe do LoginController::painel():
 *   $aluno - registro completo, vindo do banco (sem a coluna senha)
 */
?>
<div class="titulo-com-acao">
    <h1>Ola, <?= e($aluno['nome']) ?>!</h1>
    <a class="botao" href="<?= url('alunos/editar/' . $aluno['id']) ?>">Editar meus dados</a>
</div>

<p class="texto-apoio">
    Esta pagina so abre para quem esta logado. Se voce sair e tentar voltar
    pelo endereco <code><?= e(url('login/painel')) ?></code>, o sistema devolve
    voce para a tela de entrada.
</p>

<dl class="ficha">
    <dt>Codigo</dt>
    <dd>#<?= (int) $aluno['id'] ?></dd>

    <dt>E-mail</dt>
    <dd><?= e($aluno['email']) ?></dd>

    <dt>Curso</dt>
    <dd><?= e($aluno['curso']) ?></dd>

    <dt>Nota</dt>
    <dd>
        <?php if ($aluno['nota'] === null): ?>
            <span class="etiqueta">sem nota</span>
        <?php else: ?>
            <span class="etiqueta <?= $aluno['nota'] >= 6 ? 'etiqueta--ok' : 'etiqueta--alerta' ?>">
                <?= moeda_br($aluno['nota']) ?>
                (<?= $aluno['nota'] >= 6 ? 'aprovado' : 'em recuperacao' ?>)
            </span>
        <?php endif ?>
    </dd>

    <dt>Cadastrado em</dt>
    <dd><?= e(data_br($aluno['criado_em'] ?? null, true)) ?></dd>
</dl>

<p class="texto-apoio">
    Repare que a coluna <code>senha</code> nao aparece nesta tela nem em
    <a href="<?= url('alunos/api') ?>">/alunos/api</a>: o modelo a declara em
    <code>$ocultos</code>, entao ela nem chega ate aqui.
</p>

<form method="POST" action="<?= url('login/sair') ?>">
    <button type="submit" class="botao botao--secundario">Sair</button>
</form>
