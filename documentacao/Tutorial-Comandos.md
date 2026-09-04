# Tutorial: do zero a um sistema completo

Este tutorial constroi, do inicio ao fim, um sistema inteiro usando so o
console do framework: o **Diario de aulas** de uma escola.

Ao terminar voce vai ter uma aplicacao com cadastro de turmas, alunos ligados
a uma turma, pesquisa na listagem, tela de login para o professor, rotas
protegidas, relatorio em PDF e testes automatizados passando.

Nao e preciso saber nada do framework antes: cada passo mostra o comando, o
que ele imprime, o que aparece no navegador e o que foi gerado por baixo.

- Tempo estimado: 45 a 60 minutos.
- Quando quiser so a sintaxe de um comando, use a
  [Referencia de comandos](Referencia-Comandos.md).

## O que vamos construir

| Rota | O que faz | Protegida? |
|---|---|---|
| `/` | pagina inicial | nao |
| `/turmas` | CRUD de turmas | sim (passo 7) |
| `/alunos` | CRUD de alunos, cada um em uma turma, com pesquisa | sim (passo 7) |
| `/aulas` | CRUD de aulas de cada turma | sim, desde o passo 7 |
| `/professores` | cadastro dos professores | sim (passo 7) |
| `/auth-professor/registrar` | cria a conta do professor | publica |
| `/auth-professor/login` | entrada | publica |
| `/auth-professor/sair` | saida | publica |
| `/alunos/relatorio` | PDF filtravel da lista de alunos | sim |

Roteiro:

