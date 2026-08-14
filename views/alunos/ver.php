<?php
/**
 * Detalhe de um aluno.
 * Recebe do AlunosController::ver(): $aluno
 */
?>
<div class="titulo-com-acao">
    <h1><?= e($aluno['nome']) ?></h1>
    <a class="botao" href="<?= url('alunos/editar/' . $aluno['id']) ?>">Editar</a>
</div>

<dl class="ficha">
    <dt>Codigo</dt>
    <dd>#<?= (int) $aluno['id'] ?></dd>

    <dt>E-mail</dt>
    <dd><a href="mailto:<?= e($aluno['email']) ?>"><?= e($aluno['email']) ?></a></dd>

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

<p>
    <a class="botao botao--secundario" href="<?= url('alunos') ?>">Voltar a lista</a>
</p>

<form
    method="POST"
    action="<?= url('alunos/excluir/' . $aluno['id']) ?>"
    data-confirmar="Excluir o aluno <?= e($aluno['nome']) ?>? Esta acao nao pode ser desfeita."
>
    <button type="submit" class="botao botao--perigo">Excluir aluno</button>
</form>
