<?php

namespace Testes\Suporte;

use Nucleo\App;
use Nucleo\Database;
use Nucleo\NaoEncontradoException;
use Nucleo\RedirecionamentoException;
use Nucleo\Sessao;
use Nucleo\Sql;
use Nucleo\View;
use Throwable;

/**
 * Classe base de TODOS os testes.
 *
 * Como escrever um teste:
 *   1. Crie um arquivo terminando em "Test.php" dentro de uma das subpastas
 *      de testes/ (modelos, controllers, nucleo, exemplos...).
 *   2. O namespace acompanha a pasta e a classe tem o nome do arquivo.
 *   3. A classe herda de TesteBase.
 *   4. Cada metodo publico que comece com "teste" e executado automaticamente.
 *
 * Exemplo (testes/modelos/CalculadoraTest.php):
 *
 *     namespace Testes\Modelos;
 *
 *     use Testes\Suporte\TesteBase;
 *
 *     class CalculadoraTest extends TesteBase
 *     {
 *         public function testeSomaDoisNumeros(): void
 *         {
 *             $this->assertIgual(4, 2 + 2);
 *         }
 *     }
 *
 * Rode com:  php testes/executar.php
 */
abstract class TesteBase
{
    /** Quantas verificacoes este teste ja fez. */
    private int $assercoes = 0;

    // ------------------------------------------------------------------
    // Ganchos: rodam antes e depois de CADA metodo de teste
    // ------------------------------------------------------------------

    /**
     * Preparacao. Sobrescreva para deixar o cenario pronto
     * (limpar tabelas, cadastrar registros de apoio...).
     */
    public function preparar(): void
    {
    }

    /**
     * Limpeza. Sobrescreva se precisar desfazer algo.
     */
    public function finalizar(): void
    {
    }

    // ------------------------------------------------------------------
    // Verificacoes (assercoes)
    // ------------------------------------------------------------------

    /** Os dois valores sao iguais? (comparacao solta, 5 == '5') */
    public function assertIgual(mixed $esperado, mixed $obtido, string $mensagem = ''): void
    {
        $this->verificar(
            $esperado == $obtido,
            $mensagem ?: sprintf(
                'Esperava %s mas recebeu %s',
                $this->descrever($esperado),
                $this->descrever($obtido)
            )
        );
    }

    /** Os dois valores sao iguais E do mesmo tipo? (5 !== '5') */
    public function assertIdentico(mixed $esperado, mixed $obtido, string $mensagem = ''): void
    {
        $this->verificar(
            $esperado === $obtido,
            $mensagem ?: sprintf(
                'Esperava %s (identico) mas recebeu %s',
                $this->descrever($esperado),
                $this->descrever($obtido)
            )
        );
    }

    public function assertDiferente(mixed $naoEsperado, mixed $obtido, string $mensagem = ''): void
    {
        $this->verificar(
            $naoEsperado != $obtido,
            $mensagem ?: 'Esperava um valor diferente de ' . $this->descrever($naoEsperado)
        );
    }

    public function assertVerdadeiro(mixed $condicao, string $mensagem = ''): void
    {
        $this->verificar(
            $condicao === true,
            $mensagem ?: 'Esperava true mas recebeu ' . $this->descrever($condicao)
        );
    }

    public function assertFalso(mixed $condicao, string $mensagem = ''): void
    {
        $this->verificar(
            $condicao === false,
            $mensagem ?: 'Esperava false mas recebeu ' . $this->descrever($condicao)
        );
    }

    public function assertNulo(mixed $valor, string $mensagem = ''): void
    {
        $this->verificar(
            $valor === null,
            $mensagem ?: 'Esperava null mas recebeu ' . $this->descrever($valor)
        );
    }

    public function assertNaoNulo(mixed $valor, string $mensagem = ''): void
    {
        $this->verificar($valor !== null, $mensagem ?: 'Nao esperava null');
    }

    public function assertVazio(mixed $valor, string $mensagem = ''): void
    {
        $this->verificar(
            empty($valor),
            $mensagem ?: 'Esperava vazio mas recebeu ' . $this->descrever($valor)
        );
    }

    public function assertNaoVazio(mixed $valor, string $mensagem = ''): void
    {
        $this->verificar(!empty($valor), $mensagem ?: 'Esperava algum conteudo, veio vazio');
    }

    /** O texto contem o trecho procurado? */
    public function assertContem(string $trecho, string $texto, string $mensagem = ''): void
    {
        $this->verificar(
            str_contains($texto, $trecho),
            $mensagem ?: "Esperava encontrar \"{$trecho}\" no texto"
        );
    }

