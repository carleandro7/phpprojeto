-- Registros de exemplo para a turma ter dados na tela desde o primeiro acesso.
-- Funciona tanto em SQLite quanto em MySQL.
--
-- SENHA DE TODOS OS ALUNOS DE EXEMPLO: 123456
--
-- Repare que os oito hashes abaixo sao DIFERENTES entre si, mesmo todos
-- vindo da mesma senha "123456". Isso acontece porque password_hash() sorteia
-- um "sal" (salt) novo a cada chamada e o guarda dentro do proprio hash.
-- E por isso que duas pessoas com a mesma senha nao tem o mesmo registro no
-- banco — e que nao da para descobrir a senha comparando as linhas.

INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Ana Beatriz Souza',   'ana.souza@escola.br',    '$2y$10$2QzNiRSJcwdcnhrMKrz.wuUBAOO2uIbgL81lE9Nuet6w8k5/I0002', 'Informatica',    9.5);
INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Bruno Carvalho',      'bruno.c@escola.br',      '$2y$10$bV0EzLeuhISNb7ykQXcrpuZ3J5h6JRmj210WuWhtusDQcmexXlIS2', 'Informatica',    7.0);
INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Carla Menezes',       'carla.m@escola.br',      '$2y$10$b/vBwoIb9q4zOi4f/Pnl..HtJAJMiFy7IelH.ctx8kQgNdisdcpnO', 'Administracao',  8.25);
INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Diego Fontes',        'diego.fontes@escola.br', '$2y$10$7Ux7Lo.afgXqVfy/SS7Heuhu1rfT2cUbmzz3rASmGWtmA.fUgL/dC', 'Edificacoes',    5.5);
INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Elisa Prado',         'elisa.prado@escola.br',  '$2y$10$INIINNaDDItIpN6dpTOVeenWn/xvsxAsUpt3IuOCJZJ4tL0Yn74yW', 'Enfermagem',     10.0);
INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Felipe Andrade',      'felipe.a@escola.br',     '$2y$10$EWqoYDy7W2Gl0zBeXujlquBpz9QzC7RsbpVGA9EdJdAlXO/669xGe', 'Eletrotecnica',  4.0);
INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Gabriela Nunes',      'gabi.nunes@escola.br',   '$2y$10$x9LcIII3W11w5QAM30irvee8I08ACG/Bnb.3jvTCFEX9q2xFhKffy', 'Informatica',    6.75);
INSERT INTO alunos (nome, email, senha, curso, nota) VALUES ('Henrique Lopes',      'henrique.l@escola.br',   '$2y$10$pnyYcTLJCrdsUOJBEGO.qOMvYVGFpFSZCyvBTBqunHzNHU5t5iORe', 'Administracao',  NULL);
