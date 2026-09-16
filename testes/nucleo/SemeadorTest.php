<?php

namespace Testes\Nucleo;

use Nucleo\Database;
use Nucleo\Semeador;
use RuntimeException;
use Testes\Suporte\TesteBase;

/**
 * Testes da SEMEADURA (banco/semear.php).
 *
 * Rode so estes:  php testes/executar.php SemeadorTest
 */
class SemeadorTest extends TesteBase
{
    private string $arquivo = '';

    public function preparar(): void
    {
        $this->recriarTabelas([
            'semear_categorias' => 'CREATE TABLE semear_categorias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NULL
            )',
            'semear_contas' => 'CREATE TABLE semear_contas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NULL,
                email VARCHAR(255) NULL,
                senha VARCHAR(255) NULL
            )',
            'semear_produtos' => "CREATE TABLE semear_produtos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NULL,
                preco DECIMAL(12,2) NULL,
                categoria_id INT NULL,
                CONSTRAINT fk_semear_produtos_categoria FOREIGN KEY (categoria_id)
                    REFERENCES semear_categorias(id)
            )",
        ]);

        $this->arquivo = sys_get_temp_dir() . '/framework_semear_teste.php';
    }

    public function finalizar(): void
    {
        if ($this->arquivo !== '' && is_file($this->arquivo)) {
            unlink($this->arquivo);
        }
    }

    // ------------------------------------------------------------------
    // semear()
    // ------------------------------------------------------------------

    public function testeCriaOsRegistrosInformados(): void
    {
        $ids = Semeador::criar('semear_categorias', [
            ['nome' => 'Eletronicos'],
            ['nome' => 'Moveis'],
        ]);

        $this->assertTotal(2, $ids);
        $this->assertIgual(2, $this->contar('semear_categorias'));
        $this->assertIgual('Eletronicos', $this->primeiro('semear_categorias')['nome']);
    }

    /**
     * Com chave, o retorno vem indexado por ela — e o que amarra as relacoes
     * sem precisar adivinhar ids.
     */
    public function testeChaveDevolveOsIdsIndexados(): void
    {
        $cat = Semeador::criar('semear_categorias', [
            ['nome' => 'Eletronicos'],
            ['nome' => 'Moveis'],
        ], 'nome');

        $this->assertTemChave('Eletronicos', $cat);
        $this->assertTemChave('Moveis', $cat);

        Semeador::criar('semear_produtos', [
            ['nome' => 'Mesa', 'preco' => 450.0, 'categoria_id' => $cat['Moveis']],
        ]);

        $this->assertIgual($cat['Moveis'], (int) $this->primeiro('semear_produtos')['categoria_id']);
    }

    /** Rodar duas vezes com chave nao duplica: e o que deixa repetir o comando. */
    public function testeChaveNaoDuplica(): void
    {
        $registros = [['nome' => 'Eletronicos'], ['nome' => 'Moveis']];

        $primeira = Semeador::criar('semear_categorias', $registros, 'nome');
        $segunda  = Semeador::criar('semear_categorias', $registros, 'nome');

        $this->assertIgual(2, $this->contar('semear_categorias'));

        // E os ids devolvidos continuam os mesmos, entao as relacoes de
        // quem depende deles nao quebram na segunda execucao.
        $this->assertIgual($primeira, $segunda);
    }

    /** Sem chave, duplica — igual ao Rails. Para recomecar existe o limpar(). */
    public function testeSemChaveDuplica(): void
    {
        Semeador::criar('semear_categorias', [['nome' => 'Eletronicos']]);
        Semeador::criar('semear_categorias', [['nome' => 'Eletronicos']]);

        $this->assertIgual(2, $this->contar('semear_categorias'));
    }

    /**
     * A senha escrita em texto puro no arquivo chega cifrada ao banco: sem
     * isso a conta semeada nunca entraria pela tela de login.
     */
    public function testeSenhaEntraCifrada(): void
    {
        Semeador::criar('semear_contas', [
            ['nome' => 'Admin', 'email' => 'admin@example.com', 'senha' => 'segredo123'],
        ]);

        $conta = $this->primeiro('semear_contas');

        $this->assertDiferente('segredo123', $conta['senha']);
        $this->assertVerdadeiro(password_verify('segredo123', $conta['senha']));
    }

    /** Uma senha que ja veio cifrada nao e cifrada de novo. */
    public function testeNaoCifraDuasVezes(): void
    {
        $hash = password_hash('segredo123', PASSWORD_DEFAULT);

        Semeador::criar('semear_contas', [
            ['nome' => 'Admin', 'email' => 'a@example.com', 'senha' => $hash],
        ]);

        $this->assertIgual($hash, $this->primeiro('semear_contas')['senha']);
    }

    public function testeRecusaColunaQueNaoExiste(): void
    {
        $this->assertExcecao(RuntimeException::class, function (): void {
            Semeador::criar('semear_categorias', [['nome' => 'X', 'inventada' => 1]]);
        });
    }

    public function testeRecusaTabelaQueNaoExiste(): void
    {
        $this->assertExcecao(RuntimeException::class, function (): void {
            Semeador::criar('tabela_inexistente', [['nome' => 'X']]);
        });
    }

    // ------------------------------------------------------------------
    // falsos()
    // ------------------------------------------------------------------

    public function testeEncheATabela(): void
    {
        $ids = Semeador::inventar('semear_categorias', 12);

        $this->assertTotal(12, $ids);
        $this->assertIgual(12, $this->contar('semear_categorias'));
        $this->assertNaoVazio($this->primeiro('semear_categorias')['nome']);
    }

    /** A chave estrangeira aponta para um pai que existe de verdade. */
    public function testeRelacaoSorteiaUmPaiExistente(): void
    {
        Semeador::criar('semear_categorias', [['nome' => 'Uma'], ['nome' => 'Outra']]);
        Semeador::inventar('semear_produtos', 8);

        $orfaos = Database::conexao()->query(
            'SELECT COUNT(*) FROM semear_produtos p
             LEFT JOIN semear_categorias c ON c.id = p.categoria_id
             WHERE c.id IS NULL'
        )->fetchColumn();

        $this->assertIgual(0, (int) $orfaos);
    }

    public function testeAvisaQuandoOPaiEstaVazio(): void
    {
        $this->assertExcecao(RuntimeException::class, function (): void {
            Semeador::inventar('semear_produtos', 3);
        });
    }

    // ------------------------------------------------------------------
    // limpar()
    // ------------------------------------------------------------------

    public function testeLimparEsvaziaEReiniciaOId(): void
    {
        Semeador::criar('semear_categorias', [['nome' => 'Uma'], ['nome' => 'Outra']]);

        Semeador::limpar(['semear_categorias']);

        $this->assertIgual(0, $this->contar('semear_categorias'));

        $ids = Semeador::criar('semear_categorias', [['nome' => 'Nova']]);

        $this->assertIgual(1, $ids[0], 'o id volta a contar do 1');
    }

    // ------------------------------------------------------------------
    // executar()
    // ------------------------------------------------------------------

    public function testeRodaOArquivoDeSemeadura(): void
    {
        file_put_contents($this->arquivo, <<<'PHP'
            <?php
            $cat = semear('semear_categorias', [['nome' => 'Moveis']], 'nome');
            semear('semear_produtos', [
                ['nome' => 'Mesa', 'preco' => 450.0, 'categoria_id' => $cat['Moveis']],
            ]);
            falsos('semear_categorias', 4);
            PHP);

        $contagens = Semeador::executar($this->arquivo);

        $this->assertIgual(5, $contagens['semear_categorias']);
        $this->assertIgual(1, $contagens['semear_produtos']);
        $this->assertIgual(5, $this->contar('semear_categorias'));
    }

    /**
     * Um erro na linha 10 nao pode deixar as linhas 1 a 9 gravadas: o
     * arquivo inteiro roda dentro de uma transacao.
     */
    public function testeErroNoMeioDesfazTudo(): void
    {
        file_put_contents($this->arquivo, <<<'PHP'
            <?php
            semear('semear_categorias', [['nome' => 'Vai sumir']]);
            semear('semear_categorias', [['nome' => 'X', 'coluna_inventada' => 1]]);
            PHP);

        $this->assertExcecao(RuntimeException::class, function (): void {
            Semeador::executar($this->arquivo);
        });

        $this->assertIgual(0, $this->contar('semear_categorias'), 'nada ficou gravado');
    }

    public function testeAvisaQuandoOArquivoNaoExiste(): void
    {
        $this->assertExcecao(RuntimeException::class, function (): void {
            Semeador::executar(sys_get_temp_dir() . '/nao_existe_semear.php');
        });
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    private function contar(string $tabela): int
    {
        return (int) Database::conexao()->query("SELECT COUNT(*) FROM `{$tabela}`")->fetchColumn();
    }

    private function primeiro(string $tabela): array
    {
        return Database::conexao()->query("SELECT * FROM `{$tabela}` ORDER BY id LIMIT 1")->fetch() ?: [];
    }
}
