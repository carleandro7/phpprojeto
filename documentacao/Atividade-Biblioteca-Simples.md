# Atividade guiada — Biblioteca (versao simples)

Atividade de cerca de **2h30**, feita em sala, individualmente.

Voce vai construir um sistema pequeno para a biblioteca da escola:

- o **bibliotecario** e o administrador: so ele entra no sistema e so ele
  mexe no acervo;
- o cadastro dele fica **guardado no banco de dados**, com a senha protegida;
- depois de entrar, ele cadastra, pesquisa, edita e exclui livros;
- o sistema recusa livro sem ano ou com ano no futuro — uma regra que **voce**
  escreve.

Todo passo segue o mesmo formato:

| Marca | O que significa |
|---|---|
| **Faca** | o que digitar ou editar |
| **Confira** | o que deve aparecer na tela ou no terminal |
| **Entenda** | por que funciona assim |

Nao pule o **Entenda**. Copiar comando qualquer um copia; o que voce vai
levar desta aula esta ali.

> Esta e a versao curta. A *Atividade de laboratorio — Biblioteca da escola*
> (`Atividade-Laboratorio-Biblioteca.md`) vai bem alem: categorias, mais regras
> de negocio, testes escritos do zero e Git. Faca esta primeiro.

---

## O que vamos construir

| Rota | O que faz | Quem abre |
|---|---|---|
| `/` | pagina inicial | qualquer um |
| `/auth-bibliotecario/login` | tela de entrada | qualquer um |
| `/auth-bibliotecario/sair` | saida | qualquer um |
| `/auth-bibliotecario/registrar` | cadastrar outro bibliotecario | so bibliotecario logado |
| `/bibliotecarios` | lista da equipe | so bibliotecario logado |
| `/livros` | cadastro de livros, com pesquisa | so bibliotecario logado |

As duas tabelas:

```text
bibliotecarios                      livros
  id                                  id
  nome                                titulo
  email   <- usado para entrar        autor
  senha   <- guardada como hash       ano     <- obrigatorio, nao pode estar no futuro
                                      disponivel
```

As tabelas nao se ligam uma a outra. E de proposito: hoje o assunto e
**login e administrador**.

## Roteiro

| Parte | O que fica pronto | Tempo |
|---|---|---|
| 1 | Projeto copiado, banco criado, servidor no ar | 15 min |
| 2 | Tabela dos bibliotecarios | 10 min |
| 3 | Tela de login do bibliotecario | 10 min |
| 4 | O administrador cadastrado no banco | 20 min |
| 5 | Portas trancadas: so o bibliotecario entra | 20 min |
| 6 | Cadastro de livros com pesquisa | 20 min |
| 7 | Uma regra de negocio: o ano do livro | 15 min |
| 8 | Testes conferindo tudo | 20 min |
| 9 | Arrumacao final | 10 min |

---

## Parte 1 — Preparar o projeto (15 min)

### 1.1 Confira o ambiente

**Faca:**

```bash
php -v
```

**Confira:** a versao precisa ser **8.1 ou maior**.

Abra o painel do XAMPP e inicie o **MySQL** e o **Apache**.

**Entenda:** o MySQL guarda os dados. O Apache so e necessario para abrir o
phpMyAdmin na parte 4 — o sistema em si roda no servidor do proprio PHP.

### 1.2 Faca uma copia do framework

**Faca:** copie a pasta do framework e de o nome `minha-biblioteca`. Pode ser
pelo explorador de arquivos ou pelo terminal:

```bash
cd ~/Desenvolvimento          # ou a pasta onde voce guarda seus projetos
cp -R framework minha-biblioteca
cd minha-biblioteca
```

**Entenda:** trabalhando numa copia, o framework original fica limpo para as
proximas atividades.

### 1.3 Crie um banco so para esta atividade

**Faca:** abra `configuracoes/banco.php` e troque os dois nomes de banco:

```php
'banco'        => 'minha_biblioteca',
'banco_testes' => 'minha_biblioteca_testes',
```

Depois, no terminal:

```bash
php instalar.php
```

**Confira:**

```text
Instalando o banco de dados (MySQL)...
Servidor: localhost | Banco: minha_biblioteca
[ok] Banco de dados pronto. Nenhuma tabela padrao foi criada.
[ok] Banco de testes pronto: minha_biblioteca_testes
```

Deu `[ERRO]`? O MySQL esta desligado, ou o usuario e a senha em
`configuracoes/banco.php` nao batem com os do seu XAMPP (o padrao do XAMPP e
usuario `root` e senha vazia).

**Entenda:** sao **dois** bancos. `minha_biblioteca` guarda os seus dados.
`minha_biblioteca_testes` e apagado e recriado toda vez que os testes rodam —
assim testar nunca apaga o que voce cadastrou.

### 1.4 Suba o servidor

**Faca:** abra um **segundo terminal** na pasta `minha-biblioteca` e rode:

```bash
php -S localhost:8000 roteador.php
```

Deixe esse terminal aberto ate o fim da aula. Os comandos das proximas partes
vao no **primeiro** terminal.

**Confira:** abra <http://localhost:8000>. A pagina inicial do framework
aparece.

---

## Parte 2 — A tabela dos bibliotecarios (10 min)

### 2.1 Por que comecar por aqui

Para alguem fazer login, o sistema precisa de um lugar onde guardar **quem
pode entrar**. Esse lugar e a tabela `bibliotecarios`.

A ordem e: primeiro a tabela existe (parte 2), depois ela ganha a capacidade
de fazer login (parte 3).

### 2.2 Gere o cadastro

**Faca:**

```bash
php console.php scaffold:crud bibliotecarios nome:string
```

**Confira:**

```text
CRUD criado: /bibliotecarios
  + modelos/Bibliotecario.php
  + controllers/BibliotecariosController.php
  + views/bibliotecarios/index.php
  + views/bibliotecarios/formulario.php
  + views/bibliotecarios/ver.php
  + testes/modelos/BibliotecarioTest.php
  + testes/controllers/BibliotecariosControllerTest.php
  ~ banco/esquema.sql
  ~ configuracoes/menu.php

ATENCAO: todas as rotas de /bibliotecarios sao publicas, inclusive excluir e o relatorio.
Para exigir login, gere com --auth ou chame exigirAutenticacao() no controller.

Rode os testes com: php testes/executar.php Bibliotecario
```

**Entenda o comando, pedaco por pedaco:**

| Pedaco | Significado |
|---|---|
| `scaffold:crud` | "gere um cadastro completo": criar, listar, ver, editar e excluir |
| `bibliotecarios` | o nome da tabela, no plural |
| `nome:string` | uma coluna chamada `nome`, do tipo texto curto |

**Entenda o que foi gerado.** `+` e arquivo criado; `~` e arquivo alterado.
Cada arquivo tem um papel no MVC:

