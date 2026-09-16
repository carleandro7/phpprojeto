<?php

namespace Testes\Nucleo;

use Nucleo\Arquivo;
use Testes\Suporte\TesteBase;

/**
 * Testes do ARQUIVO enviado por formulario.
 *
 * O caminho feliz (mover o arquivo para views/uploads) depende de um upload
 * HTTP de verdade: o move_uploaded_file() so aceita arquivo que o proprio
 * PHP recebeu em um POST, e e justamente essa a protecao que nao faz sentido
 * contornar no teste. O que da para cobrir aqui e o resto — que e onde moram
 * as decisoes de seguranca.
 *
 * Rode so estes:  php testes/executar.php ArquivoTest
 */
class ArquivoTest extends TesteBase
{
    public function preparar(): void
    {
        $_FILES = [];
    }

    public function testeCampoSemArquivoDevolveNulo(): void
    {
        $this->assertNulo(Arquivo::de('foto'), 'campo que nem existe no formulario');

        $_FILES['foto'] = [
            'name'     => '',
            'type'     => '',
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_NO_FILE,
            'size'     => 0,
        ];

        // Ninguem escolheu arquivo: e o caso normal de uma edicao em que so
        // os outros campos mudaram, e nao pode virar erro.
        $this->assertNulo(Arquivo::de('foto'));
    }

    public function testeCampoComVariosArquivosNaoEAceito(): void
    {
        $_FILES['anexos'] = [
            'name'     => ['a.pdf', 'b.pdf'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size'     => [10, 20],
        ];

        $this->assertNulo(Arquivo::de('anexos'));
    }

    public function testeExplicaOsErrosDoEnvio(): void
    {
        $arquivo = $this->arquivoFalso('foto.png', UPLOAD_ERR_INI_SIZE);

        $problema = $arquivo->problema();

        $this->assertNaoNulo($problema);
        $this->assertContem('upload_max_filesize', $problema);

        $interrompido = $this->arquivoFalso('foto.png', UPLOAD_ERR_PARTIAL)->problema();

        $this->assertNaoNulo($interrompido);
        $this->assertContem('interrompido', $interrompido);
    }

    /**
     * Um arquivo que nao veio de upload e recusado mesmo com nome e extensao
     * perfeitos: e o que impede alguem de apontar para um arquivo que ja
     * estava no servidor.
     */
    public function testeRecusaArquivoQueNaoVeioDeUpload(): void
    {
        $caminho = sys_get_temp_dir() . '/framework_teste_upload.png';
        file_put_contents($caminho, 'conteudo');

        $arquivo = $this->arquivoFalso('foto.png', UPLOAD_ERR_OK, $caminho, 8);

        $this->assertNaoNulo($arquivo->problema());

        unlink($caminho);
    }

    public function testeLeNomeEExtensao(): void
    {
        $arquivo = $this->arquivoFalso('Foto Da Turma.JPEG', UPLOAD_ERR_OK, '/tmp/x', 1234);

        $this->assertIgual('Foto Da Turma.JPEG', $arquivo->nomeOriginal());
        $this->assertIgual('jpeg', $arquivo->extensao(), 'a extensao sai em minusculas');
        $this->assertIgual(1234, $arquivo->tamanho());
    }

    // ------------------------------------------------------------------
    // Apagar
    // ------------------------------------------------------------------

    public function testeApagarIgnoraCaminhoVazio(): void
    {
        $this->assertFalso(Arquivo::apagar(null));
        $this->assertFalso(Arquivo::apagar(''));
        $this->assertFalso(Arquivo::apagar('   '));
    }

    /**
     * O caminho vem do banco. Se alguem conseguisse gravar ali
     * "../../index.php", apagar um registro derrubaria o site.
     */
    public function testeApagarNaoSaiDaPastaDeUploads(): void
    {
        $alvo = CAMINHO_VIEWS . '/nao_apague_teste.txt';
        file_put_contents($alvo, 'importante');

        $this->assertFalso(Arquivo::apagar('../views/nao_apague_teste.txt'));
        $this->assertFalso(Arquivo::apagar('uploads/../nao_apague_teste.txt'));
        $this->assertFalso(Arquivo::apagar('/etc/hosts'));

        $this->assertVerdadeiro(is_file($alvo), 'o arquivo de fora continua la');

        unlink($alvo);
    }

    public function testeApagaArquivoDeDentroDaPastaDeUploads(): void
    {
        $pasta = CAMINHO_VIEWS . '/' . Arquivo::PASTA . '/teste_apagar';

        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        file_put_contents($pasta . '/arquivo.txt', 'qualquer coisa');

        $this->assertVerdadeiro(Arquivo::apagar(Arquivo::PASTA . '/teste_apagar/arquivo.txt'));
        $this->assertFalso(is_file($pasta . '/arquivo.txt'));

        // Apagar duas vezes nao quebra: o arquivo ja nao esta mais la.
        $this->assertFalso(Arquivo::apagar(Arquivo::PASTA . '/teste_apagar/arquivo.txt'));

        rmdir($pasta);
    }

    public function testeListasDeExtensoesNaoAceitamExecutavel(): void
    {
        foreach (['php', 'phtml', 'sh', 'exe', 'htaccess'] as $perigosa) {
            $this->assertFalso(
                in_array($perigosa, Arquivo::IMAGENS, true),
                "{$perigosa} nao pode estar na lista de imagens"
            );

            $this->assertFalso(
                in_array($perigosa, Arquivo::DOCUMENTOS, true),
                "{$perigosa} nao pode estar na lista de documentos"
            );
        }
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    private function arquivoFalso(
        string $nome,
        int $erro,
        string $temporario = '/tmp/inexistente',
        int $tamanho = 0
    ): Arquivo {
        $_FILES['foto'] = [
            'name'     => $nome,
            'type'     => 'image/png',
            'tmp_name' => $temporario,
            'error'    => $erro,
            'size'     => $tamanho,
        ];

        $arquivo = Arquivo::de('foto', Arquivo::IMAGENS, 2048, true);

        $this->assertNaoNulo($arquivo);

        return $arquivo;
    }
}
