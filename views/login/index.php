<?php
/**
 * Tela de login.
 *
 * Recebe do LoginController::index():
 *   $acao - URL para onde o formulario e enviado (/login/entrar)
 *
 * Nao existe erro por campo aqui: o controller devolve UMA mensagem so
 * ("E-mail ou senha incorretos"), exibida no topo pelo template/mensagens.
 * Dizer qual dos dois estava errado entregaria quais e-mails tem conta.
 */
?>
<div class="acesso">
    <h1>Entrar</h1>

    <p class="texto-apoio">
        Use o e-mail e a senha cadastrados no sistema.
    </p>

    <form method="POST" action="<?= e($acao) ?>" class="formulario">

        <div class="campo">
            <label for="email">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="username"
                value="<?= e((string) antigo('email')) ?>"
                required
            >
        </div>

        <div class="campo">
            <label for="senha">Senha</label>
            <input
                type="password"
                id="senha"
                name="senha"
                autocomplete="current-password"
                required
            >
            <?php /*
                type="password" faz duas coisas: esconde o que esta sendo
                digitado e impede o navegador de guardar o valor no historico
                do formulario. E note que nao ha value="...": senha nunca volta
                preenchida na tela, nem depois de um erro.
            */ ?>
        </div>

        <div class="formulario__acoes">
            <button type="submit" class="botao">Entrar</button>
            <a class="botao botao--secundario" href="<?= url() ?>">Voltar</a>
        </div>
    </form>

    <p class="texto-apoio">
        Ainda nao tem cadastro?
        <a href="<?= url('alunos/criar') ?>">Cadastre-se aqui</a>.
    </p>
</div>
