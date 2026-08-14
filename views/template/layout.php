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

    <link rel="stylesheet" href="<?= asset('css/estilo.css') ?>">
</head>
<body>

    <?= parcial('template/cabecalho', ['nomeDoSite' => $nomeDoSite]) ?>

    <main class="conteudo">
        <?= parcial('template/mensagens') ?>

        <?= $conteudo ?>
    </main>

    <?= parcial('template/rodape') ?>

    <script src="<?= asset('javascripts/app.js') ?>"></script>
</body>
</html>
