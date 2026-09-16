<?php

/**
 * DADOS INICIAIS DO SISTEMA
 * =========================
 *
 * Aqui ficam os dados que o sistema precisa ter: as categorias do catalogo,
 * os status de um pedido, a conta de administrador — e alguns registros de
 * exemplo, para as telas nao nascerem vazias.
 *
 * Rode com:
 *
 *     php console.php db:semear
 *
 * Este e um arquivo PHP comum: voce escreve os dados, ele cria. Tudo roda
 * dentro de uma transacao, entao um erro no meio nao deixa metade gravada.
 *
 * ---------------------------------------------------------------------
 * AS TRES FUNCOES
 * ---------------------------------------------------------------------
 *
 *   semear('tabela', [ ['coluna' => valor], ... ])
 *       cria os registros e devolve os ids criados.
 *
 *   semear('tabela', [...], 'coluna')
 *       mesma coisa, mas PULA quem ja existe com aquele valor e devolve os
 *       ids indexados por ele. E o que deixa rodar o comando quantas vezes
 *       quiser sem duplicar nada.
 *
 *   falsos('tabela', 50)
 *       enche a tabela com registros inventados a partir do nome e do tipo
 *       de cada coluna ("preco" vira dinheiro, "email" vira e-mail). Serve
 *       para ver listagem, pesquisa e paginacao funcionando.
 *
 *   limpar()                  apaga tudo antes de comecar
 *   limpar('produtos')        apaga so o que voce indicar
 *
 * Uma coluna chamada "senha" recebe password_hash() sozinha: escreva a senha
 * em texto puro aqui, que ela chega cifrada ao banco.
 *
 * A ordem importa: crie a tabela pai antes da filha.
 * ---------------------------------------------------------------------
 *
 * Descomente os exemplos abaixo e troque pelos seus dados.
 */

// Comece do zero a cada execucao. Cuidado: apaga os dados do banco da
// aplicacao. Deixe comentado se quiser so acrescentar.
// limpar();


// ---------------------------------------------------------------------
// 1. Dados que o sistema precisa ter
// ---------------------------------------------------------------------

// $categorias = semear('categorias', [
//     ['nome' => 'Eletronicos'],
//     ['nome' => 'Moveis'],
//     ['nome' => 'Papelaria'],
// ], 'nome');


// ---------------------------------------------------------------------
// 2. Registros que apontam para os de cima
// ---------------------------------------------------------------------

// semear('produtos', [
//     ['nome' => 'Teclado', 'preco' => 149.90, 'categoria_id' => $categorias['Eletronicos']],
//     ['nome' => 'Monitor', 'preco' => 899.00, 'categoria_id' => $categorias['Eletronicos']],
//     ['nome' => 'Mesa',    'preco' => 450.00, 'categoria_id' => $categorias['Moveis']],
// ]);


// ---------------------------------------------------------------------
// 3. A primeira conta do sistema
// ---------------------------------------------------------------------

// semear('usuarios', [
//     ['nome' => 'Administrador', 'email' => 'admin@example.com', 'senha' => 'segredo123'],
// ], 'email');


// ---------------------------------------------------------------------
// 4. Volume, para as telas nao ficarem vazias
// ---------------------------------------------------------------------

// falsos('produtos', 50);
