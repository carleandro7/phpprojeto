/* =====================================================================
   JavaScript padrao do framework
   Carregado pelo template em views/template/layout.php
   ===================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    /* -----------------------------------------------------------------
       1. Confirmacao antes de acoes destrutivas
       Basta colocar data-confirmar="mensagem" no formulario ou link:

           <form method="POST" data-confirmar="Excluir mesmo?">
       ----------------------------------------------------------------- */
    document.querySelectorAll('[data-confirmar]').forEach(function (elemento) {
        elemento.addEventListener('submit', function (evento) {
            if (!window.confirm(elemento.dataset.confirmar)) {
                evento.preventDefault();
            }
        });

        elemento.addEventListener('click', function (evento) {
            if (elemento.tagName === 'A' && !window.confirm(elemento.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });

    /* -----------------------------------------------------------------
       2. Some com as mensagens de sucesso depois de alguns segundos
       ----------------------------------------------------------------- */
    document.querySelectorAll('.alerta--sucesso').forEach(function (alerta) {
        window.setTimeout(function () {
            alerta.style.transition = 'opacity .4s';
            alerta.style.opacity = '0';
            window.setTimeout(function () { alerta.remove(); }, 400);
        }, 4000);
    });

    /* -----------------------------------------------------------------
       3. Evita envio duplicado ao clicar duas vezes em "Salvar"
       ----------------------------------------------------------------- */
    document.querySelectorAll('form.formulario').forEach(function (formulario) {
        formulario.addEventListener('submit', function () {
            var botao = formulario.querySelector('button[type="submit"]');

            if (botao) {
                window.setTimeout(function () {
                    botao.disabled = true;
                    botao.textContent = 'Aguarde...';
                }, 0);
            }
        });
    });

    /* -----------------------------------------------------------------
       4. Coloca o cursor no primeiro campo do formulario
       ----------------------------------------------------------------- */
    var primeiroCampo = document.querySelector('.formulario input, .formulario select');

    if (primeiroCampo) {
        primeiroCampo.focus();
    }
});
