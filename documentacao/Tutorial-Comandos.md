# Tutorial dos comandos do framework

Este tutorial mostra como iniciar uma aplicacao do zero usando o console do
framework. O projeto nao cria tabelas nem dados de exemplo automaticamente.

## 1. Preparar o projeto

Requisitos:

- PHP 8.1 ou superior;
- SQLite ou MySQL/MariaDB;
- terminal aberto na pasta do projeto.

Confira se o framework esta funcionando:

```bash
php testes/executar.php
```

Depois prepare o banco:

```bash
php instalar.php
```

O instalador cria o banco, mas nao cria tabelas da aplicacao. Isso permite que
cada projeto comece com seu proprio modelo de dados.

## 2. Gerar um CRUD

Use o comando abaixo informando o nome da tabela e os campos:

```bash
php console.php scaffold:crud produtos nome:string preco:decimal estoque:integer
```

O comando:

- cria o modelo `modelos/Produto.php`;
- cria `controllers/ProdutosController.php`;
- cria as views em `views/produtos/`;
- cria o teste `testes/modelos/ProdutoTest.php`;
- adiciona a tabela aos esquemas SQLite e MySQL;
- executa o esquema do driver configurado.

### Tipos disponiveis

| Tipo do comando | Tipo SQLite | Tipo MySQL | Campo HTML |
|---|---|---|---|
| `string` | `TEXT` | `VARCHAR(255)` | texto |
| `text` | `TEXT` | `VARCHAR(255)` | texto |
| `integer` | `INTEGER` | `INT` | numero |
| `decimal` | `REAL` | `DECIMAL(12,2)` | numero |
| `boolean` | `INTEGER` | `TINYINT(1)` | checkbox |
| `date` | `TEXT` | `DATE` | data |
| `datetime` | `TEXT` | `DATETIME` | data e hora |
| `time` | `TEXT` | `TIME` | hora |

O campo `id` e criado automaticamente e nao deve ser informado. O campo
`criado_em` tambem e reservado.

### Rotas geradas

Para o exemplo `produtos`, as rotas serao:

| Rota | Funcao |
|---|---|
| `/produtos` | lista registros |
| `/produtos/criar` | abre o formulario |
| `/produtos/salvar` | grava um registro via POST |
| `/produtos/ver/1` | mostra um registro |
| `/produtos/editar/1` | abre a edicao |
| `/produtos/atualizar/1` | atualiza via POST |
| `/produtos/excluir/1` | exclui um registro |

O comando nao sobrescreve arquivos existentes. Se precisar gerar outro recurso,
use outro nome ou remova manualmente os arquivos gerados depois de confirmar
que nao serao mais usados.

### Personalizar o CRUD

O modelo gerado ja herda o CRUD de `Nucleo\Model`:

```php
$produto = new \Modelos\Produto();
$produto->todos();
$produto->buscar($id);
$produto->criar(['nome' => 'Teclado', 'preco' => 99.90]);
$produto->atualizar($id, ['preco' => 89.90]);
$produto->excluir($id);
```

Use o modelo para validacoes e consultas especificas. Use o controller para
receber dados e escolher a view. Use as views somente para HTML.

O teste gerado usa o banco SQLite em memoria e verifica o CRUD basico do
modelo: criar, buscar, contar, atualizar e excluir. Rode somente esse teste
com o filtro:

```bash
php testes/executar.php ProdutoTest
```

Depois de personalizar o modelo ou o controller, amplie esse teste com as
regras especificas da sua aplicacao.

## 3. Adicionar login

Para criar a tabela de usuarios e as telas de autenticacao, execute:

```bash
php console.php auth:install
```

O comando cria:

- `modelos/Usuario.php`;
- `controllers/AuthController.php`;
- `views/auth/login.php`;
- `views/auth/registrar.php`;
- a tabela `usuarios` nos esquemas SQLite e MySQL.

### Paginas de autenticacao

Depois do comando, estas rotas ficam disponiveis:

| Rota | Funcao |
|---|---|
| `/auth/login` | mostra e processa o login |
| `/auth/registrar` | cria uma conta |
| `/auth/sair` | encerra a sessao |

As senhas nunca sao gravadas em texto puro. O controller usa `password_hash()`
para salvar e `password_verify()` para conferir a senha no login.

### Proteger um controller

Chame `exigirAutenticacao()` no inicio de cada metodo que precisa de usuario:

```php
<?php

namespace Controllers;

use Nucleo\Controller;

class RelatoriosController extends Controller
{
    public function index(): void
    {
        $this->exigirAutenticacao();

        $this->view('relatorios/index', [
            'titulo' => 'Relatorios',
        ]);
    }
}
```

Quem nao estiver logado sera redirecionado para `/auth/login`.

Tambem e possivel verificar a sessao diretamente nas views ou controllers:

```php
<?php if (autenticado()): ?>
    <a href="<?= url('auth/sair') ?>">Sair</a>
<?php endif ?>
```

O helper `usuario_id()` devolve o id do usuario logado ou `null`.

## 4. Fluxo recomendado

Para iniciar um projeto novo:

```bash
php instalar.php
php console.php scaffold:crud clientes nome:string email:string telefone:string
php console.php auth:install
php -S localhost:8000 roteador.php
```

Abra `http://localhost:8000/auth/registrar` para criar o primeiro usuario e
`http://localhost:8000/clientes` para usar o CRUD.

## 5. Testar

Rode a suite completa depois de personalizar o projeto:

```bash
php testes/executar.php
```

Antes de usar em producao, revise as validacoes dos campos, proteja as rotas
necessarias com `exigirAutenticacao()` e configure `app.debug` como `false`.