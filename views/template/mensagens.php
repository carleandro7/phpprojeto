<?php
/**
 * Exibe as mensagens rapidas (flash) gravadas pelos controladores:
 *
 *     $this->mensagem('sucesso', 'Registro cadastrado!');
 *
 * Depois de exibidas, as mensagens somem.
 */

$lista = mensagens();

if ($lista === []) {
    return;
}
?>
<div class="mensagens">
    <?php foreach ($lista as $mensagem): ?>
        <div class="alerta alerta--<?= e($mensagem['tipo']) ?>">
            <?= e($mensagem['texto']) ?>
        </div>
    <?php endforeach ?>
</div>
