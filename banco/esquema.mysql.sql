-- Estrutura das tabelas para MySQL / MariaDB (XAMPP).
--
-- Normalmente voce NAO precisa executar este arquivo na mao:
-- "php instalar.php" cria o banco, roda este esquema e insere os dados
-- de exemplo (banco/dados_exemplo.sql) automaticamente.
--
-- Se quiser importar pelo phpMyAdmin mesmo assim:
--   1. Crie o banco:  CREATE DATABASE framework_aula CHARACTER SET utf8mb4;
--   2. Selecione o banco e importe este arquivo.
--   3. Depois importe banco/dados_exemplo.sql para ter os registros.
--
-- Atencao: o DROP TABLE abaixo apaga a tabela e todos os seus dados.

DROP TABLE IF EXISTS alunos;

CREATE TABLE alunos (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100)  NOT NULL,
    email     VARCHAR(150)  NOT NULL UNIQUE,
    -- Guarda o HASH da senha, nunca a senha digitada.
    --
    -- Por que 255 e nao 20, se a senha do aluno tem 6 caracteres?
    -- Porque nao e a senha que fica aqui: e o resultado do password_hash(),
    -- que hoje ocupa 60 caracteres (bcrypt) e pode crescer quando o PHP
    -- adotar um algoritmo mais novo. 255 e a folga recomendada no manual.
    --
    -- Aceita NULL para o caso de um registro antigo, importado antes de a
    -- tela de login existir. Quem esta sem senha simplesmente nao entra.
    senha     VARCHAR(255)      NULL,
    curso     VARCHAR(60)   NOT NULL,
    nota      DECIMAL(4,2)      NULL,
    criado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alunos_curso (curso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
