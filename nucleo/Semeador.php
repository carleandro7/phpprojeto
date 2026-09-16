<?php

namespace Nucleo {

use PDO;
use RuntimeException;
use Throwable;

/**
 * Executa o arquivo de semeadura: banco/semear.php.
 *
 * ------------------------------------------------------------------------
 * O QUE E UM ARQUIVO DE SEMEADURA
 * ------------------------------------------------------------------------
 * E onde ficam os dados que o sistema precisa ter: as categorias do catalogo,
 * os status de um pedido, a conta de administrador, alguns registros de
 * exemplo para a tela nao nascer vazia.
 *
 * Ele e um arquivo PHP comum — voce escreve os dados, ele cria:
 *
 *     semear('categorias', [
 *         ['nome' => 'Eletronicos'],
 *         ['nome' => 'Moveis'],
 *     ]);
 *
 * e roda com:
 *
 *     php console.php db:semear
 *
 * O arquivo inteiro roda dentro de uma transacao: se der erro na linha 40,
 * nada do que veio antes fica gravado pela metade.
 * ------------------------------------------------------------------------
 *
 * As tres funcoes disponiveis dentro dele sao semear(), falsos() e limpar().
 * Elas estao definidas no fim deste arquivo.
 */
class Semeador
{
    /** Quantos registros cada tabela recebeu nesta execucao. */
    private static array $contagens = [];

    /** Colunas de cada tabela, ja lidas do banco. */
    private static array $colunas = [];

