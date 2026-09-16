<?php

/**
 * Testes dos PERFIS de acesso.
 *
 * Rode so estes:  php testes/executar.php PerfisTest
 *
 * O arquivo usa namespaces com chaves porque precisa de duas coisas em
 * lugares diferentes: o controller de mentira tem que morar em Controllers
 * para o roteador encontra-lo em /area-restrita.
 */

namespace Controllers {

    /** Controller de mentira, so para exercitar o exigirPerfil(). */
    class AreaRestritaController extends \Nucleo\Controller
    {
        public function index(): void
        {
            $this->exigirPerfil('admin', \Testes\Nucleo\PerfisTest::PROVIDER);

            echo 'conteudo da area restrita';
        }
    }
}

namespace Testes\Nucleo {

    use Nucleo\Autenticacao;
    use Nucleo\Autenticavel;
    use Nucleo\Config;
    use Nucleo\Model;
    use Nucleo\Perfis;
    use Nucleo\Sessao;
    use Testes\Suporte\TesteBase;

    class ContaComPerfilTeste extends Model
    {
        use Autenticavel;

        protected string $tabela = 'contas_perfil_teste';
        protected array $preenchiveis = ['nome', 'email', 'senha', 'perfil'];
    }

    class PerfisTest extends TesteBase
    {
        /**
         * Login proprio do teste.
         *
         * exigirPerfil() chama exigirAutenticacao() antes, e este so aceita
         * um provider instalado. Se o teste usasse o /auth do projeto, ele
         * passaria ou falharia conforme o que o aluno tivesse gerado — entao
         * ele instala o seu, e desinstala no finalizar().
         */
        public const PROVIDER = 'perfil_teste';

        private function arquivoDoProvider(): string
        {
            return CAMINHO_CONTROLLERS . '/' . Autenticacao::controlador(self::PROVIDER) . '.php';
        }

        public function preparar(): void
        {
            $this->limparSessao();
            Perfis::esquecer();

            file_put_contents(
                $this->arquivoDoProvider(),
                "<?php\n\nnamespace Controllers;\n\n"
                . "/** Criado e apagado pelo PerfisTest. */\n"
                . 'class ' . Autenticacao::controlador(self::PROVIDER) . " extends \\Nucleo\\Controller\n{\n}\n"
            );

            $this->recriarTabelas([
                'contas_perfil_teste' => 'CREATE TABLE contas_perfil_teste (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(255) NULL,
                    email VARCHAR(255) NULL,
                    senha VARCHAR(255) NULL,
                    perfil VARCHAR(255) NULL
                )',
            ]);

            Config::definir('perfis', [
                self::PROVIDER => [
                    'modelo' => ContaComPerfilTeste::class,
                    'perfis' => ['admin' => 'Administrador', 'operador' => 'Operador'],
                ],
            ]);
        }

        public function finalizar(): void
        {
            if (is_file($this->arquivoDoProvider())) {
                unlink($this->arquivoDoProvider());
            }
        }

        public function testeLeAConfiguracao(): void
        {
            $this->assertIgual(['admin' => 'Administrador', 'operador' => 'Operador'], Perfis::configurados(self::PROVIDER));
            $this->assertIgual(['admin', 'operador'], Perfis::chaves(self::PROVIDER));
            $this->assertVerdadeiro(Perfis::ativo(self::PROVIDER));
            $this->assertIgual(ContaComPerfilTeste::class, Perfis::modelo(self::PROVIDER));
        }

        public function testeProviderSemPerfisConfigurados(): void
        {
            $this->assertFalso(Perfis::ativo('nao_configurado'));
            $this->assertVazio(Perfis::chaves('nao_configurado'));
            $this->assertNulo(Perfis::atual('nao_configurado'));
        }

        public function testeVisitanteSemLoginNaoTemPerfil(): void
        {
            $this->assertNulo(Perfis::atual(self::PROVIDER));
            $this->assertFalso(Perfis::tem('admin', self::PROVIDER));
        }

