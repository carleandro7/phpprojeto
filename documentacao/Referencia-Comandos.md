# Referencia de comandos

Este documento lista os comandos disponiveis no framework. Execute todos a
partir da pasta raiz do projeto.

Para o passo a passo comentado, veja o
[Tutorial dos comandos](Tutorial-Comandos.md).

## 1. Ver a ajuda do console

```bash
php console.php
```

Mostra a sintaxe de todos os comandos, os tipos de campo aceitos e as opcoes.

Quando um comando falha, a mensagem explica o problema e sugere a correcao.
Acrescente `-v` para ver arquivo, linha e pilha de chamadas:

```bash
php console.php scaffold:crud produtos nome:strng -v
```

O comando devolve `0` em caso de sucesso e `1` em caso de erro, entao ele pode
ser usado em scripts.

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
php console.php scaffold:crud <tabela> <campo:tipo> ... [opcoes]
```

Exemplo:

```bash
php console.php scaffold:crud produtos nome:string preco:decimal --auth
```

O comando gera:

```text
modelos/Produto.php
controllers/ProdutosController.php
views/produtos/index.php
views/produtos/formulario.php
views/produtos/ver.php
testes/modelos/ProdutoTest.php
testes/controllers/ProdutosControllerTest.php
```

E atualiza:

```text
banco/esquema.sqlite.sql
banco/esquema.mysql.sql
configuracoes/menu.php
```

Depois executa o esquema do banco configurado. Se a tabela ja existir, as
colunas novas sao adicionadas sem apagar os dados.

Nada e gravado antes de toda a validacao passar: se algum passo falhar,
nenhum arquivo fica pela metade e o esquema volta ao estado anterior.

### 3.1 Opcoes

| Opcao | Efeito |
|---|---|
| `--auth` | exige login em todas as acoes do controller, usando o provider padrao (`/auth`) |
| `--auth=prefixo` | idem, usando o provider nomeado (`/auth-prefixo`) |
| `--modelo=Nome` | define a classe do model em vez de derivar do plural |
| `--sem-menu` | nao acrescenta o recurso a `configuracoes/menu.php` |
| `-v` | mostra os detalhes tecnicos quando o comando falha |

`--auth` exige que a tela de login ja exista. Rode `auth:install` antes; caso
contrario o comando para e mostra qual comando falta.

**Sem `--auth`, todas as rotas ficam publicas**, inclusive `excluir` e
`relatorio`. O comando avisa isso no fim da execucao. Para proteger apenas
algumas acoes, edite o controller gerado e chame `$this->exigirAutenticacao()`
somente nos metodos desejados.

### 3.2 Tipos de campos

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

| Tipo | SQLite | MySQL | Campo HTML |
|---|---|---|---|
| `string` | `TEXT` | `VARCHAR(255)` | `input type="text"` |
| `text` | `TEXT` | `TEXT` | `textarea` |
| `integer` | `INTEGER` | `INT` | `input type="number"` |
| `decimal` | `REAL` | `DECIMAL(12,2)` | `input type="number" step="0.01"` |
| `boolean` | `INTEGER` | `TINYINT(1)` | checkbox (grava `1` ou `0`) |
| `date` | `TEXT` | `DATE` | `input type="date"` |
| `datetime` | `TEXT` | `DATETIME` | `input type="datetime-local"` |
| `time` | `TEXT` | `TIME` | `input type="time"` |

Campos `boolean` sempre gravam `1` ou `0`: o formulario acompanha um
`<input type="hidden">` porque o navegador nao envia nada quando a caixa esta
desmarcada. Na listagem e na tela de detalhes o valor aparece como
`Sim` / `Nao`, pelo helper `sim_nao()`.

Regras:

- o nome da tabela deve conter letras minusculas, numeros ou `_`, comecando
  por uma letra;
- cada campo usa o formato `nome:tipo`; um campo sem `:` e recusado;
- `id` e `criado_em` sao reservados;
- campos repetidos sao recusados;
- o comando nao sobrescreve arquivos existentes.

### 3.3 Nome da classe (singular)

A classe do model vem do singular da tabela:

| Tabela | Classe | Tabela | Classe |
|---|---|---|---|
| `produtos` | `Produto` | `professores` | `Professor` |
| `clientes` | `Cliente` | `animais` | `Animal` |
| `cidades` | `Cidade` | `papeis` | `Papel` |
| `opcoes` | `Opcao` | `viagens` | `Viagem` |
| `itens` | `Item` | `jardins` | `Jardim` |
| `luzes` | `Luz` | `pais` | `Pais` |

Nenhuma regra automatica acerta todos os plurais do portugues. Quando errar,
informe a classe:

```bash
php console.php scaffold:crud funis nome:string --modelo=Funil
```

### 3.4 Relacao 1:N

Crie primeiro a tabela do lado 1:

```bash
php console.php scaffold:crud turmas nome:string
php console.php scaffold:crud matriculas nome:string turma_id:belongs_to=turmas
```

O formato `campo_id:belongs_to=tabela_pai` faz o scaffold:

- criar `turma_id` como chave estrangeira inteira;
- criar no model `Matricula` o metodo `turmas()`, que carrega os registros pai;
- enviar a lista `turmas` pelo controller nas telas de cadastro e edicao;
- gerar um `<select>` Bootstrap 5 com as opcoes;
- criar a restricao `FOREIGN KEY` nos esquemas SQLite e MySQL;
- marcar o campo como obrigatorio na validacao do model;
- incluir a verificacao da relacao no teste gerado.

Se a tabela pai nao existir, o comando para e mostra como cria-la — antes ele
gerava um CRUD que quebrava no primeiro cadastro.

O select usa o campo `nome` como texto da opcao. Se ele nao existir, usa
`descricao` e, por ultimo, `#id`.

