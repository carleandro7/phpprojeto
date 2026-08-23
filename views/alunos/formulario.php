<?php
/**
 * Formulario usado tanto para CRIAR quanto para EDITAR.
 *
 * Recebe do AlunosController:
 *   $aluno - registro atual (null quando e cadastro novo)
 *   $acao  - URL para onde o formulario sera enviado
 *
 * A funcao antigo() repoe o que o usuario digitou caso a validacao falhe.
 */

use Modelos\Aluno;

$editando = $aluno !== null;
?>
<h1><?= $editando ? 'Editar aluno' : 'Novo aluno' ?></h1>

<form method="POST" action="<?= e($acao) ?>" class="formulario">

    <div class="campo <?= tem_erro('nome') ? 'campo--erro' : '' ?>">
        <label for="nome">Nome</label>
        <input
            type="text"
            id="nome"
            name="nome"
            value="<?= e(antigo('nome', $aluno['nome'] ?? '')) ?>"
            required
        >
        <?php if ($msg = erro_de('nome')): ?>
            <span class="campo__erro"><?= e($msg) ?></span>
        <?php endif ?>
    </div>

    <div class="campo <?= tem_erro('email') ? 'campo--erro' : '' ?>">
        <label for="email">E-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= e(antigo('email', $aluno['email'] ?? '')) ?>"
            required
        >
        <?php if ($msg = erro_de('email')): ?>
            <span class="campo__erro"><?= e($msg) ?></span>
        <?php endif ?>
    </div>

    <?php /*
        SENHA
        Os dois campos abaixo sao os unicos do formulario que nunca voltam
        preenchidos: nao ha value="..." e nem poderia haver. O banco guarda
        apenas o hash, e dele nao se recupera a senha digitada.

        No CADASTRO a senha e obrigatoria. Na EDICAO ela e opcional: em branco
        significa "mantenha a senha que ja esta la" — quem trata isso e o
        protegerSenha() do modelo.
    */ ?>
    <div class="campo <?= tem_erro('senha') ? 'campo--erro' : '' ?>">
        <label for="senha">
            <?= $editando ? 'Nova senha' : 'Senha' ?>
        </label>
        <input
            type="password"
            id="senha"
            name="senha"
            minlength="<?= Aluno::SENHA_MINIMA ?>"
            autocomplete="new-password"
            <?= $editando ? '' : 'required' ?>
        >
        <?php if ($msg = erro_de('senha')): ?>
            <span class="campo__erro"><?= e($msg) ?></span>
        <?php else: ?>
            <span class="campo__dica">
                <?= $editando
                    ? 'Deixe em branco para continuar com a senha atual.'
                    : 'Pelo menos ' . Aluno::SENHA_MINIMA . ' caracteres.' ?>
            </span>
        <?php endif ?>
    </div>

    <div class="campo <?= tem_erro('senha_confirmacao') ? 'campo--erro' : '' ?>">
        <label for="senha_confirmacao">Confirme a senha</label>
        <input
            type="password"
            id="senha_confirmacao"
            name="senha_confirmacao"
            autocomplete="new-password"
            <?= $editando ? '' : 'required' ?>
        >
        <?php if ($msg = erro_de('senha_confirmacao')): ?>
            <span class="campo__erro"><?= e($msg) ?></span>
        <?php endif ?>
    </div>

    <div class="campo <?= tem_erro('curso') ? 'campo--erro' : '' ?>">
        <label for="curso">Curso</label>
        <select id="curso" name="curso" required>
            <option value="">Selecione...</option>
            <?php
            $cursoAtual = (string) antigo('curso', $aluno['curso'] ?? '');
            foreach (Aluno::CURSOS as $curso):
            ?>
                <option value="<?= e($curso) ?>" <?= $cursoAtual === $curso ? 'selected' : '' ?>>
                    <?= e($curso) ?>
                </option>
            <?php endforeach ?>
        </select>
        <?php if ($msg = erro_de('curso')): ?>
            <span class="campo__erro"><?= e($msg) ?></span>
        <?php endif ?>
    </div>

    <div class="campo <?= tem_erro('nota') ? 'campo--erro' : '' ?>">
        <label for="nota">Nota (0 a 10)</label>
        <input
            type="number"
            id="nota"
            name="nota"
            step="0.1"
            min="0"
            max="10"
            value="<?= e((string) antigo('nota', $aluno['nota'] ?? '')) ?>"
        >
        <?php if ($msg = erro_de('nota')): ?>
            <span class="campo__erro"><?= e($msg) ?></span>
        <?php endif ?>
    </div>

    <div class="formulario__acoes">
        <button type="submit" class="botao">
            <?= $editando ? 'Salvar alteracoes' : 'Cadastrar' ?>
        </button>

        <a class="botao botao--secundario" href="<?= url('alunos') ?>">Cancelar</a>
    </div>
</form>
