<?php
/**
 * TEMPLATE PRINCIPAL
 *
 * Toda tela do sistema e desenhada dentro deste arquivo.
 * A variavel $conteudo traz o HTML da view que foi chamada pelo controlador.
 *
 * Variaveis disponiveis:
 *   $conteudo - HTML da tela (preenchido automaticamente)
 *   $titulo   - titulo da pagina (enviado pelo controlador)
 */

use Nucleo\Config;

$titulo = $titulo ?? '';
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
<body>

    <?= parcial('template/cabecalho', ['nomeDoSite' => $nomeDoSite]) ?>

    <main class="app-main">
        <div class="container-fluid conteudo">
        <?= parcial('template/mensagens') ?>

        <?= $conteudo ?>
        </div>
    </main>

    <?= parcial('template/rodape') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('javascripts/app.js') ?>"></script>
</body>
</html>
