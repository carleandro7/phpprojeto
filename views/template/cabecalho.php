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
    'alunos' => ['rota' => 'alunos', 'texto' => 'Alunos'],
];
?>
<header class="cabecalho">
    <div class="cabecalho__interno">
        <a class="cabecalho__marca" href="<?= url() ?>">
            <span class="cabecalho__logo">&lt;/&gt;</span>
            <?= e($nomeDoSite ?? 'Framework MVC') ?>
        </a>

        <nav class="menu">
            <?php foreach ($itens as $chave => $item): ?>
                <a
                    class="menu__item <?= $secao === $chave ? 'menu__item--ativo' : '' ?>"
                    href="<?= url($item['rota']) ?>"
                >
                    <?= e($item['texto']) ?>
                </a>
            <?php endforeach ?>

            <a class="menu__item menu__item--destaque" href="<?= url('alunos/criar') ?>">
                + Novo aluno
            </a>
        </nav>
    </div>
</header>
