<?php

namespace Modelos;

use Nucleo\Model;
use Nucleo\Sql;
use Nucleo\Validador;

/**
 * Modelo de exemplo — copie este arquivo para criar os seus.
 *
 * Repare que quase nao existe codigo: todo o CRUD vem por HERANCA da classe
 * Nucleo\Model. Aqui so declaramos as particularidades desta tabela.
 */
class Aluno extends Model
{
    /** Tabela no banco de dados. */
    protected string $tabela = 'alunos';

    /** Coluna que identifica cada registro. */
    protected string $chavePrimaria = 'id';

    /** Campos que podem ser gravados (protege contra envio de campos extras). */
    protected array $preenchiveis = ['nome', 'email', 'curso', 'nota'];

    /** Ordenacao usada pelo metodo todos(). */
    protected string $ordemPadrao = 'nome ASC';

    /** Opcoes do campo "curso" — usadas no formulario e na validacao. */
    public const CURSOS = [
        'Informatica',
        'Administracao',
        'Edificacoes',
        'Eletrotecnica',
        'Enfermagem',
    ];

    /**
     * Regras de validacao deste modelo.
     *
     * @param array           $dados      dados vindos do formulario
     * @param int|string|null $ignorarId  id a ignorar na checagem de e-mail unico
     *                                    (usado na edicao, senao o registro
     *                                     acusaria conflito com ele mesmo)
     *
     * @return array<string,string> lista de erros (vazio = tudo certo)
     */
    public function validar(array $dados, int|string|null $ignorarId = null): array
    {
        $validador = new Validador($dados);

        $validador
            ->obrigatorio('nome', 'Nome')
            ->minimo('nome', 3, 'Nome')
            ->maximo('nome', 100, 'Nome')
            ->obrigatorio('email', 'E-mail')
            ->email('email', 'E-mail')
            ->obrigatorio('curso', 'Curso')
            ->dentroDe('curso', self::CURSOS, 'Curso')
            ->numerico('nota', 'Nota')
            ->entre('nota', 0, 10, 'Nota');

        // Regra que precisa consultar o banco: e-mail nao pode repetir.
        $email = trim((string) ($dados['email'] ?? ''));

        if ($email !== '' && $this->emailJaUsado($email, $ignorarId)) {
            $validador->personalizada('email', false, 'Este e-mail ja esta cadastrado.');
        }

        return $validador->erros();
    }

    /**
     * Verifica se o e-mail ja pertence a outro aluno.
     */
    public function emailJaUsado(string $email, int|string|null $ignorarId = null): bool
    {
        $existente = $this->primeiroOnde('email', $email);

        if ($existente === null) {
            return false;
        }

        return $ignorarId === null || (string) $existente['id'] !== (string) $ignorarId;
    }

    // ------------------------------------------------------------------
    // Consultas proprias deste modelo (exemplos para a turma)
    // ------------------------------------------------------------------

    /**
     * Busca alunos cujo nome OU e-mail contenham o termo digitado.
     */
    public function procurar(string $termo): array
    {
        // Sql::comoLike neutraliza os curingas % e _ que o usuario digitar,
        // e o valor segue como parametro (?), nunca dentro do texto do SQL.
        $termo = Sql::comoLike($termo);

        return $this->consultar(
            'SELECT * FROM alunos WHERE nome LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE
            . ' OR email LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE
            . ' ORDER BY nome',
            [$termo, $termo]
        );
    }

    /**
     * Alunos com nota maior ou igual a 6.
     */
    public function aprovados(): array
    {
        return $this->consultar('SELECT * FROM alunos WHERE nota >= 6 ORDER BY nota DESC');
    }

    /**
     * Media das notas da turma.
     */
    public function mediaGeral(): float
    {
        $linhas = $this->consultar('SELECT AVG(nota) AS media FROM alunos WHERE nota IS NOT NULL');

        return round((float) ($linhas[0]['media'] ?? 0), 2);
    }

    /**
     * Quantos alunos existem em cada curso.
     */
    public function totalPorCurso(): array
    {
        return $this->consultar(
            'SELECT curso, COUNT(*) AS total FROM alunos GROUP BY curso ORDER BY total DESC, curso'
        );
    }
}
