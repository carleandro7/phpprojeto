<?php

namespace Testes\Nucleo;

use Nucleo\Autenticacao;
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
        Database::conexao()->exec('CREATE TABLE IF NOT EXISTS autenticaveis_teste (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NULL, email VARCHAR(255) NULL, senha VARCHAR(255) NULL)');
        $this->limparTabela('autenticaveis_teste');
        $this->limparSessao();
        $this->modelo = new ModeloAutenticavelTeste();
    }

    public function testeCriaEValidaSenhaComHash(): void
    {
        $id = $this->modelo->criarComSenha([
            'nome'  => 'Ana',
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

    /**
     * O ponto central da correcao: criar() tambem aplica o hash.
     * Antes a senha ia para o banco em texto puro e o login nunca funcionava.
     */
    public function testeCriarTambemAplicaHash(): void
    {
        $id = $this->modelo->criar([
            'nome'  => 'Bia',
            'email' => 'bia@example.com',
            'senha' => 'segredo123',
        ]);

        $registro = $this->modelo->buscar($id);

        $this->assertDiferente('segredo123', $registro['senha']);
        $this->assertVerdadeiro(Autenticacao::ehHash($registro['senha']));
        $this->assertNaoNulo($this->modelo->autenticar('bia@example.com', 'segredo123'));
    }

    public function testeAtualizarTambemAplicaHash(): void
    {
        $id = $this->modelo->criarComSenha(['email' => 'ana@example.com'], 'segredo123');

        $this->assertVerdadeiro($this->modelo->atualizar($id, ['senha' => 'novasenha1']));
        $this->assertNaoNulo($this->modelo->autenticar('ana@example.com', 'novasenha1'));
        $this->assertNulo($this->modelo->autenticar('ana@example.com', 'segredo123'));
    }

    /** Senha em branco no formulario de edicao nao apaga a senha atual. */
    public function testeSenhaVaziaMantemASenhaAtual(): void
    {
        $id = $this->modelo->criarComSenha(['email' => 'ana@example.com'], 'segredo123');

        $this->modelo->atualizar($id, ['nome' => 'Ana Maria', 'senha' => '']);

        $this->assertIgual('Ana Maria', $this->modelo->buscar($id)['nome']);
        $this->assertNaoNulo($this->modelo->autenticar('ana@example.com', 'segredo123'));
    }

    /** Um hash pronto (seed, importacao) nao pode ser hasheado de novo. */
    public function testeNaoAplicaHashDuasVezes(): void
    {
        $hash = password_hash('segredo123', PASSWORD_DEFAULT);
        $id   = $this->modelo->criar(['email' => 'ana@example.com', 'senha' => $hash]);

        $this->assertIgual($hash, $this->modelo->buscar($id)['senha']);
        $this->assertNaoNulo($this->modelo->autenticar('ana@example.com', 'segredo123'));
    }

    /**
     * O CRUD gerado pelo scaffold grava sem email/senha. Isso precisa
     * continuar funcionando depois de auth:install no mesmo model.
     */
    public function testeCrudComumContinuaFuncionandoSemCredenciais(): void
    {
        $id = $this->modelo->criar(['nome' => 'Joao']);

        $this->assertVerdadeiro($id > 0);
        $this->assertIgual('Joao', $this->modelo->buscar($id)['nome']);
        $this->assertIgual(1, $this->modelo->contar());
    }

    public function testeCadastroDeContaExigeEmailESenhaValidos(): void
    {
        // Sem e-mail.
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->criarComSenha(
            ['nome' => 'Ana'],
            'segredo123'
        ));

        // E-mail invalido.
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->criarComSenha(
            ['email' => 'nao-e-um-email'],
            'segredo123'
        ));

        // Senha curta.
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->criarComSenha(
            ['email' => 'ana@example.com'],
            '12345'
        ));

        // Senha curta tambem e recusada por criar().
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->criar([
            'email' => 'ana@example.com',
            'senha' => '12345',
        ]));

        $this->assertIgual(0, $this->modelo->contar());
    }

    public function testeTrocaSenhaDeContaExistente(): void
    {
        $id = $this->modelo->criarComSenha(['email' => 'ana@example.com'], 'segredo123');

        $this->assertVerdadeiro($this->modelo->trocarSenha($id, 'outrasenha'));
        $this->assertNaoNulo($this->modelo->autenticar('ana@example.com', 'outrasenha'));
        $this->assertExcecao(\InvalidArgumentException::class, fn () => $this->modelo->trocarSenha($id, '123'));
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
