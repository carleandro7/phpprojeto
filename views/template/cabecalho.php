<?php
/**
 * Cabecalho com o menu de navegacao.
 * Incluido pelo template/layout.php.
 */

// Descobre a rota atual para destacar o item do menu.
$rotaAtual = trim((string) ($_GET['url'] ?? ''), '/');
$secao     = explode('/', $rotaAtual)[0] ?: 'home';

$itens = [
    'home'   => ['rota' => '',       'texto' => 'Inicio'],
];

?>
<header class="cabecalho">
    <aside class="sidebar offcanvas-lg offcanvas-start" id="menuPrincipal" tabindex="-1">
        <a class="sidebar__marca" href="<?= url() ?>">
            <span class="sidebar__logo">&lt;/&gt;</span>
            <span><?= e($nomeDoSite ?? 'Framework MVC') ?></span>
        </a>
        <div class="sidebar__rotulo">Navegacao</div>
        <nav class="sidebar__menu">
            <?php foreach ($itens as $chave => $item): ?>
                <a class="sidebar__item <?= $secao === $chave ? 'sidebar__item--ativo' : '' ?>" href="<?= url($item['rota']) ?>">
                    <span class="sidebar__icone"><?= $chave === 'home' ? '&#8962;' : '&#9632;' ?></span>
                    <?= e($item['texto']) ?>
                </a>
            <?php endforeach ?>
            <?php if (autenticado()): ?>
                <a class="sidebar__item" href="<?= url('auth/sair') ?>"><span class="sidebar__icone">&#8594;</span>Sair</a>
            <?php elseif (is_file(CAMINHO_CONTROLLERS . '/AuthController.php')): ?>
                <a class="sidebar__item" href="<?= url('auth/login') ?>"><span class="sidebar__icone">&#8594;</span>Entrar</a>
            <?php endif ?>
        </nav>
    </aside>
    <div class="topbar">
        <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal">Menu</button>
        <div class="topbar__titulo"><?= e($titulo ?? 'Painel') ?></div>
        <div class="topbar__status"><span class="status-ponto"></span> Sistema online</div>
    </div>
</header>