| Arquivo | Camada | Papel |
|---|---|---|
| `modelos/Bibliotecario.php` | Model | conversa com o banco e valida os dados |
| `controllers/BibliotecariosController.php` | Controller | recebe o pedido do navegador e escolhe a tela |
| `views/bibliotecarios/*.php` | View | o HTML que aparece |
| `testes/...` | Testes | provam que tudo funciona |
| `banco/esquema.sql` | Banco | o `CREATE TABLE` da tabela nova |

### 2.3 Leia o aviso do fim

O comando avisou: **todas as rotas sao publicas**. Qualquer visitante pode
abrir `/bibliotecarios` e excluir alguem.

Por enquanto nao da para resolver: ainda nao existe login. Guarde esse aviso —
voce resolve na parte 5.

**Confira:** abra <http://localhost:8000/bibliotecarios>. A lista aparece
vazia.

> **Nao cadastre ninguem por essa tela.** O formulario dela so tem o campo
> `nome`: um bibliotecario criado ali fica sem e-mail e sem senha, e nunca
> vai conseguir entrar.

---

## Parte 3 — O login do bibliotecario (10 min)

### 3.1 Instale o login na tabela

**Faca:**

```bash
php console.php auth:install Bibliotecario
```

**Confira:**

```text
Autenticacao aplicada ao modelo Bibliotecario.
  + controllers/AuthBibliotecarioController.php
  + views/auth/bibliotecario/login.php
  + views/auth/bibliotecario/registrar.php
  + testes/controllers/AuthBibliotecarioControllerTest.php
  ~ modelos/Bibliotecario.php
  ~ banco/esquema.sql

Login em /auth-bibliotecario: prefixo "bibliotecario", vindo do modelo Bibliotecario.
Para deixa-lo no login unico /auth: php console.php auth:install Bibliotecario auth

Rotas:
  /auth-bibliotecario/registrar   cria uma conta
  /auth-bibliotecario/login       entra
  /auth-bibliotecario/sair        encerra a sessao

Para exigir esse login:
  em um CRUD novo    php console.php scaffold:crud <tabela> <campo:tipo> ... --auth=bibliotecario
  em um controller   $this->exigirAutenticacao('bibliotecario');

Rode os testes com: php testes/executar.php AuthBibliotecarioController
```

Guarde as duas ultimas linhas de "Para exigir esse login": voce vai usar as
duas.

### 3.2 Entenda o que mudou

**No banco**, a tabela `bibliotecarios` ganhou duas colunas:

| Coluna | Para que serve |
|---|---|
| `id` | numero do registro, criado sozinho |
| `nome` | o nome da pessoa (parte 2) |
| `email` | **novo** — o que se digita para entrar. Tem indice `UNIQUE`: nao existem dois bibliotecarios com o mesmo e-mail |
| `senha` | **novo** — guarda a senha, sempre como hash (parte 4) |

**No model**, `modelos/Bibliotecario.php` ganhou uma linha e duas colunas
permitidas:

```php
class Bibliotecario extends Model
{
    use Autenticavel;

    protected string $tabela = 'bibliotecarios';
    protected array $preenchiveis = ['nome', 'email', 'senha'];
    // ...
}
```

`use Autenticavel;` e um **trait**: um pacote de metodos que o model passa a
ter, sem voce escrever. Os que importam hoje:

| Metodo | O que faz |
|---|---|
| `autenticar($email, $senha)` | confere e-mail e senha; devolve o bibliotecario ou `null` |
| `criarComSenha($dados, $senha)` | cria um bibliotecario ja com a senha em hash |
| `trocarSenha($id, $senha)` | troca a senha de quem ja existe |

**Nas rotas**, o prefixo `auth-bibliotecario` saiu do nome do model. Por isso
o menu mostra "Entrar (bibliotecario)".

### 3.3 Olhe a tela

**Faca:** abra <http://localhost:8000/auth-bibliotecario/login> e tente
entrar com qualquer e-mail e senha.

**Confira:**

- a tela **nao tem o menu lateral** — quem ainda nao entrou nao precisa ver
  a lista de telas do sistema;
- aparece "E-mail ou senha invalidos." — claro: a tabela ainda esta vazia.

---

## Parte 4 — O administrador cadastrado no banco (20 min)

O sistema precisa de um primeiro bibliotecario. Mas repare no link "Criar uma
conta" na tela de login: se ele ficar aberto, **qualquer aluno cria uma conta
e vira administrador da biblioteca**.

Por isso o primeiro bibliotecario — o administrador — **nao** vai ser criado
por tela nenhuma. Ele vai ser inserido direto no banco, por quem instala o
sistema: voce.

Os dados do administrador:

| Campo | Valor |
|---|---|
| Nome | `Maria Souza` |
| E-mail | `admin@biblioteca.com` |
| Senha | `biblioteca123` |

> Na aula, use exatamente esses dados. Num sistema de verdade a senha seria
> outra, e so o administrador saberia.

### 4.1 Por que nao da para gravar a senha direto

A tentacao e escrever `'biblioteca123'` no banco e pronto. **Nao funciona** —
e ainda bem.

Abra `nucleo/Autenticavel.php` e procure o metodo `autenticar()`. A ultima
linha e esta:

```php
return password_verify($senha, (string) $registro['senha']) ? $registro : null;
```

O login **nunca compara** a senha digitada com um texto guardado. Ele usa
`password_verify()`, que espera encontrar no banco um **hash**.

**Entenda o hash:** pense numa impressao digital. Da pessoa se tira a digital,
mas olhando a digital ninguem reconstroi a pessoa. O hash e a digital da
senha:

- da senha se calcula o hash;
- do hash **nao** se volta para a senha;
- para conferir, o PHP calcula de novo a partir do que foi digitado e compara
  as duas digitais.

Se alguem roubar a tabela `bibliotecarios`, leva as digitais — e nao as
senhas.

### 4.2 Gere o hash da senha

**Faca:**

```bash
php -r "echo password_hash('biblioteca123', PASSWORD_DEFAULT) . PHP_EOL;"
```

**Confira:** aparece uma linha parecida com esta:

```text
$2y$12$eM.x7vAHotRY5lfy7X/2UuRllxZWDu7GWMt8J8mnWHs7/mG6QVYJW
```

**O seu vai sair diferente — e esta certo.** Rode o comando duas vezes e
compare: cada vez sai um hash novo. O PHP mistura um valor aleatorio (o
*sal*) antes de calcular, entao duas pessoas com a mesma senha ficam com hashes
diferentes no banco. O `password_verify()` sabe ler o sal que esta dentro do
proprio hash.

**Entenda as partes:**

| Pedaco | Significado |
|---|---|
| `php -r "..."` | roda um trecho de PHP direto no terminal, sem criar arquivo |
| `password_hash(...)` | calcula o hash |
| `$2y$` | o algoritmo usado (bcrypt) |
| `12$` | o "custo": quanto maior, mais lento para quem tenta adivinhar (no PHP 8.1 a 8.3 aparece `10$`) |

**Copie a linha inteira** do seu hash. Voce vai colar no proximo passo.

### 4.3 Insira o administrador no banco

**Faca:**

