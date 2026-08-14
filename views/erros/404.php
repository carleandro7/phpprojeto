<?php
/**
 * Tela exibida quando a rota nao existe.
 * Recebe: $mensagem, $rota
 */
?>
<div class="erro-pagina">
    <span class="erro-pagina__codigo">404</span>
    <h1>Pagina nao encontrada</h1>

    <p class="texto-apoio">
        O endereco <code><?= e('/' . ltrim((string) ($rota ?? ''), '/')) ?></code>
        nao corresponde a nenhum controlador ou metodo.
    </p>

    <?php if (Nucleo\Config::obter('app.debug') && !empty($mensagem)): ?>
        <p class="detalhe-tecnico"><?= e($mensagem) ?></p>

        <p class="texto-apoio">
            Confira: o arquivo existe em <code>controllers/</code>?
            O nome da classe termina com <code>Controller</code>?
            O metodo e <code>public</code>?
        </p>
    <?php endif ?>

    <p><a class="botao" href="<?= url() ?>">Voltar ao inicio</a></p>
</div>
