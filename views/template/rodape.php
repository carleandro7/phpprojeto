<?php
/**
 * Rodape do site. Incluido pelo template/layout.php.
 */
?>
<footer class="rodape">
    <div class="rodape__interno">
        <p>
            <?= e(Nucleo\Config::obter('app.nome', 'Framework MVC')) ?>
            &mdash; material didatico de Desenvolvimento Web
        </p>
        <p class="rodape__nota">
            <?= date('Y') ?> &middot; PHP <?= PHP_VERSION ?>
        </p>
    </div>
</footer>