1. Abra <http://localhost/phpmyadmin>.
2. Na coluna da esquerda, clique no banco **`minha_biblioteca`**.
3. Abra a aba **SQL**.
4. Cole o comando abaixo, trocando `COLE_AQUI_O_HASH` pelo **seu** hash:

```sql
INSERT INTO bibliotecarios (nome, email, senha)
VALUES ('Maria Souza', 'admin@biblioteca.com', 'COLE_AQUI_O_HASH');
```

5. Clique em **Executar**.

Tres cuidados que evitam 90% dos problemas:

- o hash fica **entre aspas simples**, como os outros valores;
- nao pode sobrar **espaco** antes ou depois do hash;
- o hash tem **60 caracteres** — se faltar um pedaco, o login falha.

> **Sem phpMyAdmin?** Pelo terminal da no mesmo:
> `mysql -u root minha_biblioteca` (no Windows:
> `C:\xampp\mysql\bin\mysql -u root minha_biblioteca`), cole o mesmo
> `INSERT` e digite `exit` para sair.

**Confira:** abra a tabela `bibliotecarios` (aba **Visualizar**). Deve haver
uma linha:

| id | nome | email | senha |
|---|---|---|---|
| 1 | Maria Souza | admin@biblioteca.com | `$2y$12$...` |

A senha **nao aparece legivel**. Se um dia voce vir `biblioteca123` escrito
nessa coluna, alguem quebrou o sistema.

### 4.4 Entre como administrador

**Faca:** abra <http://localhost:8000/auth-bibliotecario/login> e entre com
`admin@biblioteca.com` e `biblioteca123`.

**Confira:**

- voce vai para a pagina inicial com a mensagem **"Bem-vindo!"**;
- no menu, em "Conta", aparece **"Sair (bibliotecario)"**.

**Faca:** clique em "Sair" e tente entrar com a senha `biblioteca000`.

**Confira:** "E-mail ou senha invalidos." Repare que a mensagem nao diz se o
erro foi no e-mail ou na senha. E de proposito: dizer "esse e-mail existe"
ajudaria quem esta tentando adivinhar contas.

Entre de novo com a senha certa antes de seguir.

**Entenda o caminho do login:**

```text
voce digita  admin@biblioteca.com  +  biblioteca123
                    |
                    v
   AuthBibliotecarioController::login()        (Controller)
                    |  "model, confere pra mim?"
                    v
   Bibliotecario::autenticar()                  (Model)
       1. busca a linha pelo e-mail
       2. password_verify(senha digitada, hash do banco)
                    |
          deu certo? v
   guarda o id da Maria na SESSAO  ->  "Bem-vindo!"
```

A **sessao** e a memoria do servidor sobre o seu navegador. Enquanto o id da
Maria estiver nela, o sistema sabe que e ela, de uma pagina para a outra.

> **A Maria mora no banco, nao no codigo.** Se voce levar o projeto para
> outro computador e rodar `php instalar.php`, a tabela nasce vazia: repita
> os passos 4.2 e 4.3 la.

---

## Parte 5 — Trancar as portas (20 min)

### 5.1 O teste do intruso

**Faca:** abra uma **janela anonima** no navegador (`Ctrl+Shift+N` no Chrome,
`Ctrl+Shift+P` no Firefox). Nela voce **nao** esta logado. Visite:

- <http://localhost:8000/bibliotecarios>
- <http://localhost:8000/auth-bibliotecario/registrar>

**Confira:** as duas abrem. Ou seja:

1. qualquer visitante ve, edita e exclui a equipe da biblioteca;
2. qualquer visitante cria uma conta de bibliotecario — e vira administrador.

Duas portas abertas. Deixe a janela anonima aberta; voce volta nela no 5.6.

**Entenda a ferramenta que vai trancar as duas:**

```php
$this->exigirAutenticacao('bibliotecario');
```

Ela faz uma pergunta: "quem pediu esta pagina esta logado como
bibliotecario?"

- **sim** — deixa passar, e a pagina abre normalmente;
- **nao** — manda para `/auth-bibliotecario/login` com o aviso "Entre para
  continuar.".

Ela **nao** e uma configuracao que vale para o sistema todo. E uma linha de
codigo, e vale **so onde voce escrever**.

### 5.2 Porta 1: a lista de bibliotecarios

**Faca:** em `controllers/BibliotecariosController.php`, acrescente a linha
no construtor.

Antes:

```php
public function __construct()
{
    $this->modelo = new Bibliotecario();
}
```

Depois:

```php
public function __construct()
{
    $this->exigirAutenticacao('bibliotecario');

    $this->modelo = new Bibliotecario();
}
```

**Entenda:** o construtor roda **antes de qualquer acao** do controller. Uma
linha ali protege todas de uma vez: `index`, `criar`, `salvar`, `ver`,
`editar`, `atualizar`, `excluir` e `relatorio`.

### 5.3 Porta 2: o cadastro de contas

**Faca:** em `controllers/AuthBibliotecarioController.php`, acrescente a linha
**dentro do metodo `registrar()`**, logo no comeco:

```php
/** GET e POST /auth-bibliotecario/registrar */
public function registrar(): void
{
    // So um bibliotecario que ja entrou pode cadastrar outro.
    $this->exigirAutenticacao('bibliotecario');

    if ($this->ehPost()) {
        // ... o resto continua igual
```

> **Atencao: aqui NAO e no construtor.** Este controller tambem cuida do
> `login()`. Se a linha fosse para o construtor, a propria tela de login
> exigiria login — e ninguem nunca mais entraria. Por isso ela vai so no
> metodo que deve ser fechado.

**Faca:** ainda no `registrar()`, mais para baixo, troque o que acontece
depois de cadastrar.

Antes:

```php
$this->mensagem('sucesso', 'Conta criada. Agora entre com seus dados.');
$this->redirecionar('auth-bibliotecario/login');
```

Depois:

```php
$this->mensagem('sucesso', 'Bibliotecario cadastrado.');
$this->redirecionar('bibliotecarios');
```

**Entenda:** antes, quem usava essa tela era um visitante criando a propria
conta, e fazia sentido manda-lo para o login. Agora quem usa e o
administrador, **que ja esta logado**: o natural e voltar para a lista da
equipe.

### 5.4 Tire os links que nao fazem mais sentido

**Faca:** tres ajustes pequenos nas views.

**a)** Em `views/auth/bibliotecario/login.php`, **apague** o bloco do fim:

```php
<p class="text-center mt-4 mb-0">
    <a href="<?= url('auth-bibliotecario/registrar') ?>">Criar uma conta</a>
</p>
```

**b)** Em `views/auth/bibliotecario/registrar.php`, troque o link do fim:

```php
<p class="text-center mt-4 mb-0">
    <a href="<?= url('bibliotecarios') ?>">Voltar para a lista</a>
</p>
```

**c)** Em `views/bibliotecarios/index.php`, faca o botao "Novo registro"
apontar para a tela certa:

Antes:

```php
<a class="btn btn-primary" href="<?= url('bibliotecarios/criar') ?>">Novo registro</a>
```