1. [Preparar o ambiente](#passo-1--preparar-o-ambiente)
2. [Entender o caminho de uma requisicao](#passo-2--o-caminho-de-uma-requisicao)
3. [O primeiro CRUD: turmas](#passo-3--o-primeiro-crud-turmas)
4. [Ligar duas tabelas: alunos de uma turma](#passo-4--ligar-duas-tabelas-alunos-de-uma-turma)
5. [Pesquisa na listagem](#passo-5--pesquisa-na-listagem)
6. [A tela de login](#passo-6--a-tela-de-login)
7. [Proteger as rotas](#passo-7--proteger-as-rotas)
8. [Relatorio em PDF](#passo-8--relatorio-em-pdf)
9. [Um segundo login](#passo-9--um-segundo-login-opcional)
10. [Testes](#passo-10--testes)
11. [Ajustes finais](#passo-11--ajustes-finais)
12. [Fim: o que voce construiu](#fim-o-que-voce-construiu)

Ao final ha dois apendices: [erros comuns](#apendice-a--erros-comuns) e
[helpers das views](#apendice-b--helpers-das-views).

---

## Passo 1 — Preparar o ambiente

Confira a versao do PHP (precisa ser 8.1 ou maior):

```bash
php -v
```

Inicie o **MySQL** no painel de controle do XAMPP. Depois confira os dados de
acesso em `configuracoes/banco.php` — no XAMPP o padrao e usuario `root` com
senha vazia:

```php
return [
    'mysql' => [
        'host'         => 'localhost',
        'porta'        => 3306,
        'banco'        => 'framework_aula',
        'banco_testes' => 'framework_aula_testes',
        'usuario'      => 'root',
        'senha'        => '',
        'charset'      => 'utf8mb4',
    ],
];
```

Sao dois bancos de proposito: `banco` guarda os dados do sistema e
`banco_testes` e recriado do zero toda vez que voce roda os testes. Assim
testar nunca apaga o que voce cadastrou.

Crie os dois:

```bash
php instalar.php
```

```text
Instalando o banco de dados (MySQL)...
Servidor: localhost | Banco: framework_aula
[ok] Banco de dados pronto. Nenhuma tabela padrao foi criada.
[ok] Banco de testes pronto: framework_aula_testes
```

Voce nao precisa abrir o phpMyAdmin: o instalador cria os bancos sozinho. Se
aparecer erro de conexao, ele mostra o checklist (MySQL iniciado? usuario e
senha certos?).

Repare na terceira linha: **o projeto comeca sem nenhuma tabela**. Nada de
tabelas de exemplo para apagar depois; tudo o que existir no seu banco vai
ter sido criado por voce nos proximos passos.

Confira se a base esta sadia:

```bash
php testes/executar.php
```

```text
Testes: 64 | Passaram: 64 | Falharam: 0 | Erros: 0 | Assercoes: 155

TUDO CERTO! O sistema esta funcionando.
```

Suba o servidor:

```bash
php -S localhost:8000 roteador.php
```

Abra <http://localhost:8000>. Deve aparecer a pagina inicial do framework.

> Deixe **este terminal aberto** com o servidor rodando e abra um **segundo
> terminal** na mesma pasta para os comandos do console. Se fechar o
> servidor, o site sai do ar; os comandos do console nao precisam dele.

---

## Passo 2 — O caminho de uma requisicao

Antes de gerar codigo, vale entender o que acontece quando alguem abre uma
pagina. Sao cinco minutos que economizam horas depois.

```text
Navegador  ->  index.php  ->  Nucleo\App
                                  |
                                  v
                  Controller  ->  Model  ->  banco de dados
                                  |
                                  v
                               View  ->  HTML
```

A URL vira controller e metodo **por convencao**, sem arquivo de rotas:

```text
/                     ->  HomeController::index()
/turmas               ->  TurmasController::index()
/turmas/ver/7         ->  TurmasController::ver(7)
/alunos/editar/3      ->  AlunosController::editar(3)
```

Ou seja: `/controlador/metodo/parametro1/parametro2`. Sem metodo na URL, o
framework chama `index()`.

As pastas que voce vai mexer:

| Pasta | O que fica ali |
|---|---|
| `controllers/` | recebe a requisicao, decide o que fazer e escolhe a view |
| `modelos/` | conversa com o banco e valida os dados |
| `views/` | so HTML |
| `configuracoes/` | banco, aplicacao e itens do menu |
| `banco/` | os esquemas SQL gerados pelo console |
| `testes/` | testes automaticos |
| `nucleo/` | o framework em si — voce nao precisa editar |

Regra de ouro: **model** decide o que e valido e como falar com o banco;
**controller** recebe dados e escolhe a tela; **view** so mostra.

---

## Passo 3 — O primeiro CRUD: turmas

Um CRUD e o conjunto "criar, listar, ver, editar e excluir". O console gera
tudo isso de uma vez:

```bash
php console.php scaffold:crud turmas nome:string ano:integer
```

```text
CRUD criado: /turmas
  + modelos/Turma.php
  + controllers/TurmasController.php
  + views/turmas/index.php
  + views/turmas/formulario.php
  + views/turmas/ver.php
  + testes/modelos/TurmaTest.php
  + testes/controllers/TurmasControllerTest.php
  ~ banco/esquema.sql
  ~ configuracoes/menu.php

ATENCAO: todas as rotas de /turmas sao publicas, inclusive excluir e o relatorio.
Para exigir login, gere com --auth ou chame exigirAutenticacao() no controller.

Rode os testes com: php testes/executar.php Turma
```

`+` e arquivo criado, `~` e arquivo alterado. O aviso do fim e proposital:
por enquanto qualquer visitante pode excluir turmas. Resolvemos isso no
passo 7.

**Abra <http://localhost:8000/turmas>.** A tela ja existe, com o item
"Turmas" no menu lateral. Clique em "Novo", cadastre `3A / 2026` e
`3B / 2026`, edite uma, exclua a outra. Tudo funcionando, sem uma linha
escrita a mao.

### O que foi gerado

O nome da classe sai do singular da tabela: `turmas` vira `Turma`. Abra
`modelos/Turma.php`:

```php
class Turma extends Model
{
    protected string $tabela = 'turmas';
    protected array $preenchiveis = ['nome', 'ano'];
    protected string $ordemPadrao = 'id DESC';

    public function validar(array $dados, int|string|null $ignorarId = null): array
    {
        return (new Validador($dados))
            ->obrigatorio('nome')
            ->maximo('nome', 255)
            ->numerico('ano')
            ->erros();
    }
}
```

- `$preenchiveis` e a lista de colunas que o formulario pode gravar. Uma
  coluna fora dessa lista e ignorada, mesmo que alguem a envie no POST.
- `validar()` roda antes de gravar. Devolver um array vazio significa "esta
  tudo certo".
- `$ordemPadrao` define a ordem da listagem.

Do `Nucleo\Model` o model ja herda o acesso ao banco:

```php
$turmas = new \Modelos\Turma();

$turmas->todos();
$turmas->buscar(1);
$turmas->onde('ano = ?', [2026]);
$turmas->primeiroOnde('nome = ?', ['3A']);
$turmas->contar();
$turmas->criar(['nome' => '3C', 'ano' => 2026]);
$turmas->atualizar(1, ['ano' => 2027]);
$turmas->excluir(1);
```

As rotas que nasceram prontas:

| Rota | Metodo HTTP | Funcao |
|---|---|---|
| `/turmas` | GET | lista |
| `/turmas/criar` | GET | formulario de cadastro |
| `/turmas/salvar` | POST | grava |
| `/turmas/ver/1` | GET | detalhes |
| `/turmas/editar/1` | GET | formulario de edicao |
| `/turmas/atualizar/1` | POST | atualiza |
| `/turmas/excluir/1` | POST | exclui |
| `/turmas/relatorio` | GET | PDF filtravel |

`salvar`, `atualizar` e `excluir` **so aceitam POST**, e o botao de excluir
envia um formulario com o token da sessao. Digite
`localhost:8000/turmas/excluir/1` na barra de enderecos: da 404 e nao apaga
nada. E assim de proposito — um `<img src="...">` colocado em outro site nao
pode apagar registros do seu sistema.

### Os tipos de campo

| Tipo | Coluna criada no MySQL | Campo no formulario |
|---|---|---|
| `string` | `VARCHAR(255)` | texto |
| `text` | `TEXT` | area de texto |
| `integer` | `INT` | numero |
| `decimal` | `DECIMAL(12,2)` | numero com centavos |
| `boolean` | `TINYINT(1)` | caixa de marcar (grava 1 ou 0) |
| `date` | `DATE` | data |
| `datetime` | `DATETIME` | data e hora |
| `time` | `TIME` | hora |

`id` e `criado_em` sao criados sozinhos e nao devem ser informados.

### Quando o plural engana

O singular automatico acerta `produtos -> Produto`, `professores ->
Professor`, `animais -> Animal`, `opcoes -> Opcao`. Quando errar, diga o nome
da classe:

```bash
php console.php scaffold:crud funis nome:string --modelo=Funil
```

### Experimente

Rode `php testes/executar.php Turma`. Os testes do CRUD que voce acabou de
gerar rodam sozinhos e passam.

---

## Passo 4 — Ligar duas tabelas: alunos de uma turma

Cada aluno pertence a uma turma. Isso e uma relacao 1:N, e o console monta a
chave estrangeira, o `<select>` e a validacao:

```bash
php console.php scaffold:crud alunos nome:string email:string nascimento:date ativo:boolean turma_id:belongs_to=turmas
```

```text
CRUD criado: /alunos
  + modelos/Aluno.php
  + controllers/AlunosController.php
  + views/alunos/index.php
  + views/alunos/formulario.php
  + views/alunos/ver.php
  + testes/modelos/AlunoTest.php
  + testes/controllers/AlunosControllerTest.php
  ~ banco/esquema.sql
  ~ configuracoes/menu.php
```

**Abra <http://localhost:8000/alunos/criar>.** O campo "Turma" e uma lista
suspensa com as turmas cadastradas no passo 3. Cadastre dois ou tres alunos.

O formato `campo_id:belongs_to=tabela_pai` faz o console:

- criar `turma_id` como inteiro com `FOREIGN KEY` nos dois esquemas;
- criar no model `Aluno` o metodo `turmas()`, que devolve as opcoes;
- mandar essa lista do controller para as telas de cadastro e edicao;
- gerar o `<select>` no formulario;
- marcar o campo como obrigatorio na validacao.

No `modelos/Aluno.php`:

```php
protected array $preenchiveis = ['nome', 'email', 'nascimento', 'ativo', 'turma_id'];

public function validar(array $dados, int|string|null $ignorarId = null): array
{
    return (new Validador($dados))
        ->obrigatorio('nome')
        ->maximo('nome', 255)
        ->email('email')
        ->maximo('email', 255)
        ->obrigatorio('turma_id')
        ->numerico('turma_id')
        ->erros();
}

/** Opcoes da tabela pai, usadas no <select> do formulario. */
public function turmas(): array
{
    return (new \Modelos\Turma())->todos();
}
```

Um campo chamado `email` ganhou a regra `email()` sozinho. As outras regras
disponiveis: `minimo()`, `entre()`, `dentroDe()` e `personalizada()`.

Tente cadastrar um aluno sem nome: a tela volta com a mensagem embaixo do
campo e **sem perder** o que ja tinha sido digitado. Quem faz isso e o
controller:

```php
$erros = $this->modelo->validar($dados);

if ($erros !== []) {
    $this->voltarComErros($erros, 'alunos/criar');
}
```

O texto da opcao no `<select>` sai do campo `nome` do pai; se ele nao
existir, o console usa `descricao` e, por ultimo, `#id`.

A ordem importa: a tabela pai precisa existir antes. Se voce inverter, o
comando recusa e diz o que fazer:

```text
[ERRO] A tabela pai "turmas" nao existe (campo turma_id).
Gere-a primeiro:
  php console.php scaffold:crud turmas nome:string
```

### Experimente

Cadastre um aluno com `ativo` desmarcado e veja a listagem: o valor aparece
como `Nao`. Campos `boolean` sempre gravam `1` ou `0` — o formulario manda um
`<input type="hidden">` junto com a caixa, porque o navegador nao envia nada
quando ela esta desmarcada.

---

## Passo 5 — Pesquisa na listagem

A listagem nasce mostrando tudo. Quando a tabela cresce, acrescente um
formulario de pesquisa escolhendo por quais campos as pessoas vao procurar:

```bash
php console.php scaffold:pesquisa alunos nome ativo turma_id
```

```text
Pesquisa criada em /alunos
  ~ controllers/AlunosController.php
  ~ views/alunos/index.php

Campos pesquisaveis:
  nome               texto         contem o trecho digitado (LIKE)
  ativo              Sim/Nao       valor exato
  turma_id           turmas        lista suspensa com os registros de turmas

O formulario aparece acima da tabela em /alunos e envia os campos
pela query string: /alunos?nome=...

Para desfazer: php console.php scaffold:pesquisa alunos --remover
```

Repare que este comando **altera dois arquivos que ja existiam** em vez de
criar arquivos novos. Ele nao pergunta os tipos: le os campos do esquema e
escolhe o filtro certo para cada um.

**Abra <http://localhost:8000/alunos>.** O formulario aparece acima da
tabela. Digite `an` no nome: acha "Ana" e "Luana", porque texto procura por
trecho. `Turma` virou lista suspensa e `ativo` virou Todos / Sim / Nao.

Como a pesquisa se comporta:

- campo em branco nao filtra, entao a lista inteira continua aparecendo;
- dois campos preenchidos somam as condicoes (`AND`);
- o endereco guarda a pesquisa e pode ser enviado para outra pessoa ou
  salvo nos favoritos: `/alunos?nome=ana&ativo=1`;
- sem resultado, a tabela diz "Nenhum registro encontrado para a pesquisa."

Nada disso abre brecha de SQL Injection: o que a pessoa digita vai para o
banco como parametro (`?`), nunca dentro do texto do SQL, e os curingas `%` e
`_` sao neutralizados — pesquisar por `%` traz quem tem `%` no nome, e nao a
tabela inteira.

O trecho gerado fica entre marcadores (`// ----- scaffold:pesquisa inicio
-----`), entao da para mudar de ideia quantas vezes quiser:

```bash
php console.php scaffold:pesquisa alunos nome        # fica so o nome
php console.php scaffold:pesquisa alunos --remover   # volta a listagem simples
```

O que estiver fora dos marcadores continua como voce deixou.

---

## Passo 6 — A tela de login

Ate aqui qualquer visitante faz tudo. Vamos criar a conta do professor.

O login precisa de um model com as colunas `email` e `senha`. O comando
`auth:install` cria essas colunas sozinho, mas o model precisa existir antes
— a unica excecao e o `Usuario`, que ele cria do zero. Entao gere o cadastro
de professores primeiro:

```bash
php console.php scaffold:crud professores nome:string
```

Agora instale o login **sobre esse model**:

```bash
php console.php auth:install Professor
```

```text
Autenticacao aplicada ao modelo Professor.
  + controllers/AuthProfessorController.php
  + views/auth/professor/login.php
  + views/auth/professor/registrar.php
  + testes/controllers/AuthProfessorControllerTest.php
  ~ modelos/Professor.php
  ~ banco/esquema.sql

Login em /auth-professor: prefixo "professor", vindo do modelo Professor.
Para deixa-lo no login unico /auth: php console.php auth:install Professor auth

Rotas:
  /auth-professor/registrar   cria uma conta
  /auth-professor/login       entra
  /auth-professor/sair        encerra a sessao

Para exigir esse login:
  em um CRUD novo    php console.php scaffold:crud <tabela> <campo:tipo> ... --auth=professor
  em um controller   $this->exigirAutenticacao('professor');

Rode os testes com: php testes/executar.php AuthProfessorController
```

### O prefixo sai do proprio modelo

Cada tela de login instalada e um **provider**: tem controller, telas, rotas
e chaves de sessao proprias. O prefixo do provider vem do nome do model, sem
voce precisar repetir:

| Comando | Model usado | Rotas |
|---|---|---|
| `auth:install` | cria `Usuario` (tabela `usuarios`) | `/auth/login` |
| `auth:install Professor` | `Professor` (ja existente) | `/auth-professor/login` |
| `auth:install professores` | idem — o nome da tabela tambem serve | `/auth-professor/login` |
| `auth:install Professor equipe` | `Professor`, com prefixo escolhido | `/auth-equipe/login` |
| `auth:install Professor auth` | `Professor`, no login unico | `/auth/login` |

Ou seja: **o segundo argumento so serve para escolher outro nome**. Se o seu
projeto tem uma unica tela de login e voce nao se importa com o endereco,
`php console.php auth:install` resolve e as rotas ficam em `/auth`.

### O que o comando fez no banco e no model

- acrescentou as colunas `email` e `senha` na tabela `professores`;
- criou um indice UNIQUE em `email`, para nao existirem duas contas com o
  mesmo endereco;
- acrescentou `use Nucleo\Autenticavel;` ao model e incluiu os dois campos em
  `$preenchiveis`.

```php
class Professor extends Model
{
    use Autenticavel;

    protected string $tabela = 'professores';
    protected array $preenchiveis = ['nome', 'email', 'senha'];
    // ...
}
```

O CRUD de professores continua funcionando igual: `POST /professores/salvar`
grava so o `nome`, e `email`/`senha` ficam `NULL`. Isso e proposital — um
professor cadastrado pela secretaria ainda nao tem conta. Quem exige
credenciais e a tela `/auth-professor/registrar`.

### A senha nunca fica em texto puro

O trait `Nucleo\Autenticavel` intercepta toda escrita no model:

| Chamada | Resultado |
|---|---|
| `criar(['email' => ..., 'senha' => 'segredo123'])` | grava o hash |
| `atualizar($id, ['senha' => 'nova123'])` | grava o novo hash |
| `atualizar($id, ['senha' => ''])` | ignora o campo e mantem a senha atual |
| `criar(['senha' => $hashPronto])` | percebe que ja e hash e nao aplica de novo |
| `criarComSenha($dados, $senha)` | exige e-mail valido e senha com 6+ caracteres |
| `trocarSenha($id, 'nova123')` | valida e grava o hash |
| `autenticar($email, $senha)` | confere com `password_verify()` |

### Crie a primeira conta

**Abra <http://localhost:8000/auth-professor/registrar>**, cadastre um
professor e entre em `/auth-professor/login`. No menu lateral aparece um
grupo "Conta" com **Entrar (professor)** — depois de entrar, ele vira
**Sair (professor)**.

Depois do login o sistema manda voce para a pagina inicial (`/`). Se quiser
outro destino, mude a linha `$this->redirecionar();` no
`controllers/AuthProfessorController.php`, por exemplo para
`$this->redirecionar('aulas');`.

### A tela de login tem template proprio

Repare que as telas de entrar e criar conta **nao mostram o menu lateral**.
Elas sao desenhadas dentro de `views/template/layout-login.php`, uma pagina
isolada com o formulario centralizado. Sao dois motivos: quem ainda nao
entrou nao consegue abrir nenhum daqueles atalhos, e o menu entregaria a
lista de recursos do sistema para quem nao esta autenticado.

Quem escolhe o template e o controller, no **terceiro argumento** de
`view()`:

```php
$this->view('auth/professor/login', ['titulo' => 'Entrar'], 'template/layout-login');
```

Esse argumento vale para qualquer tela sua:

| Chamada | Resultado |
|---|---|
| `$this->view('turmas/index', $dados)` | template padrao, com menu (`template/layout`) |
| `$this->view('auth/professor/login', $dados, 'template/layout-login')` | pagina de login, sem menu |
| `$this->viewSemLayout('turmas/linha', $dados)` | so o HTML da view, sem template (util em AJAX) |

Se voce ja tinha rodado o `auth:install` antes desta versao, basta
acrescentar esse terceiro argumento nas duas chamadas `view()` do seu
`AuthController`.

---

## Passo 7 — Proteger as rotas

Agora que existe login, temos duas formas de exigir que a pessoa esteja
logada.

### a) Nos CRUDs novos: a opcao `--auth`

```bash
php console.php scaffold:crud aulas titulo:string data:date turma_id:belongs_to=turmas --auth
```

```text
Rotas protegidas pelo login /auth-professor.
```

Repare: escrevemos `--auth` sem prefixo e o console entendeu
`/auth-professor`. Quando existe **uma unica** tela de login instalada,
`--auth` usa ela. Com duas ou mais, escolha qual:
`--auth=professor`. Se o nome nao existir, o comando lista os disponiveis em
vez de gerar um CRUD quebrado.

Abra `controllers/AulasController.php`: cada acao publica comeca com a mesma
linha.

```php
/** GET /aulas */
public function index(): void
{
    $this->exigirAutenticacao('professor');

    $this->view('aulas/index', [
        'titulo'    => 'Aulas',
        'registros' => $this->modelo->todos(),
    ]);
}
```

Saia da sessao e abra `/aulas`: voce cai na tela de login com o aviso
"Entre para continuar.".

### b) Nos controllers que ja existem: chame o metodo

`exigirAutenticacao()` **nao e uma configuracao global**: e uma chamada de
metodo comum, que vale exatamente onde voce escrever. Isso da dois jeitos de
usar.

Uma acao de cada vez, quando so parte da tela e restrita:

```php
public function index(): void      // lista publica
{
    $this->view('turmas/index', ['registros' => $this->modelo->todos()]);
}

public function criar(): void      // cadastro so para quem esta logado
{
    $this->exigirAutenticacao('professor');

    $this->view('turmas/formulario', ['registro' => null]);
}
```

Ou o controller inteiro, colocando a chamada no construtor — ele roda antes
de qualquer acao:

```php
class TurmasController extends Controller
{
    private Turma $modelo;

    public function __construct()
    {
        $this->exigirAutenticacao('professor');

        $this->modelo = new Turma();
    }

    // todas as acoes daqui para baixo exigem login
}
```

Faca isso em `TurmasController`, `AlunosController` e
`ProfessoresController` e o Diario de aulas fica inteiro protegido.

Tres duvidas que aparecem sempre:

- **Isso tranca a propria tela de login?** Nao. `/auth-professor/login` e
  atendido por outro controller (`AuthProfessorController`), que nao tem a
  chamada. So daria laco se voce colocasse a guarda dentro do proprio
  controller de login.
- **E se o provider nao existir?** `exigirAutenticacao('professor')` sem o
  `AuthProfessorController` instalado nao redireciona: levanta erro e
  aparece a tela de erro 500, dizendo qual comando rodar. Provider errado e
  bug de programador, nao caminho de visitante.
- **Sem argumento, qual login ele usa?** `exigirAutenticacao()` usa `/auth`;
  se `/auth` nao existir e houver so uma tela instalada, usa essa — o mesmo
  atalho do `--auth`. Com duas ou mais telas, escreva qual:
  `exigirAutenticacao('professor')`.

### c) Nas views: mostrar so o que interessa

```php
<?php if (autenticado('professor')): ?>
    <a href="<?= url('aulas/criar') ?>">Nova aula</a>
    <a href="<?= url(rota_sair('professor')) ?>">Sair</a>
<?php else: ?>
    <a href="<?= url(rota_login('professor')) ?>">Entrar</a>
<?php endif ?>
```

`usuario_id('professor')` devolve o id de quem esta logado, ou `null`.

Esconder o link nao protege nada — quem souber o endereco continua entrando.
A protecao de verdade e a do controller; a view so evita mostrar botao que
nao vai funcionar.

No menu, o mesmo efeito sem tocar em HTML. Em `configuracoes/menu.php`:

```php
return [
    ['rota' => '', 'texto' => 'Inicio'],
    ['rota' => 'turmas', 'texto' => 'Turmas', 'auth' => 'sim'],
    ['rota' => 'alunos', 'texto' => 'Alunos', 'auth' => 'sim'],
    ['rota' => 'aulas',  'texto' => 'Aulas',  'auth' => 'sim'],
    // scaffold:crud
];
```

`'auth' => 'sim'` mostra o item so para quem esta logado; `'auth' => 'nao'`
faz o contrario.

### d) Formularios: o token anti-CSRF

Todo formulario gerado ja inclui o token, e o controller confere:

```php
<form method="post" action="<?= url('turmas/salvar') ?>">
    <?= campo_csrf() ?>
    ...
</form>
```

```php
public function salvar(): void
{
    $this->exigirFormularioValido();   // exige POST + token valido
    // ...
}
```

CSRF e quando outro site faz o navegador da vitima enviar um formulario para
o seu sistema aproveitando a sessao aberta. Como o site atacante nao consegue
ler o token, ele nao consegue montar um envio valido. Faca o mesmo nos seus
proprios formularios.

Na entrada, o login chama `Sessao::regenerar()`, que troca o identificador da
sessao: sem isso, um id capturado antes do login continuaria valendo depois
("session fixation").

---

## Passo 8 — Relatorio em PDF

Todo CRUD ja nasce com uma rota de relatorio, e a listagem traz o link:

```text
/alunos/relatorio
/alunos/relatorio?nome=ana&ativo=1
```

Os filtros sao os mesmos da pesquisa: campos de texto filtram por trecho;
`id`, numeros, datas, booleanos e chaves estrangeiras filtram por valor
exato. Sem filtro nenhum, o PDF traz todos os registros.

O PDF e montado na memoria e devolvido pela rota — nao vira arquivo em
pasta publica. Isso importa: como e uma rota normal do controller, ela
respeita o `exigirAutenticacao()` que voce colocou no passo 7. Um arquivo
solto em `public/` continuaria acessivel para qualquer um com o endereco.

Para gerar um arquivo pelo terminal (backup, envio por e-mail, anexo de
trabalho):

```bash
php console.php relatorio:pdf alunos
php console.php relatorio:pdf Aluno relatorios/alunos.pdf
```

O comando aceita o nome da tabela ou o do model. Sem o segundo argumento
salva em `relatorios/{tabela}.pdf`; caminhos relativos partem da raiz do
projeto e nao podem sair dela.

---

## Passo 9 — Um segundo login (opcional)

No Diario de aulas o professor lanca as aulas. E se o aluno tambem precisar
entrar, para ver so as proprias informacoes? Cada tabela autenticavel pode
ter a sua tela:

```bash
php console.php auth:install Aluno
```

```text
Autenticacao aplicada ao modelo Aluno.
  + controllers/AuthAlunoController.php
  + views/auth/aluno/login.php
  + views/auth/aluno/registrar.php
  + testes/controllers/AuthAlunoControllerTest.php
  ~ modelos/Aluno.php
  ~ banco/esquema.sql

Login em /auth-aluno: prefixo "aluno", vindo do modelo Aluno.
```

A tabela `alunos` ja tinha `email`, entao o comando so acrescentou `senha` e
o indice unico. Agora existem dois providers:

| Provider | Controller | Rotas | Sessao |
|---|---|---|---|
| `professor` | `AuthProfessorController` | `/auth-professor/...` | `autenticacao_professor_id` |
| `aluno` | `AuthAlunoController` | `/auth-aluno/...` | `autenticacao_aluno_id` |

As chaves de sessao sao separadas: entrar como aluno **nao** da acesso as
telas do professor, e da para estar logado nos dois ao mesmo tempo (util
para testar). O menu passa a mostrar "Entrar (professor)" e "Entrar
(aluno)".

Com duas telas instaladas, diga sempre qual voce quer:

```bash
php console.php scaffold:crud notas valor:decimal aluno_id:belongs_to=alunos --auth=professor
```

```php
$this->exigirAutenticacao('aluno');     // no controller
autenticado('aluno')                    // na view
usuario_id('aluno')                     // id de quem esta logado
```

Sem o nome, `--auth` e `exigirAutenticacao()` procuram `/auth`; como ele nao
existe e agora ha **duas** telas, o console lista as opcoes e o
`exigirAutenticacao()` levanta erro. Nao e chatice: e melhor errar no
terminal do que mandar o visitante para a tela de login errada.

Um detalhe que confunde: depois de um login bem-sucedido o controller gerado
manda para a pagina inicial (`$this->redirecionar();`). Se a `/` exigir o
login do professor, o aluno vai entrar e voltar direto para a tela de login —
parece que "o login nao funciona", mas e a home recusando o provider errado.
Se isso acontecer, mude o destino no controller do provider:

```php
$this->mensagem('sucesso', 'Bem-vindo!');
$this->redirecionar('aulas');   // em vez de $this->redirecionar();
```

---

## Passo 10 — Testes

```bash
php testes/executar.php
```

```text
Testes: 103 | Passaram: 103 | Falharam: 0 | Erros: 0 | Assercoes: 331

TUDO CERTO! O sistema esta funcionando.
```

Todos esses testes vieram de graca junto com os comandos dos passos
anteriores. Para rodar so uma parte:

```bash
php testes/executar.php AlunoTest
php testes/executar.php AlunoTest::testeExecutaCrudCompleto
```

Os testes rodam no **banco de testes** (`banco_testes` em
`configuracoes/banco.php`), que e apagado e recriado a cada execucao: os
alunos e as turmas que voce cadastrou continuam intactos. Cada classe recria
as proprias tabelas, entao a ordem de execucao nao muda o resultado.

Precisa do MySQL ligado. Se ele estiver fora do ar, o comando avisa antes de
rodar qualquer teste, em vez de acusar dezenas de falhas sem explicacao.

O que os testes gerados ja cobrem:

- o CRUD completo do model e as regras de validacao;
- as rotas de listagem, cadastro, detalhes, edicao, exclusao e relatorio;
- a recusa de dados invalidos;
- a recusa de um POST sem token (CSRF);
- a recusa de exclusao por GET;
- o redirecionamento para o login nos recursos gerados com `--auth`;
- no `auth:install`: cadastro, login, saida, senha errada, e-mail repetido,
  senha curta e o hash gravado no banco.

### Escrevendo o seu proprio teste

Crie um arquivo terminado em `Test.php` dentro de uma subpasta de `testes/`
— o namespace acompanha a pasta e cada metodo publico comecado por `teste`
roda sozinho. Salve este como `testes/modelos/AlunoAtivoTest.php`:

```php
<?php

namespace Testes\Modelos;

use Modelos\Aluno;
use Nucleo\Sessao;
use Testes\Suporte\TesteBase;

class AlunoAtivoTest extends TesteBase
{
    public function preparar(): void
    {
        $this->limparSessao();

        // Cada classe de teste monta as tabelas que vai usar.
        $this->recriarTabelas([
            'aulas' => 'CREATE TABLE aulas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(255) NULL,
                data DATE NULL,
                turma_id INT NULL
            )',
        ]);
    }

    public function testeSoAceitaAlunoComTurma(): void
    {
        $erros = (new Aluno())->validar(['nome' => 'Ana']);

        $this->assertTemChave('turma_id', $erros);
    }

    public function testeListaDeAulasPedeLogin(): void
    {
        $resposta = $this->requisitar('aulas');

        $this->assertVerdadeiro($resposta->redirecionouPara('auth-professor/login'));
    }

    public function testeProfessorLogadoVeAsAulas(): void
    {
        Sessao::definir(Sessao::chaveAutenticacao('professor'), 1);

        $resposta = $this->requisitar('aulas');

        $this->assertIgual(200, $resposta->status);
        $this->assertVerdadeiro($resposta->contem('Aulas'));
    }
}
```

```text
Modelos\AlunoAtivoTest
  PASSOU so aceita aluno com turma (12.9ms)
  PASSOU lista de aulas pede login (9.6ms)
  PASSOU professor logado ve as aulas (10.5ms)
```

Repare no `preparar()`: como o banco dos testes e criado do zero na memoria,
a tabela usada precisa ser montada ali. Sem isso o teste quebra com
"no such table".

Ferramentas da `TesteBase`:

| Metodo | Para que serve |
|---|---|
| `requisitar('alunos')` | simula um GET e devolve a resposta |
| `requisitar('alunos', 'POST', [...])` | simula um POST cru |
| `postar('alunos/salvar', [...])` | POST com o token CSRF ja incluido |
| `postarSemToken(...)` | POST sem token, para testar a recusa |
| `recriarTabelas([...])` | monta o cenario do teste |
| `limparSessao()` | comeca deslogado |
| `assertIgual`, `assertContem`, `assertVerdadeiro`, `assertTemChave`, ... | verificacoes |

Da resposta voce le `->status`, `->html`, `->contem('texto')`,
`->foiRedirecionado()`, `->redirecionouPara('rota')` e `->json()`.

---

## Passo 11 — Ajustes finais

O sistema esta pronto; falta a arrumacao.

**1. Menu.** Em `configuracoes/menu.php`, cada `scaffold:crud` deixou uma
linha. Mude textos, reordene, remova o que nao deve aparecer e esconda o que
so interessa a quem entrou:

```php
return [
    ['rota' => '', 'texto' => 'Inicio'],
    ['rota' => 'turmas', 'texto' => 'Turmas', 'auth' => 'sim'],
    ['rota' => 'alunos', 'texto' => 'Alunos', 'auth' => 'sim'],
    ['rota' => 'aulas', 'texto' => 'Diario de aulas', 'auth' => 'sim'],
    ['rota' => 'professores', 'texto' => 'Professores', 'auth' => 'sim'],
    // scaffold:crud
];
```

**2. Pagina inicial.** `views/home/index.php` ainda e a tela de boas-vindas
do framework. Troque pelo texto do seu sistema.

**3. Nome do sistema.** `configuracoes/app.php`, chave `nome`.

**4. Antes de publicar**, deixe `'debug' => false` em
`configuracoes/app.php`: com `true`, a tela de erro mostra arquivo, linha e
pilha de chamadas — otimo enquanto voce desenvolve, informacao demais para o
mundo.

**5. Revise as protecoes.** Para cada controller, pergunte: quem pode abrir
isso? As acoes que gravam ou apagam estao com `exigirFormularioValido()`?
Os relatorios com dados pessoais exigem login?

**6. Rode os testes uma ultima vez.**

```bash
php testes/executar.php
```

---

## Fim: o que voce construiu

Em pouco mais de uma dezena de comandos saiu um sistema com:

- quatro cadastros completos, com validacao, mensagens de erro e telas
  prontas em Bootstrap 5;
- alunos e aulas ligados a uma turma por chave estrangeira, com `<select>`
  montado sozinho;
- pesquisa por nome, situacao e turma, compartilhavel pela URL;
- login do professor (e, se quiser, do aluno), com senha em hash e protecao
  contra CSRF e fixacao de sessao;
- relatorio em PDF filtravel e protegido;
- 100+ testes automatizados passando.

O roteiro inteiro, para repetir do zero:

```bash
php instalar.php
php console.php scaffold:crud turmas nome:string ano:integer
php console.php scaffold:crud alunos nome:string email:string nascimento:date ativo:boolean turma_id:belongs_to=turmas
php console.php scaffold:pesquisa alunos nome ativo turma_id
php console.php scaffold:crud professores nome:string
php console.php auth:install Professor
php console.php scaffold:crud aulas titulo:string data:date turma_id:belongs_to=turmas --auth
php testes/executar.php
php -S localhost:8000 roteador.php
```

Depois protegeu `TurmasController`, `AlunosController` e
`ProfessoresController` com `exigirAutenticacao('professor')` no construtor.

### Para onde ir agora

- Personalize os models: novas regras em `validar()`, consultas proprias com
  `onde()` e `primeiroOnde()`, metodos de negocio (`aulasDaTurma()`).
- Personalize as views geradas: elas sao HTML comum, sem magica.
- Acrescente colunas rodando o `scaffold:crud` de novo depois de apagar os
  arquivos do recurso — a definicao da tabela e substituida, e os dados
  existentes sao preservados.
- Consulte a [Referencia de comandos](Referencia-Comandos.md) para as opcoes
  que este tutorial nao usou.

---

## Apendice A — Erros comuns

Todos os comandos param no primeiro problema e **nao deixam arquivos pela
metade**: o console valida tudo, mexe no banco e so entao grava; se algo
falhar no meio, o que ja foi criado e removido e o esquema volta ao estado
anterior. Acrescente `-v` para ver arquivo, linha e pilha de chamadas.

| Mensagem | O que aconteceu | Solucao |
|---|---|---|
| `Tipo invalido em "nome:strng": strng` | erro de digitacao no tipo | use um dos tipos da tabela do passo 3 |
| `A tabela pai "turmas" nao existe (campo turma_id)` | o `belongs_to` aponta para uma tabela que ainda nao foi gerada | gere a tabela pai primeiro |
| `Estes arquivos ja existem e nao serao sobrescritos` | o recurso ja foi gerado | apague os arquivos listados e rode de novo |
| `Modelo nao encontrado: "Coordenador"` | `auth:install` num model que nao existe | gere o CRUD antes, ou use `auth:install` sem argumento para criar o `Usuario` |
| `A tela de login /auth ainda nao existe` | `--auth` antes do `auth:install` | rode o `auth:install` primeiro |
| `A tela de login /auth-professor ja existe` | segundo `auth:install` no mesmo provider | escolha outro prefixo: `auth:install Modelo prefixo` |
| `A tela de login /auth nao existe. Telas instaladas: ...` | ha mais de um provider e o comando nao sabe qual usar | escreva `--auth=professor` |
| `Nenhuma tela de login foi instalada` (na tela de erro 500) | um controller chama `exigirAutenticacao()` sem nenhum login instalado | rode o `auth:install` ou tire a chamada |
| `Controlador nao encontrado` (404) | a URL nao corresponde a nenhum controller | confira o nome no plural: `/alunos`, nao `/aluno` |
| A pagina abre sem estilo | o servidor foi iniciado sem o roteador | use `php -S localhost:8000 roteador.php` |
| `Nao foi possivel conectar ao banco "framework_aula"` | o MySQL esta parado ou a senha esta errada | inicie o MySQL no XAMPP e confira `configuracoes/banco.php` |
| `Nao foi possivel preparar o banco de testes` | mesma coisa, ao rodar `php testes/executar.php` | idem — o banco de testes e recriado a cada execucao |

---

## Apendice B — Helpers das views

| Helper | O que faz |
|---|---|
| `url('alunos/criar')` | monta o endereco completo de uma rota |
| `asset('css/estilo.css')` | endereco de um arquivo publico |
| `e($texto)` | escapa HTML (use sempre que imprimir dado do banco) |
| `campo_csrf()` | gera o campo escondido com o token |
| `antigo('nome')` | valor digitado antes de um erro de validacao |
| `erro_de('nome')` / `tem_erro('nome')` | mensagem de erro de um campo |
| `mensagens()` | mensagens flash (sucesso, erro, aviso) |
| `autenticado('professor')` | ha alguem logado nesse provider? |
| `usuario_id('professor')` | id de quem esta logado, ou `null` |
| `rota_login('professor')` / `rota_sair('professor')` | rotas do provider |
| `sim_nao($valor)` | mostra `Sim` / `Nao` para campos booleanos |
| `data_br('2026-03-01')` | mostra `01/03/2026` |
| `moeda_br(1234.5)` | mostra `1.234,50` |
| `parcial('alunos/linha', [...])` | inclui um pedaco de view |
| `dd($valor)` | mostra o valor e para a execucao (depuracao) |
