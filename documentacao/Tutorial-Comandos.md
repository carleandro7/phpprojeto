# Tutorial dos comandos do framework

Este tutorial mostra como iniciar uma aplicacao do zero usando o console do
framework. O projeto nao cria tabelas nem dados de exemplo automaticamente.

Para consultar somente a sintaxe, veja a
[Referencia de comandos](Referencia-Comandos.md).

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

## 2. Criar a tela de login primeiro

Comece pela autenticacao. Assim voce ja pode proteger os CRUDs conforme os
gera, em vez de deixar tudo publico e lembrar disso depois:

```bash
php console.php auth:install
```

O comando cria o model `Usuario` (tabela `usuarios`), o `AuthController`, as
telas de login e cadastro e um teste de integracao. As rotas ficam em
`/auth/registrar`, `/auth/login` e `/auth/sair`.

Se voce preferir usar um model que ja existe, veja a
[secao 4](#4-usar-um-model-existente-como-login).

## 3. Gerar um CRUD

```bash
php console.php scaffold:crud produtos nome:string preco:decimal estoque:integer --auth
```

O comando:

- cria o modelo `modelos/Produto.php`, ja com regras de validacao;
- cria `controllers/ProdutosController.php`;
- cria as views em `views/produtos/`;
- cria `testes/modelos/ProdutoTest.php`;
- cria `testes/controllers/ProdutosControllerTest.php`;
- adiciona a tabela aos esquemas SQLite e MySQL;
- adiciona o recurso a `configuracoes/menu.php`;
- executa o esquema do driver configurado.

No fim ele diz se as rotas ficaram protegidas ou publicas.

### O que `--auth` muda

Sem `--auth`, **todas** as rotas ficam publicas: qualquer visitante cadastra,
edita, exclui e baixa o relatorio. O comando avisa isso ao terminar.

Com `--auth`, cada acao do controller comeca com:

```php
$this->exigirAutenticacao();
```

Para liberar apenas a listagem, apague essa linha do metodo `index()` do
controller gerado. O contrario tambem vale: gere sem `--auth` e acrescente a
chamada so onde precisar.

`--auth` exige que a tela de login ja exista. Se voce ainda nao rodou
`auth:install`, o comando para e diz o que fazer:

```text
[ERRO] A tela de login /auth ainda nao existe.
Instale-a antes:
  php console.php auth:install
```

### Tipos disponiveis

| Tipo do comando | SQLite | MySQL | Campo HTML |
|---|---|---|---|
| `string` | `TEXT` | `VARCHAR(255)` | texto |
| `text` | `TEXT` | `TEXT` | area de texto |
| `integer` | `INTEGER` | `INT` | numero |
| `decimal` | `REAL` | `DECIMAL(12,2)` | numero com centavos |
| `boolean` | `INTEGER` | `TINYINT(1)` | checkbox |
| `date` | `TEXT` | `DATE` | data |
| `datetime` | `TEXT` | `DATETIME` | data e hora |
| `time` | `TEXT` | `TIME` | hora |

O campo `id` e criado automaticamente e nao deve ser informado; `criado_em`
tambem e reservado.

Um campo `boolean` sempre grava `1` ou `0`. O formulario traz um
`<input type="hidden">` junto com a caixa porque o navegador nao envia nada
quando ela esta desmarcada — sem isso o valor chegaria como `"on"` ou como
`NULL`. Na listagem e nos detalhes o valor aparece como `Sim` / `Nao`.

### Nome da classe

O framework calcula o singular da tabela: `produtos` vira `Produto`,
`professores` vira `Professor`, `animais` vira `Animal`, `opcoes` vira
`Opcao`. Quando a regra errar, informe o nome:

```bash
php console.php scaffold:crud funis nome:string --modelo=Funil
```

### Regerar um recurso

O comando nunca sobrescreve arquivos. Para regerar, apague os arquivos do
recurso e rode de novo:

```bash
rm modelos/Produto.php controllers/ProdutosController.php
rm -r views/produtos
rm testes/modelos/ProdutoTest.php testes/controllers/ProdutosControllerTest.php
php console.php scaffold:crud produtos nome:string preco:decimal cor:string --auth
```

A definicao da tabela no arquivo de esquema e **substituida**, nao duplicada.
Isso importa: com duas definicoes da mesma tabela, um `CREATE TABLE IF NOT
EXISTS` criaria a versao antiga em uma instalacao limpa e o sistema quebraria
na maquina do proximo aluno. Os dados existentes sao preservados e as colunas
novas sao adicionadas.

### Rotas geradas

| Rota | Metodo | Funcao |
|---|---|---|
| `/produtos` | GET | lista registros |
| `/produtos/criar` | GET | abre o formulario |
| `/produtos/salvar` | POST | grava um registro |
| `/produtos/ver/1` | GET | mostra um registro |
| `/produtos/editar/1` | GET | abre a edicao |
| `/produtos/atualizar/1` | POST | atualiza |
| `/produtos/excluir/1` | POST | exclui |
| `/produtos/relatorio` | GET | PDF filtravel |

A listagem e a tela de detalhes ja trazem os botoes **Editar** e **Excluir**.

`excluir` so aceita POST, e o botao envia um formulario com o token da sessao.
Um link comum (ou um `<img src="/produtos/excluir/1">` colocado em outro site)
devolve 404 e nao apaga nada.

### Validacao

O model gerado ja traz regras:

```php
public function validar(array $dados, int|string|null $ignorarId = null): array
{
    return (new Validador($dados))
        ->obrigatorio('nome')
        ->maximo('nome', 255)
        ->numerico('preco')
        ->numerico('estoque')
        ->erros();
}
```

O controller chama `validar()` antes de gravar. Se houver erros:

```php
$this->voltarComErros($erros, 'produtos/criar');
```

O visitante volta ao formulario, ve a mensagem em cada campo e nao perde o que
tinha digitado. Nas views isso e feito pelos helpers `tem_erro()`,
`erro_de()` e `antigo()`, ja incluidos nos formularios gerados.

Amplie `validar()` com as regras da sua aplicacao: `minimo()`, `email()`,
`entre()`, `dentroDe()` e `personalizada()` estao disponiveis em
`Nucleo\Validador`.

### Personalizar o CRUD

O modelo gerado herda o CRUD de `Nucleo\Model`:

```php
$produto = new \Modelos\Produto();
$produto->todos();
$produto->buscar($id);
$produto->criar(['nome' => 'Teclado', 'preco' => 99.90]);
$produto->atualizar($id, ['preco' => 89.90]);
$produto->excluir($id);
```

Use o modelo para validacoes e consultas especificas, o controller para
receber dados e escolher a view, e as views somente para HTML.

### Relacao 1:N com select

Crie primeiro a tabela pai:

```bash
php console.php scaffold:crud turmas nome:string --auth
php console.php scaffold:crud matriculas nome:string turma_id:belongs_to=turmas --auth
```

O model gerado carrega as turmas pelo metodo `turmas()`, o controller envia a
lista para as telas de cadastro/edicao e o formulario gera um select Bootstrap
5. A tabela filha recebe a chave estrangeira nos dois esquemas, e o campo vira
obrigatorio na validacao.

Se a tabela pai nao existir, o comando recusa:

```text
[ERRO] A tabela pai "turmas" nao existe (campo turma_id).
Gere-a primeiro:
  php console.php scaffold:crud turmas nome:string
```

### Pesquisar na listagem

A listagem nasce mostrando tudo. Quando a tabela cresce, acrescente um
formulario de pesquisa escolhendo por quais campos as pessoas vao procurar:

```bash
php console.php scaffold:pesquisa produtos nome preco disponivel
```

```text
Pesquisa criada em /produtos
  ~ controllers/ProdutosController.php
  ~ views/produtos/index.php

Campos pesquisaveis:
  nome               texto         contem o trecho digitado (LIKE)
  preco              numero        valor exato
  disponivel         Sim/Nao       valor exato
```

Abra `/produtos`: o formulario aparece acima da tabela, um campo para cada
coluna que voce escolheu. Nomes viram caixa de texto e procuram por trecho
(digitar `tec` acha "Teclado"); numeros e datas procuram pelo valor exato;
`boolean` vira uma lista Todos / Sim / Nao; e uma chave estrangeira como
`turma_id` vira um select com as turmas cadastradas.

Campo em branco nao filtra nada, entao a lista inteira continua aparecendo
enquanto ninguem pesquisa. Preencher dois campos soma as duas condicoes. E,
como o formulario usa `method="get"`, a pesquisa fica na barra de enderecos e
pode ser guardada nos favoritos ou enviada para outra pessoa:

```text
/produtos?nome=teclado&disponivel=1
```

Nada disso abre brecha de SQL Injection: o que a pessoa digita vai para o banco
como parametro (`?`), e os curingas `%` e `_` sao neutralizados. Pesquisar por
`%` mostra os produtos que tem `%` no nome, e nao a tabela inteira.

O comando escreve entre marcadores, entao da para mudar de ideia:

```bash
php console.php scaffold:pesquisa produtos nome      # so o nome, os outros saem
php console.php scaffold:pesquisa produtos --remover # volta a listagem sem pesquisa
```

O que estiver fora dos marcadores continua como voce deixou.

### Relatorio PDF

O scaffold cria a rota do relatorio e o link na listagem:

```text
/produtos/relatorio
/produtos/relatorio?nome=teclado&estoque=1
```

Campos de texto filtram por trecho; `id`, numeros, booleanos e chaves
estrangeiras filtram por valor exato. Sem filtros, todos os registros entram.

A rota devolve o PDF em memoria e nao cria arquivo publico, entao a
verificacao de autenticacao continua valendo. Para uma exportacao offline:

```bash
php console.php relatorio:pdf produtos
php console.php relatorio:pdf Produto relatorios/produtos.pdf
```

O caminho relativo parte da raiz do projeto e nao pode sair dela.

## 4. Usar um model existente como login

```bash
php console.php scaffold:crud clientes nome:string telefone:string
php console.php auth:install Cliente
```

O comando adiciona `email` e `senha` na tabela, cria um indice UNIQUE em
`email`, atualiza o model com o trait `Nucleo\Autenticavel` e gera o
controller, as telas e o teste.

Tambem e possivel passar o nome da tabela (`auth:install clientes`).

### O CRUD de clientes continua funcionando

Depois do `auth:install`, `POST /clientes/salvar` continua gravando apenas
`nome` e `telefone`. As colunas `email` e `senha` entram como `NULL`
justamente por isso: um cliente cadastrado pela secretaria ainda nao tem
conta. Quem exige credenciais e a tela de cadastro (`/auth/registrar`), atraves
de `criarComSenha()`.

### A senha nunca fica em texto puro

O trait aplica `password_hash()` em qualquer caminho de escrita:

```php
$cliente->criar(['email' => 'ana@x.com', 'senha' => 'segredo123']);
// grava o hash, nao "segredo123"

$cliente->atualizar($id, ['senha' => 'novasenha']);
// grava o novo hash

$cliente->atualizar($id, ['nome' => 'Ana', 'senha' => '']);
// senha em branco = nao mexer na senha atual

$cliente->criarComSenha(['email' => 'ana@x.com'], 'segredo123');
// exige e-mail valido e senha com pelo menos 6 caracteres
```

O login usa `autenticar($email, $senha)`, que confere com
`password_verify()`.

### Trocar a senha

```php
$cliente->trocarSenha($id, 'novasenha');
```

## 5. Varios logins (providers)

Um projeto pode ter mais de uma tabela autenticavel. O primeiro comando usa
`/auth`; para uma segunda tela, informe um prefixo:

```bash
php console.php scaffold:crud professores nome:string
php console.php auth:install Professor professor
php console.php scaffold:crud aulas titulo:string --auth=professor
```

Isso gera:

- `controllers/AuthProfessorController.php`;
- `views/auth/professor/login.php` e `registrar.php`;
- rotas `/auth-professor/login`, `/auth-professor/registrar`,
  `/auth-professor/sair`;
- chaves de sessao separadas das de `/auth`.

Para proteger um controller pelo provider:

```php
$this->exigirAutenticacao('professor');
```

Nas views, `autenticado('professor')` e `usuario_id('professor')`.

Sem argumento, `exigirAutenticacao()` usa `/auth`. Se `/auth` nao existir e
houver apenas um provider instalado, ele e usado automaticamente. Se nenhuma
tela de login existir, o erro diz qual comando rodar:

```text
Nenhuma tela de login foi instalada. Rode: php console.php auth:install
```

Os links de Entrar e Sair do menu sao montados a partir dos providers
instalados, entao um provider com prefixo aparece sozinho na barra lateral.

## 6. Proteger seus proprios controllers

```php
<?php

namespace Controllers;

use Nucleo\Controller;

class RelatoriosController extends Controller
{
    public function index(): void
    {
        $this->exigirAutenticacao();

        $this->view('relatorios/index', ['titulo' => 'Relatorios']);
    }

    public function gerar(): void
    {
        $this->exigirAutenticacao();
        $this->exigirFormularioValido();   // POST + token do formulario

        // ...
    }
}
```

Nas views:

```php
<?php if (autenticado()): ?>
    <a href="<?= url(rota_sair()) ?>">Sair</a>
<?php endif ?>
```

`usuario_id()` devolve o id de quem esta logado ou `null`.

## 7. Formularios seguros (CSRF)

Todo formulario que grava precisa do token:

```php
<form method="post" action="<?= url('produtos/salvar') ?>">
    <?= campo_csrf() ?>
    ...
</form>
```

E o controller confere:

```php
$this->exigirFormularioValido();
```

CSRF ("Cross-Site Request Forgery") e quando outro site faz o navegador da
vitima enviar um formulario para o seu sistema aproveitando a sessao ja
aberta. Como o site atacante nao consegue ler o token, ele nao consegue montar
um envio valido. Sem o campo, o envio e recusado e o visitante volta para a
tela anterior com uma mensagem.

Ao entrar, o login chama `Sessao::regenerar()`, que troca o identificador da
sessao. Sem isso, um id capturado antes do login continuaria valendo depois
(ataque de "session fixation").

## 8. Menu de navegacao

Os itens ficam em `configuracoes/menu.php`:

```php
return [
    ['rota' => '', 'texto' => 'Inicio'],
    ['rota' => 'produtos', 'texto' => 'Produtos'],
    // scaffold:crud
];
```

Cada `scaffold:crud` acrescenta uma linha antes do comentario. Voce pode mudar
o texto, reordenar, remover, ou esconder um item de quem nao esta logado:

```php
['rota' => 'relatorios', 'texto' => 'Relatorios', 'auth' => 'sim'],
```

Use `--sem-menu` no scaffold para nao mexer nesse arquivo.

## 9. Quando um comando falha

As mensagens explicam o problema e sugerem a correcao:

```text
[ERRO] Tipo invalido em "nome:strng": strng.
Tipos aceitos: string, text, integer, decimal, boolean, date, datetime, time ou belongs_to=tabela_pai
```

Nenhum arquivo fica pela metade: o console valida tudo, mexe no banco e so
entao grava os arquivos; se algo falhar no meio, o que ja foi criado e
removido e o esquema volta ao estado anterior.

Para ver arquivo, linha e pilha de chamadas, acrescente `-v`:

```bash
php console.php scaffold:crud produtos nome:strng -v
```

## 10. Fluxo recomendado

```bash
php instalar.php
php console.php auth:install
php console.php scaffold:crud clientes nome:string email:string telefone:string --auth
php console.php scaffold:pesquisa clientes nome email
php testes/executar.php
php -S localhost:8000 roteador.php
```

Abra `http://localhost:8000/auth/registrar` para criar o primeiro usuario e
`http://localhost:8000/clientes` para usar o CRUD.

## 11. Testar

```bash
php testes/executar.php
php testes/executar.php ProdutoTest
```

Os testes rodam em um SQLite em memoria. Cada classe recria as proprias
tabelas com `recriarTabelas()`, entao a ordem de execucao nao muda o
resultado — antes, um teste que criava uma tabela auxiliar podia deixar outro
teste sem colunas.

Os testes gerados pelo scaffold cobrem o CRUD, a validacao, a recusa de POST
sem token, a recusa de exclusao por GET, o relatorio e (com `--auth`) o
redirecionamento para o login. O teste de `auth:install` cobre cadastro,
login, saida, senha errada, e-mail repetido e o hash da senha.

Depois de personalizar o modelo ou o controller, amplie esses testes com as
regras especificas da sua aplicacao.

Antes de publicar, revise as validacoes, proteja as rotas necessarias e
configure `app.debug` como `false` em `configuracoes/app.php`.