Depois:

```php
<a class="btn btn-primary" href="<?= url('auth-bibliotecario/registrar') ?>">Novo bibliotecario</a>
```

**Entenda o item c:** lembra do aviso da parte 2? O formulario de
`/bibliotecarios/criar` so pede o `nome`. A tela `registrar` pede nome,
e-mail e senha, e grava a senha em hash com `criarComSenha()`. E a unica que
cria um bibliotecario capaz de entrar.

### 5.5 Esconda o item do menu

**Faca:** em `configuracoes/menu.php`, acrescente `'auth' => 'sim'`:

```php
['rota' => 'bibliotecarios', 'texto' => 'Bibliotecarios', 'auth' => 'sim'],
```

**Entenda:** `'auth' => 'sim'` mostra o item so para quem esta logado.

> **Esconder o link nao protege nada.** Quem souber o endereco digita e
> entra. Quem protege de verdade e o `exigirAutenticacao()` do controller; o
> menu so evita mostrar um botao que nao vai funcionar.

### 5.6 Confira: o teste do intruso, de novo

**Faca:** volte a **janela anonima** e visite os mesmos enderecos.

**Confira:**

| Endereco | Antes | Agora |
|---|---|---|
| `/bibliotecarios` | abria | vai para o login com "Entre para continuar." |
| `/auth-bibliotecario/registrar` | abria | vai para o login com "Entre para continuar." |
| `/auth-bibliotecario/login` | abria | continua abrindo, **sem** o link "Criar uma conta" |
| menu lateral | mostrava "Bibliotecarios" | mostra so "Inicio" e "Entrar (bibliotecario)" |

**Faca:** agora na janela **normal**, logado como Maria:

1. Clique em **Bibliotecarios** > **Novo bibliotecario**.
2. Cadastre `Joao Lima`, `joao@biblioteca.com`, senha `joao123`.

**Confira:** voce volta para a lista com "Bibliotecario cadastrado." e os dois
nomes aparecem.

**Faca:** saia e entre como `joao@biblioteca.com` / `joao123`. Funciona. Saia
de novo e volte a entrar como Maria.

No phpMyAdmin, a senha do Joao tambem comeca com `$2y$` — dessa vez quem
calculou o hash foi o `criarComSenha()`, e nao voce.

---

## Parte 6 — O cadastro de livros (20 min)

### 6.1 Gere o CRUD ja protegido

**Faca:**

```bash
php console.php scaffold:crud livros titulo:string autor:string ano:integer disponivel:boolean --auth=bibliotecario
```

**Confira:**

```text
CRUD criado: /livros
  + modelos/Livro.php
  + controllers/LivrosController.php
  + views/livros/index.php
  + views/livros/formulario.php
  + views/livros/ver.php
  + testes/modelos/LivroTest.php
  + testes/controllers/LivrosControllerTest.php
  ~ banco/esquema.sql
  ~ configuracoes/menu.php

Rotas protegidas pelo login /auth-bibliotecario.
```

Repare: **nenhum aviso de rota publica**.

**Entenda os tipos:**

| Campo | Tipo | Coluna no MySQL | Campo na tela |
|---|---|---|---|
| `titulo` | `string` | `VARCHAR(255)` | caixa de texto |
| `autor` | `string` | `VARCHAR(255)` | caixa de texto |
| `ano` | `integer` | `INT` | caixa de numero |
| `disponivel` | `boolean` | `TINYINT(1)` | caixa de marcar (grava 1 ou 0) |

**Entenda o `--auth=bibliotecario`:** abra `controllers/LivrosController.php`.
Cada acao comeca com a mesma linha que voce escreveu a mao na parte 5:

```php
public function index(): void
{
    $this->exigirAutenticacao('bibliotecario');
    // ...
```

Na parte 5 voce protegeu **depois**, editando arquivo por arquivo. Aqui o CRUD
**ja nasceu protegido**. Por isso a regra do framework e: **instale o login
antes de gerar os cadastros**. (E, na parte 8, voce vai ver mais um custo de
proteger depois.)

### 6.2 Acrescente a pesquisa

**Faca:**

```bash
php console.php scaffold:pesquisa livros titulo autor disponivel
```

**Confira:**

```text
Pesquisa criada em /livros
  ~ controllers/LivrosController.php
  ~ views/livros/index.php

Campos pesquisaveis:
  titulo             texto         contem o trecho digitado (LIKE)
  autor              texto         contem o trecho digitado (LIKE)
  disponivel         Sim/Nao       valor exato

O formulario aparece acima da tabela em /livros e envia os campos
pela query string: /livros?titulo=...

Para desfazer: php console.php scaffold:pesquisa livros --remover
```

**Entenda:** este comando **nao cria** arquivos, ele **altera** dois que ja
existiam (`~`). Ele le no `banco/esquema.sql` o tipo de cada coluna e escolhe
o filtro certo: texto procura por trecho, `boolean` vira a lista Todos / Sim /
Nao.

### 6.3 Menu e rotulos

**Faca:** em `configuracoes/menu.php`, proteja o item novo tambem:

```php
['rota' => 'livros', 'texto' => 'Livros', 'auth' => 'sim'],
```

**Faca:** o gerador usou o nome da coluna como rotulo (`titulo`,
`disponivel`). Troque por texto de gente — `Titulo`, `Autor`, `Ano`,
`Disponivel` — nestes lugares:

| Arquivo | Onde |
|---|---|
| `views/livros/formulario.php` | nas tags `<label class="form-label" ...>` |
| `views/livros/index.php` | nos `<label>` da pesquisa e nos `<th>` da tabela |
| `views/livros/ver.php` | nas tags `<dt>` |

Exemplo em `formulario.php`:

```php
<label class="form-label" for="titulo">titulo</label>     <!-- antes  -->
<label class="form-label" for="titulo">Titulo</label>     <!-- depois -->
```

Mude **so o texto entre as tags**. O `for="titulo"`, o `name="titulo"` e o
`$registro['titulo']` sao o nome da coluna e precisam continuar iguais.

Aproveite e faca o mesmo em `views/bibliotecarios/index.php` e `ver.php`
(`nome` vira `Nome`).

### 6.4 Confira usando

**Faca:** abra <http://localhost:8000/livros> e cadastre tres livros:

| Titulo | Autor | Ano | Disponivel |
|---|---|---|---|
| Dom Casmurro | Machado de Assis | 1899 | marcado |
| Vidas Secas | Graciliano Ramos | 1938 | marcado |
| Quincas Borba | Machado de Assis | 1891 | **desmarcado** |

**Confira, um por um:**

1. **Validacao.** Clique em "Novo", preencha so o autor e salve. A tela volta
   com "O campo Titulo e obrigatorio." embaixo do campo, e o autor que voce
   digitou **continua la**.
2. **Pesquisa por trecho.** No campo Autor digite `machado` e pesquise. Aparecem
   os dois livros do Machado de Assis.
3. **Pesquisa por Sim/Nao.** Limpe o autor e escolha Disponivel = Nao. So
   aparece Quincas Borba.