    /**
     * Roda o arquivo de semeadura.
     *
     * @return array<string,int> tabela => quantos registros entraram
     */
    public static function executar(?string $arquivo = null): array
    {
        $arquivo = $arquivo ?? CAMINHO_BANCO . '/semear.php';

        if (!is_file($arquivo)) {
            throw new RuntimeException(
                'Arquivo de semeadura nao encontrado: ' . $arquivo . "\n"
                . 'Crie o arquivo e escreva os dados do sistema dentro dele.'
            );
        }

        self::$contagens = [];
        self::$colunas   = [];

        $pdo = Database::conexao();
        $pdo->beginTransaction();

        try {
            require $arquivo;

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return self::$contagens;
    }

    /**
     * Cria os registros informados.
     *
     * Com $chave, um registro cujo valor dessa coluna ja exista e pulado, e o
     * retorno vem indexado por ela — bom para amarrar as relacoes:
     *
     *     $cat = semear('categorias', [['nome' => 'Moveis']], 'nome');
     *     semear('produtos', [['nome' => 'Mesa', 'categoria_id' => $cat['Moveis']]]);
     *
     * Rodar duas vezes com $chave nao duplica nada.
     *
     * @param list<array<string,mixed>> $registros
     * @return array<int|string,int> ids criados (indexados pela chave, quando houver)
     */
    public static function criar(string $tabela, array $registros, ?string $chave = null): array
    {
        $tabela  = self::tabelaValida($tabela);
        $colunas = self::colunas($tabela);

        if ($chave !== null && !isset($colunas[$chave])) {
            throw new RuntimeException(
                "A tabela \"{$tabela}\" nao tem a coluna \"{$chave}\" usada como chave.\n"
                . 'Colunas: ' . implode(', ', array_keys($colunas))
            );
        }

        $ids        = [];
        $existentes = $chave === null ? [] : self::valoresDe($tabela, $chave);

        foreach ($registros as $posicao => $registro) {
            if (!is_array($registro)) {
                throw new RuntimeException(
                    "Cada registro de \"{$tabela}\" deve ser um array de coluna => valor "
                    . '(veja o registro na posicao ' . $posicao . ').'
                );
            }

            $registro = self::preparar($tabela, $registro, $colunas);

            if ($chave !== null) {
                $valor = (string) ($registro[$chave] ?? '');

                // Ja existe: nao duplica, mas devolve o id para as relacoes
                // continuarem funcionando.
                if (isset($existentes[$valor])) {
                    $ids[$valor] = $existentes[$valor];

                    continue;
                }
            }

            $id = self::inserir($tabela, $registro);

            if ($chave === null) {
                $ids[] = $id;
            } else {
                $valor              = (string) ($registro[$chave] ?? '');
                $ids[$valor]        = $id;
                $existentes[$valor] = $id;
            }

            self::$contagens[$tabela] = (self::$contagens[$tabela] ?? 0) + 1;
        }

        return $ids;
    }

    /**
     * Enche a tabela com registros inventados.
     *
     *     falsos('produtos', 50);
     *
     * Serve para ver a listagem, a pesquisa e a paginacao funcionando. Os
     * dados que o sistema precisa ter de verdade vao no semear().
     *
     * @return list<int> ids criados
     */
    public static function inventar(string $tabela, int $quantidade = 10): array
    {
        $tabela  = self::tabelaValida($tabela);
        $colunas = self::colunas($tabela);

        $preenchiveis = array_filter(
            $colunas,
            fn (array $coluna, string $nome): bool => $nome !== 'id' && !$coluna['automatica'],
            ARRAY_FILTER_USE_BOTH
        );

        if ($preenchiveis === []) {
            throw new RuntimeException("A tabela \"{$tabela}\" nao tem colunas para preencher.");
        }

        $pais   = [];
        $usados = [];

        foreach ($preenchiveis as $nome => $coluna) {
            if ($coluna['pai'] !== null) {
                $pais[$nome] = self::idsDe($coluna['pai']);

                if ($pais[$nome] === []) {
                    throw new RuntimeException(
                        "A tabela \"{$coluna['pai']}\" esta vazia, entao nao da para sortear "
                        . "o {$nome} de cada registro de \"{$tabela}\".\n"
                        . "Semeie a tabela pai antes:\n  falsos('{$coluna['pai']}', 10);"
                    );
                }
            }

            if ($coluna['unica']) {
                $usados[$nome] = array_flip(self::valoresDe($tabela, $nome, false));
            }
        }

        $ids = [];

        for ($indice = 1; $indice <= $quantidade; $indice++) {
            $registro = [];

            foreach ($preenchiveis as $nome => $coluna) {
                if ($coluna['pai'] !== null) {
                    $registro[$nome] = $pais[$nome][array_rand($pais[$nome])];

                    continue;
                }

                $valor = DadosFalsos::para($tabela, $nome, $coluna['tipo'], $indice, isset($usados[$nome]));

                // Coluna UNIQUE nao pode repetir nem entre si nem com o que
                // ja estava gravado.
                if (isset($usados[$nome])) {
                    $base   = $valor;
                    $sufixo = $indice;

                    while (isset($usados[$nome][$valor])) {
                        $valor = DadosFalsos::distinto($base, $sufixo++);
                    }

                    $usados[$nome][$valor] = true;
                }

                $registro[$nome] = $valor;
            }

            $ids[] = self::inserir($tabela, $registro);

            self::$contagens[$tabela] = (self::$contagens[$tabela] ?? 0) + 1;
        }

        return $ids;
    }

    /**
     * Apaga os registros das tabelas (e devolve o id ao comeco).
     *
     * Sem argumentos, limpa todas as tabelas do banco. A ordem e sempre a
     * inversa da dependencia: o filho sai antes do pai, senao a chave
     * estrangeira recusa.
     */
    public static function limpar(array $tabelas = []): void
    {
        $pdo   = Database::conexao();
        $todas = self::tabelasEmOrdem();

        $alvos = $tabelas === []
            ? $todas
            : array_map([self::class, 'tabelaValida'], $tabelas);

        // Reordena os alvos conforme a dependencia, e inverte.
        $ordenados = array_values(array_filter($todas, fn (string $t): bool => in_array($t, $alvos, true)));

        foreach (array_reverse($ordenados) as $tabela) {
            try {
                $pdo->exec("DELETE FROM `{$tabela}`");
                $pdo->exec("ALTER TABLE `{$tabela}` AUTO_INCREMENT = 1");
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Nao consegui limpar a tabela \"{$tabela}\": " . $e->getMessage() . "\n"
                    . 'Se outra tabela aponta para esta, limpe as duas — ou chame limpar() sem argumentos.',
                    0,
                    $e
                );
            }
        }
    }

