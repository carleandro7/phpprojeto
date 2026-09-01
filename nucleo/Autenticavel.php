<?php

namespace Nucleo;

use InvalidArgumentException;

/**
 * Da a um model a capacidade de fazer login.
 *
 * Basta usar o trait no model e ter as colunas "email" e "senha":
 *
 *     class Cliente extends \Nucleo\Model
 *     {
 *         use \Nucleo\Autenticavel;
 *
 *         protected string $tabela       = 'clientes';
 *         protected array  $preenchiveis = ['nome', 'email', 'senha'];
 *     }
 *
 * ------------------------------------------------------------------------
 * A REGRA DA SENHA
 * ------------------------------------------------------------------------
 * A senha NUNCA e gravada em texto puro. O trait intercepta criar() e
 * atualizar() e transforma a senha em hash antes de chegar ao banco:
 *
 *     $cliente->criar(['email' => 'ana@x.com', 'senha' => 'segredo123']);
 *     // grava um hash, nao "segredo123"
 *
 * Isso vale para qualquer caminho: o formulario do CRUD gerado pelo
 * scaffold, o cadastro do AuthController ou uma chamada sua no controller.
 *
 * Casos especiais tratados automaticamente:
 *
 *   - senha vazia (o formulario de edicao nao mexeu no campo)
 *     -> o campo e ignorado e a senha atual continua valendo;
 *   - senha que ja e um hash (um seed, uma copia de outro registro)
 *     -> passa direto, sem hash duplo;
 *   - nenhum campo "senha" no array
 *     -> o CRUD comum funciona normalmente, sem exigir credenciais.
 */
trait Autenticavel
{
    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------

    public function buscarPorEmail(string $email): ?array
    {
        return $this->primeiroOnde('email', trim($email));
    }

    /**
     * Confere e-mail e senha. Devolve o registro ou null.
     * A comparacao usa password_verify(), que compara o texto digitado
     * com o hash gravado sem nunca descriptografar nada.
     */
    public function autenticar(string $email, string $senha): ?array
    {
        $registro = $this->buscarPorEmail($email);

        if ($registro === null || !isset($registro['senha'])) {
            return null;
        }

        return password_verify($senha, (string) $registro['senha']) ? $registro : null;
    }

    // ------------------------------------------------------------------
    // Escrita com senha protegida
    // ------------------------------------------------------------------

    public function criar(array $dados): int
    {
        return parent::criar($this->protegerSenha($dados));
    }

    public function atualizar(int|string $id, array $dados): bool
    {
        return parent::atualizar($id, $this->protegerSenha($dados));
    }

    /**
     * Cadastro de conta: aqui e-mail e senha SAO obrigatorios.
     * Use este metodo na tela de registro.
     */
    public function criarComSenha(array $dados, string $senha): int
    {
        $this->exigirEmail($dados['email'] ?? null);
        $this->exigirSenha($senha);

        $dados['senha'] = $senha;

        return $this->criar($dados);
    }

    /**
     * Troca a senha de um registro que ja existe.
     */
    public function trocarSenha(int|string $id, string $senha): bool
    {
        $this->exigirSenha($senha);

        return $this->atualizar($id, ['senha' => $senha]);
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    /**
     * Garante que o que for gravado na coluna "senha" seja sempre um hash.
     */
    private function protegerSenha(array $dados): array
    {
        if (!array_key_exists('senha', $dados)) {
            return $dados;
        }

        $senha = $dados['senha'];

        // Campo em branco = "nao quero mexer na senha".
        if ($senha === null || (is_scalar($senha) && trim((string) $senha) === '')) {
            unset($dados['senha']);

            return $dados;
        }

        if (!is_scalar($senha)) {
            throw new InvalidArgumentException('A senha deve ser um texto.');
        }

        $senha = (string) $senha;

        if (Autenticacao::ehHash($senha)) {
            return $dados;
        }

        $this->exigirSenha($senha);
        $dados['senha'] = password_hash($senha, PASSWORD_DEFAULT);

        return $dados;
    }

    private function exigirEmail(mixed $email): void
    {
        if (!is_scalar($email) || trim((string) $email) === '') {
            throw new InvalidArgumentException('O campo email e obrigatorio para criar uma conta.');
        }

        if (!filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um e-mail valido.');
        }
    }

    private function exigirSenha(mixed $senha): void
    {
        if (!is_scalar($senha) || trim((string) $senha) === '') {
            throw new InvalidArgumentException('O campo senha e obrigatorio para criar uma conta.');
        }

        if (mb_strlen((string) $senha) < Autenticacao::SENHA_MINIMA) {
            throw new InvalidArgumentException(
                'A senha deve ter pelo menos ' . Autenticacao::SENHA_MINIMA . ' caracteres.'
            );
        }
    }
}
