<?php

/**
 * Funcoes de atalho disponiveis em qualquer lugar do sistema
 * (controladores, modelos e views).
 */

use Nucleo\Config;
use Nucleo\Sessao;
use Nucleo\View;

if (!function_exists('e')) {
    /**
     * "Escapa" um texto antes de imprimir no HTML.
     * Protege contra XSS. Use SEMPRE ao exibir dados vindos do usuario:
     *
    *     <?= e($registro['nome']) ?>
     */
    function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url_base')) {
    /**
     * Descobre o endereco raiz do sistema.
     * Se url_base estiver vazio em configuracoes/app.php, detecta sozinho
     * (funciona tanto em http://localhost:8000 quanto em
     *  http://localhost/framework no XAMPP).
     */
    function url_base(): string
    {
        $configurado = (string) Config::obter('app.url_base', '');

        if ($configurado !== '') {
            return rtrim($configurado, '/');
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $pasta  = str_replace('\\', '/', dirname($script));

        return $pasta === '/' || $pasta === '.' ? '' : rtrim($pasta, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Monta um link interno.
     *
     *     url()                 -> /framework
    *     url('produtos')       -> /framework/produtos
    *     url('produtos/ver/7') -> /framework/produtos/ver/7
     */
    function url(string $rota = ''): string
    {
        $rota = trim($rota, '/');

        return url_base() . '/' . $rota;
    }
}

if (!function_exists('asset')) {
    /**
     * Monta o caminho de um arquivo estatico dentro da pasta views.
     *
     *     asset('css/estilo.css')       -> /framework/views/css/estilo.css
     *     asset('imagens/logo.png')     -> /framework/views/imagens/logo.png
     *     asset('javascripts/app.js')   -> /framework/views/javascripts/app.js
     */
    function asset(string $caminho): string
    {
        return url_base() . '/views/' . ltrim($caminho, '/');
    }
}

if (!function_exists('parcial')) {
    /**
     * Inclui um pedaco de tela reaproveitavel dentro de outra view.
     *
     *     <?= parcial('template/mensagens') ?>
     */
    function parcial(string $view, array $dados = []): string
    {
        return View::parcial($view, $dados);
    }
}

if (!function_exists('antigo')) {
    /**
     * Recupera o valor digitado antes de um erro de validacao,
     * para nao obrigar o usuario a preencher o formulario de novo.
     *
    *     <input name="nome" value="<?= e(antigo('nome', $registro['nome'] ?? '')) ?>">
     */
    function antigo(string $campo, mixed $padrao = ''): mixed
    {
        return Sessao::entradaAntiga()[$campo] ?? $padrao;
    }
}

if (!function_exists('erro_de')) {
    /**
     * Devolve a mensagem de erro de validacao de um campo, ou null.
     *
     *     <?php if ($msg = erro_de('email')): ?>
     *         <span class="erro"><?= e($msg) ?></span>
     *     <?php endif ?>
     */
    function erro_de(string $campo): ?string
    {
        return Sessao::erros()[$campo] ?? null;
    }
}

if (!function_exists('tem_erro')) {
    /**
     * Informa se o campo tem erro (para pintar a borda de vermelho).
     */
    function tem_erro(string $campo): bool
    {
        return isset(Sessao::erros()[$campo]);
    }
}

if (!function_exists('mensagens')) {
    /**
     * Le as mensagens flash guardadas pelo controlador.
     */
    function mensagens(): array
    {
        return Sessao::lerFlash();
    }
}

if (!function_exists('token_csrf')) {
    /**
     * Token anti-CSRF desta sessao.
     */
    function token_csrf(): string
    {
        return Sessao::token();
    }
}

if (!function_exists('campo_csrf')) {
    /**
     * Campo escondido que TODO formulario POST deve conter.
     *
     *     <form method="post" action="<?= url('produtos/salvar') ?>">
     *         <?= campo_csrf() ?>
     *         ...
     *     </form>
     *
     * No controller, confira com $this->exigirFormularioValido().
     */
    function campo_csrf(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Sessao::token()) . '">';
    }
}

if (!function_exists('sim_nao')) {
    /**
     * Mostra um campo boolean como texto: 1 -> "Sim", 0/null -> "Nao".
     */
    function sim_nao(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 'Sim' : 'Nao';
    }
}

if (!function_exists('autenticado')) {
    function autenticado(?string $provider = null): bool
    {
        return Sessao::tem(Sessao::chaveAutenticacao($provider))
            || Sessao::tem(Sessao::chaveUsuario($provider));
    }
}

if (!function_exists('rota_login')) {
    /**
     * Rota da tela de login de um provider ('' = provider padrao, /auth).
     */
    function rota_login(?string $provider = null): string
    {
        return Nucleo\Autenticacao::rotaLogin($provider);
    }
}

if (!function_exists('rota_sair')) {
    function rota_sair(?string $provider = null): string
    {
        return Nucleo\Autenticacao::rotaSair($provider);
    }
}

if (!function_exists('usuario_id')) {
    function usuario_id(?string $provider = null): mixed
    {
        return Sessao::obter(
            Sessao::chaveAutenticacao($provider),
            Sessao::obter(Sessao::chaveUsuario($provider))
        );
    }
}

if (!function_exists('data_br')) {
    /**
     * Converte data do banco (2026-08-12 09:30:00) para o formato brasileiro.
     */
    function data_br(?string $data, bool $comHora = false): string
    {
        if (empty($data)) {
            return '';
        }

        $timestamp = strtotime($data);

        if ($timestamp === false) {
            return (string) $data;
        }

        return date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
    }
}

if (!function_exists('moeda_br')) {
    /**
     * Formata um numero como dinheiro: 1234.5 -> 1.234,50
     */
    function moeda_br(float|int|string|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}

if (!function_exists('dd')) {
    /**
     * "Dump and die": mostra o conteudo de uma variavel e para tudo.
     * Ferramenta de depuracao mais usada no dia a dia.
     */
    function dd(mixed ...$valores): never
    {
        echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:16px;border-radius:8px;'
           . 'font:13px/1.5 monospace;overflow:auto">';

        foreach ($valores as $valor) {
            var_dump($valor);
        }

        echo '</pre>';
        exit(1);
    }
}
