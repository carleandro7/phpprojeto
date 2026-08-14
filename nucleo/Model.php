<?php

namespace Nucleo;

use InvalidArgumentException;
use PDO;

/**
 * Classe base de todos os modelos.
 *
 * O aluno so precisa criar uma classe em "modelos/", herdar desta e informar
 * o nome da tabela. Todo o CRUD (Create, Read, Update, Delete) ja vem pronto.
 *
 * Exemplo minimo:
 *
 *     class Aluno extends \Nucleo\Model
 *     {
 *         protected string $tabela       = 'alunos';
 *         protected array  $preenchiveis = ['nome', 'email'];
 *     }
 *
 * Uso:
 *     $modelo = new Aluno();
 *     $modelo->todos();
 *     $modelo->criar(['nome' => 'Ana', 'email' => 'ana@escola.br']);
 */
abstract class Model
{
    /** Nome da tabela no banco de dados. */
    protected string $tabela = '';

    /** Coluna que identifica cada registro. */
    protected string $chavePrimaria = 'id';

    /**
     * Colunas que podem ser gravadas via criar()/atualizar().
     * Protege contra "mass assignment" (o usuario mandar campos que nao deveria).
     */
    protected array $preenchiveis = [];

    /** Ordenacao padrao das listagens. */
    protected string $ordemPadrao = '';

    public function __construct()
    {
        if ($this->tabela === '') {
            throw new InvalidArgumentException(
                'Defina a propriedade $tabela no modelo ' . static::class
            );
        }

        // Tabela e chave primaria entram no texto do SQL, entao sao
        // conferidas uma unica vez, aqui.
        $this->tabela        = Sql::identificador($this->tabela, 'tabela');
        $this->chavePrimaria = Sql::identificador($this->chavePrimaria, 'coluna');
    }

    // ------------------------------------------------------------------
    // Acesso ao PDO
    // ------------------------------------------------------------------

    protected function pdo(): PDO
    {
        return Database::conexao();
    }

