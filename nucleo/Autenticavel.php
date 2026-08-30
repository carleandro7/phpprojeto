<?php

namespace Nucleo;

use InvalidArgumentException;

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

    public function criar(array $dados): int
    {
        $this->exigirCredenciais($dados['email'] ?? null, $dados['senha'] ?? null);

        return parent::criar($dados);
    }

    public function criarComSenha(array $dados, string $senha): int
    {
        $this->exigirCredenciais($dados['email'] ?? null, $senha);
        $dados['senha'] = password_hash($senha, PASSWORD_DEFAULT);

        return $this->criar($dados);
    }

    private function exigirCredenciais(mixed $email, mixed $senha): void
    {
        if (!is_scalar($email) || trim((string) $email) === '') {
            throw new InvalidArgumentException('O campo email e obrigatorio para modelos autenticaveis.');
        }

        if (!is_scalar($senha) || trim((string) $senha) === '') {
            throw new InvalidArgumentException('O campo senha e obrigatorio para modelos autenticaveis.');
        }

        if (mb_strlen((string) $senha) < 6) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 6 caracteres.');
        }
    }
}