    public function assertNaoContem(string $trecho, string $texto, string $mensagem = ''): void
    {
        $this->verificar(
            !str_contains($texto, $trecho),
            $mensagem ?: "Nao esperava encontrar \"{$trecho}\" no texto"
        );
    }

    /** O array tem exatamente esta quantidade de itens? */
    public function assertTotal(int $esperado, array|\Countable $itens, string $mensagem = ''): void
    {
        $total = count($itens);

        $this->verificar(
            $total === $esperado,
            $mensagem ?: "Esperava {$esperado} item(ns) mas encontrou {$total}"
        );
    }

    /** O array possui esta chave? */
    public function assertTemChave(string|int $chave, array $array, string $mensagem = ''): void
    {
        $this->verificar(
            array_key_exists($chave, $array),
            $mensagem ?: "Esperava a chave \"{$chave}\" no array"
        );
    }

    /** O valor esta dentro do array? */
    public function assertTemValor(mixed $valor, array $array, string $mensagem = ''): void
    {
        $this->verificar(
            in_array($valor, $array, false),
            $mensagem ?: 'Esperava encontrar ' . $this->descrever($valor) . ' no array'
        );
    }

    public function assertInstanciaDe(string $classe, mixed $objeto, string $mensagem = ''): void
    {
        $this->verificar(
            $objeto instanceof $classe,
            $mensagem ?: "Esperava uma instancia de {$classe}"
        );
    }

    /**
     * A funcao passada dispara a excecao esperada?
     *
     *     $this->assertExcecao(InvalidArgumentException::class, function () {
     *         $modelo->onde('nome; DROP TABLE', 'x');
     *     });
     */
    public function assertExcecao(string $classeEsperada, callable $funcao, string $mensagem = ''): void
    {
        try {
            $funcao();
        } catch (Throwable $e) {
            $this->verificar(
                $e instanceof $classeEsperada,
                $mensagem ?: sprintf(
                    'Esperava %s mas veio %s (%s)',
                    $classeEsperada,
                    get_class($e),
                    $e->getMessage()
                )
            );

            return;
        }

        $this->verificar(false, $mensagem ?: "Esperava a excecao {$classeEsperada}, mas nada foi disparado");
    }

    /**
     * Marca o teste como falho na hora (util dentro de um if).
     */
    public function falhar(string $mensagem): never
    {
        $this->assercoes++;

        throw new FalhaAssercao($mensagem);
    }

    // ------------------------------------------------------------------
    // Ajudantes para testar CONTROLADORES (teste de integracao)
    // ------------------------------------------------------------------

    /**
     * Simula um acesso do navegador e devolve a resposta gerada.
     *
    *     $r = $this->requisitar('produtos');
    *     $r = $this->requisitar('produtos/salvar', 'POST', ['nome' => 'Ana']);
     */
    protected function requisitar(string $url, string $metodo = 'GET', array $dados = []): Resposta
    {
        $metodo = strtoupper($metodo);

        // Monta um ambiente de requisicao limpo.
        $_GET  = [];
        $_POST = [];

        $_SERVER['REQUEST_METHOD'] = $metodo;
        $_SERVER['SCRIPT_NAME']    = '/index.php';
        $_SERVER['REQUEST_URI']    = '/' . trim($url, '/');

        if ($metodo === 'POST') {
            $_POST = $dados;
        } else {
            $_GET = $dados;
        }

        $_GET['url'] = trim($url, '/');

        try {
            return new Resposta((new App())->despachar($url), 200, null);
        } catch (RedirecionamentoException $e) {
            return new Resposta('', $e->status, $e->destino);
        } catch (NaoEncontradoException $e) {
            $html = View::capturar('erros/404', [
                'titulo'   => 'Pagina nao encontrada',
                'mensagem' => $e->getMessage(),
                'rota'     => $url,
            ]);

            return new Resposta($html, 404, null);
        }
    }

    /**
     * Atalho para POST. O token anti-CSRF entra sozinho, entao o teste so
     * precisa se preocupar com os campos do formulario.
     */
    protected function postar(string $url, array $dados = []): Resposta
    {
        $dados['_token'] ??= Sessao::token();

        return $this->requisitar($url, 'POST', $dados);
    }

    /**
     * POST sem o token, para verificar a protecao contra CSRF:
     * a acao deve recusar o envio.
     */
    protected function postarSemToken(string $url, array $dados = []): Resposta
    {
        unset($dados['_token']);

        return $this->requisitar($url, 'POST', $dados);
    }

