-- Estrutura das tabelas para MySQL / MariaDB (XAMPP).
--
-- Como usar:
--   1. No phpMyAdmin, crie o banco:  CREATE DATABASE framework_aula;
--   2. Selecione o banco e importe este arquivo.
--   3. Em configuracoes/banco.php troque 'driver' para 'mysql'.

DROP TABLE IF EXISTS alunos;

CREATE TABLE alunos (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100)  NOT NULL,
    email     VARCHAR(150)  NOT NULL UNIQUE,
    curso     VARCHAR(60)   NOT NULL,
    nota      DECIMAL(4,2)      NULL,
    criado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alunos_curso (curso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
