# Referencia de comandos

Este documento lista os comandos disponiveis no framework. Execute todos os
comandos a partir da pasta raiz do projeto.

## 1. Ver a ajuda do console

```bash
php console.php
```

Mostra os comandos de geracao disponiveis:

```text
php console.php scaffold:crud tabela campo:tipo ...
php console.php auth:install
```

## 2. Preparar o banco

```bash
php instalar.php
```

O instalador:

- le `configuracoes/banco.php`;
- cria o banco MySQL, caso necessario;
- executa o esquema do driver configurado;
- nao cria tabelas ou dados de exemplo por conta propria.

O driver pode ser `mysql` ou `sqlite`. Depois de alterar o driver ou o
esquema, execute o instalador novamente.

## 3. Gerar um CRUD

```bash
php console.php scaffold:crud tabela campo:tipo campo2:tipo
```

Exemplo de um CRUD simples:

```bash
php console.php scaffold:crud produtos nome:string preco:decimal
```

O comando gera:

```text
modelos/Produto.php
testes/modelos/ProdutoTest.php
controllers/ProdutosController.php
views/produtos/index.php
views/produtos/formulario.php
views/produtos/ver.php
```

Tambem adiciona a tabela aos arquivos:

```text
banco/esquema.sqlite.sql
banco/esquema.mysql.sql
```

Depois executa o esquema do banco configurado. Se a tabela ja existir, as
colunas novas que ainda nao existirem sao adicionadas sem apagar os dados.

### 3.1 Tipos de campos

```bash
php console.php scaffold:crud produtos \
    nome:string \
    descricao:text \
    quantidade:integer \
    preco:decimal \
    disponivel:boolean \
    validade:date \
    iniciado_em:datetime \
    horario:time
```

Tipos aceitos:

| Tipo | SQLite | MySQL | Campo HTML |
|---|---|---|---|
| `string` | `TEXT` | `VARCHAR(255)` | texto |
| `text` | `TEXT` | `VARCHAR(255)` | texto |
| `integer` | `INTEGER` | `INT` | numero |
| `decimal` | `REAL` | `DECIMAL(12,2)` | numero |
| `boolean` | `INTEGER` | `TINYINT(1)` | checkbox |
| `date` | `TEXT` | `DATE` | data |
| `datetime` | `TEXT` | `DATETIME` | data e hora |
| `time` | `TEXT` | `TIME` | hora |

Regras:

- o nome da tabela deve conter letras minusculas, numeros ou `_`;
- o nome deve comecar com uma letra;
- cada campo usa o formato `nome:tipo`;
- `id` e `criado_em` sao reservados e nao podem ser informados;
- o comando nao sobrescreve arquivos existentes;
- os campos gerados sao incluidos no model, formulario, tabela e teste.

Se o nome da tabela terminar em `s`, o framework usa a forma singular para a
classe. Por exemplo, `produtos` gera `Produto`; `pedidos` gera `Pedido`.

### 3.2 Relacao 1:N

Crie primeiro a tabela do lado 1 e depois a tabela do lado N:

```bash
php console.php scaffold:crud turmas nome:string
php console.php scaffold:crud matriculas nome:string turma_id:belongs_to=turmas
```

O formato `campo_id:belongs_to=tabela_pai` faz o scaffold:

- criar `turma_id` como chave estrangeira inteira;
- criar no model `Matricula` o metodo `turmas()`, que carrega todos os registros da tabela pai;
- enviar a lista `turmas` pelo controller nas telas de cadastro e edicao;
- gerar um `<select>` Bootstrap 5 com todas as turmas;
- criar a restricao `FOREIGN KEY` nos esquemas SQLite e MySQL;
- incluir o teste da relacao no teste gerado.

O select usa o campo `nome` como texto da opcao. Se ele nao existir, usa
`descricao` e, por ultimo, `#id`. Para mais de uma relacao, repita o formato
com o nome da tabela correspondente.

### 3.3 Rotas do CRUD

Para o exemplo `matriculas`, as rotas geradas sao:

```text
/matriculas                 lista registros
/matriculas/criar           mostra o formulario de cadastro
/matriculas/salvar          grava um registro via POST
/matriculas/ver/1           mostra o registro 1
/matriculas/editar/1        mostra o formulario de edicao
/matriculas/atualizar/1     atualiza o registro 1 via POST
/matriculas/excluir/1       exclui o registro 1
```

As telas geradas ja usam Bootstrap 5, com cards, grids, rows, colunas,
formularios e tabelas responsivas.

## 4. Gerar autenticacao

```bash
php console.php auth:install
```

O comando gera:

```text
modelos/Usuario.php
controllers/AuthController.php
views/auth/login.php
views/auth/registrar.php
```

Tambem adiciona a tabela `usuarios` ao esquema SQLite e MySQL. A tabela possui
`nome`, `email` e `senha`. A senha e armazenada com `password_hash()`.

Rotas criadas:

```text
/auth/login             mostra e processa o login
/auth/registrar         cria uma conta
/auth/sair              encerra a sessao
```

Depois de executar o comando:

1. acesse `/auth/registrar`;
2. cadastre o nome, e-mail e senha;
3. acesse `/auth/login`;
4. proteja os controllers necessarios com `$this->exigirAutenticacao()`.

O comando nao deve ser executado novamente se os arquivos de autenticacao ja
existirem, pois o gerador nao sobrescreve arquivos.

## 5. Executar todos os testes

```bash
php testes/executar.php
```

Executa todos os arquivos terminados em `Test.php` dentro de `testes/`.

O teste gerado pelo scaffold verifica o CRUD basico do model: criar, buscar,
contar, atualizar e excluir.

## 6. Executar um teste especifico

```bash
php testes/executar.php ProdutoTest
```

O filtro tambem aceita parte do nome da classe ou do metodo:

```bash
php testes/executar.php ViewTest
php testes/executar.php testeMontaUrlInterna
php testes/executar.php ProdutoTest::testeExecutaCrudCompleto
```

## 7. Iniciar o servidor PHP

```bash
php -S localhost:8000 roteador.php
```

Depois abra:

```text
http://localhost:8000
```

O arquivo `roteador.php` permite que as rotas do framework funcionem no
servidor embutido do PHP.

## 8. Validar a sintaxe de um arquivo PHP

```bash
php -l console.php
php -l controllers/ProdutosController.php
```

Para validar todos os arquivos PHP:

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

## 9. Verificar o diff do Git

Este comando nao pertence ao framework, mas ajuda antes de criar um commit:

```bash
git diff --check
git status --short
git diff --stat
```

## 10. Fluxo completo para um projeto novo

```bash
php instalar.php
php console.php scaffold:crud clientes nome:string email:string telefone:string
php console.php auth:install
php testes/executar.php
php -S localhost:8000 roteador.php
```

Depois abra `/auth/registrar`, crie o primeiro usuario e acesse `/clientes`.
