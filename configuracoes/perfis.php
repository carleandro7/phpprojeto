<?php

/**
 * Perfis de acesso de cada tela de login.
 *
 * Estar logado e uma coisa; ter permissao e outra. O perfil responde a
 * segunda: em um sistema escolar, o aluno e o coordenador estao os dois
 * logados, e so um deles pode apagar uma turma.
 *
 * Este arquivo e escrito pelo comando:
 *
 *     php console.php auth:perfis admin,coordenador,professor
 *
 * O formato e:
 *
 *     'provider' => [
 *         'modelo' => 'Usuario',                     // model das contas
 *         'perfis' => ['admin' => 'Administrador'],  // chave => o que aparece na tela
 *     ]
 *
 * O provider padrao (a tela de login em /auth) e a chave ''. Um provider
 * chamado "professor" (/auth-professor) e a chave 'professor'.
 *
 * Depois de configurado, use no controller:
 *
 *     $this->exigirPerfil('admin');
 *     $this->exigirPerfil(['admin', 'coordenador']);
 *
 * e nas views:
 *
 *     <?php if (tem_perfil('admin')): ?> ... <?php endif ?>
 *
 * Voce pode editar os rotulos a vontade. Mudar uma CHAVE exige atualizar os
 * registros que ja usavam a chave antiga no banco.
 */

return [
    // auth:perfis
];
