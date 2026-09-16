<?php

namespace Nucleo;

use RuntimeException;

/**
 * Um arquivo enviado pelo formulario.
 *
 * ------------------------------------------------------------------------
 * POR QUE ESTA CLASSE EXISTE
 * ------------------------------------------------------------------------
 * Receber arquivo e a porta de entrada mais perigosa de um site. Tres coisas
 * precisam ser verdade, sempre:
 *
 *   1. o arquivo veio mesmo de um upload  -> is_uploaded_file()
 *      (sem isso, alguem poderia mandar o caminho de um arquivo do servidor)
 *
 *   2. a extensao esta em uma lista fechada
 *      (o "tipo" que o navegador informa e escrito pelo proprio navegador,
 *       entao ele nao vale como prova de nada)
 *
 *   3. o nome gravado no disco e inventado aqui, nunca o nome que veio junto
 *      (um nome como "../../index.php" sairia da pasta de uploads)
 *
 * Os arquivos ficam em views/uploads/, que ja e servida como conteudo
 * estatico. O .htaccess de views/ recusa qualquer .php, e o roteador do
 * servidor embutido tambem — entao um .php que escapasse da lista de
 * extensoes ainda assim nao seria executado.
 * ------------------------------------------------------------------------
 *
 * Uso no controller:
 *
 *     $foto = $this->imagem('foto');
 *
 *     if ($foto !== null && ($problema = $foto->problema()) !== null) {
 *         $erros['foto'] = $problema;
 *     }
 *
 *     // so depois que o resto passou:
 *     $dados['foto'] = $foto->salvar('produtos');
 */
class Arquivo
{
    /** Extensoes aceitas em um campo de imagem. */
    public const IMAGENS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Extensoes aceitas em um campo de arquivo comum. */
    public const DOCUMENTOS = [
        'pdf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods',
        'ppt', 'pptx', 'csv', 'txt', 'zip',
    ];

    /** Pasta dos uploads, dentro de views/. */
    public const PASTA = 'uploads';

    /** Tamanho maximo padrao, em kilobytes. */
    public const MAXIMO_KB = 4096;

    private function __construct(
        private array $dados,
        private array $extensoes,
        private int $maximoKb,
        private bool $exigirImagem
    ) {
    }