4. **Pesquisa no endereco.** Olhe a barra do navegador:
   `/livros?titulo=&autor=machado&disponivel=`. A pesquisa fica no endereco e
   pode ser salva nos favoritos.
5. **Protecao.** Na janela anonima, abra `/livros`: vai para o login.

**Entenda a validacao:** a regra "titulo e obrigatorio" mora no **model**
(`modelos/Livro.php`), dentro de `validar()`:

```php
return (new Validador($dados))
    ->obrigatorio('titulo')
    ->maximo('titulo', 255)
    ->maximo('autor', 255)
    ->numerico('ano')
    ->erros();
```

O controller so pergunta ao model se os dados sao validos e, se nao forem,
devolve o formulario com as mensagens. Regra no model, controller so delega.

Na parte 7 voce vai escrever uma regra sua **dentro desse mesmo metodo**.

---

## Parte 7 — Uma regra de negocio: o ano do livro (15 min)

Ate aqui, toda validacao veio pronta do gerador. Agora voce escreve uma regra
**sua**: a regra que so quem conhece a biblioteca sabe que existe.

### 7.1 Veja o problema primeiro

**Faca:** em <http://localhost:8000/livros/criar>, tente dois cadastros:

1. titulo `Livro do futuro`, ano `2090`;
2. titulo `Livro sem ano`, com o ano **em branco**.

**Confira:**

1. o primeiro e **aceito** — a biblioteca agora tem um livro publicado daqui a
   decadas;
2. o segundo quebra com uma **tela de erro 500** que fala em
   `Incorrect integer value`. (Em alguns MySQL ele e gravado com ano `0`, sem
   erro nenhum — o que tambem esta errado.)

**Faca:** na listagem, exclua o "Livro do futuro" (e o "Livro sem ano", se ele
aparecer).

**Entenda os dois problemas:**

- **O ano no futuro** passou porque o gerador nao conhece a biblioteca. Para
  ele, `2090` e so um numero valido.
- **O ano em branco** chegou ao banco como texto vazio (`''`), e a coluna
  `ano` e `INT`: o MySQL recusou. A validacao gerada, `->numerico('ano')`, so
  confere se *o que foi digitado* e numero; campo vazio ela deixa passar.

Os dois tem a mesma solucao: **o model recusar esses dados antes de eles
chegarem ao banco**.

### 7.2 Onde a regra mora

A regra vai no **model**, dentro do metodo `validar()` de
`modelos/Livro.php`. Nao no controller, nao na view.

O motivo: o `LivrosController` ja chama o `validar()` em dois lugares — no
`salvar()`, quando cadastra, e no `atualizar()`, quando edita:

```php
$erros = $this->modelo->validar($dados);

if ($erros !== []) {
    $this->voltarComErros($erros, 'livros/criar');
}
```

Escrevendo a regra **uma vez** no model, ela vale no cadastro e na edicao ao
mesmo tempo. **Voce nao vai mexer no controller.**

### 7.3 Escreva a regra

**Faca:** abra `modelos/Livro.php`. Hoje o metodo `validar()` esta assim:

```php
public function validar(array $dados, int|string|null $ignorarId = null): array
{
    return (new Validador($dados))
        ->obrigatorio('titulo')
        ->maximo('titulo', 255)
        ->maximo('autor', 255)
        ->numerico('ano')
        ->erros();
}
```

Voce vai fazer **duas mudancas**, as duas em volta da linha
`->numerico('ano')`:

| # | O que acrescentar | Exatamente onde |
|---|---|---|
| 1 | `->obrigatorio('ano')` | na linha **de cima** de `->numerico('ano')` |
| 2 | o comentario e o bloco `->personalizada(...)` | na linha **de baixo** de `->numerico('ano')`, **antes** de `->erros();` |

Olhando as mudancas no lugar — as linhas com `+` sao as novas (**nao digite o
`+`**):

```diff
     public function validar(array $dados, int|string|null $ignorarId = null): array
     {
         return (new Validador($dados))
             ->obrigatorio('titulo')
             ->maximo('titulo', 255)
             ->maximo('autor', 255)
+            ->obrigatorio('ano')
             ->numerico('ano')
+            // Regra de negocio: nenhum livro foi publicado no futuro.
+            ->personalizada(
+                'ano',
+                (int) ($dados['ano'] ?? 0) <= (int) date('Y'),
+                'O ano nao pode ser maior que o ano atual.'
+            )
             ->erros();
     }
```

Depois das mudancas, o arquivo `modelos/Livro.php` **inteiro** fica assim. Se
preferir, apague tudo o que tem nele e cole isto:

```php
<?php

namespace Modelos;

use Nucleo\Model;
use Nucleo\Validador;

class Livro extends Model
{
    protected string $tabela = 'livros';
    protected array $preenchiveis = ['titulo', 'autor', 'ano', 'disponivel'];
    protected string $ordemPadrao = 'id DESC';

    /**
     * Regras de validacao do formulario.
     * Devolve um array vazio quando esta tudo certo.
     */
    public function validar(array $dados, int|string|null $ignorarId = null): array
    {
        return (new Validador($dados))
            ->obrigatorio('titulo')
            ->maximo('titulo', 255)
            ->maximo('autor', 255)
            ->obrigatorio('ano')
            ->numerico('ano')
            // Regra de negocio: nenhum livro foi publicado no futuro.
            ->personalizada(
                'ano',
                (int) ($dados['ano'] ?? 0) <= (int) date('Y'),
                'O ano nao pode ser maior que o ano atual.'
            )
            ->erros();
    }
}
```

Tres erros comuns na hora de digitar:

- colocar `;` no fim de `->obrigatorio('ano')` ou do `->personalizada(...)`.
  Essas linhas sao uma **corrente**: o unico `;` e o do `->erros();`, no fim;
- escrever a regra **depois** do `->erros();` — ai ela nunca roda;
- escrever a regra no controller. Ela vai no model.

### 7.4 Entenda cada pedaco

**A corrente de regras.** Cada `->regra(...)` confere uma coisa e passa
adiante. No fim, `->erros()` devolve a lista de problemas encontrados — vazia
quando esta tudo certo. Se um campo tiver mais de um problema, **so a primeira
mensagem** fica guardada. Por isso a ordem importa:

| Ano digitado | Primeira regra que reclama | Mensagem |
|---|---|---|
| em branco | `obrigatorio('ano')` | O campo Ano e obrigatorio. |
| `2090` | `personalizada(...)` | O ano nao pode ser maior que o ano atual. |
| `1899` | nenhuma | — o livro e salvo |

**O `personalizada()`** e a regra livre: voce mesmo escreve a condicao. Ele
recebe tres coisas:

```php
->personalizada(
    'ano',                                              // 1. em qual campo a mensagem aparece
    (int) ($dados['ano'] ?? 0) <= (int) date('Y'),      // 2. a condicao do que e VALIDO
    'O ano nao pode ser maior que o ano atual.'         // 3. a mensagem, se a condicao der false
)
```

