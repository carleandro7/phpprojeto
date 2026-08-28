<?php
/**
 * Tela de erro interno (500).
 *
 * Com app.debug = true mostra os detalhes tecnicos para depuracao.
 * Com app.debug = false mostra apenas uma mensagem generica.
 *
 * Recebe: $erro (Throwable), $debug (bool)
 */
?>
<div class="erro-pagina">
    <span class="erro-pagina__codigo">500</span>
    <h1>Ops, algo deu errado</h1>

    <?php if (!empty($debug)): ?>
        <p class="detalhe-tecnico">
            <strong><?= e(get_class($erro)) ?></strong><br>
            <?= e($erro->getMessage()) ?>
        </p>

        <p class="texto-apoio">
            Arquivo: <code><?= e($erro->getFile()) ?></code>
            na linha <code><?= (int) $erro->getLine() ?></code>
        </p>

        <details class="pilha">
            <summary>Ver pilha de chamadas</summary>
            <pre class="codigo"><?= e($erro->getTraceAsString()) ?></pre>
        </details>

        <p class="texto-apoio">
            Para esconder estes detalhes, mude <code>'debug' =&gt; false</code>
            em <code>configuracoes/app.php</code>.
        </p>
    <?php else: ?>
        <p class="texto-apoio">
            Nao foi possivel concluir a operacao. Tente novamente mais tarde.
        </p>
    <?php endif ?>

    <p><a class="botao" href="<?= url() ?>">Voltar ao inicio</a></p>
</div>
