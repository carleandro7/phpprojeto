<?php
/**
 * Lista de alunos.
 *
 * Recebe do AlunosController::index():
 *   $alunos (array de registros), $busca (texto pesquisado)
 */
?>
<div class="titulo-com-acao">
    <h1>Alunos</h1>
    <a class="botao" href="<?= url('alunos/criar') ?>">+ Novo aluno</a>
</div>

<form class="busca" method="GET" action="<?= url('alunos') ?>">
    <input
        type="search"
        name="busca"
        placeholder="Buscar por nome ou e-mail..."
        value="<?= e($busca) ?>"
    >
    <button class="botao botao--secundario" type="submit">Buscar</button>

    <?php if ($busca !== ''): ?>
        <a class="link-limpar" href="<?= url('alunos') ?>">limpar</a>
    <?php endif ?>
</form>

<?php if ($alunos === []): ?>
    <p class="vazio">
        <?= $busca !== ''
            ? 'Nenhum aluno encontrado para "' . e($busca) . '".'
            : 'Ainda nao ha alunos cadastrados.' ?>
    </p>
<?php else: ?>
    <table class="tabela">
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Curso</th>
                <th class="col-numero">Nota</th>
                <th class="col-acoes">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($alunos as $aluno): ?>
                <tr>
                    <td>
                        <a href="<?= url('alunos/ver/' . $aluno['id']) ?>">
                            <?= e($aluno['nome']) ?>
                        </a>
                    </td>
                    <td><?= e($aluno['email']) ?></td>
                    <td><?= e($aluno['curso']) ?></td>
                    <td class="col-numero">
                        <?php if ($aluno['nota'] === null): ?>
                            <span class="etiqueta">sem nota</span>
                        <?php else: ?>
                            <span class="etiqueta <?= $aluno['nota'] >= 6 ? 'etiqueta--ok' : 'etiqueta--alerta' ?>">
                                <?= moeda_br($aluno['nota']) ?>
                            </span>
                        <?php endif ?>
                    </td>
                    <td class="col-acoes">
                        <a href="<?= url('alunos/editar/' . $aluno['id']) ?>">Editar</a>

                        <form
                            method="POST"
                            action="<?= url('alunos/excluir/' . $aluno['id']) ?>"
                            class="formulario-em-linha"
                            data-confirmar="Excluir o aluno <?= e($aluno['nome']) ?>?"
                        >
                            <button type="submit" class="link-perigo">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <p class="texto-apoio">
        <?= count($alunos) ?> registro(s).
        Veja tambem a versao em JSON: <a href="<?= url('alunos/api') ?>"><?= url('alunos/api') ?></a>
    </p>
<?php endif ?>