> **Atencao ao item 2:** a condicao descreve o que esta **certo**, e nao o
> erro. "O ano e menor ou igual ao ano atual?" `true` = tudo certo; `false` =
> aparece a mensagem. Quem escreve `>` no lugar de `<=` inverte a regra e passa
> a recusar todos os livros de verdade.

**A condicao, pedaco por pedaco:**

| Pedaco | O que faz |
|---|---|
| `$dados['ano']` | o ano que veio do formulario |
| `?? 0` | se o campo nem veio, usa `0` |
| `(int) (...)` | transforma o texto `"1899"` no numero `1899`, para poder comparar |
| `<=` | "menor ou igual": o ano atual **pode** |
| `date('Y')` | o ano de hoje, pelo relogio do servidor (ex.: `"2026"`) |

Repare que nao existe `2026` escrito no codigo. Com `date('Y')`, a regra
continua certa no ano que vem sem ninguem mexer nela.

### 7.5 Confira na tela

**Faca:** em <http://localhost:8000/livros/criar>, tente:

| Titulo | Ano | O que deve acontecer |
|---|---|---|
| Livro sem ano | em branco | volta com "O campo Ano e obrigatorio." — sem tela de erro 500 |
| Livro do futuro | `2090` | volta com "O ano nao pode ser maior que o ano atual." |
| Livro de agora | o ano atual | e salvo |
| Memorias Postumas de Bras Cubas | `1881` | e salvo |

**Confira** tambem que, quando o formulario volta com a mensagem, **o titulo e
o autor que voce digitou continuam la**.

**Faca:** abra o "Dom Casmurro", clique em **Editar**, troque o ano para
`2090` e salve.

**Confira:** a edicao tambem e recusada, com a mesma mensagem. Voce nao tocou
no `atualizar()` do controller — a regra do model valeu sozinha.

Exclua o "Livro de agora" antes de seguir.

---

## Parte 8 — Conferir com os testes (20 min)

Clicar na tela prova que funciona **hoje**. Teste prova que **continua**
funcionando depois que alguem mexer no codigo.

### 8.1 Rode todos os testes

**Faca:**

```bash
php testes/executar.php
```

**Confira:** sete testes falham. O fim da saida e parecido com isto:

```text
--- Detalhes dos problemas ---

1) Controllers\AuthBibliotecarioControllerTest::testeRegistraEntraESai
   Nao esperava null
2) Controllers\AuthBibliotecarioControllerTest::testeRecusaCadastroInvalido
   Esperava true mas recebeu false
3) Controllers\AuthBibliotecarioControllerTest::testeRecusaEmailRepetido
   Esperava true mas recebeu false
4) Controllers\BibliotecariosControllerTest::testeExecutaRotasDoCrud
   Esperava 200 mas recebeu 302
5) Controllers\BibliotecariosControllerTest::testeRecusaDadosInvalidos
   Esperava true mas recebeu false
6) Controllers\BibliotecariosControllerTest::testeExclusaoNaoAceitaGet
   Esperava 404 mas recebeu 302
7) Controllers\BibliotecariosControllerTest::testeGeraRelatorioEmPdf
   Esperava 200 mas recebeu 302

----------------------------------------------------------
Testes: 84 | Passaram: 77 | Falharam: 7 | Erros: 0 | Assercoes: 212

ATENCAO: 7 teste(s) com problema.
```

**Calma: voce nao quebrou o sistema.**

**Entenda:** esses testes foram gerados nas partes 2 e 3, quando as rotas
**eram publicas**, e continuam esperando isso:

- os de `BibliotecariosControllerTest` esperam abrir a pagina (`200`), mas
  agora recebem `302` — o redirecionamento para o login;
- os de `AuthBibliotecarioControllerTest` tentam criar conta **deslogados**,
  e agora sao mandados para o login.

Voce mudou a regra **de proposito**. Entao o certo e atualizar os testes para
a regra nova. (Os testes de `livros` passaram todos: eles nasceram sabendo que
a rota era protegida. Esse e o outro custo de proteger depois.)

A ferramenta do conserto e uma linha que "finge" um login dentro do teste:

```php
Sessao::definir(Sessao::chaveAutenticacao('bibliotecario'), 1);
```

Traduzindo: "coloque na sessao que o bibliotecario de id 1 esta logado" — o
mesmo que o `login()` faz quando a senha confere.

### 8.2 Conserte os testes da lista de bibliotecarios

**Faca:** em `testes/controllers/BibliotecariosControllerTest.php`, no fim do
metodo `preparar()`:

```php
    $this->modelo = new Bibliotecario();

    // As rotas agora exigem login: o teste ja comeca logado.
    Sessao::definir(Sessao::chaveAutenticacao('bibliotecario'), 1);
}
```

O `use Nucleo\Sessao;` ja esta no topo do arquivo.

**Entenda:** o `preparar()` roda **antes de cada teste** da classe. Colocando
a linha ali, todos os testes deste arquivo comecam logados.

### 8.3 Conserte os testes do cadastro de contas

**Faca:** em `testes/controllers/AuthBibliotecarioControllerTest.php`,
acrescente a linha como **primeira linha** destes tres testes:

- `testeRegistraEntraESai`
- `testeRecusaCadastroInvalido`
- `testeRecusaEmailRepetido`

Assim:

```php
public function testeRecusaCadastroInvalido(): void
{
    Sessao::definir(Sessao::chaveAutenticacao('bibliotecario'), 1);

    $curta = $this->postar('auth-bibliotecario/registrar', [
    // ... o resto continua igual
```

**Faca:** em `testeRegistraEntraESai`, troque o destino esperado depois do
cadastro — lembra que voce mudou o redirecionamento no 5.3?

```php
$this->assertVerdadeiro($registrar->redirecionouPara('auth-bibliotecario/login'));  // antes
$this->assertVerdadeiro($registrar->redirecionouPara('bibliotecarios'));            // depois
```

> **Por que aqui nao vai no `preparar()`?** Porque dois testes deste arquivo —
> `testeRecusaSenhaErrada` e `testeRecusaLoginSemToken` — precisam comecar
> **deslogados**: eles provam que o login **falhou**. Se ja comecassem
> logados, nao provariam nada.

**Faca:**

```bash
php testes/executar.php Bibliotecario
```

**Confira:** `Testes: 12 | Passaram: 12 | Falharam: 0`.

### 8.4 Um teste que prova a trava

Os consertos fizeram os testes antigos passarem. Mas **nenhum** teste prova
ainda a coisa mais importante da aula: que um visitante nao cria conta de
bibliotecario.

**Faca:** em `testes/controllers/AuthBibliotecarioControllerTest.php`,
acrescente este metodo antes do `}` final da classe:

```php
public function testeVisitanteNaoCadastraBibliotecario(): void
{
    $resposta = $this->postar('auth-bibliotecario/registrar', [
        'nome'  => 'Intruso',
        'email' => 'intruso@example.com',
        'senha' => 'segredo123',
    ]);

    $this->assertVerdadeiro($resposta->redirecionouPara('auth-bibliotecario/login'));
    $this->assertIgual(0, $this->modelo->contar());
}
```

