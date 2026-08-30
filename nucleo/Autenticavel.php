<?php

namespace Nucleo;

trait Autenticavel
{
    public function buscarPorEmail(string $email): ?array
    {
        return $this->primeiroOnde('email', $email);
    }

    public function autenticar(string $email, string $senha): ?array
    {
        $registro = $this->buscarPorEmail($email);

        if ($registro === null || !isset($registro['senha'])) {
            return null;
        }

        return password_verify($senha, (string) $registro['senha']) ? $registro : null;
    }

    public function criarComSenha(array $dados, string $senha): int
    {
        $dados['senha'] = password_hash($senha, PASSWORD_DEFAULT);

        return $this->criar($dados);
    }
}