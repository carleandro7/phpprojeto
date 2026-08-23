-- Estrutura das tabelas para SQLite (driver alternativo do framework).
-- Executado por: php instalar.php

DROP TABLE IF EXISTS alunos;

CREATE TABLE alunos (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    nome      TEXT    NOT NULL,
    email     TEXT    NOT NULL UNIQUE,
    -- Guarda o HASH da senha (password_hash), nunca a senha digitada.
    -- Aceita NULL: quem esta sem senha simplesmente nao consegue entrar.
    senha     TEXT        NULL,
    curso     TEXT    NOT NULL,
    nota      REAL        NULL,
    criado_em TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_alunos_curso ON alunos (curso);