### 3.5 Rotas do CRUD

Para o exemplo `matriculas`:

| Rota | Metodo | Funcao |
|---|---|---|
| `/matriculas` | GET | lista registros |
| `/matriculas/criar` | GET | formulario de cadastro |
| `/matriculas/salvar` | POST | grava um registro |
| `/matriculas/ver/1` | GET | mostra o registro 1 |
| `/matriculas/editar/1` | GET | formulario de edicao |
| `/matriculas/atualizar/1` | POST | atualiza o registro 1 |
| `/matriculas/excluir/1` | POST | exclui o registro 1 |
| `/matriculas/relatorio` | GET | PDF filtravel |

`salvar`, `atualizar` e `excluir` **so aceitam POST e exigem o token do
formulario**. Acessar `/matriculas/excluir/1` pelo navegador devolve 404: sem
isso, um `<img src="...">` em outro site apagaria registros da sua aplicacao.

As telas geradas usam Bootstrap 5 e ja trazem os botoes de **Editar** e
**Excluir** na listagem e na tela de detalhes.

### 3.6 Validacao

O model gerado ja implementa `validar()`:

```php
public function validar(array $dados, int|string|null $ignorarId = null): array
{
    return (new Validador($dados))
        ->obrigatorio('nome')
        ->maximo('nome', 255)
        ->numerico('preco')
        ->erros();
}
```

O primeiro campo vira obrigatorio, campos numericos recebem `numerico()`,
campos `string` recebem `maximo(255)`, um campo chamado `email` recebe
`email()` e as chaves estrangeiras viram obrigatorias. Ajuste a vontade.

Quando a validacao falha, o controller chama `voltarComErros()`, que devolve o
visitante ao formulario com as mensagens por campo e o que ele ja tinha
digitado (helpers `erro_de()`, `tem_erro()` e `antigo()`).

## 4. Pesquisa na listagem

```bash
php console.php scaffold:pesquisa <tabela|Modelo> <campo> [campo2 ...] [--remover]
```

Exemplo:

```bash
php console.php scaffold:pesquisa produtos nome preco disponivel validade
```

O comando coloca um formulario de pesquisa logo acima da tabela do index, com
um campo para cada coluna informada, e faz o `index()` do controller filtrar
por eles.

Ele **altera** dois arquivos que ja existem:

```text
controllers/ProdutosController.php   (o metodo index)
views/produtos/index.php             (o formulario acima da tabela)
```

Se um dos dois nao existir, o comando para e indica o `scaffold:crud`. Os
dois sao gravados juntos: se a gravacao falhar no meio, o conteudo anterior de
ambos volta.

### 4.1 Campo e filtro de cada tipo

Os tipos vem de `banco/esquema.mysql.sql`; ninguem precisa informa-los de novo.

| Tipo da coluna | Campo no formulario | Filtro no SQL |
|---|---|---|
| `string`, `text` | `input type="text"` | `LIKE %termo%` |
| `integer` | `input type="number"` | `= ?` |
| `decimal` | `input type="number" step="0.01"` | `= ?` |
| `boolean` | `select` Todos / Sim / Nao | `= ?` |
| `date` | `input type="date"` | `= ?` |
| `datetime` | `input type="date"` | `LIKE data%` (qualquer horario) |
| `time` | `input type="time"` | `= ?` |
| chave estrangeira | `select` com os registros do pai | `= ?` |

`id` tambem pode ser pesquisado, por valor exato.

O `boolean` vira uma lista de tres estados de proposito: uma caixa de marcar
desmarcada nao diz se o visitante quer os registros com `Nao` ou quer todos.