    // ------------------------------------------------------------------
    // Ajudantes para testar MODELOS (banco de dados)
    // ------------------------------------------------------------------

    /**
     * Recria as tabelas usadas pelo teste, apagando as versoes que outras
     * classes de teste tenham criado antes.
     *
     *     $this->recriarTabelas([
     *         'turmas'     => 'CREATE TABLE turmas (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NULL)',
     *         'matriculas' => 'CREATE TABLE matriculas (...)',
     *     ]);
     *
     * Todas as classes compartilham o mesmo banco de testes. Sem recriar, um
     * "CREATE TABLE IF NOT EXISTS" nao faria nada quando outra classe ja
     * tivesse criado a tabela com menos colunas, e o resultado passaria a
     * depender da ordem em que os testes rodam.
     *
     * Informe as tabelas pai antes das filhas.
     *
     * @param array<string,string> $tabelas nome => comando CREATE TABLE
     */
    protected function recriarTabelas(array $tabelas): void
    {
        $pdo = Database::conexao();

        // As chaves estrangeiras ficam desligadas so durante a recriacao,
        // senao apagar uma tabela pai esbarraria nas filhas de outros testes.
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            // Outra classe de teste pode ter deixado registros apontando para
            // estas tabelas. Sem limpar, apagar um registro aqui esbarraria
            // na chave estrangeira daquela outra tabela.
            $this->limparDependentes(array_keys($tabelas));

            foreach ($tabelas as $nome => $definicao) {
                $pdo->exec('DROP TABLE IF EXISTS ' . Sql::identificador($nome, 'tabela'));
                $pdo->exec($definicao);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Esvazia as tabelas que possuem chave estrangeira para alguma das
     * tabelas informadas. Usado por recriarTabelas().
     *
     * @param list<string> $tabelas
     */
    private function limparDependentes(array $tabelas): void
    {
        if ($tabelas === []) {
            return;
        }

        $pdo = Database::conexao();

        $consulta = $pdo->prepare(
            'SELECT DISTINCT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME IN (' . implode(', ', array_fill(0, count($tabelas), '?')) . ')'
        );

        $consulta->execute(array_values($tabelas));

        foreach ($consulta->fetchAll(\PDO::FETCH_COLUMN) as $tabela) {
            if (in_array($tabela, $tabelas, true)) {
                continue;
            }

            $pdo->exec('DELETE FROM ' . Sql::identificador($tabela, 'tabela'));
        }
    }

    /**
     * Apaga todos os registros de uma tabela.
     * Chame no preparar() para cada teste comecar do zero.
     */
    protected function limparTabela(string $tabela): void
    {
        // Mesmo em teste o nome da tabela passa pela validacao:
        // e o mesmo cuidado que o Model tem.
        $tabela = Sql::identificador($tabela, 'tabela');

        $pdo = Database::conexao();
        $pdo->exec("DELETE FROM {$tabela}");

        // Zera o contador do AUTO_INCREMENT para os ids comecarem sempre em 1.
        $pdo->exec("ALTER TABLE {$tabela} AUTO_INCREMENT = 1");
    }

    /**
     * Limpa tambem a sessao (mensagens flash, dados de formulario).
     */
    protected function limparSessao(): void
    {
        Sessao::limpar();
    }

    /**
     * Conta as linhas de uma tabela direto no banco, sem passar pelo modelo
     * (assim o teste nao depende do codigo que ele esta verificando).
     */
    protected function contarNaTabela(string $tabela): int
    {
        $tabela = Sql::identificador($tabela, 'tabela');

        return (int) Database::conexao()
            ->query("SELECT COUNT(*) FROM {$tabela}")
            ->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Uso interno do Executor
    // ------------------------------------------------------------------

    public function totalDeAssercoes(): int
    {
        return $this->assercoes;
    }

    private function verificar(bool $condicao, string $mensagem): void
    {
        $this->assercoes++;

        if (!$condicao) {
            throw new FalhaAssercao($mensagem);
        }
    }

    /**
     * Transforma qualquer valor em texto legivel para a mensagem de erro.
     */
    private function descrever(mixed $valor): string
    {
        return match (true) {
            is_null($valor)   => 'null',
            is_bool($valor)   => $valor ? 'true' : 'false',
            is_string($valor) => '"' . (mb_strlen($valor) > 60 ? mb_substr($valor, 0, 60) . '...' : $valor) . '"',
            is_array($valor)  => 'array(' . count($valor) . ')',
            is_object($valor) => get_class($valor),
            default           => (string) $valor,
        };
    }
}
