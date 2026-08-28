# Pasta de testes

```bash
php testes/executar.php
```

## Organização

| Pasta          | O que guarda                                                        |
|----------------|---------------------------------------------------------------------|
| `suporte/`     | o motor de testes: `TesteBase`, `Executor`, `Resposta`. Não mexa.   |
| `exemplos/`    | `ExemploTest.php` — todas as verificações comentadas. Comece aqui.  |
| `modelos/`     | testes dos modelos (banco de dados, CRUD, validação)                |
| `controllers/` | testes dos controladores (simulam o navegador de ponta a ponta)     |
| `nucleo/`      | testes do próprio framework: rotas, validador, views, segurança     |

A subpasta vira parte do namespace:

```
testes/modelos/ProdutoTest.php      →  Testes\Modelos\ProdutoTest
testes/controllers/HomeTest.php     →  Testes\Controllers\HomeTest
```

Crie novas pastas à vontade — o executor procura recursivamente.

## Regras

1. O arquivo termina em `Test.php`.
2. A classe tem o nome do arquivo e herda de `Testes\Suporte\TesteBase`.
3. Os métodos de teste começam com `teste`.
4. `preparar()` roda antes de cada teste; `finalizar()`, depois.

## Rodando parte dos testes

```bash
php testes/executar.php Modelos          # a pasta modelos
php testes/executar.php ViewTest         # um arquivo
php testes/executar.php SegurancaSql     # outro arquivo
php testes/executar.php validacao        # qualquer teste com "validacao" no nome
```

## Banco de dados

Os testes usam um **SQLite em memória**, criado vazio a cada execução
(veja `bootstrap.php`). Rodar os testes **nunca** altera `banco/dados.sqlite`.

Por isso cada teste precisa criar os dados de que precisa, normalmente no
`preparar()`:

```php
public function preparar(): void
{
    $this->limparTabela('produtos');
}
```

## Consulta rápida

Verificações: `assertIgual`, `assertIdentico`, `assertDiferente`,
`assertVerdadeiro`, `assertFalso`, `assertNulo`, `assertNaoNulo`,
`assertVazio`, `assertNaoVazio`, `assertContem`, `assertNaoContem`,
`assertTotal`, `assertTemChave`, `assertTemValor`, `assertInstanciaDe`,
`assertExcecao`, `falhar`.

Simulação de navegador: `requisitar($url)`, `postar($url, $dados)`,
`$resposta->html`, `$resposta->status`, `$resposta->foiRedirecionado()`,
`$resposta->redirecionouPara()`, `$resposta->json()`.

Banco e sessão: `limparTabela()`, `contarNaTabela()`, `limparSessao()`.

Detalhes e exemplos completos: `README.md` na raiz, seção 8.
