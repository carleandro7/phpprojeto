<?php

namespace Modelos;

use Nucleo\Model;
use Nucleo\Sql;
use Nucleo\Validador;

/**
 * Modelo de exemplo — copie este arquivo para criar os seus.
 *
 * ------------------------------------------------------------------------
 * O QUE E UM MODELO?
 * ------------------------------------------------------------------------
 * E a UNICA parte do sistema que fala com o banco de dados. Toda consulta
 * e toda regra de validacao moram aqui. O controller apenas pede; a view
 * apenas exibe.
 *
 * ------------------------------------------------------------------------
 * POR QUE ESTE ARQUIVO E TAO PEQUENO?
 * ------------------------------------------------------------------------
 * Repare que nao existe metodo todos(), buscar(), criar(), atualizar() nem
 * excluir() aqui — e mesmo assim o controller chama todos eles. Isso e
 * HERANCA: o "extends Model" traz o CRUD inteiro pronto de Nucleo\Model.
 *
 * Neste arquivo ficam apenas as PARTICULARIDADES desta tabela:
 *   1. as quatro propriedades de configuracao (logo abaixo);
 *   2. as regras de validacao (metodo validar);
 *   3. as consultas que so fazem sentido para alunos (procurar, mediaGeral...).
 *
 * O que voce ganha de graca da classe-base (todos publicos):
 *   todos()        SELECT * FROM alunos ORDER BY $ordemPadrao
 *   buscar($id)    SELECT ... WHERE id = ?   -> array OU null
 *   onde($c, $v)   SELECT ... WHERE coluna = ?
 *   primeiroOnde() idem, so a primeira linha  -> array OU null
 *   existe($id)    true/false
 *   contar()       SELECT COUNT(*)
 *   criar($dados)  INSERT  -> devolve o id novo
 *   atualizar()    UPDATE  -> devolve true se mudou alguma linha
 *   excluir($id)   DELETE  -> devolve true se apagou
 *   consultar()    SELECT livre, para quando o pronto nao basta
 */
class Aluno extends Model
{
    /**
     * Tabela no banco de dados.
     * E a UNICA propriedade obrigatoria: sem ela o construtor da classe-base
     * lanca erro avisando.
     */
    protected string $tabela = 'alunos';

    /**
     * Coluna que identifica cada registro.
     * E ela que entra no WHERE de buscar(), atualizar() e excluir().
     */
    protected string $chavePrimaria = 'id';

    /**
     * Campos que podem ser gravados (protege contra envio de campos extras).
     *
     * Isto e uma LISTA BRANCA de seguranca. Como os dados vem do $_POST,
     * sem ela alguem poderia acrescentar um <input name="admin"> na pagina
     * pelo navegador e gravar uma coluna que nao deveria — golpe conhecido
     * como "mass assignment". Tudo que estiver fora desta lista e descartado
     * em silencio por criar() e atualizar().
     */
    protected array $preenchiveis = ['nome', 'email', 'curso', 'nota'];

    /**
     * Ordenacao usada pelo metodo todos().
     * Evita repetir "ORDER BY nome" em cada chamada.
     */
    protected string $ordemPadrao = 'nome ASC';

    /**
     * Opcoes do campo "curso" — usadas no formulario e na validacao.
     *
     * "const" e um valor fixo da classe: nao muda em tempo de execucao.
     * Dentro da classe use self::CURSOS; fora dela, Aluno::CURSOS.
     *
     * Existe para haver UMA UNICA FONTE DA VERDADE: a mesma lista monta o
     * <select> na view e confere o que chegou no validar(). Se fossem duas
     * listas separadas, um dia elas ficariam diferentes.
     */
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
     * Sobrescreve o validar() da classe-base (que nao valida nada). O
     * controller chama este metodo antes de gravar e so segue em frente
     * se o array devolvido vier VAZIO.
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
        // O validador recebe os dados uma vez e depois so responde perguntas.
        $validador = new Validador($dados);

        // Cada regra devolve o proprio validador, por isso da para emendar
        // uma na outra (o chamado "encadeamento"). O segundo/ultimo argumento
        // e apenas o ROTULO que aparece na mensagem: sem ele sairia
        // "O campo email e obrigatorio" em vez de "O campo E-mail ...".
        $validador
            ->obrigatorio('nome', 'Nome')            // nao pode vir vazio
            ->minimo('nome', 3, 'Nome')              // pelo menos 3 caracteres
            ->maximo('nome', 100, 'Nome')            // no maximo 100 (igual ao VARCHAR)
            ->obrigatorio('email', 'E-mail')
            ->email('email', 'E-mail')               // formato valido (tem @, dominio...)
            ->obrigatorio('curso', 'Curso')
            ->dentroDe('curso', self::CURSOS, 'Curso')  // tem que ser um dos 5
            ->numerico('nota', 'Nota')               // e mesmo um numero?
            ->entre('nota', 0, 10, 'Nota');          // esta na faixa 0..10?