**Entenda, linha por linha:**

| Trecho | O que faz |
|---|---|
| `public function teste...` | todo metodo publico que comeca com `teste` roda sozinho |
| (sem `Sessao::definir`) | o `preparar()` chama `limparSessao()`: o teste comeca como **visitante** |
| `$this->postar(...)` | simula o envio do formulario, ja com o token CSRF |
| `redirecionouPara('auth-bibliotecario/login')` | o visitante foi mandado para o login |
| `assertIgual(0, ...->contar())` | e nenhuma conta foi criada no banco |

**Faca:**

```bash
php testes/executar.php Bibliotecario
```

**Confira:**

```text
Controllers\AuthBibliotecarioControllerTest
  PASSOU registra entra e sai (1,079.8ms)
  PASSOU recusa senha errada (728.9ms)
  PASSOU recusa cadastro invalido (16.5ms)
  PASSOU recusa email repetido (367.7ms)
  PASSOU recusa login sem token (368.7ms)
  PASSOU visitante nao cadastra bibliotecario (14.5ms)

Controllers\BibliotecariosControllerTest
  PASSOU executa rotas do crud (14.5ms)
  PASSOU recusa dados invalidos (10.8ms)
  PASSOU recusa formulario sem token (27.9ms)
  PASSOU exclusao nao aceita get (11.3ms)
  PASSOU gera relatorio em pdf (14.8ms)

Modelos\BibliotecarioTest
  PASSOU executa crud completo (12.8ms)
  PASSOU valida os campos obrigatorios (6.4ms)

----------------------------------------------------------
Testes: 13 | Passaram: 13 | Falharam: 0 | Erros: 0 | Assercoes: 54

TUDO CERTO! O sistema esta funcionando.
```

Os tempos mudam de uma maquina para outra.

**Prove que o teste presta.** Um teste que nunca falha nao vigia nada. Em
`AuthBibliotecarioController.php`, **comente** a linha da trava:

```php
// $this->exigirAutenticacao('bibliotecario');
```

Rode de novo: `visitante nao cadastra bibliotecario` **falha**. Agora
**descomente** a linha e rode mais uma vez: volta a passar. Se um dia alguem
apagar a trava sem querer, esse teste avisa.

### 8.5 Dois testes para a regra do ano

A regra da parte 7 funciona na tela. Agora ela ganha testes, para ninguem
estraga-la sem perceber.

**Faca:** abra `testes/modelos/LivroTest.php`. Ele foi gerado na parte 6 e
termina assim:

```php
    public function testeValidaOsCamposObrigatorios(): void
    {
        $dados = [
            'titulo' => '',
            'autor' => 'Teste',
            'ano' => 1,
            'disponivel' => 1,
        ];

        $erros = $this->modelo->validar($dados);

        $this->assertNaoVazio($erros);
        $this->assertTemChave('titulo', $erros);
    }
}
```

Cole os dois metodos novos **depois do `}` que fecha o
`testeValidaOsCamposObrigatorios()`** e **antes do `}` final**, que fecha a
classe. As linhas com `+` sao as novas (**nao digite o `+`**):

```diff
         $this->assertNaoVazio($erros);
         $this->assertTemChave('titulo', $erros);
     }
+
+    public function testeRecusaAnoNoFuturo(): void
+    {
+        $anoQueVem = (int) date('Y') + 1;
+
+        $erros = $this->modelo->validar([
+            'titulo' => 'Livro do futuro',
+            'ano'    => $anoQueVem,
+        ]);
+
+        $this->assertTemChave('ano', $erros);
+    }
+
+    public function testeAceitaOAnoAtual(): void
+    {
+        $erros = $this->modelo->validar([
+            'titulo' => 'Livro de agora',
+            'ano'    => date('Y'),
+        ]);
+
+        $this->assertVazio($erros);
+    }
 }
```

O fim do arquivo fica assim:

```php
        $this->assertNaoVazio($erros);
        $this->assertTemChave('titulo', $erros);
    }

    public function testeRecusaAnoNoFuturo(): void
    {
        $anoQueVem = (int) date('Y') + 1;

        $erros = $this->modelo->validar([
            'titulo' => 'Livro do futuro',
            'ano'    => $anoQueVem,
        ]);

        $this->assertTemChave('ano', $erros);
    }

    public function testeAceitaOAnoAtual(): void
    {
        $erros = $this->modelo->validar([
            'titulo' => 'Livro de agora',
            'ano'    => date('Y'),
        ]);

        $this->assertVazio($erros);
    }
}
```

**Entenda:**

| Trecho | O que faz |
|---|---|
| `$this->modelo->validar([...])` | chama a regra direto no model, sem navegador e sem banco |
| `(int) date('Y') + 1` | o ano que vem. Nao use `2090` fixo: o teste tem que estar certo em qualquer ano |
| `assertTemChave('ano', $erros)` | "tem que existir um erro no campo `ano`" |
| `assertVazio($erros)` | "nao pode existir erro nenhum" |

**O segundo teste e o mais esperto.** O ano atual e o limite da regra: e
exatamente ai que a pessoa troca `<=` por `<` sem querer. Um teste com `1899`
nunca perceberia esse erro; um teste com o ano atual percebe.

**Faca:**

```bash
php testes/executar.php Livro
```

**Confira:**

```text
Controllers\LivrosControllerTest
  PASSOU executa rotas do crud (68.6ms)
  PASSOU recusa dados invalidos (19.6ms)
  PASSOU recusa formulario sem token (11.4ms)
  PASSOU exclusao nao aceita get (10.7ms)
  PASSOU gera relatorio em pdf (23.4ms)
  PASSOU exige login nas rotas (9.2ms)

Modelos\LivroTest
  PASSOU executa crud completo (20.5ms)
  PASSOU valida os campos obrigatorios (6.9ms)
  PASSOU recusa ano no futuro (9.5ms)
  PASSOU aceita o ano atual (6.9ms)

----------------------------------------------------------
Testes: 10 | Passaram: 10 | Falharam: 0 | Erros: 0 | Assercoes: 37

TUDO CERTO! O sistema esta funcionando.
```

**Prove que o teste presta.** Em `modelos/Livro.php`, troque o `<=` da regra
por `<`. Rode `php testes/executar.php Livro`: **aceita o ano atual** falha.
Volte o `<=` e rode de novo: tudo passa.

### 8.6 Todos os testes

**Faca:**

```bash
php testes/executar.php
```

**Confira:**

```text
Testes: 87 | Passaram: 87 | Falharam: 0 | Erros: 0 | Assercoes: 245

TUDO CERTO! O sistema esta funcionando.
```

---

## Parte 9 — Arrumacao final (10 min)

**1. Nome do sistema.** Em `configuracoes/app.php`:

```php
'nome' => 'Biblioteca da Escola',
```

**2. Pagina inicial.** Troque todo o conteudo de `views/home/index.php` por:

