<?php

namespace Testes\Suporte;

use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Encontra e executa os testes.
 *
 * Regras de descoberta:
 *   - arquivos terminados em "Test.php" em qualquer subpasta de testes/
 *   - classes que herdam de TesteBase
 *   - metodos publicos que comecam com "teste"
 *
 * A subpasta vira parte do namespace, seguindo o autoloader:
 *
 *     testes/modelos/ProdutoTest.php    ->  Testes\Modelos\ProdutoTest
 *     testes/controllers/HomeTest.php   ->  Testes\Controllers\HomeTest
 *
 * A pasta testes/suporte guarda o motor de testes e e ignorada
 * automaticamente (nenhum arquivo dela termina em Test.php).
 */
class Executor
{
    private int   $totalTestes = 0;
    private int   $passaram    = 0;
    private int   $assercoes   = 0;
    private array $falhas      = [];
    private array $erros       = [];
    private float $inicio      = 0.0;

    private bool $colorido;

    public function __construct(
        private string $diretorio,
        private ?string $filtro = null
    ) {
        // Só usa cores se a saida for um terminal de verdade.
        $this->colorido = PHP_SAPI === 'cli'
            && function_exists('posix_isatty')
            && @posix_isatty(STDOUT);
    }

    /**
     * Roda tudo e devolve o codigo de saida (0 = sucesso, 1 = houve falhas).
     */
    public function executar(): int
    {
        $this->inicio = microtime(true);

        $this->linha($this->cor('Executando os testes do framework', 'ciano'));

        if ($this->filtro !== null) {
            $this->linha('Filtro: ' . $this->filtro);
        }

        $this->linha('');

        $classes = $this->descobrirClasses();

        if ($classes === []) {
            $this->linha($this->cor('Nenhum teste encontrado.', 'amarelo'));
            $this->linha('Crie um arquivo terminado em Test.php dentro de testes/.');

            return 0;
        }

        foreach ($classes as $classe) {
            $this->executarClasse($classe);
        }

        return $this->resumo();
    }

    // ------------------------------------------------------------------
    // Descoberta
    // ------------------------------------------------------------------

    /**
     * @return array<int,string> nomes completos das classes de teste
     */
    private function descobrirClasses(): array
    {
        $classes = [];

        foreach ($this->arquivosDeTeste() as $arquivo) {
            require_once $arquivo;

            $relativo = substr($arquivo, strlen($this->diretorio) + 1, -4); // tira ".php"
            $classe   = 'Testes\\' . str_replace('/', '\\', $relativo);

            if (!class_exists($classe)) {
                continue;
            }

            $reflexao = new ReflectionClass($classe);

            if ($reflexao->isAbstract() || !$reflexao->isSubclassOf(TesteBase::class)) {
                continue;
            }

            // getName() devolve o nome exatamente como foi declarado no arquivo
            // (o caminho da pasta pode estar em minusculas).
            $classes[] = $reflexao->getName();
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return array<int,string> caminhos dos arquivos *Test.php
     */
    private function arquivosDeTeste(): array
    {
        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->diretorio, \FilesystemIterator::SKIP_DOTS)
        );

        $arquivos = [];

        foreach ($iterador as $arquivo) {
            if ($arquivo->isFile() && str_ends_with($arquivo->getFilename(), 'Test.php')) {
                $arquivos[] = str_replace('\\', '/', $arquivo->getPathname());
            }
        }

        sort($arquivos);

        return $arquivos;
    }

    // ------------------------------------------------------------------
    // Execucao
    // ------------------------------------------------------------------

    private function executarClasse(string $classe): void
    {
        $reflexao = new ReflectionClass($classe);
        $metodos  = [];

        foreach ($reflexao->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            if (str_starts_with($metodo->getName(), 'teste')) {
                $metodos[] = $metodo->getName();
            }
        }

        if ($metodos === []) {
            return;
        }

        $nomeCurto = substr($classe, strlen('Testes\\'));

        // Se ha filtro, so mostra a classe caso algum metodo passe no filtro.
        $metodos = array_values(array_filter(
            $metodos,
            fn (string $m) => $this->passaNoFiltro($nomeCurto, $m)
        ));

        if ($metodos === []) {
            return;
        }

        $this->linha($this->cor($nomeCurto, 'negrito'));

        foreach ($metodos as $metodo) {
            $this->executarMetodo($classe, $nomeCurto, $metodo);
        }

        $this->linha('');
    }

