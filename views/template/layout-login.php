<?php
/**
 * TEMPLATE DAS TELAS DE LOGIN
 *
 * Usado pelas telas de entrar e criar conta geradas por "auth:install".
 *
 * E uma pagina isolada de proposito: sem menu lateral e sem os atalhos do
 * sistema. Quem ainda nao entrou nao tem acesso a nenhuma daquelas telas,
 * entao mostrar o menu antes do login so confunde (e ainda entrega a lista
 * de recursos do sistema para quem nao esta autenticado).
 *
 * Variaveis disponiveis:
 *   $conteudo - HTML da tela (preenchido automaticamente)
 *   $titulo   - titulo da pagina (enviado pelo controlador)
 */

use Nucleo\Config;

$titulo     = $titulo ?? '';
$nomeDoSite = Config::obter('app.nome', 'Framework MVC');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo !== '' ? "{$titulo} | {$nomeDoSite}" : $nomeDoSite) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/estilo.css') ?>">
</head>
<body class="corpo-login">

    <main class="login">
        <div class="login__caixa">
            <a class="login__marca" href="<?= url() ?>">
                <span class="login__logo">&lt;/&gt;</span>
                <span><?= e($nomeDoSite) ?></span>
            </a>

            <?= parcial('template/mensagens') ?>

            <?= $conteudo ?>
        </div>

        <p class="login__rodape">
            <a href="<?= url() ?>">&larr; Voltar ao inicio</a>
        </p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('javascripts/app.js') ?>"></script>
</body>
</html>