```php
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h3">Biblioteca da Escola</h1>
        <p class="text-secondary">Controle do acervo feito pela equipe da biblioteca.</p>

        <?php if (autenticado('bibliotecario')): ?>
            <a class="btn btn-primary" href="<?= url('livros') ?>">Ver os livros</a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= url('auth-bibliotecario/login') ?>">Entrar</a>
        <?php endif ?>
    </div>
</div>
```

**Entenda:** `autenticado('bibliotecario')` e a mesma pergunta do
`exigirAutenticacao()`, so que sem redirecionar: devolve `true` ou `false`, e
a view escolhe o botao.

**3. Atualize o teste da pagina inicial.** Rode os testes:

```bash
php testes/executar.php
```

Um teste do proprio framework falha:

```text
1) Nucleo\RoteamentoTest::testeRaizAbreAPaginaInicial
   Esperava encontrar "Bem-vindo ao framework MVC" no texto
```

E a mesma historia da parte 8: o teste confere se a pagina inicial mostra o
texto antigo, e voce trocou esse texto de proposito.

**Faca:** em `testes/nucleo/RoteamentoTest.php`, no metodo
`testeRaizAbreAPaginaInicial()`, troque so o texto procurado:

```php
$this->assertContem('Bem-vindo ao framework MVC', $resposta->html);   // antes
$this->assertContem('Biblioteca da Escola', $resposta->html);         // depois
```

**Confira:** `php testes/executar.php` volta a terminar com
`Testes: 87 | Passaram: 87 | Falharam: 0` e `TUDO CERTO!`.

**4. Olhe tudo uma ultima vez.** Navegue por todas as telas logado e
deslogado, procurando rotulo cru e aviso de PHP na tela.

---

## Checklist de saida

Marque antes de chamar o professor:

- [ ] `php testes/executar.php` termina com `TUDO CERTO!` (87 testes), ja
      com a pagina inicial nova
- [ ] No phpMyAdmin, a senha da Maria e a do Joao comecam com `$2y$`
- [ ] Entro com `admin@biblioteca.com` / `biblioteca123`, e senha errada e
      recusada
- [ ] Na janela anonima, `/bibliotecarios`, `/livros` e
      `/auth-bibliotecario/registrar` levam ao login
- [ ] A tela de login **nao** tem o link "Criar uma conta"
- [ ] Logado, cadastro um bibliotecario pelo botao "Novo bibliotecario" e ele
      consegue entrar
- [ ] Deslogado, o menu mostra so "Inicio" e "Entrar"; logado, mostra
      "Bibliotecarios", "Livros" e "Sair"
- [ ] Salvar um livro sem titulo mostra a mensagem e nao perde o que foi
      digitado
- [ ] Livro com ano em branco mostra "O campo Ano e obrigatorio." (e nao a
      tela de erro 500)
- [ ] Livro com ano `2090` e recusado, no cadastro **e** na edicao; o ano
      atual e aceito
- [ ] Pesquisar autor `machado` traz os dois livros do Machado de Assis
- [ ] Sei explicar, com as minhas palavras: por que o administrador foi
      inserido no banco, por que a senha e um hash, por que a trava do
      `registrar()` nao vai no construtor e por que a regra do ano fica no
      model e nao no controller

---

## Desafios

Para quem terminou antes. Sem passo a passo — use a
[Referencia de comandos](Referencia-Comandos.md) e o
[Tutorial](Tutorial-Comandos.md).

**D1 — Ola, Maria.** Na pagina inicial, mostre "Ola, Maria Souza" para quem
esta logado. Dica: no `HomeController::index()`, pegue o id com
`usuario_id('bibliotecario')`, busque o registro com
`(new \Modelos\Bibliotecario())->buscar($id)` e mande o nome para a view. Nao
consulte o banco de dentro da view.

**D2 — O administrador nao se exclui.** Hoje a Maria pode excluir a si mesma
na lista e ficar trancada para fora. No `excluir()` do
`BibliotecariosController`, recuse quando o `$id` for o de quem esta logado,
com uma mensagem de erro. Escreva um teste para isso.

**D3 — Ano antigo demais.** A imprensa de Gutenberg e de 1450: nao faz sentido
um livro do acervo com ano menor que isso. Acrescente essa regra ao lado da
regra da parte 7, com a mensagem propria e um teste para ela.

**D4 — Relatorio.** A rota `/livros/relatorio` ja existe desde a parte 6 e a
listagem tem o link. Abra, filtre por autor e explique por que, na janela
anonima, o PDF **nao** abre.

**D5 — Trocar a propria senha.** Crie uma tela onde o bibliotecario logado
troca a propria senha. Dica: o trait ja tem `trocarSenha($id, $senha)`; nao
esqueca o `campo_csrf()` no formulario e o `exigirFormularioValido()` no
controller.

---

## Socorro rapido

| O que aparece | O que fazer |
|---|---|
| `[ERRO]` ao rodar `php instalar.php` | MySQL desligado, ou usuario/senha errados em `configuracoes/banco.php` |
| o phpMyAdmin nao abre | ligue o **Apache** no painel do XAMPP |
| a pagina abre sem estilo | o servidor foi iniciado sem o roteador: `php -S localhost:8000 roteador.php` |
| "E-mail ou senha invalidos." com os dados certos da Maria | o hash foi colado errado (pedaco faltando, espaco sobrando). Apague a linha da Maria no phpMyAdmin e repita 4.2 e 4.3 |
| erro `#1062` (entrada duplicada) ao inserir a Maria | ela ja foi inserida: o e-mail e unico. Nao insira de novo |
| erro `#1146` (tabela nao existe) ao inserir a Maria | voce esta em outro banco no phpMyAdmin, ou pulou a parte 2 |
| a tela de login fica pedindo login sem parar | voce colocou `exigirAutenticacao()` no **construtor** do `AuthBibliotecarioController`. Tire de la e deixe so dentro do `registrar()` |
| o bibliotecario criado em "Novo registro" nao consegue entrar | ele foi criado sem e-mail e senha. Faca o ajuste 5.4 c e cadastre de novo pela tela `registrar` |
| `--auth=bibliotecario` da erro ao gerar livros | o login ainda nao foi instalado: faca a parte 3 antes |
| `Esperava 200 mas recebeu 302` nos testes | teste antigo esperando rota publica: veja a parte 8 |
| erro 500 com `Incorrect integer value` ao salvar livro | o ano ficou em branco e ainda falta o `->obrigatorio('ano')`: veja a parte 7.3 |
| a regra do ano nao funciona | a regra foi escrita no controller ou fora do `validar()`. Ela vai **dentro** do `validar()` de `modelos/Livro.php`, antes do `->erros();` |
| `Esperava encontrar "Bem-vindo ao framework MVC"` nos testes | voce trocou a pagina inicial e o teste antigo ainda procura o texto velho: veja a parte 9 |
| erro de tabela inexistente so nos testes | o banco de testes e recriado a cada execucao; confira o MySQL ligado e o `banco_testes` em `configuracoes/banco.php` |
