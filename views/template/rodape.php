<?php
/**
 * Rodape do site. Incluido pelo template/layout.php.
 */
?>
<footer class="rodape">
    <div class="rodape__interno">
        <p class="mb-0">
            <?= e(Nucleo\Config::obter('app.nome', 'Framework MVC')) ?>
            <span class="rodape__nota">&middot; <?= date('Y') ?> &middot; PHP <?= PHP_VERSION ?></span>
        </p>
    </div>
</footer>
