<?php
/**
 * Cabecalho com o menu de navegacao.
 * Incluido pelo template/layout.php.
 */

// Descobre a rota atual para destacar o item do menu.
$rotaAtual = trim((string) ($_GET['url'] ?? ''), '/');
$secao     = explode('/', $rotaAtual)[0] ?: 'home';

// Quem esta logado (ou null). Vem de Nucleo\Autenticacao pelo helper.
$logado = aluno_logado();

$itens = [
    'home'   => ['rota' => '',       'texto' => 'Inicio'],
    'alunos' => ['rota' => 'alunos', 'texto' => 'Alunos'],
];

// "Minha area" so faz sentido para quem entrou.
if ($logado !== null) {
    $itens['login'] = ['rota' => 'login/painel', 'texto' => 'Minha area'];
}
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

            <?php if ($logado === null): ?>
                <a
                    class="menu__item <?= $secao === 'login' ? 'menu__item--ativo' : '' ?>"
                    href="<?= url('login') ?>"
                >
                    Entrar
                </a>
            <?php else: ?>
                <span class="menu__usuario" title="<?= e($logado['email']) ?>">
                    <?= e($logado['nome']) ?>
                </span>

                <?php /*
                    Sair e um POST, nao um link: veja o comentario do metodo
                    sair() em controllers/LoginController.php.
                */ ?>
                <form method="POST" action="<?= url('login/sair') ?>" class="formulario-em-linha">
                    <button type="submit" class="menu__sair">Sair</button>
                </form>
            <?php endif ?>
        </nav>
    </div>
</header>
