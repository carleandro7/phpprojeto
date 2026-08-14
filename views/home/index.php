<?php
/**
 * Tela inicial (dashboard).
 *
 * Recebe do HomeController::index():
 *   $totalAlunos, $media, $totalPorCurso
 */
?>
<h1>Bem-vindo ao framework MVC</h1>

<p class="texto-apoio">
    Esta e a estrutura base da disciplina de Desenvolvimento Web.
    Tudo ja vem pronto por <strong>heranca</strong>: seus controladores herdam de
    <code>Nucleo\Controller</code> e seus modelos de <code>Nucleo\Model</code>.
</p>

<section class="cartoes">
    <div class="cartao">
        <span class="cartao__rotulo">Alunos cadastrados</span>
        <strong class="cartao__valor"><?= (int) $totalAlunos ?></strong>
    </div>

    <div class="cartao">
        <span class="cartao__rotulo">Media geral</span>
        <strong class="cartao__valor"><?= moeda_br($media) ?></strong>
    </div>

    <div class="cartao">
        <span class="cartao__rotulo">Cursos ativos</span>
        <strong class="cartao__valor"><?= count($totalPorCurso) ?></strong>
    </div>
</section>

<?php if ($totalPorCurso !== []): ?>
    <h2>Alunos por curso</h2>

    <table class="tabela">
        <thead>
            <tr>
                <th>Curso</th>
                <th class="col-numero">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($totalPorCurso as $linha): ?>
                <tr>
                    <td><?= e($linha['curso']) ?></td>
                    <td class="col-numero"><?= (int) $linha['total'] ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<h2>Por onde comecar</h2>

<ol class="lista-passos">
    <li>Abra <code>controllers/AlunosController.php</code> e veja o CRUD completo.</li>
    <li>Abra <code>modelos/Aluno.php</code>: quase nao tem codigo, tudo vem da heranca.</li>
    <li>Crie o seu controlador copiando o exemplo e ja tera a rota funcionando.</li>
    <li>Rode os testes com <code>php testes/executar.php</code>.</li>
</ol>

<p>
    <a class="botao" href="<?= url('alunos') ?>">Ver a lista de alunos</a>
    <a class="botao botao--secundario" href="<?= url('home/sobre') ?>">Como funciona</a>
</p>
