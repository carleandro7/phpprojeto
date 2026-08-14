<?php

namespace Nucleo;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Responsavel pela conexao com o banco de dados.
 *
 * Usa o padrao "Singleton": a conexao e criada uma unica vez e reaproveitada
 * em toda a aplicacao. Suporta SQLite (padrao, nao precisa instalar nada)
 * e MySQL (para quando a turma usar XAMPP / phpMyAdmin).
 */
class Database
{
    private static ?PDO $conexao = null;

    /**
     * Devolve a conexao PDO, criando-a na primeira chamada.
     */
    public static function conexao(): PDO
    {
        if (self::$conexao === null) {
            self::$conexao = self::criar();
        }

        return self::$conexao;
    }

    /**
     * Injeta uma conexao pronta. Usado pelos testes para apontar
     * para um banco de dados descartavel.
     */
    public static function definirConexao(?PDO $pdo): void
    {
        self::$conexao = $pdo;
    }

    /**
     * Fecha/esquece a conexao atual.
     */
    public static function desconectar(): void
    {
        self::$conexao = null;
    }

    /**
     * Monta a conexao de acordo com o driver escolhido em configuracoes/banco.php.
     */
    private static function criar(): PDO
    {
        $driver  = Config::obter('banco.driver', 'sqlite');
        $opcoes  = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $arquivo = Config::obter('banco.sqlite.arquivo');

                if ($arquivo !== ':memory:' && !is_dir(dirname($arquivo))) {
                    mkdir(dirname($arquivo), 0777, true);
                }

                $pdo = new PDO('sqlite:' . $arquivo, null, null, $opcoes);
                // Liga a checagem de chaves estrangeiras (desligada por padrao no SQLite).
                $pdo->exec('PRAGMA foreign_keys = ON');

                return $pdo;
            }

            if ($driver === 'mysql') {
                $c   = Config::obter('banco.mysql');
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $c['host'],
                    $c['porta'],
                    $c['banco'],
                    $c['charset']
                );

                return new PDO($dsn, $c['usuario'], $c['senha'], $opcoes);
            }
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Nao foi possivel conectar ao banco de dados: ' . $e->getMessage(),
                0,
                $e
            );
        }

        throw new RuntimeException("Driver de banco desconhecido: {$driver}");
    }

    /**
     * Cria as tabelas lendo o arquivo de esquema correspondente ao driver.
     * Ex.: banco/esquema.sqlite.sql
     */
    public static function migrar(): void
    {
        $driver  = Config::obter('banco.driver', 'sqlite');
        $arquivo = CAMINHO_RAIZ . '/banco/esquema.' . $driver . '.sql';

        if (!is_file($arquivo)) {
            throw new RuntimeException("Arquivo de esquema nao encontrado: {$arquivo}");
        }

        self::executarArquivoSql($arquivo);
    }

    /**
     * Insere os registros de exemplo (seed).
     */
    public static function popular(): void
    {
        self::executarArquivoSql(CAMINHO_RAIZ . '/banco/dados_exemplo.sql');
    }

    /**
     * Executa um arquivo .sql comando por comando.
     */
    public static function executarArquivoSql(string $arquivo): void
    {
        if (!is_file($arquivo)) {
            throw new RuntimeException("Arquivo SQL nao encontrado: {$arquivo}");
        }

        $sql = file_get_contents($arquivo);

        // Remove os comentarios de linha para nao atrapalhar a divisao por ";".
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        $pdo = self::conexao();

        foreach (explode(';', $sql) as $comando) {
            $comando = trim($comando);

            if ($comando !== '') {
                $pdo->exec($comando);
            }
        }
    }
}