    private function executarMetodo(string $classe, string $nomeCurto, string $metodo): void
    {
        $this->totalTestes++;

        /** @var TesteBase $instancia */
        $instancia = new $classe();
        $comecou   = microtime(true);

        try {
            $instancia->preparar();
            $instancia->$metodo();
            $instancia->finalizar();

            $this->passaram++;
            $this->assercoes += $instancia->totalDeAssercoes();

            $this->linha(sprintf(
                '  %s %s %s',
                $this->cor('PASSOU', 'verde'),
                $this->descreverMetodo($metodo),
                $this->cor($this->duracao($comecou), 'cinza')
            ));
        } catch (FalhaAssercao $falha) {
            $this->assercoes += $instancia->totalDeAssercoes();
            $this->encerrarComSeguranca($instancia);

            $this->falhas[] = [
                'teste'    => "{$nomeCurto}::{$metodo}",
                'mensagem' => $falha->getMessage(),
                'arquivo'  => $this->localDaFalha($falha),
            ];

            $this->linha(sprintf(
                '  %s %s',
                $this->cor('FALHOU', 'vermelho'),
                $this->descreverMetodo($metodo)
            ));
            $this->linha('         ' . $this->cor($falha->getMessage(), 'vermelho'));
        } catch (Throwable $erro) {
            $this->assercoes += $instancia->totalDeAssercoes();
            $this->encerrarComSeguranca($instancia);

            $this->erros[] = [
                'teste'    => "{$nomeCurto}::{$metodo}",
                'mensagem' => get_class($erro) . ': ' . $erro->getMessage(),
                'arquivo'  => $erro->getFile() . ':' . $erro->getLine(),
            ];

            $this->linha(sprintf(
                '  %s  %s',
                $this->cor('ERRO', 'amarelo'),
                $this->descreverMetodo($metodo)
            ));
            $this->linha('         ' . $this->cor(get_class($erro) . ': ' . $erro->getMessage(), 'amarelo'));
        }
    }

    /**
     * Roda o finalizar() sem deixar um erro nele mascarar o erro original.
     */
    private function encerrarComSeguranca(TesteBase $instancia): void
    {
        try {
            $instancia->finalizar();
        } catch (Throwable) {
            // Ignorado de proposito.
        }
    }

    // ------------------------------------------------------------------
    // Relatorio
    // ------------------------------------------------------------------

    private function resumo(): int
    {
        $segundos = number_format(microtime(true) - $this->inicio, 3);
        $falhou   = count($this->falhas) + count($this->erros);

        if ($falhou > 0) {
            $this->linha($this->cor('--- Detalhes dos problemas ---', 'negrito'));
            $this->linha('');

            $numero = 0;

            foreach (array_merge($this->falhas, $this->erros) as $item) {
                $numero++;
                $this->linha($this->cor("{$numero}) {$item['teste']}", 'vermelho'));
                $this->linha('   ' . $item['mensagem']);
                $this->linha('   ' . $this->cor($item['arquivo'], 'cinza'));
                $this->linha('');
            }
        }

        $this->linha(str_repeat('-', 58));

        $this->linha(sprintf(
            'Testes: %d | Passaram: %s | Falharam: %s | Erros: %s | Assercoes: %d',
            $this->totalTestes,
            $this->cor((string) $this->passaram, 'verde'),
            $this->cor((string) count($this->falhas), count($this->falhas) ? 'vermelho' : 'cinza'),
            $this->cor((string) count($this->erros), count($this->erros) ? 'amarelo' : 'cinza'),
            $this->assercoes
        ));

        $this->linha("Tempo: {$segundos}s");
        $this->linha('');

        if ($falhou === 0) {
            $this->linha($this->cor('TUDO CERTO! O sistema esta funcionando.', 'verde'));

            return 0;
        }

        $this->linha($this->cor("ATENCAO: {$falhou} teste(s) com problema.", 'vermelho'));

        return 1;
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    private function passaNoFiltro(string $classe, string $metodo): bool
    {
        if ($this->filtro === null) {
            return true;
        }

        $alvo = strtolower("{$classe}::{$metodo}");

        return str_contains($alvo, strtolower($this->filtro));
    }

    /**
     * testeSomaDoisNumeros -> "soma dois numeros"
     */
    private function descreverMetodo(string $metodo): string
    {
        $texto = preg_replace('/^teste/', '', $metodo);
        $texto = preg_replace('/(?<!^)[A-Z]/', ' $0', (string) $texto);

        return strtolower(trim((string) $texto));
    }

    private function localDaFalha(FalhaAssercao $falha): string
    {
        // Procura no rastro a primeira linha que esteja em um arquivo *Test.php.
        foreach ($falha->getTrace() as $quadro) {
            if (isset($quadro['file']) && str_ends_with($quadro['file'], 'Test.php')) {
                return $quadro['file'] . ':' . ($quadro['line'] ?? 0);
            }
        }

        return $falha->getFile() . ':' . $falha->getLine();
    }

    private function duracao(float $comecou): string
    {
        return '(' . number_format((microtime(true) - $comecou) * 1000, 1) . 'ms)';
    }

    private function linha(string $texto = ''): void
    {
        echo $texto . PHP_EOL;
    }

    private function cor(string $texto, string $cor): string
    {
        if (!$this->colorido) {
            return $texto;
        }

        $codigos = [
            'vermelho' => '0;31',
            'verde'    => '0;32',
            'amarelo'  => '0;33',
            'ciano'    => '0;36',
            'cinza'    => '0;90',
            'negrito'  => '1',
        ];

        $codigo = $codigos[$cor] ?? '0';

        return "\033[{$codigo}m{$texto}\033[0m";
    }
}