    /**
     * Pega o arquivo que veio no campo do formulario.
     *
     * Devolve null quando ninguem escolheu arquivo — que e o caso normal em
     * uma edicao onde so o nome mudou.
     */
    public static function de(
        string $campo,
        array $extensoes = self::DOCUMENTOS,
        int $maximoKb = self::MAXIMO_KB,
        bool $exigirImagem = false
    ): ?self {
        $dados = $_FILES[$campo] ?? null;

        if (!is_array($dados) || !isset($dados['error'], $dados['tmp_name'])) {
            return null;
        }

        // Campo com [] no name manda arrays. Esta classe cuida de um arquivo
        // por campo; varios de uma vez exigiriam outro tratamento.
        if (is_array($dados['error'])) {
            return null;
        }

        if ((int) $dados['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return new self($dados, $extensoes, $maximoKb, $exigirImagem);
    }

    // ------------------------------------------------------------------
    // Leitura
    // ------------------------------------------------------------------

    /** Nome com que o arquivo saiu do computador de quem enviou. */
    public function nomeOriginal(): string
    {
        return (string) ($this->dados['name'] ?? '');
    }

    /** Extensao em minusculas, sem o ponto. */
    public function extensao(): string
    {
        return strtolower(pathinfo($this->nomeOriginal(), PATHINFO_EXTENSION));
    }

    public function tamanho(): int
    {
        return (int) ($this->dados['size'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Conferencia
    // ------------------------------------------------------------------

    /**
     * Devolve a mensagem do que esta errado, ou null quando esta tudo certo.
     *
     * A mensagem vai para o mesmo array de erros da validacao do model, entao
     * o problema aparece embaixo do campo como qualquer outro.
     */
    public function problema(): ?string
    {
        $erro = (int) ($this->dados['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($erro !== UPLOAD_ERR_OK) {
            return $this->mensagemDoErro($erro);
        }

        // A prova de que o arquivo chegou por upload, e nao por outro caminho.
        if (!is_uploaded_file((string) $this->dados['tmp_name'])) {
            return 'Nao foi possivel receber o arquivo. Envie novamente.';
        }

        if ($this->tamanho() > $this->maximoKb * 1024) {
            return 'O arquivo deve ter no maximo ' . $this->emMegabytes($this->maximoKb * 1024) . '.';
        }

        if ($this->tamanho() === 0) {
            return 'O arquivo enviado esta vazio.';
        }

        if (!in_array($this->extensao(), $this->extensoes, true)) {
            return 'Tipo de arquivo nao aceito. Envie: ' . implode(', ', $this->extensoes) . '.';
        }

        // Em imagem a extensao nao basta: um .php renomeado para .jpg passaria
        // pela lista. getimagesize() le o comeco do arquivo e so reconhece
        // imagem de verdade.
        if ($this->exigirImagem && @getimagesize((string) $this->dados['tmp_name']) === false) {
            return 'O arquivo enviado nao e uma imagem valida.';
        }

        return null;
    }

    public function valido(): bool
    {
        return $this->problema() === null;
    }

    // ------------------------------------------------------------------
    // Gravacao
    // ------------------------------------------------------------------

    /**
     * Move o arquivo para views/uploads/<pasta> e devolve o caminho que deve
     * ser gravado no banco — o mesmo que a view passa para asset().
     *
     * Chame apenas depois que problema() devolveu null e o resto do
     * formulario passou: gravar antes deixaria arquivo orfao no disco toda
     * vez que a validacao falhasse.
     */
    public function salvar(string $pasta): string
    {
        $problema = $this->problema();

        if ($problema !== null) {
            throw new RuntimeException('Arquivo recusado: ' . $problema);
        }

        $pasta   = self::pastaValida($pasta);
        $destino = CAMINHO_VIEWS . '/' . self::PASTA . '/' . $pasta;

        if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
            throw new RuntimeException("Nao foi possivel criar a pasta de uploads: {$destino}");
        }

        // O nome e sorteado aqui. O nome que veio do navegador nunca toca o
        // disco: ele poderia ser "../../index.php" ou repetir um arquivo que
        // ja existe.
        $nome    = bin2hex(random_bytes(8)) . '.' . $this->extensao();
        $caminho = $destino . '/' . $nome;

        if (!move_uploaded_file((string) $this->dados['tmp_name'], $caminho)) {
            throw new RuntimeException('Nao foi possivel gravar o arquivo enviado.');
        }

        chmod($caminho, 0644);

        return self::PASTA . '/' . $pasta . '/' . $nome;
    }

    /**
     * Apaga um arquivo gravado antes — ao trocar a foto de um registro, ou ao
     * excluir o registro. Sem isso, o disco so cresce.
     *
     * Só apaga dentro de views/uploads: um caminho vindo do banco que aponte
     * para outro lugar e ignorado.
     */
    public static function apagar(?string $caminho): bool
    {
        if ($caminho === null || trim($caminho) === '') {
            return false;
        }

        $raiz    = realpath(CAMINHO_VIEWS . '/' . self::PASTA);
        $arquivo = realpath(CAMINHO_VIEWS . '/' . ltrim($caminho, '/'));

        if ($raiz === false || $arquivo === false) {
            return false;
        }

        if (!str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return is_file($arquivo) && unlink($arquivo);
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    /** A pasta de destino e escolhida pelo programador, nunca pelo visitante. */
    private static function pastaValida(string $pasta): string
    {
        $pasta = trim($pasta, '/');

        if (!preg_match('/^[A-Za-z0-9_-]+(\/[A-Za-z0-9_-]+)*$/', $pasta)) {
            throw new RuntimeException(
                "Pasta de upload invalida: \"{$pasta}\". Use apenas letras, numeros, _ e -."
            );
        }

        return $pasta;
    }

    /**
     * Traduz os codigos do PHP para uma frase que a pessoa entenda.
     *
     * O limite que costuma pegar primeiro nao e o desta classe: e o
     * upload_max_filesize do php.ini, e por isso a mensagem diz onde mexer.
     */
    private function mensagemDoErro(int $erro): string
    {
        return match ($erro) {
            UPLOAD_ERR_INI_SIZE => 'O arquivo passa do limite do servidor ('
                . $this->emMegabytes(self::limiteDoPhp())
                . '). Aumente upload_max_filesize e post_max_size no php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'O arquivo passa do limite definido no formulario.',
            UPLOAD_ERR_PARTIAL    => 'O envio foi interrompido. Tente de novo.',
            UPLOAD_ERR_NO_TMP_DIR => 'O servidor esta sem pasta temporaria para receber arquivos.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor nao conseguiu gravar o arquivo em disco.',
            UPLOAD_ERR_EXTENSION  => 'Uma extensao do PHP interrompeu o envio.',
            default               => 'Nao foi possivel receber o arquivo. Envie novamente.',
        };
    }

    private function emMegabytes(int $bytes): string
    {
        $mb = $bytes / 1048576;

        return ($mb >= 1 ? round($mb, 1) : round($mb, 2)) . ' MB';
    }

    /** upload_max_filesize do php.ini, em bytes. */
    public static function limiteDoPhp(): int
    {
        $valor  = trim((string) ini_get('upload_max_filesize'));
        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g'     => $numero * 1024 * 1024 * 1024,
            'm'     => $numero * 1024 * 1024,
            'k'     => $numero * 1024,
            default => $numero,
        };
    }
}