Quando so existe o esquema do SQLite, os tipos chegam menos detalhados (tudo e
`TEXT`, `INTEGER` ou `REAL`), entao um `boolean` aparece como campo de numero.
Regerar o CRUD recria o esquema MySQL e resolve.

### 4.2 Como a pesquisa se comporta

- campo em branco nao entra no filtro, entao a listagem completa continua
  aparecendo enquanto ninguem pesquisa;
- varios campos preenchidos se somam com `AND`;
- os valores vao para o banco como parametros do PDO (`?`), nunca dentro do
  texto do SQL;
- `%` e `_` sao neutralizados por `Sql::comoLike()`: quem pesquisar por `%` ve
  os registros que tem `%` no texto, e nao a tabela inteira;
- a lista continua na ordem declarada em `$ordemPadrao` no model;
- a pesquisa funciona igual pela URL: `/produtos?nome=teclado&disponivel=1`;
- sem resultado, a tabela mostra "Nenhum registro encontrado para a pesquisa."
  em vez de "Nenhum registro cadastrado.".

### 4.3 Rodar de novo e desfazer

O trecho gerado fica entre marcadores nos dois arquivos:

```php
// ----- scaffold:pesquisa inicio -----
// ----- scaffold:pesquisa fim -----
```

```html
<!-- scaffold:pesquisa inicio -->
<!-- scaffold:pesquisa fim -->
```

Rodar o comando de novo troca o trecho anterior — inclusive tirando os campos
que sairam da lista — em vez de empilhar um segundo formulario:

```bash
php console.php scaffold:pesquisa produtos nome preco
php console.php scaffold:pesquisa produtos nome            # fica so o nome
php console.php scaffold:pesquisa produtos --remover       # volta ao CRUD sem pesquisa
```

O que estiver fora dos marcadores nao e tocado, entao ajustes seus no `index()`
e no resto da view continuam de pe.

## 5. Gerar relatorio PDF

Rota web gerada pelo scaffold:

```text
/produtos/relatorio
/produtos/relatorio?nome=teclado&estoque=1
```

Campos de texto filtram por trecho (`LIKE`); `id`, numeros, booleanos e chaves
estrangeiras filtram por valor exato.

Arquivo offline pelo terminal:

```bash
php console.php relatorio:pdf produtos
php console.php relatorio:pdf Produto relatorios/produtos.pdf
```

O comando aceita o nome da tabela ou do model. Sem o segundo argumento, salva
em `relatorios/{tabela}.pdf`; caminhos relativos partem da raiz do projeto e
nao podem sair dela. Para dados protegidos na web, use a rota do controller em
vez de apontar para o arquivo.

## 6. Gerar autenticacao

```bash
php console.php auth:install [Modelo|tabela] [Prefixo]
```

Para aplicar autenticacao a um model ja criado:

```bash
php console.php scaffold:crud clientes nome:string
php console.php auth:install Cliente
```

Tambem e aceito o nome da tabela (`auth:install clientes`). Sem argumentos, o
comando cria o model `Usuario` com a tabela `usuarios`.

O comando:

- adiciona as colunas `email` e `senha` quando elas nao existem;
- cria um indice UNIQUE em `email`, para nao existirem duas contas com o mesmo
  e-mail;
- adiciona `use Nucleo\Autenticavel;` ao model, preservando a formatacao do
  arquivo, e inclui os campos em `$preenchiveis`;
- gera o controller, as telas de login/cadastro e um teste de integracao.

Gera ou atualiza:

```text
modelos/Cliente.php                          (atualizado)
controllers/AuthController.php
views/auth/login.php
views/auth/registrar.php
testes/controllers/AuthControllerTest.php
banco/esquema.sqlite.sql, banco/esquema.mysql.sql
```

Rotas criadas:

```text
/auth/registrar    cria uma conta
/auth/login        mostra e processa o login
/auth/sair         encerra a sessao
```

### 6.1 Como a senha e tratada

O trait `Nucleo\Autenticavel` intercepta a escrita no model:

| Chamada | Resultado |
|---|---|
| `criar(['email' => ..., 'senha' => 'segredo123'])` | grava o hash |
| `atualizar($id, ['senha' => 'novasenha'])` | grava o hash |
| `atualizar($id, ['senha' => ''])` | ignora o campo e mantem a senha atual |
| `criar(['senha' => $hashPronto])` | mantem o hash, sem aplicar de novo |
| `criarComSenha($dados, $senha)` | exige e-mail valido e senha com 6+ caracteres |
| `criar(['nome' => 'Joao'])` | funciona: o CRUD comum nao precisa de credenciais |
| `trocarSenha($id, 'novasenha')` | valida e grava o hash |