    /**
     * Executa um SELECT livre e devolve todas as linhas.
     * Sempre use "?" ou ":nome" nos parametros — nunca concatene valores!
     */
    public function consultar(string $sql, array $parametros = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    /**
     * Executa INSERT/UPDATE/DELETE livre e devolve o numero de linhas afetadas.
     */
    public function executar(string $sql, array $parametros = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->rowCount();
    }

    // ------------------------------------------------------------------
    // Leitura (Read)
    // ------------------------------------------------------------------

    /**
     * Lista todos os registros da tabela.
     */
    public function todos(?string $ordem = null): array
    {
        $ordem = $ordem ?? $this->ordemPadrao;
        $sql   = "SELECT * FROM {$this->tabela}";

        if ($ordem !== '') {
            $sql .= ' ORDER BY ' . $this->validarOrdem($ordem);
        }

        return $this->consultar($sql);
    }

    /**
     * Busca um registro pela chave primaria. Devolve null se nao existir.
     */
    public function buscar(int|string $id): ?array
    {
        $sql  = "SELECT * FROM {$this->tabela} WHERE {$this->chavePrimaria} = ? LIMIT 1";
        $linhas = $this->consultar($sql, [$id]);

        return $linhas[0] ?? null;
    }

    /**
     * Busca registros por uma coluna qualquer.
     * Ex.: $modelo->onde('curso', 'Informatica')
     */
    public function onde(string $coluna, mixed $valor, string $operador = '='): array
    {
        $coluna   = $this->validarColuna($coluna);
        $operador = $this->validarOperador($operador);

        return $this->consultar(
            "SELECT * FROM {$this->tabela} WHERE {$coluna} {$operador} ? ",
            [$valor]
        );
    }

    /**
     * Igual ao onde(), mas devolve apenas o primeiro resultado (ou null).
     */
    public function primeiroOnde(string $coluna, mixed $valor, string $operador = '='): ?array
    {
        return $this->onde($coluna, $valor, $operador)[0] ?? null;
    }

    /**
     * Conta quantos registros existem na tabela.
     */
    public function contar(): int
    {
        $linhas = $this->consultar("SELECT COUNT(*) AS total FROM {$this->tabela}");

        return (int) ($linhas[0]['total'] ?? 0);
    }

    /**
     * Verifica se existe um registro com aquele id.
     */
    public function existe(int|string $id): bool
    {
        return $this->buscar($id) !== null;
    }

    // ------------------------------------------------------------------
    // Escrita (Create / Update / Delete)
    // ------------------------------------------------------------------

    /**
     * Insere um registro e devolve o id gerado.
     */
    public function criar(array $dados): int
    {
        $dados = $this->filtrar($dados);

        if ($dados === []) {
            throw new InvalidArgumentException(
                'Nenhum campo valido para inserir. Verifique a propriedade $preenchiveis.'
            );
        }

        $colunas     = array_keys($dados);
        $marcadores  = array_fill(0, count($colunas), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->tabela,
            implode(', ', $colunas),
            implode(', ', $marcadores)
        );

        $this->executar($sql, array_values($dados));

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Atualiza um registro pelo id. Devolve true se alguma linha mudou.
     */
    public function atualizar(int|string $id, array $dados): bool
    {
        $dados = $this->filtrar($dados);

        if ($dados === []) {
            return false;
        }

        $atribuicoes = [];
        foreach (array_keys($dados) as $coluna) {
            $atribuicoes[] = "{$coluna} = ?";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->tabela,
            implode(', ', $atribuicoes),
            $this->chavePrimaria
        );

        $parametros   = array_values($dados);
        $parametros[] = $id;

        return $this->executar($sql, $parametros) > 0;
    }

    /**
     * Remove um registro pelo id.
     */
    public function excluir(int|string $id): bool
    {
        $sql = "DELETE FROM {$this->tabela} WHERE {$this->chavePrimaria} = ?";

        return $this->executar($sql, [$id]) > 0;
    }

    // ------------------------------------------------------------------
    // Validacao
    // ------------------------------------------------------------------

    /**
     * Cada modelo pode sobrescrever este metodo para dizer o que e valido.
     * Deve devolver um array de erros (vazio = tudo certo).
     *
     * @return array<string,string>
     */
    public function validar(array $dados, int|string|null $ignorarId = null): array
    {
        return [];
    }

    // ------------------------------------------------------------------
    // Apoio interno
    // ------------------------------------------------------------------

    /**
     * Mantem apenas as chaves declaradas em $preenchiveis e confere que cada
     * uma e um nome de coluna valido.
     *
     * Essa segunda checagem importa porque as chaves do array viram nomes de
     * coluna dentro do INSERT/UPDATE — e elas costumam vir do $_POST, ou seja,
     * do usuario. Sem $preenchiveis declarado, alguem poderia enviar um campo
     * de formulario com nome malicioso.
     */
    protected function filtrar(array $dados): array
    {
        if ($this->preenchiveis !== []) {
            $dados = array_intersect_key($dados, array_flip($this->preenchiveis));
        }

        $limpos = [];

        foreach ($dados as $coluna => $valor) {
            $limpos[Sql::identificador((string) $coluna, 'coluna')] = $valor;
        }

        return $limpos;
    }

    /**
     * Nomes de coluna, ordenacao e operadores nao podem ser enviados como
     * parametro do PDO — eles fazem parte do texto do SQL. Por isso passam
     * pela classe Nucleo\Sql, que so aceita formatos seguros.
     *
     * Ja os VALORES nunca sao concatenados: vao sempre como "?".
     */
    protected function validarColuna(string $coluna): string
    {
        return Sql::identificador($coluna, 'coluna');
    }

    protected function validarOrdem(string $ordem): string
    {
        return Sql::ordenacao($ordem);
    }

    protected function validarOperador(string $operador): string
    {
        return Sql::operador($operador);
    }

    public function tabela(): string
    {
        return $this->tabela;
    }
}
