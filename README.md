# Framework MVC Didatico em PHP

Base MVC para criar aplicacoes PHP sem dependencias externas. O projeto comeca
sem tabelas, dados ou entidades predefinidas.

Requisitos: PHP 8.1 ou superior e SQLite ou MySQL/MariaDB.

## Inicio rapido

```bash
php instalar.php
php console.php auth:install
php console.php scaffold:crud produtos nome:string preco:decimal estoque:integer --auth
php console.php scaffold:pesquisa produtos nome preco
php -S localhost:8000 roteador.php
```

Acesse `http://localhost:8000/auth/registrar`, crie a primeira conta e entre em
`http://localhost:8000/produtos`.

> A ordem importa: `auth:install` cria a tela de login, e so depois o
> `--auth` do scaffold consegue proteger as rotas do CRUD.

## Comandos

### Scaffold CRUD

```bash
php console.php scaffold:crud tabela campo:tipo campo2:tipo [opcoes]
```

Tipos aceitos: `string`, `text`, `integer`, `decimal`, `boolean`, `date`,
`datetime` e `time`. O comando gera modelo (com regras de validacao),
controller, views, testes de model e de controller, e o esquema para
SQLite/MySQL. Ele nao sobrescreve arquivos existentes.

Opcoes:

| Opcao | O que faz |
|---|---|
| `--auth` | exige login em todas as rotas do recurso (provider padrao, `/auth`) |
| `--auth=prefixo` | mesma coisa, usando um provider nomeado (`/auth-prefixo`) |
| `--modelo=Nome` | define o nome da classe quando o plural automatico erra |
| `--sem-menu` | nao adiciona o recurso a `configuracoes/menu.php` |
| `-v` | mostra os detalhes tecnicos quando um comando falha |

**Sem `--auth`, todas as rotas do CRUD sao publicas** — inclusive excluir e o
relatorio em PDF. O comando avisa isso ao terminar.

O nome da classe vem do singular da tabela (`produtos` -> `Produto`,
`professores` -> `Professor`, `animais` -> `Animal`). Para plurais que a regra
nao cobre, use `--modelo=`:

```bash
php console.php scaffold:crud funis nome:string --modelo=Funil
```

Regerar um recurso substitui a definicao da tabela no arquivo de esquema em
vez de acrescentar uma segunda, entao uma instalacao limpa sempre reproduz a
estrutura atual. Quando a tabela ja existe, os dados sao preservados e as
colunas ausentes sao adicionadas.

### Relacao 1:N

```bash
php console.php scaffold:crud turmas nome:string
php console.php scaffold:crud matriculas nome:string turma_id:belongs_to=turmas
```

A tabela pai precisa existir antes; o comando recusa a relacao caso contrario.

### Pesquisa na listagem

```bash
php console.php scaffold:pesquisa produtos nome preco disponivel
```

Coloca um formulario de pesquisa logo acima da tabela do index, com um campo
para cada coluna informada. O comando edita o `index()` do controller e a view
`index.php` do recurso, que ja precisam existir.

Cada tipo ganha o campo e o filtro que fazem sentido:

| Tipo da coluna | Campo no formulario | Filtro |
|---|---|---|
| `string`, `text` | caixa de texto | contem o trecho digitado (`LIKE`) |
| `integer`, `decimal` | caixa de numero | valor exato |
| `boolean` | lista Todos / Sim / Nao | valor exato |
| `date`, `time` | seletor de data ou hora | valor exato |
| `datetime` | seletor de data | qualquer horario daquela data |
| `campo_id` (relacao) | lista com os registros do pai | valor exato |

Campo em branco nao filtra nada, entao a listagem completa continua aparecendo
enquanto ninguem pesquisa. Quando mais de um campo e preenchido, eles se somam
(`E`, nao `OU`). A pesquisa tambem funciona direto pela URL:
`/produtos?nome=teclado&disponivel=1`.

Os valores digitados vao para o banco como parametros do PDO, e `%` e `_` sao
neutralizados por `Sql::comoLike()` — quem pesquisar por `%` ve os registros
que tem `%` no texto, e nao a tabela inteira.

O trecho gerado fica entre marcadores `scaffold:pesquisa`. Rodar o comando de
novo troca esse trecho em vez de empilhar um segundo formulario, e o
`--remover` devolve o CRUD ao estado anterior:

```bash
php console.php scaffold:pesquisa produtos --remover
```

### Autenticacao

```bash
php console.php auth:install [Modelo|tabela] [Prefixo]
```

Sem argumentos, cria o model `Usuario` (tabela `usuarios`) e o login unico do
projeto em `/auth`. Para aplicar a autenticacao a um model que ja existe,
informe o nome dele — **o prefixo das rotas sai do proprio modelo**:

```bash
php console.php scaffold:crud clientes nome:string
php console.php auth:install Cliente          # rotas em /auth-cliente
php console.php auth:install Cliente auth     # ou no login unico, em /auth
```

O comando adiciona `email` e `senha` (com indice unico no e-mail), aplica o
trait `Nucleo\Autenticavel` ao model e gera as telas, as rotas
`registrar`, `login` e `sair` do provider e um teste de integracao.

As senhas nunca vao para o banco em texto puro: o trait aplica
`password_hash()` em `criar()`, `atualizar()` e `criarComSenha()`, e o login
confere com `password_verify()`. O CRUD comum do model continua funcionando
sem informar credenciais.

### Varios logins (providers)

```bash
php console.php scaffold:crud professores nome:string
php console.php auth:install Professor
php console.php scaffold:crud aulas titulo:string --auth=professor
```

Cada provider tem controller, telas, rotas (`/auth-professor/...`) e chaves de
sessao proprias. Nos controllers use `$this->exigirAutenticacao('professor')`;
nas views, `autenticado('professor')` e `usuario_id('professor')`.

`exigirAutenticacao()` e uma chamada de metodo, nao uma configuracao global:
vale so na acao onde estiver escrita. Para proteger o controller inteiro,
chame-a no construtor.

### Relatorio em PDF

```bash
php console.php relatorio:pdf produtos
php console.php relatorio:pdf Produto relatorios/produtos.pdf
```

O scaffold tambem gera a rota `/produtos/relatorio`, filtravel pela query
string (`?nome=teclado&estoque=1`).

## Seguranca das telas geradas

- todo formulario gerado inclui `<?= campo_csrf() ?>` e o controller confere o
  token com `$this->exigirFormularioValido()`;
- `excluir` so aceita `POST`: um link ou um `<img>` de outro site nao apagam
  registros;
- o login chama `Sessao::regenerar()` para evitar fixacao de sessao;
- os dados invalidos voltam para o formulario com as mensagens por campo, sem
  perder o que ja tinha sido digitado.

## Testes

```bash
php testes/executar.php              # tudo
php testes/executar.php ProdutoTest  # filtro por classe ou metodo
```

Os testes rodam em um SQLite em memoria e cada classe recria as proprias
tabelas, entao a ordem de execucao nao muda o resultado.

## Documentacao

- [Tutorial dos comandos](documentacao/Tutorial-Comandos.md) — passo a passo
- [Referencia de comandos](documentacao/Referencia-Comandos.md) — sintaxe completa