`autenticar($email, $senha)` confere com `password_verify()` e devolve o
registro ou `null`.

Isso significa que o CRUD gerado antes do `auth:install` continua funcionando
depois dele: `salvar()` grava `nome` e `telefone` sem exigir e-mail e senha.
As colunas `email` e `senha` sao adicionadas como `NULL` justamente por isso —
quem exige credenciais e a tela de cadastro, nao a tabela.

Para o model `Usuario` criado do zero, as colunas nascem `NOT NULL` com
`UNIQUE` no e-mail, porque a unica porta de entrada e a tela de cadastro.

### 6.2 Varios providers

Cada provider recebe controller, telas, rotas e chaves de sessao proprios:

```bash
php console.php scaffold:crud professores nome:string
php console.php auth:install Professor professor
php console.php scaffold:crud aulas titulo:string --auth=professor
```

As telas ficam em `views/auth/professor/` e as rotas sao
`/auth-professor/login`, `/auth-professor/registrar` e `/auth-professor/sair`.

Para proteger uma rota pelo provider nomeado:

```php
$this->exigirAutenticacao('professor');
```

`autenticado('professor')` e `usuario_id('professor')` consultam a mesma
sessao. Sem argumento, `exigirAutenticacao()` usa o provider padrao (`/auth`);
se ele nao existir e houver apenas um provider instalado, usa esse. Se nenhuma
tela de login existir, o erro diz qual comando rodar em vez de redirecionar
para uma pagina inexistente.

## 7. Protecao dos formularios (CSRF)

Todo formulario gerado inclui:

```php
<?= campo_csrf() ?>
```

E o controller confere o token:

```php
$this->exigirFormularioValido();   // exige POST + token valido
```

Nos seus proprios formularios, faca o mesmo. Sem o campo, o envio e recusado e
o visitante volta para a tela anterior com uma mensagem.

Metodos disponiveis em `Nucleo\Controller`:

| Metodo | Funcao |
|---|---|
| `exigirPost()` | recusa a requisicao se nao for POST |
| `exigirTokenValido()` | confere o token anti-CSRF |
| `exigirFormularioValido()` | os dois acima |
| `exigirAutenticacao(?string $provider)` | redireciona quem nao esta logado |
| `voltarComErros(array $erros, string $rota)` | volta ao formulario com erros e dados |

## 8. Menu de navegacao

Os itens da barra lateral ficam em `configuracoes/menu.php`:

```php
return [
    ['rota' => '', 'texto' => 'Inicio'],
    ['rota' => 'produtos', 'texto' => 'Produtos'],
    // scaffold:crud
];
```

O `scaffold:crud` acrescenta uma linha antes do comentario `// scaffold:crud`.
Use `--sem-menu` para pular esse passo, ou edite o arquivo a vontade (texto,
ordem, remover itens). Cada item aceita `'auth' => 'sim'` para aparecer apenas
para quem esta logado, ou `'auth' => 'nao'` para o contrario.

Os links de **Entrar** e **Sair** sao montados a partir dos providers
instalados, entao um provider com prefixo tambem aparece no menu.

## 9. Executar todos os testes

```bash
php testes/executar.php
```

Executa todos os arquivos terminados em `Test.php` dentro de `testes/`.

Os testes gerados pelo scaffold verificam:

- o CRUD completo do model e as regras de validacao;
- as rotas de listagem, cadastro, visualizacao, edicao, exclusao e relatorio;
- a recusa de dados invalidos;
- a recusa de um POST sem token (CSRF);
- a recusa de exclusao por GET;
- o redirecionamento para o login, quando o recurso foi gerado com `--auth`.

O teste gerado por `auth:install` verifica cadastro, login, saida, senha
errada, e-mail repetido, senha curta e POST sem token — alem de confirmar que
a senha ficou com hash no banco.

## 10. Executar um teste especifico

```bash
php testes/executar.php ProdutoTest
php testes/executar.php ViewTest
php testes/executar.php ProdutoTest::testeExecutaCrudCompleto
```

## 11. Iniciar o servidor PHP

```bash
php -S localhost:8000 roteador.php
```

O arquivo `roteador.php` permite que as rotas do framework funcionem no
servidor embutido do PHP.

## 12. Validar a sintaxe de um arquivo PHP

```bash
php -l console.php
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

## 13. Fluxo completo para um projeto novo

```bash
php instalar.php
php console.php auth:install
php console.php scaffold:crud clientes nome:string email:string telefone:string --auth
php console.php scaffold:pesquisa clientes nome email
php testes/executar.php
php -S localhost:8000 roteador.php
```

Abra `/auth/registrar`, crie o primeiro usuario e acesse `/clientes`.