        // Regra que precisa consultar o banco: e-mail nao pode repetir.
        // As regras acima so olham o TEXTO digitado; unicidade so se descobre
        // perguntando ao banco, por isso ela vem separada, aqui embaixo.
        $email = trim((string) ($dados['email'] ?? ''));

        if ($email !== '' && $this->emailJaUsado($email, $ignorarId)) {
            // personalizada() aceita qualquer condicao propria: passamos
            // "false" para dizer que a regra FALHOU e registrar a mensagem.
            $validador->personalizada('email', false, 'Este e-mail ja esta cadastrado.');
        }

        // Array "campo => mensagem". Vazio = pode gravar.
        return $validador->erros();
    }

    /**
     * Verifica se o e-mail ja pertence a outro aluno.
     */
    public function emailJaUsado(string $email, int|string|null $ignorarId = null): bool
    {
        // primeiroOnde() = "SELECT * FROM alunos WHERE email = ?", primeira linha.
        $existente = $this->primeiroOnde('email', $email);

        // Ninguem usa este e-mail: caminho livre.
        if ($existente === null) {
            return false;
        }

        // Chegando aqui, o e-mail EXISTE. A pergunta que resta e: existe em
        // outro registro, ou justamente no que estou editando?
        //
        // Em portugues, a linha abaixo diz:
        //   "esta usado, A NAO SER que o dono dele seja o registro que estou
        //    editando agora".
        //
        // Os (string) existem porque o banco pode devolver o id como texto
        // ("5") e a URL tambem: com !== (que compara TIPO tambem), 5 !== "5"
        // daria verdadeiro por engano e o aluno nunca conseguiria se salvar.
        return $ignorarId === null || (string) $existente['id'] !== (string) $ignorarId;
    }

    // ------------------------------------------------------------------
    // Consultas proprias deste modelo (exemplos para a turma)
    //
    // Aqui entra tudo que o CRUD pronto da classe-base nao cobre. Todas
    // usam consultar(), que executa o SELECT e devolve um array de linhas.
    // ------------------------------------------------------------------

    /**
     * Busca alunos cujo nome OU e-mail contenham o termo digitado.
     * Chamado pelo index() do controller quando existe ?busca=... na URL.
     */
    public function procurar(string $termo): array
    {
        // Sql::comoLike neutraliza os curingas % e _ que o usuario digitar,
        // e o valor segue como parametro (?), nunca dentro do texto do SQL.
        // (Sem isso, buscar por "%" traria a tabela inteira.)
        // Ele tambem ja envolve o termo em %...% para casar no meio do texto.
        $termo = Sql::comoLike($termo);

        // Os dois "?" recebem o mesmo termo — por isso ele aparece duas vezes
        // no array de parametros, na ordem em que os "?" aparecem no SQL.
        return $this->consultar(
            'SELECT * FROM alunos WHERE nome LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE
            . ' OR email LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE
            . ' ORDER BY nome',
            [$termo, $termo]
        );
    }

    /**
     * Alunos com nota maior ou igual a 6.
     *
     * Exemplo de WHERE com condicao fixa. Ainda nao e usado por nenhuma
     * rota — fica de exercicio para a turma exibi-lo em uma tela.
     */
    public function aprovados(): array
    {
        return $this->consultar('SELECT * FROM alunos WHERE nota >= 6 ORDER BY nota DESC');
    }

    /**
     * Media das notas da turma.
     * Usado pelo api() do controller.
     */
    public function mediaGeral(): float
    {
        // AVG() e uma funcao de agregacao do proprio SQL: o banco calcula e
        // devolve UMA linha com UMA coluna chamada "media".
        // O "WHERE nota IS NOT NULL" evita que alunos sem nota entrem na
        // conta como zero e derrubem a media.
        $linhas = $this->consultar('SELECT AVG(nota) AS media FROM alunos WHERE nota IS NOT NULL');

        // ?? 0     -> cobre o caso da tabela vazia (AVG devolve NULL).
        // (float)  -> o banco entrega numeros como texto.
        // round(,2)-> deixa 7.35 em vez de 7.3500000001.
        return round((float) ($linhas[0]['media'] ?? 0), 2);
    }

    /**
     * Quantos alunos existem em cada curso.
     *
     * Exemplo classico de GROUP BY: devolve uma linha por curso, no formato
     * [ ['curso' => 'Informatica', 'total' => 12], ... ].
     * Assim como aprovados(), ainda esta disponivel para a turma usar.
     */
    public function totalPorCurso(): array
    {
        return $this->consultar(
            'SELECT curso, COUNT(*) AS total FROM alunos GROUP BY curso ORDER BY total DESC, curso'
        );
    }
}
