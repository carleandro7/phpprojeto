<?php

namespace Nucleo;

/**
 * Inventa valores para encher uma tabela.
 *
 * E o que esta por tras da funcao falsos() do arquivo de semeadura:
 *
 *     falsos('produtos', 50);
 *
 * Os valores combinam com o NOME e o TIPO de cada coluna — "preco" vira
 * dinheiro, "email" vira e-mail, "nascimento" vira data. Nao e para
 * substituir os dados que voce escreve a mao; e para a tela nao ficar vazia
 * quando voce precisa ver a listagem, a pesquisa e a paginacao funcionando.
 */
class DadosFalsos
{
    /** Senha das contas inventadas. */
    public const SENHA = '123456';

    /**
     * Um valor para a coluna.
     *
     * @param string $tabela  nome da tabela (decide se "nome" e de gente)
     * @param string $coluna  nome da coluna
     * @param string $tipo    tipo do scaffold: string, integer, date...
     * @param int    $indice  numero da linha, usado onde precisa nao repetir
     * @param bool   $unico   a coluna tem indice UNIQUE
     */
    public static function para(
        string $tabela,
        string $coluna,
        string $tipo,
        int $indice,
        bool $unico = false
    ): mixed {
        $perfil = self::perfilDoCampo($coluna);

        if ($perfil === 'senha') {
            return password_hash(self::SENHA, PASSWORD_DEFAULT);
        }

        // O perfil manda no valor, desde que caiba no tipo da coluna.
        $porPerfil = match ($perfil) {
            'email'    => strtolower(self::sorteado(self::primeirosNomes())) . $indice . '@example.com',
            'pessoa'   => self::nomeDoRegistro($tabela, $coluna, $indice),
            'titulo'   => self::sorteado(self::titulos()),
            'telefone' => sprintf('(%02d) 9%04d-%04d', mt_rand(11, 99), mt_rand(1000, 9999), mt_rand(1000, 9999)),
            'cpf'      => sprintf('%03d.%03d.%03d-%02d', mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 99)),
            'cnpj'     => sprintf('%02d.%03d.%03d/0001-%02d', mt_rand(0, 99), mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 99)),
            'cep'      => sprintf('%05d-%03d', mt_rand(1000, 99999), mt_rand(0, 999)),
            'uf'       => self::sorteado(['SP', 'RJ', 'MG', 'BA', 'PR', 'RS', 'PE', 'CE', 'SC', 'GO']),
            'cidade'   => self::sorteado(self::cidades()),
            'endereco' => 'Rua ' . self::sorteado(self::sobrenomes()) . ', ' . mt_rand(10, 1999),
            'texto'    => self::frase(),
            'dinheiro' => round(mt_rand(500, 500000) / 100, 2),
            'contagem' => mt_rand(0, 250),
            'nota'     => round(mt_rand(0, 100) / 10, 1),
            'idade'    => mt_rand(16, 75),
            'situacao' => self::sorteado(['ativo', 'pendente', 'concluido', 'cancelado']),
            // Coluna de arquivo fica vazia: inventar um caminho so produziria
            // imagem quebrada na tela, porque o arquivo nao existe no disco.
            'arquivo'  => '',
            default    => null,
        };

        $numerico = in_array($tipo, ['integer', 'decimal'], true);
        $texto    = in_array($tipo, ['string', 'text'], true);

        if ($porPerfil !== null && ($texto || ($numerico && is_numeric($porPerfil)))) {
            return $porPerfil;
        }

        return match ($tipo) {
            'integer'  => $unico ? $indice : mt_rand(1, 500),
            'decimal'  => round(mt_rand(100, 200000) / 100, 2),
            'boolean'  => mt_rand(0, 1),
            'date'     => self::dataSorteada('Y-m-d'),
            'datetime' => self::dataSorteada('Y-m-d H:i:s'),
            'time'     => sprintf('%02d:%02d:00', mt_rand(7, 22), self::sorteado([0, 15, 30, 45])),
            'text'     => self::frase(),
            default    => self::sorteado(self::produtos()) . ' ' . $indice,
        };
    }

    /**
     * Muda um valor o suficiente para ele nao repetir.
     *
     * Em e-mail o numero entra antes do @, senao o endereco deixaria de ser
     * um e-mail valido — e a tela de login nunca aceitaria a conta.
     */
    public static function distinto(mixed $valor, int $sufixo): mixed
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor + $sufixo;
        }

        $texto = (string) $valor;

        if (str_contains($texto, '@')) {
            [$antes, $depois] = explode('@', $texto, 2);

            return $antes . $sufixo . '@' . $depois;
        }

        return $texto . ' ' . $sufixo;
    }

    /**
     * Descobre o que a coluna guarda pelo nome dela.
     *
     * E o que faz "preco" nascer com dinheiro e "email" com um e-mail de
     * verdade, em vez de todo campo de texto virar "Texto 1".
     */
    public static function perfilDoCampo(string $nome): string
    {
        $nome = strtolower($nome);

        $perfis = [
            'senha'     => ['senha', 'password'],
            'email'     => ['email', 'e_mail'],
            'arquivo'   => ['foto', 'imagem', 'arquivo', 'anexo', 'documento', 'capa', 'avatar'],
            'pessoa'    => ['nome', 'responsavel', 'autor', 'professor', 'aluno', 'cliente', 'usuario'],
            'titulo'    => ['titulo', 'assunto', 'disciplina', 'curso', 'materia'],
            'telefone'  => ['telefone', 'fone', 'celular', 'whatsapp', 'contato'],
            'cpf'       => ['cpf'],
            'cnpj'      => ['cnpj'],
            'cep'       => ['cep'],
            'uf'        => ['uf', 'estado'],
            'cidade'    => ['cidade', 'municipio'],
            'endereco'  => ['endereco', 'rua', 'logradouro'],
            'texto'     => ['descricao', 'observacao', 'obs', 'resumo', 'comentario', 'conteudo', 'mensagem'],
            'dinheiro'  => ['preco', 'valor', 'salario', 'total', 'custo', 'desconto'],
            'contagem'  => ['quantidade', 'estoque', 'qtd', 'vagas', 'paginas'],
            'nota'      => ['nota', 'media', 'pontuacao'],
            'idade'     => ['idade'],
            'situacao'  => ['situacao', 'status'],
        ];

        foreach ($perfis as $perfil => $pistas) {
            foreach ($pistas as $pista) {
                if ($nome === $pista || str_contains($nome, $pista)) {
                    return $perfil;
                }
            }
        }

        return '';
    }

    /**
     * Traduz o tipo do MySQL para o tipo do scaffold.
     *
     * E o que permite descobrir sozinho que "ativo" e um boolean
     * (TINYINT(1)) e "nascimento" uma data.
     */
    public static function tipoDoSql(string $sql): string
    {
        $sql = strtoupper((string) preg_replace('/\s+/', '', $sql));

        return match (true) {
            str_starts_with($sql, 'TINYINT(1)') => 'boolean',
            str_starts_with($sql, 'DATETIME'),
            str_starts_with($sql, 'TIMESTAMP')  => 'datetime',
            str_starts_with($sql, 'DATE')       => 'date',
            str_starts_with($sql, 'TIME')       => 'time',
            str_starts_with($sql, 'DECIMAL'),
            str_starts_with($sql, 'NUMERIC'),
            str_starts_with($sql, 'DOUBLE'),
            str_starts_with($sql, 'FLOAT'),
            str_starts_with($sql, 'REAL')       => 'decimal',
            str_starts_with($sql, 'TINYINT'),
            str_starts_with($sql, 'SMALLINT'),
            str_starts_with($sql, 'MEDIUMINT'),
            str_starts_with($sql, 'BIGINT'),
            str_starts_with($sql, 'INT')        => 'integer',
            str_starts_with($sql, 'TEXT')       => 'text',
            default                             => 'string',
        };
    }

    // ------------------------------------------------------------------
    // Listas e sorteios
    // ------------------------------------------------------------------

    /**
     * O que escrever na coluna "nome".
     *
     * Em tabela de gente sai o nome de uma pessoa; nas outras sai o nome da
     * propria entidade com o numero da linha — "Categoria 3" diz muito mais
     * em um <select> do que "Joao Martins" em uma lista de categorias.
     */
    public static function nomeDoRegistro(string $tabela, string $coluna, int $indice): string
    {
        $pessoas = [
            'pessoa', 'aluno', 'professor', 'cliente', 'usuario', 'funcionario',
            'autor', 'membro', 'participante', 'medico', 'paciente', 'motorista',
            'vendedor', 'responsavel', 'contato', 'fornecedor',
        ];

        foreach ($pessoas as $pista) {
            if (str_contains(strtolower($tabela), $pista) || str_contains(strtolower($coluna), $pista)) {
                return self::sorteado(self::primeirosNomes()) . ' ' . self::sorteado(self::sobrenomes());
            }
        }

        return self::entidade($tabela) . ' ' . $indice;
    }

    /** 'categorias' -> 'Categoria' (so para escrever na tela). */
    private static function entidade(string $tabela): string
    {
        $nome = str_replace('_', ' ', strtolower($tabela));

        $singular = match (true) {
            str_ends_with($nome, 'oes'), str_ends_with($nome, 'aes') => substr($nome, 0, -3) . 'ao',
            str_ends_with($nome, 'ais') => substr($nome, 0, -3) . 'al',
            str_ends_with($nome, 'eis') => substr($nome, 0, -3) . 'el',
            str_ends_with($nome, 'ns')  => substr($nome, 0, -2) . 'm',
            str_ends_with($nome, 'res'), str_ends_with($nome, 'zes'), str_ends_with($nome, 'ses')
                => substr($nome, 0, -2),
            str_ends_with($nome, 's')   => substr($nome, 0, -1),
            default                     => $nome,
        };

        return ucwords($singular);
    }

    private static function dataSorteada(string $formato): string
    {
        return date($formato, time() + mt_rand(-365, 365) * 86400 + mt_rand(0, 86399));
    }

    private static function sorteado(array $opcoes): mixed
    {
        return $opcoes[array_rand($opcoes)];
    }

    private static function frase(): string
    {
        $partes = [
            'Registro criado para teste',
            'Cadastro de exemplo do sistema',
            'Item gerado pela semeadura',
            'Dados ficticios para demonstracao',
            'Informacao de apoio para a listagem',
        ];

        return self::sorteado($partes) . ' em ' . date('d/m/Y') . '.';
    }

    private static function primeirosNomes(): array
    {
        return [
            'Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Felipe', 'Gabriela', 'Heitor',
            'Isabela', 'Joao', 'Larissa', 'Marcos', 'Natalia', 'Otavio', 'Paula',
            'Rafael', 'Sofia', 'Tiago', 'Vitoria', 'Yuri',
        ];
    }

    private static function sobrenomes(): array
    {
        return [
            'Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Lima', 'Costa',
            'Almeida', 'Nunes', 'Ferreira', 'Rodrigues', 'Barbosa', 'Martins',
        ];
    }

    private static function cidades(): array
    {
        return [
            'Sao Paulo', 'Rio de Janeiro', 'Belo Horizonte', 'Curitiba', 'Salvador',
            'Recife', 'Porto Alegre', 'Fortaleza', 'Goiania', 'Manaus', 'Florianopolis',
        ];
    }

    private static function titulos(): array
    {
        return [
            'Introducao a Programacao', 'Banco de Dados', 'Redes de Computadores',
            'Desenvolvimento Web', 'Logica de Programacao', 'Engenharia de Software',
            'Sistemas Operacionais', 'Projeto Integrador',
        ];
    }

    private static function produtos(): array
    {
        return [
            'Teclado', 'Mouse', 'Monitor', 'Notebook', 'Impressora', 'Cadeira',
            'Mesa', 'Headset', 'Webcam', 'Roteador', 'Caderno', 'Mochila',
        ];
    }
}
