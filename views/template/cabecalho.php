<?php
/**
 * Cabecalho com o menu de navegacao.
 * Incluido pelo template/layout.php.
 *
 * Os itens vem de configuracoes/menu.php, que o comando scaffold:crud
 * atualiza a cada CRUD gerado. As telas de login vem dos providers
 * instalados por auth:install.
 */

use Nucleo\Autenticacao;
use Nucleo\Config;

// Descobre a rota atual para destacar o item do menu.
$rotaAtual = trim((string) ($_GET['url'] ?? ''), '/');
$secao     = explode('/', $rotaAtual)[0] ?: '';

$itens = array_values(array_filter(
    (array) Config::obter('menu', [['rota' => '', 'texto' => 'Inicio']]),
    function (array $item): bool {
        $regra = $item['auth'] ?? null;

        return match ($regra) {
            'sim' => Autenticacao::conectados() !== [],
            'nao' => Autenticacao::conectados() === [],
            default => true,
        };
    }
));

$conectados = Autenticacao::conectados();
$providers  = Autenticacao::providers();
?>
<header class="cabecalho">
    <aside class="sidebar offcanvas-lg offcanvas-start" id="menuPrincipal" tabindex="-1">
        <a class="sidebar__marca" href="<?= url() ?>">
            <span class="sidebar__logo">&lt;/&gt;</span>
            <span><?= e($nomeDoSite ?? 'Framework MVC') ?></span>
        </a>
        <div class="sidebar__rotulo">Navegacao</div>
        <nav class="sidebar__menu">
            <?php foreach ($itens as $item): ?>
                <?php $rota = trim((string) ($item['rota'] ?? ''), '/'); ?>
                <a class="sidebar__item <?= $secao === $rota ? 'sidebar__item--ativo' : '' ?>" href="<?= url($rota) ?>">
                    <span class="sidebar__icone"><?= $rota === '' ? '&#8962;' : '&#9632;' ?></span>
                    <?= e((string) ($item['texto'] ?? $rota)) ?>
                </a>
            <?php endforeach ?>

            <?php if ($providers !== []): ?>
                <div class="sidebar__rotulo">Conta</div>
            <?php endif ?>

            <?php foreach ($conectados as $provider): ?>
                <a class="sidebar__item" href="<?= url(Autenticacao::rotaSair($provider)) ?>">
                    <span class="sidebar__icone">&#8594;</span>
                    Sair<?= $provider === '' ? '' : ' (' . e($provider) . ')' ?>
                </a>
            <?php endforeach ?>

            <?php foreach ($providers as $provider): ?>
                <?php if (in_array($provider, $conectados, true)) { continue; } ?>
                <a class="sidebar__item" href="<?= url(Autenticacao::rotaLogin($provider)) ?>">
                    <span class="sidebar__icone">&#8594;</span>
                    Entrar<?= $provider === '' ? '' : ' (' . e($provider) . ')' ?>
                </a>
            <?php endforeach ?>
        </nav>
    </aside>
    <div class="topbar">
        <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal">Menu</button>
        <div class="topbar__titulo"><?= e($titulo ?? 'Painel') ?></div>
        <div class="topbar__status"><span class="status-ponto"></span> Sistema online</div>
    </div>
</header>
