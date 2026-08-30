<?php

namespace Testes\Nucleo;

use Nucleo\Autenticavel;
use Nucleo\Database;
use Nucleo\Model;
use Nucleo\Sessao;
use Testes\Suporte\TesteBase;

class ModeloAutenticavelTeste extends Model
{
    use Autenticavel;

    protected string $tabela = 'autenticaveis_teste';
    protected array $preenchiveis = ['nome', 'email', 'senha'];
}

class AutenticavelTest extends TesteBase
{
    private ModeloAutenticavelTeste $modelo;

    public function preparar(): void
    {
        Database::conexao()->exec('CREATE TABLE IF NOT EXISTS autenticaveis_teste (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NULL, email TEXT NULL, senha TEXT NULL)');
        $this->limparTabela('autenticaveis_teste');
        $this->limparSessao();
        $this->modelo = new ModeloAutenticavelTeste();
    }

    public function testeCriaEValidaSenhaComHash(): void
    {
        $id = $this->modelo->criarComSenha([
            'nome' => 'Ana',
            'email' => 'ana@example.com',
        ], 'segredo123');
        $registro = $this->modelo->buscar($id);

        $this->assertNaoNulo($registro);
        $this->assertDiferente('segredo123', $registro['senha']);
        $this->assertVerdadeiro(password_verify('segredo123', $registro['senha']));
        $this->assertNaoNulo($this->modelo->buscarPorEmail('ana@example.com'));
        $this->assertNaoNulo($this->modelo->autenticar('ana@example.com', 'segredo123'));
        $this->assertNulo($this->modelo->autenticar('ana@example.com', 'senha-errada'));
    }

    public function testeExigeEmailSenhaESeisCaracteres(): void
    {
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->criar([
            'nome' => 'Ana',
            'senha' => 'segredo',
        ]));
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->criar([
            'nome' => 'Ana',
            'email' => 'ana@example.com',
        ]));
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->criarComSenha([
            'nome' => 'Ana',
            'email' => 'ana@example.com',
        ], '12345'));
        $this->assertIgual(0, $this->modelo->contar());
    }

    public function testeHelperAceitaSessaoGenerica(): void
    {
        $this->assertFalso(autenticado());
        Sessao::definir('autenticacao_id', 7);
        $this->assertVerdadeiro(autenticado());
        $this->assertIgual(7, usuario_id());
    }

    public function testeHelperIsolaProviders(): void
    {
        $this->assertFalso(autenticado('professor'));
        Sessao::definir('autenticacao_id', 7);
        Sessao::definir(Sessao::chaveAutenticacao('professor'), 8);
        $this->assertVerdadeiro(autenticado('professor'));
        $this->assertIgual(8, usuario_id('professor'));
        $this->assertIgual(7, usuario_id());
    }
}