<?php

/**
 * Itens da barra lateral de navegacao.
 *
 * O comando "php console.php scaffold:crud" adiciona uma linha aqui
 * automaticamente para cada CRUD gerado. Edite a vontade: mude o texto,
 * reordene ou remova o que nao deve aparecer no menu.
 *
 * Cada item aceita:
 *   'rota'   - destino, no mesmo formato de url(): '' e a pagina inicial
 *   'texto'  - o que aparece no menu
 *   'auth'   - opcional: 'sim' so mostra para quem esta logado,
 *              'nao' so mostra para quem NAO esta logado
 *   'perfil' - opcional: so mostra para quem tem esse perfil de acesso.
 *              Aceita um ('admin') ou varios (['admin', 'coordenador']).
 *              Veja configuracoes/perfis.php e o comando auth:perfis.
 *   'provider' - opcional: de qual tela de login sai o perfil acima
 *              ('professor' para /auth-professor). O padrao e /auth.
 *
 * Esconder o item e cortesia com quem usa, nao seguranca: quem souber o
 * endereco continua chegando la. Quem protege a rota e o exigirPerfil()
 * dentro do controller.
 *
 * Exemplo:
 *   ['rota' => 'usuarios', 'texto' => 'Usuarios', 'perfil' => 'admin'],
 */

return [
    ['rota' => '', 'texto' => 'Inicio'],
    // scaffold:crud
];
