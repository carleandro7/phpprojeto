<?php

namespace Testes\Nucleo;

use Nucleo\Sessao;
use Testes\Suporte\TesteBase;

class SessaoTest extends TesteBase
{
    public function preparar(): void
    {
        $this->limparSessao();
    }

    public function testeGeraUmTokenEstavelPorSessao(): void
    {
        $token = Sessao::token();

        $this->assertNaoVazio($token);
        $this->assertIgual($token, Sessao::token());
        $this->assertVerdadeiro(strlen($token) >= 32);
    }

    public function testeValidaApenasOTokenCorreto(): void
    {
        $token = Sessao::token();

        $this->assertVerdadeiro(Sessao::tokenValido($token));
        $this->assertFalso(Sessao::tokenValido('token-invalido'));
        $this->assertFalso(Sessao::tokenValido(''));
        $this->assertFalso(Sessao::tokenValido(null));
        $this->assertFalso(Sessao::tokenValido(['nao', 'e', 'texto']));
    }

    public function testeRegenerarTrocaOToken(): void
    {
        $antigo = Sessao::token();

        Sessao::regenerar();

        $this->assertDiferente($antigo, Sessao::token());
        $this->assertFalso(Sessao::tokenValido($antigo));
    }

    /** Regenerar preserva o que ja estava guardado na sessao. */
    public function testeRegenerarMantemOsDados(): void
    {
        Sessao::definir('autenticacao_id', 7);

        Sessao::regenerar();

        $this->assertIgual(7, Sessao::obter('autenticacao_id'));
    }
}