    /** @return array<string,int> quantos registros cada tabela recebeu */
    public static function contagens(): array
    {
        return self::$contagens;
    }

    /**
     * Tabelas do banco com as pai antes das filhas.
     *
     * @return list<string>
     */
    public static function tabelasEmOrdem(): array
    {
        $tabelas = [];

        foreach (Database::conexao()->query('SHOW TABLES') as $linha) {
            $tabelas[] = (string) array_values($linha)[0];
        }

        $pendentes = $tabelas;
        $ordenadas = [];

        // No maximo uma volta por tabela: um ciclo de chaves estrangeiras
        // (A aponta para B, B aponta para A) nao pode travar o comando.
        for ($volta = 0; $volta < count($tabelas) + 1 && $pendentes !== []; $volta++) {
            $restaram = [];

            foreach ($pendentes as $tabela) {
                $pronta = true;

                foreach (self::colunas($tabela) as $coluna) {
                    $pai = $coluna['pai'];

                    if ($pai !== null && $pai !== $tabela
                        && !in_array($pai, $ordenadas, true)
                        && in_array($pai, $tabelas, true)) {
                        $pronta = false;
                        break;
                    }
                }

                if ($pronta) {
                    $ordenadas[] = $tabela;

                    continue;
                }

                $restaram[] = $tabela;
            }

            $pendentes = $restaram;
        }

        return array_merge($ordenadas, $pendentes);
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    /**
     * Confere e ajusta um registro antes de inserir.
     *
     * Aqui acontece a unica "magica" da semeadura: uma coluna chamada senha
     * recebe password_hash(). Sem isso, a conta criada pelo arquivo nunca
     * entraria pela tela de login — e a senha ficaria em texto puro no banco.
     */
    private static function preparar(string $tabela, array $registro, array $colunas): array
    {
        $limpo = [];

        foreach ($registro as $coluna => $valor) {
            $coluna = Sql::identificador((string) $coluna, 'coluna');

            if (!isset($colunas[$coluna])) {
                throw new RuntimeException(
                    "A tabela \"{$tabela}\" nao tem a coluna \"{$coluna}\".\n"
                    . 'Colunas: ' . implode(', ', array_keys($colunas))
                );
            }

            if ($coluna === 'senha' && is_string($valor) && $valor !== '' && !Autenticacao::ehHash($valor)) {
                $valor = password_hash($valor, PASSWORD_DEFAULT);
            }

            $limpo[$coluna] = $valor;
        }

        if ($limpo === []) {
            throw new RuntimeException("Registro vazio para a tabela \"{$tabela}\".");
        }

        return $limpo;
    }

    private static function inserir(string $tabela, array $registro): int
    {
        $colunas = array_keys($registro);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $tabela,
            implode('`, `', $colunas),
            implode(', ', array_fill(0, count($colunas), '?'))
        );

        $comando = Database::conexao()->prepare($sql);
        $comando->execute(array_values($registro));

        return (int) Database::conexao()->lastInsertId();
    }