        public function testeLeOPerfilDeQuemEstaLogado(): void
        {
            $this->entrarComo('admin');

            $this->assertIgual('admin', Perfis::atual(self::PROVIDER));
            $this->assertVerdadeiro(Perfis::tem('admin', self::PROVIDER));
            $this->assertFalso(Perfis::tem('operador', self::PROVIDER));
            $this->assertVerdadeiro(Perfis::tem(['operador', 'admin'], self::PROVIDER), 'aceita lista de perfis');
        }

        public function testeContaSemPerfilNaoPassaEmNenhum(): void
        {
            $this->entrarComo(null);

            $this->assertNulo(Perfis::atual(self::PROVIDER));
            $this->assertFalso(Perfis::tem('admin', self::PROVIDER));
            $this->assertFalso(Perfis::tem(['admin', 'operador'], self::PROVIDER));
        }

        /**
         * O perfil vem do banco, nao da sessao: tirar o admin de alguem vale
         * na hora, sem esperar o proximo login.
         */
        public function testeMudancaDePerfilValeNaMesmaSessao(): void
        {
            $id = $this->entrarComo('admin');

            $this->assertVerdadeiro(Perfis::tem('admin', self::PROVIDER));

            (new ContaComPerfilTeste())->atualizar($id, ['perfil' => 'operador']);
            Perfis::esquecer();

            $this->assertFalso(Perfis::tem('admin', self::PROVIDER));
            $this->assertVerdadeiro(Perfis::tem('operador', self::PROVIDER));
        }

        public function testeRotuloLegivel(): void
        {
            $this->assertIgual('Administrador', Perfis::rotulo('admin', self::PROVIDER));
            $this->assertIgual('', Perfis::rotulo(null, self::PROVIDER));
            $this->assertIgual('', Perfis::rotulo('', self::PROVIDER));

            // Perfil fora da lista aparece como esta gravado, em vez de
            // sumir da tela sem explicacao.
            $this->assertIgual('antigo', Perfis::rotulo('antigo', self::PROVIDER));
        }

        // --------------------------------------------------------------
        // exigirPerfil() no controller
        // --------------------------------------------------------------

        public function testeExigirPerfilDeixaPassarQuemTem(): void
        {
            $this->entrarComo('admin');

            $resposta = $this->requisitar('area-restrita');

            $this->assertIgual(200, $resposta->status);
            $this->assertContem('area restrita', $resposta->html);
        }

        public function testeExigirPerfilBarraQuemNaoTem(): void
        {
            $this->entrarComo('operador');

            $resposta = $this->requisitar('area-restrita');

            $this->assertVerdadeiro($resposta->foiRedirecionado());
            $this->assertNaoContem('conteudo da area restrita', $resposta->html);
        }

        /** Quem nem entrou vai para o login, nao para a mensagem de permissao. */
        public function testeVisitanteSemLoginVaiParaOLogin(): void
        {
            $this->limparSessao();
            Perfis::esquecer();

            $resposta = $this->requisitar('area-restrita');

            $this->assertVerdadeiro($resposta->foiRedirecionado());
            $this->assertNaoContem('conteudo da area restrita', $resposta->html);
        }

        // --------------------------------------------------------------
        // Apoio
        // --------------------------------------------------------------

        private function entrarComo(?string $perfil): int
        {
            $modelo = new ContaComPerfilTeste();

            $id = $modelo->criarComSenha([
                'nome'   => 'Conta ' . ($perfil ?? 'sem perfil'),
                'email'  => ($perfil ?? 'sem') . '@example.com',
                'perfil' => $perfil,
            ], 'segredo123');

            Sessao::definir(Sessao::chaveAutenticacao(self::PROVIDER), $id);
            Perfis::esquecer();

            return $id;
        }
    }
}
