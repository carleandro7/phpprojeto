<?php

namespace Testes\Nucleo;

use Nucleo\Autenticacao;
use Testes\Suporte\TesteBase;

class AutenticacaoTest extends TesteBase
{
    public function testeNormalizaOPrefixo(): void
    {
        $this->assertIgual('', Autenticacao::normalizar(null));
        $this->assertIgual('', Autenticacao::normalizar(''));
        $this->assertIgual('professor', Autenticacao::normalizar('Professor'));
        $this->assertIgual('area_aluno', Autenticacao::normalizar('area-aluno'));
    }

    public function testeMontaAsRotasDoProvider(): void
    {
        $this->assertIgual('auth/login', Autenticacao::rotaLogin());
        $this->assertIgual('auth/registrar', Autenticacao::rotaRegistrar());
        $this->assertIgual('auth/sair', Autenticacao::rotaSair());

        $this->assertIgual('auth-professor/login', Autenticacao::rotaLogin('professor'));
        $this->assertIgual('auth-area-aluno/login', Autenticacao::rotaLogin('area_aluno'));
    }

    public function testeMontaOControladorEAPastaDeViews(): void
    {
        $this->assertIgual('AuthController', Autenticacao::controlador());
        $this->assertIgual('AuthProfessorController', Autenticacao::controlador('professor'));
        $this->assertIgual('AuthAreaAlunoController', Autenticacao::controlador('area_aluno'));

        $this->assertIgual('auth', Autenticacao::pastaViews());
        $this->assertIgual('auth/professor', Autenticacao::pastaViews('professor'));
    }

    /**
     * Sem nenhum provider instalado o erro precisa dizer o que fazer,
     * em vez de mandar o visitante para um 404.
     */
    public function testeExplicaQuandoNaoHaTelaDeLogin(): void
    {
        if (Autenticacao::providers() !== []) {
            return; // o projeto ja instalou algum provider
        }

        $this->assertExcecao(
            \RuntimeException::class,
            fn () => Autenticacao::resolver()
        );
    }
}