    /**
     * Colunas da tabela, lidas do banco.
     *
     * @return array<string,array{tipo:string,pai:?string,unica:bool,automatica:bool}>
     */
    private static function colunas(string $tabela): array
    {
        if (isset(self::$colunas[$tabela])) {
            return self::$colunas[$tabela];
        }

        $pdo     = Database::conexao();
        $colunas = [];

        foreach ($pdo->query("SHOW COLUMNS FROM `{$tabela}`") as $coluna) {
            $nome = (string) $coluna['Field'];

            $colunas[$nome] = [
                'tipo'  => DadosFalsos::tipoDoSql((string) $coluna['Type']),
                'pai'   => null,
                'unica' => in_array($coluna['Key'], ['PRI', 'UNI'], true),
                // Colunas que o proprio MySQL preenche (id, criado_em).
                'automatica' => str_contains((string) $coluna['Extra'], 'auto_increment')
                    || $coluna['Default'] === 'CURRENT_TIMESTAMP'
                    || str_contains(strtoupper((string) $coluna['Extra']), 'DEFAULT_GENERATED'),
            ];
        }

        $chaves = $pdo->prepare(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $chaves->execute([$tabela]);

        foreach ($chaves->fetchAll() as $chave) {
            $nome = (string) $chave['COLUMN_NAME'];

            if (isset($colunas[$nome])) {
                $colunas[$nome]['pai'] = (string) $chave['REFERENCED_TABLE_NAME'];
            }
        }

        return self::$colunas[$tabela] = $colunas;
    }

    /** Valores ja gravados em uma coluna, indexados por eles mesmos. */
    private static function valoresDe(string $tabela, string $coluna, bool $comId = true): array
    {
        $coluna = Sql::identificador($coluna, 'coluna');

        $sql = $comId
            ? "SELECT id, `{$coluna}` AS valor FROM `{$tabela}`"
            : "SELECT `{$coluna}` AS valor FROM `{$tabela}`";

        $valores = [];

        foreach (Database::conexao()->query($sql) as $linha) {
            if ($comId) {
                $valores[(string) $linha['valor']] = (int) $linha['id'];
            } else {
                $valores[] = $linha['valor'];
            }
        }

        return $valores;
    }

    /** @return list<int> */
    private static function idsDe(string $tabela): array
    {
        return array_map('intval', Database::conexao()
            ->query("SELECT id FROM `" . self::tabelaValida($tabela) . "` ORDER BY id")
            ->fetchAll(PDO::FETCH_COLUMN));
    }

    /** O nome da tabela vem do arquivo de semeadura, entao e conferido. */
    private static function tabelaValida(string $tabela): string
    {
        $tabela = Sql::identificador(trim($tabela), 'tabela');

        $consulta = Database::conexao()->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $consulta->execute([$tabela]);

        if ($consulta->fetchColumn() === false) {
            throw new RuntimeException(
                "A tabela \"{$tabela}\" nao existe no banco.\n"
                . "Confira o nome, e rode o instalador se o esquema mudou:\n  php instalar.php"
            );
        }

        return $tabela;
    }
}

}

// ---------------------------------------------------------------------
// As funcoes que o arquivo banco/semear.php usa
//
// Elas ficam no namespace GLOBAL de proposito: banco/semear.php e um arquivo
// solto, sem namespace, e quem escreve nele nao deveria precisar saber disso.
// ---------------------------------------------------------------------

namespace {

if (!function_exists('semear')) {
    /**
     * Cria os registros informados.
     *
     *     semear('categorias', [
     *         ['nome' => 'Eletronicos'],
     *         ['nome' => 'Moveis'],
     *     ]);
     *
     * Com o terceiro argumento, nao duplica e devolve os ids indexados por
     * ele — o jeito de amarrar as relacoes:
     *
     *     $cat = semear('categorias', [['nome' => 'Moveis']], 'nome');
     *     semear('produtos', [['nome' => 'Mesa', 'categoria_id' => $cat['Moveis']]]);
     */
    function semear(string $tabela, array $registros, ?string $chave = null): array
    {
        return Nucleo\Semeador::criar($tabela, $registros, $chave);
    }
}

if (!function_exists('falsos')) {
    /**
     * Enche a tabela com registros inventados, para a tela nao ficar vazia.
     *
     *     falsos('produtos', 50);
     */
    function falsos(string $tabela, int $quantidade = 10): array
    {
        return Nucleo\Semeador::inventar($tabela, $quantidade);
    }
}

if (!function_exists('limpar')) {
    /**
     * Apaga os registros antes de semear de novo.
     *
     *     limpar();                          // todas as tabelas
     *     limpar('produtos', 'categorias');  // so estas
     */
    function limpar(string ...$tabelas): void
    {
        Nucleo\Semeador::limpar($tabelas);
    }
}

}
