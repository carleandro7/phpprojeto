# Atividade guiada — Biblioteca (versao simples)

Atividade de cerca de **2h30**, feita em sala, individualmente.

Voce vai construir um sistema pequeno para a biblioteca da escola:

- o **bibliotecario** e o administrador: so ele entra no sistema e so ele
  mexe no acervo;
- o cadastro dele fica **guardado no banco de dados**, com a senha protegida;
- depois de entrar, ele cadastra, pesquisa, edita e exclui livros;
- o sistema recusa livro sem ano ou com ano no futuro — uma regra que **voce**
  escreve.

> Esta e a versao curta. A *Atividade de laboratorio — Biblioteca da escola*
> (`Atividade-Laboratorio-Biblioteca.md`) vai bem alem: categorias, mais regras
> de negocio, testes escritos do zero e Git. Faca esta primeiro.

---

## Como ler esta atividade

Cada acao comeca com uma **etiqueta** que diz **onde** voce faz aquilo:

| Etiqueta | Onde voce faz | O que vem embaixo |
|---|---|---|
| **TERMINAL 1** | no terminal de **comandos** | um comando para digitar e apertar Enter |
| **TERMINAL 2** | no terminal que so roda o **servidor** | o comando do servidor (so na parte 1) |
| **CODIGO** | no **editor** (VS Code), dentro do arquivo indicado | codigo PHP para escrever no arquivo |
| **NAVEGADOR** | no Chrome ou Firefox | um endereco para abrir e o que clicar |

E duas marcas que nao pedem acao:

| Marca | O que significa |
|---|---|
| **Confira** | o que deve aparecer. Se aparecer outra coisa, **pare** e veja o [Socorro rapido](#socorro-rapido) |
| **Entenda** | por que funciona assim. Nao pule: o aprendizado esta aqui |

Quatro regras para nao se confundir:

1. **Comando vai no terminal. Codigo vai no arquivo.** Nunca cole codigo PHP no
   terminal, nem comando dentro de um arquivo.
2. **Um comando por vez.** Digite (ou cole) a linha e aperte Enter. Espere
   terminar antes do proximo.
3. **Nao digite a saida.** Os blocos depois de **Confira** mostram o que o
   terminal **responde**.
4. **Nos blocos de mudanca** (os que tem `+` e `-` no comeco das linhas):
   - linha com `+` e linha que **entra** no arquivo;
   - linha com `-` e linha que **sai** do arquivo;
   - linha sem sinal ja existe: ela esta ali so para mostrar **onde** mexer;
   - **nao digite** o `+` nem o `-`.

Exemplo de bloco de mudanca:

```diff
     public function __construct()
     {
+        $this->exigirAutenticacao('bibliotecario');
+
         $this->modelo = new Bibliotecario();
     }
```

Leia assim: "dentro do `__construct()`, logo acima de
`$this->modelo = new Bibliotecario();`, acrescente a linha
`$this->exigirAutenticacao('bibliotecario');` e uma linha em branco".

Todos os comandos estao reunidos no [Apendice A](#apendice-a--todos-os-comandos-do-terminal)
e todo o codigo, arquivo por arquivo, no
[Apendice B](#apendice-b--todo-o-codigo-que-voce-escreve).

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
**login, administrador e uma regra de negocio**.

## Roteiro

| Parte | O que fica pronto | Tempo |
|---|---|---|
| 1 | Projeto copiado, banco criado, servidor no ar | 15 min |
| 2 | Tabela dos bibliotecarios | 10 min |
| 3 | Tela de login do bibliotecario | 10 min |
| 4 | O primeiro bibliotecario (o administrador) cadastrado no banco | 20 min |
| 5 | Portas trancadas: so o bibliotecario entra | 20 min |
| 6 | Cadastro de livros com pesquisa | 20 min |
| 7 | Uma regra de negocio: o ano do livro | 15 min |
| 8 | Testes conferindo tudo | 20 min |
| 9 | Arrumacao final | 10 min |

---

## Parte 1 — Preparar o projeto (15 min)

### 1.1 Ligue o XAMPP

Abra o painel do XAMPP e clique em **Start** no **MySQL** e no **Apache**.

**Entenda:** o MySQL guarda os dados. O Apache so e necessario para abrir o
phpMyAdmin — o sistema em si roda no servidor do proprio PHP.

### 1.2 Faca uma copia do framework

Copie a pasta do framework e de o nome **`minha-biblioteca`** a copia. O jeito
mais facil e pelo explorador de arquivos: copiar, colar e renomear.

> No Mac ou Linux, se preferir o terminal, o mesmo resultado sai com
> `cp -R framework minha-biblioteca`, rodado na pasta onde esta o framework.

**Entenda:** trabalhando numa copia, o framework original fica limpo para as
proximas atividades.

### 1.3 Abra o projeto e os dois terminais

1. No VS Code: **Arquivo > Abrir pasta...** e escolha **`minha-biblioteca`**.
2. Abra um terminal: **Terminal > Novo terminal**. Este e o **TERMINAL 1**.
3. Abra um segundo: clique no **+** do painel do terminal. Este e o
   **TERMINAL 2**.

Como a pasta aberta e a `minha-biblioteca`, os dois terminais ja comecam
dentro dela.

**TERMINAL 1**

```bash
php -v
```

**Confira:** aparece `PHP 8.1` ou maior (`8.2`, `8.3`...).

Apareceu "php nao e reconhecido"? Veja o [Socorro rapido](#socorro-rapido).

**Entenda os dois terminais:**

| Terminal | Para que | Durante a aula |
|---|---|---|
| TERMINAL 1 | todos os comandos (`php console.php ...`, `php testes/...`) | voce usa o tempo todo |
| TERMINAL 2 | so o servidor (`php -S ...`) | fica rodando; nao digite mais nada nele |

### 1.4 Crie um banco so para esta atividade

**CODIGO** · `configuracoes/banco.php` · troque os dois nomes de banco

```diff
-        'banco'        => 'framework_aula',
-        'banco_testes' => 'framework_aula_testes',
+        'banco'        => 'minha_biblioteca',
+        'banco_testes' => 'minha_biblioteca_testes',
```

Salve o arquivo (**Ctrl+S**).

**TERMINAL 1**

```bash
php instalar.php
```

**Confira:** as primeiras linhas da resposta sao estas:

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

### 1.5 Suba o servidor

**TERMINAL 2**

```bash
php -S localhost:8000 roteador.php
```

**Confira:** o terminal mostra `Development Server (http://localhost:8000)
started` e **fica parado assim**. Esta certo: ele esta esperando o navegador.
Deixe-o rodando ate o fim da aula.

**NAVEGADOR** · <http://localhost:8000>

**Confira:** a pagina inicial do framework aparece.

---

## Parte 2 — A tabela dos bibliotecarios (10 min)

### 2.1 Por que comecar por aqui

Para alguem fazer login, o sistema precisa de um lugar onde guardar **quem
pode entrar**. Esse lugar e a tabela `bibliotecarios`.

A ordem e: primeiro a tabela existe (parte 2), depois ela ganha a capacidade
de fazer login (parte 3), depois recebe o primeiro bibliotecario (parte 4).

### 2.2 Gere o cadastro

**TERMINAL 1**

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
| `php console.php` | "PHP, rode o console do framework" |
| `scaffold:crud` | "gere um cadastro completo": criar, listar, ver, editar e excluir |
| `bibliotecarios` | o nome da tabela, no plural |
| `nome:string` | uma coluna chamada `nome`, do tipo texto curto |

**Entenda o que foi gerado.** `+` e arquivo criado; `~` e arquivo alterado.
Voce ainda nao escreveu nenhuma linha: tudo isso veio do comando. Cada arquivo
tem um papel no MVC:

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

**NAVEGADOR** · <http://localhost:8000/bibliotecarios>

**Confira:** a lista aparece vazia.

> **Nao cadastre ninguem por essa tela.** O formulario dela so tem o campo
> `nome`: um bibliotecario criado ali fica sem e-mail e sem senha, e nunca
> vai conseguir entrar.

---

## Parte 3 — O login do bibliotecario (10 min)

### 3.1 Instale o login na tabela

**TERMINAL 1**

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

As linhas de "Para exigir esse login" sao **dicas** do console. **Nao rode
nada delas agora**: voce vai usar as duas nas partes 5 e 6.

### 3.2 Entenda o que mudou

**No banco**, a tabela `bibliotecarios` ganhou duas colunas:

| Coluna | Para que serve |
|---|---|
| `id` | numero do registro, criado sozinho |
| `nome` | o nome da pessoa (parte 2) |
| `email` | **nova** — o que se digita para entrar. Tem indice `UNIQUE`: nao existem dois bibliotecarios com o mesmo e-mail |
| `senha` | **nova** — guarda a senha, sempre como hash (parte 4) |

**No model**, `modelos/Bibliotecario.php` ganhou uma linha e duas colunas
permitidas (o console fez isso, voce nao precisa digitar):

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
| `buscarPorEmail($email)` | procura um bibliotecario pelo e-mail |
| `criarComSenha($dados, $senha)` | cria um bibliotecario ja com a senha em hash |

**Nas rotas**, o prefixo `auth-bibliotecario` saiu do nome do model. Por isso
o menu mostra "Entrar (bibliotecario)".

### 3.3 Olhe a tela

**NAVEGADOR** · <http://localhost:8000/auth-bibliotecario/login>

Tente entrar com qualquer e-mail e senha.

**Confira:**

- a tela **nao tem o menu lateral** — quem ainda nao entrou nao precisa ver
  a lista de telas do sistema;
- aparece "E-mail ou senha invalidos." — claro: a tabela ainda esta vazia,
  **ninguem** consegue entrar. A parte 4 resolve isso.

---

## Parte 4 — O primeiro bibliotecario: o administrador (20 min)

O login existe, mas a tabela `bibliotecarios` esta vazia. Precisamos cadastrar
o **primeiro** bibliotecario: a **Maria**, que sera a administradora.

Os dados dela:

| Campo | Valor |
|---|---|
| Nome | `Maria Souza` |
| E-mail | `admin@biblioteca.com` |
| Senha | `biblioteca123` |

> Na aula, use exatamente esses dados. Num sistema de verdade a senha seria
> outra, e so o administrador saberia.

### 4.1 Por que nao pela tela "Criar uma conta"?

A tela de login tem um link "Criar uma conta". Ela funcionaria para a Maria —
mas funcionaria **para qualquer um**: um aluno qualquer criaria uma conta e
viraria administrador da biblioteca.

Por isso, na parte 5, essa tela vai ser **trancada**: so quem ja esta logado
podera usa-la. E ai surge o problema do ovo e da galinha: se so quem esta
logado cadastra bibliotecarios, **quem cadastra o primeiro?**

Resposta: **voce**, por fora do site. Voce vai escrever um arquivo PHP pequeno
que grava a Maria direto no banco, e roda-lo uma vez pelo terminal.

O caminho inteiro tem tres passos:

```text
PASSO 1   CODIGO       crie o arquivo  banco/criar-administrador.php
PASSO 2   TERMINAL 1   rode:           php banco/criar-administrador.php
PASSO 3   NAVEGADOR    veja a Maria no phpMyAdmin e entre no sistema
```

### 4.2 Passo 1 — Crie o arquivo

**CODIGO** · arquivo **novo** `banco/criar-administrador.php`

Para criar o arquivo no VS Code:

1. Na lista de arquivos da esquerda, clique com o **botao direito** na pasta
   **`banco`**.
2. Escolha **Novo arquivo**.
3. Digite o nome **`criar-administrador.php`** e aperte Enter.
4. Confira que ele apareceu **dentro** da pasta `banco`, ao lado do
   `esquema.sql`.

Escreva no arquivo **todo** o codigo abaixo e salve (**Ctrl+S**):

```php
<?php

/**
 * Cria o primeiro bibliotecario: o administrador do sistema.
 *
 * Rode no terminal, dentro da pasta do projeto:
 *     php banco/criar-administrador.php
 */

require_once __DIR__ . '/../nucleo/bootstrap.php';

use Modelos\Bibliotecario;

// 1. Os dados do administrador.
$nome  = 'Maria Souza';
$email = 'admin@biblioteca.com';
$senha = 'biblioteca123';

$bibliotecarios = new Bibliotecario();

// 2. Se ja existe alguem com esse e-mail, nao cadastra de novo.
if ($bibliotecarios->buscarPorEmail($email) !== null) {
    echo "O administrador {$email} ja esta cadastrado. Nada foi feito." . PHP_EOL;
    exit;
}

// 3. Grava no banco. O criarComSenha() transforma a senha em hash antes.
$id = $bibliotecarios->criarComSenha(['nome' => $nome, 'email' => $email], $senha);

echo "Administrador cadastrado com o id {$id}." . PHP_EOL;
echo "Entre em http://localhost:8000/auth-bibliotecario/login com {$email}" . PHP_EOL;
```

**Entenda, pedaco por pedaco:**

| Trecho | O que faz |
|---|---|
| `require_once __DIR__ . '/../nucleo/bootstrap.php';` | liga o framework: configuracoes, banco e models. `__DIR__` e a pasta deste arquivo (`banco`); `/../` sobe um nivel, para a raiz do projeto |
| `use Modelos\Bibliotecario;` | avisa que vamos usar o model criado nas partes 2 e 3 |
| `$nome`, `$email`, `$senha` | os dados da Maria. Para outro administrador, e so trocar aqui |
| `new Bibliotecario()` | o model: e ele que sabe falar com a tabela `bibliotecarios` |
| `buscarPorEmail($email) !== null` | "ja existe alguem com esse e-mail?" Se sim, avisa e para (`exit`). Rodar duas vezes nao cria duas Marias |
| `criarComSenha([...], $senha)` | grava a linha no banco — com a senha **em hash** — e devolve o `id` |
| `echo "..." . PHP_EOL;` | escreve uma linha no terminal. `PHP_EOL` e a quebra de linha |

**Entenda por que o arquivo fica em `banco/`:** o framework **bloqueia** a
pasta `banco/` para o navegador. Se o arquivo ficasse solto na raiz do projeto,
um visitante poderia roda-lo pelo site. Voce vai conferir isso no passo 4.4.

### 4.3 Passo 2 — Rode o arquivo no terminal

**TERMINAL 1**

```bash
php banco/criar-administrador.php
```

**Confira:**

```text
Administrador cadastrado com o id 1.
Entre em http://localhost:8000/auth-bibliotecario/login com admin@biblioteca.com
```

Agora rode **o mesmo comando mais uma vez**:

**TERMINAL 1**

```bash
php banco/criar-administrador.php
```

**Confira:**

```text
O administrador admin@biblioteca.com ja esta cadastrado. Nada foi feito.
```

**Entenda:** o comando `php arquivo.php` roda o arquivo **uma vez** e termina.
Ele nao e uma pagina do site: nao passa pelo servidor do TERMINAL 2, so
precisa do MySQL ligado. Na segunda vez, o `buscarPorEmail()` achou a Maria e
o arquivo parou sem gravar nada.

### 4.4 Passo 3a — Veja a Maria no banco

**NAVEGADOR** · <http://localhost/phpmyadmin>

1. Na coluna da esquerda, clique no banco **`minha_biblioteca`**.
2. Clique na tabela **`bibliotecarios`**.
3. Abra a aba **Visualizar**.

**Confira:** existe **uma** linha, e a senha **nao aparece legivel**:

| id | nome | email | senha |
|---|---|---|---|
| 1 | Maria Souza | admin@biblioteca.com | `$2y$12$...` (60 caracteres) |

Se um dia voce vir `biblioteca123` escrito nessa coluna, alguem quebrou o
sistema.

**Entenda o hash:** pense numa impressao digital. Da pessoa se tira a digital,
mas olhando a digital ninguem reconstroi a pessoa. O hash e a digital da
senha:

- da senha se calcula o hash — foi o que o `criarComSenha()` fez;
- do hash **nao** se volta para a senha;
- no login, o PHP calcula de novo a partir do que foi digitado e compara.

E exatamente isso que o login faz. Em `nucleo/Autenticavel.php`, a ultima
linha do metodo `autenticar()` e:

```php
return password_verify($senha, (string) $registro['senha']) ? $registro : null;
```

Se alguem roubar a tabela `bibliotecarios`, leva as digitais — e nao as
senhas.

**NAVEGADOR** · <http://localhost:8000/banco/criar-administrador.php>

**Confira:** aparece so "Acesso negado.". O arquivo nao roda pelo site.

### 4.5 Passo 3b — Entre como administradora

**NAVEGADOR** · <http://localhost:8000/auth-bibliotecario/login>

Entre com `admin@biblioteca.com` e `biblioteca123`.

**Confira:**

- voce vai para a pagina inicial com a mensagem **"Bem-vindo!"**;
- no menu, em "Conta", aparece **"Sair (bibliotecario)"**.

Agora clique em **"Sair"** e tente entrar com a senha **errada**
`biblioteca000`.

**Confira:** "E-mail ou senha invalidos." Repare que a mensagem nao diz se o
erro foi no e-mail ou na senha. E de proposito: dizer "esse e-mail existe"
ajudaria quem esta tentando adivinhar contas.

**Entre de novo com a senha certa** antes de seguir.

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
> outro computador, o banco de la comeca vazio. Rode, no TERMINAL 1 de la,
> `php instalar.php` e depois `php banco/criar-administrador.php`.

> **Prefere cadastrar a Maria digitando SQL no phpMyAdmin?** O
> [Apendice C](#apendice-c--outro-jeito-cadastrar-a-maria-pelo-phpmyadmin)
> mostra esse caminho. Use **um** dos dois, nao os dois.

---

## Parte 5 — Trancar as portas (20 min)

### 5.1 O teste do intruso

**NAVEGADOR** · abra uma **janela anonima** (`Ctrl+Shift+N` no Chrome,
`Ctrl+Shift+P` no Firefox). Nela voce **nao** esta logado. Visite:

- <http://localhost:8000/bibliotecarios>
- <http://localhost:8000/auth-bibliotecario/registrar>

**Confira:** as duas abrem. Ou seja:

1. qualquer visitante ve, edita e exclui a equipe da biblioteca;
2. qualquer visitante cria uma conta de bibliotecario — e vira administrador.

Duas portas abertas. **Deixe a janela anonima aberta**; voce volta nela no
5.6.

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

**CODIGO** · `controllers/BibliotecariosController.php` · dentro do
`__construct()`

```diff
     public function __construct()
     {
+        $this->exigirAutenticacao('bibliotecario');
+
         $this->modelo = new Bibliotecario();
     }
```

**Entenda:** o construtor roda **antes de qualquer acao** do controller. Uma
linha ali protege todas de uma vez: `index`, `criar`, `salvar`, `ver`,
`editar`, `atualizar`, `excluir` e `relatorio`.

### 5.3 Porta 2: o cadastro de contas

Sao **duas mudancas no mesmo metodo**.

**CODIGO** · `controllers/AuthBibliotecarioController.php` · no **comeco** do
metodo `registrar()`

```diff
     /** GET e POST /auth-bibliotecario/registrar */
     public function registrar(): void
     {
+        // So um bibliotecario que ja entrou pode cadastrar outro.
+        $this->exigirAutenticacao('bibliotecario');
+
         if ($this->ehPost()) {
             $this->exigirTokenValido();
```

> **Atencao: aqui NAO e no construtor.** Este controller tambem cuida do
> `login()`. Se a linha fosse para o construtor, a propria tela de login
> exigiria login — e ninguem nunca mais entraria. Por isso ela vai **so** no
> metodo que deve ser fechado.

**CODIGO** · `controllers/AuthBibliotecarioController.php` · no **fim** do
mesmo `registrar()`, logo depois de `criarComSenha(...)`

```diff
             $this->modelo->criarComSenha($dados, $senha);

-            $this->mensagem('sucesso', 'Conta criada. Agora entre com seus dados.');
-            $this->redirecionar('auth-bibliotecario/login');
+            $this->mensagem('sucesso', 'Bibliotecario cadastrado.');
+            $this->redirecionar('bibliotecarios');
         }
```

**Entenda:** antes, quem usava essa tela era um visitante criando a propria
conta, e fazia sentido manda-lo para o login. Agora quem usa e o
administrador, **que ja esta logado**: o natural e voltar para a lista da
equipe.

### 5.4 Tire os links que nao fazem mais sentido

Tres ajustes pequenos, um em cada view.

**CODIGO** · `views/auth/bibliotecario/login.php` · no **fim** do arquivo,
apague o link "Criar uma conta"

```diff
     <button class="btn btn-primary w-100" type="submit">Entrar</button>
 </form>
-
-<p class="text-center mt-4 mb-0">
-    <a href="<?= url('auth-bibliotecario/registrar') ?>">Criar uma conta</a>
-</p>
```

**CODIGO** · `views/auth/bibliotecario/registrar.php` · no **fim** do arquivo,
troque o link

```diff
 <p class="text-center mt-4 mb-0">
-    <a href="<?= url('auth-bibliotecario/login') ?>">Ja tenho uma conta</a>
+    <a href="<?= url('bibliotecarios') ?>">Voltar para a lista</a>
 </p>
```

**CODIGO** · `views/bibliotecarios/index.php` · no **topo** do arquivo, troque
o botao "Novo registro"

```diff
         <a class="btn btn-outline-secondary" href="<?= url('bibliotecarios/relatorio') ?>">Relatorio PDF</a>
-        <a class="btn btn-primary" href="<?= url('bibliotecarios/criar') ?>">Novo registro</a>
+        <a class="btn btn-primary" href="<?= url('auth-bibliotecario/registrar') ?>">Novo bibliotecario</a>
```

**Entenda o ultimo ajuste:** lembra do aviso da parte 2? O formulario de
`/bibliotecarios/criar` so pede o `nome`. A tela `registrar` pede nome,
e-mail e senha, e grava a senha em hash com `criarComSenha()`. E a unica que
cria um bibliotecario capaz de entrar.

### 5.5 Esconda o item do menu

**CODIGO** · `configuracoes/menu.php` · na linha de Bibliotecarios

```diff
     ['rota' => '', 'texto' => 'Inicio'],
-    ['rota' => 'bibliotecarios', 'texto' => 'Bibliotecarios'],
+    ['rota' => 'bibliotecarios', 'texto' => 'Bibliotecarios', 'auth' => 'sim'],
     // scaffold:crud
```

**Entenda:** `'auth' => 'sim'` mostra o item so para quem esta logado.

> **Esconder o link nao protege nada.** Quem souber o endereco digita e
> entra. Quem protege de verdade e o `exigirAutenticacao()` do controller; o
> menu so evita mostrar um botao que nao vai funcionar.

### 5.6 Confira: o teste do intruso, de novo

**NAVEGADOR** · na **janela anonima**, visite os mesmos enderecos

**Confira:**

| Endereco | Antes | Agora |
|---|---|---|
| `/bibliotecarios` | abria | vai para o login com "Entre para continuar." |
| `/auth-bibliotecario/registrar` | abria | vai para o login com "Entre para continuar." |
| `/auth-bibliotecario/login` | abria | continua abrindo, **sem** o link "Criar uma conta" |
| menu lateral | mostrava "Bibliotecarios" | mostra so "Inicio" e "Entrar (bibliotecario)" |

**NAVEGADOR** · na janela **normal**, logado como Maria

1. Clique em **Bibliotecarios** > **Novo bibliotecario**.
2. Cadastre `Joao Lima`, `joao@biblioteca.com`, senha `joao123`.

**Confira:** voce volta para a lista com "Bibliotecario cadastrado." e os dois
nomes aparecem.

Saia e entre como `joao@biblioteca.com` / `joao123`: funciona. Saia de novo e
**volte a entrar como Maria**.

No phpMyAdmin, a senha do Joao tambem comeca com `$2y$` — dessa vez quem
calculou o hash foi a tela `registrar`, com o mesmo `criarComSenha()` do seu
arquivo.

---

## Parte 6 — O cadastro de livros (20 min)

### 6.1 Gere o CRUD ja protegido

**TERMINAL 1** · e **uma linha so**; copie inteira

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

**Entenda os campos:**

| Pedaco do comando | Coluna no MySQL | Campo na tela |
|---|---|---|
| `titulo:string` | `VARCHAR(255)` | caixa de texto |
| `autor:string` | `VARCHAR(255)` | caixa de texto |
| `ano:integer` | `INT` | caixa de numero |
| `disponivel:boolean` | `TINYINT(1)` | caixa de marcar (grava 1 ou 0) |
| `--auth=bibliotecario` | — | todas as rotas exigem o login do bibliotecario |

**Entenda o `--auth=bibliotecario`:** abra `controllers/LivrosController.php`
(so para ler). Cada acao comeca com a mesma linha que voce escreveu a mao na
parte 5:

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

**TERMINAL 1**

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

A ultima linha e uma **dica**; nao rode o `--remover`.

**Entenda:** este comando **nao cria** arquivos, ele **altera** dois que ja
existiam (`~`). Ele le no `banco/esquema.sql` o tipo de cada coluna e escolhe
o filtro certo: texto procura por trecho, `boolean` vira a lista Todos / Sim /
Nao.

### 6.3 Menu e rotulos

**CODIGO** · `configuracoes/menu.php` · na linha de Livros

```diff
     ['rota' => 'bibliotecarios', 'texto' => 'Bibliotecarios', 'auth' => 'sim'],
-    ['rota' => 'livros', 'texto' => 'Livros'],
+    ['rota' => 'livros', 'texto' => 'Livros', 'auth' => 'sim'],
     // scaffold:crud
```

O gerador usou o nome da coluna como rotulo das telas (`titulo`,
`disponivel`). Troque por texto de gente. Use o **Localizar e substituir** do
VS Code (**Ctrl+H**) dentro de cada arquivo:

**CODIGO** · rotulos das telas

| Arquivo | Troque | Por |
|---|---|---|
| `views/livros/formulario.php` | `>titulo</label>` `>autor</label>` `>ano</label>` `>disponivel</label>` | `>Titulo</label>` `>Autor</label>` `>Ano</label>` `>Disponivel</label>` |
| `views/livros/index.php` | os mesmos quatro `</label>` (da pesquisa) e `<th>titulo</th>` `<th>autor</th>` `<th>ano</th>` `<th>disponivel</th>` | `Titulo`, `Autor`, `Ano`, `Disponivel` |
| `views/livros/ver.php` | `>titulo</dt>` `>autor</dt>` `>ano</dt>` `>disponivel</dt>` | `>Titulo</dt>` `>Autor</dt>` `>Ano</dt>` `>Disponivel</dt>` |
| `views/bibliotecarios/index.php` | `<th>nome</th>` | `<th>Nome</th>` |
| `views/bibliotecarios/ver.php` | `>nome</dt>` | `>Nome</dt>` |

Exemplo em `views/livros/formulario.php`:

```diff
-        <label class="form-label" for="titulo">titulo</label>
+        <label class="form-label" for="titulo">Titulo</label>
```

Mude **so o texto que aparece entre as tags**. O `for="titulo"`, o
`name="titulo"` e o `$registro['titulo']` sao o nome da coluna e precisam
continuar iguais.

> Um teste gerado procura a palavra `nome` na lista de bibliotecarios. Com o
> rotulo novo ele vai reclamar — voce acerta isso na parte 8.2.

### 6.4 Confira usando

**NAVEGADOR** · <http://localhost:8000/livros>

Cadastre tres livros:

| Titulo | Autor | Ano | Disponivel |
|---|---|---|---|
| Dom Casmurro | Machado de Assis | 1899 | marcado |
| Vidas Secas | Graciliano Ramos | 1938 | marcado |
| Quincas Borba | Machado de Assis | 1891 | **desmarcado** |

**Confira, um por um:**

1. **Validacao.** Clique em "Novo", preencha **so** o autor e salve. A tela
   volta com "O campo Titulo e obrigatorio." embaixo do campo, e o autor que
   voce digitou **continua la**.
2. **Pesquisa por trecho.** No campo Autor digite `machado` e pesquise.
   Aparecem os dois livros do Machado de Assis.
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

**NAVEGADOR** · <http://localhost:8000/livros/criar>

Tente dois cadastros:

1. titulo `Livro do futuro`, ano `2090`;
2. titulo `Livro sem ano`, com o ano **em branco**.

**Confira:**

1. o primeiro e **aceito** — a biblioteca agora tem um livro publicado daqui a
   decadas;
2. o segundo quebra com uma **tela de erro 500** que fala em
   `Incorrect integer value`. (Em alguns MySQL ele e gravado com ano `0`, sem
   erro nenhum — o que tambem esta errado.)

**NAVEGADOR** · na listagem de livros, exclua o "Livro do futuro" (e o "Livro
sem ano", se ele aparecer).

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

Hoje o metodo `validar()` de `modelos/Livro.php` esta assim:

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

**CODIGO** · `modelos/Livro.php` · dentro do metodo `validar()`

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
preferir, apague tudo o que tem nele, cole isto e salve:

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

**NAVEGADOR** · <http://localhost:8000/livros/criar>

| Titulo | Ano | O que deve acontecer |
|---|---|---|
| Livro sem ano | em branco | volta com "O campo Ano e obrigatorio." — sem tela de erro 500 |
| Livro do futuro | `2090` | volta com "O ano nao pode ser maior que o ano atual." |
| Livro de agora | o ano atual | e salvo |
| Memorias Postumas de Bras Cubas | `1881` | e salvo |

**Confira** tambem que, quando o formulario volta com a mensagem, **o titulo e
o autor que voce digitou continuam la**.

**NAVEGADOR** · abra o "Dom Casmurro", clique em **Editar**, troque o ano para
`2090` e salve.

**Confira:** a edicao tambem e recusada, com a mesma mensagem. Voce nao tocou
no `atualizar()` do controller — a regra do model valeu sozinha.

Exclua o "Livro de agora" antes de seguir.

---

## Parte 8 — Conferir com os testes (20 min)

Clicar na tela prova que funciona **hoje**. Teste prova que **continua**
funcionando depois que alguem mexer no codigo.

### 8.1 Rode todos os testes

**TERMINAL 1**

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
Testes: 86 | Passaram: 79 | Falharam: 7 | Erros: 0 | Assercoes: 216

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

Duas mudancas no mesmo arquivo. O `use Nucleo\Sessao;` ja esta no topo dele.

**CODIGO** · `testes/controllers/BibliotecariosControllerTest.php` · no
**fim** do metodo `preparar()`

```diff
         $this->modelo = new Bibliotecario();
+
+        // As rotas agora exigem login: o teste ja comeca logado.
+        Sessao::definir(Sessao::chaveAutenticacao('bibliotecario'), 1);
     }
```

**CODIGO** · `testes/controllers/BibliotecariosControllerTest.php` · no
**comeco** do metodo `testeExecutaRotasDoCrud()`

```diff
         $lista = $this->requisitar('bibliotecarios');
         $this->assertIgual(200, $lista->status);
-        $this->assertContem('nome', $lista->html);
+        $this->assertContem('Nome', $lista->html);
```

**Entenda:**

- o `preparar()` roda **antes de cada teste** da classe. Com a linha ali,
  todos os testes deste arquivo comecam logados;
- o `assertContem('nome', ...)` procurava o rotulo antigo da tabela. Na 6.3
  voce trocou `nome` por `Nome` — e para o teste, maiuscula e minuscula sao
  textos diferentes.

### 8.3 Conserte os testes do cadastro de contas

Quatro mudancas, todas em `testes/controllers/AuthBibliotecarioControllerTest.php`.

**CODIGO** · `testes/controllers/AuthBibliotecarioControllerTest.php` · no
metodo `testeRegistraEntraESai()` — uma linha no comeco e o destino esperado

```diff
     public function testeRegistraEntraESai(): void
     {
+        Sessao::definir(Sessao::chaveAutenticacao('bibliotecario'), 1);
+
         $registrar = $this->postar('auth-bibliotecario/registrar', [
             'nome'  => 'Ana',
             'email' => 'ana@example.com',
             'senha' => 'segredo123',
         ]);
-        $this->assertVerdadeiro($registrar->redirecionouPara('auth-bibliotecario/login'));
+        $this->assertVerdadeiro($registrar->redirecionouPara('bibliotecarios'));
```

O destino mudou porque, na 5.3, voce trocou o `redirecionar()` do cadastro.

**CODIGO** · mesmo arquivo · no comeco do metodo
`testeRecusaCadastroInvalido()`

```diff
     public function testeRecusaCadastroInvalido(): void
     {
+        Sessao::definir(Sessao::chaveAutenticacao('bibliotecario'), 1);
+
         $curta = $this->postar('auth-bibliotecario/registrar', [
```

**CODIGO** · mesmo arquivo · no comeco do metodo `testeRecusaEmailRepetido()`

```diff
     public function testeRecusaEmailRepetido(): void
     {
+        Sessao::definir(Sessao::chaveAutenticacao('bibliotecario'), 1);
+
         $this->modelo->criarComSenha(['email' => 'ana@example.com'], 'segredo123');
```

> **Por que aqui nao vai no `preparar()`?** Porque dois testes deste arquivo —
> `testeRecusaSenhaErrada` e `testeRecusaLoginSemToken` — precisam comecar
> **deslogados**: eles provam que o login **falhou**. Se ja comecassem
> logados, nao provariam nada.

**TERMINAL 1**

```bash
php testes/executar.php Bibliotecario
```

**Confira:** a ultima linha de numeros comeca com
`Testes: 12 | Passaram: 12 | Falharam: 0`.

**Entenda o `Bibliotecario` no fim do comando:** e um **filtro**. Roda so os
testes cujo nome tem essa palavra, em vez dos 86. Mais rapido enquanto voce
conserta um arquivo.

### 8.4 Um teste que prova a trava

Os consertos fizeram os testes antigos passarem. Mas **nenhum** teste prova
ainda a coisa mais importante da aula: que um visitante nao cria conta de
bibliotecario.

**CODIGO** · `testes/controllers/AuthBibliotecarioControllerTest.php` · no
**fim** do arquivo: depois do `}` do ultimo teste e **antes** do `}` final,
que fecha a classe

```diff
         $this->assertFalso(autenticado('bibliotecario'));
     }
+
+    public function testeVisitanteNaoCadastraBibliotecario(): void
+    {
+        $resposta = $this->postar('auth-bibliotecario/registrar', [
+            'nome'  => 'Intruso',
+            'email' => 'intruso@example.com',
+            'senha' => 'segredo123',
+        ]);
+
+        $this->assertVerdadeiro($resposta->redirecionouPara('auth-bibliotecario/login'));
+        $this->assertIgual(0, $this->modelo->contar());
+    }
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

**TERMINAL 1**

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

Os tempos entre parenteses mudam de uma maquina para outra.

**Prove que o teste presta.** Um teste que nunca falha nao vigia nada.

**CODIGO** · `controllers/AuthBibliotecarioController.php` · no `registrar()`,
**comente** a linha da trava (so para o experimento)

```diff
-        $this->exigirAutenticacao('bibliotecario');
+        // $this->exigirAutenticacao('bibliotecario');
```

**TERMINAL 1**

```bash
php testes/executar.php Bibliotecario
```

**Confira:** `visitante nao cadastra bibliotecario` **falha**.

Agora **tire o `//`** para a linha voltar ao normal, e rode o mesmo comando de
novo: volta a passar. Se um dia alguem apagar a trava sem querer, esse teste
avisa.

### 8.5 Dois testes para a regra do ano

A regra da parte 7 funciona na tela. Agora ela ganha testes, para ninguem
estraga-la sem perceber.

O arquivo `testes/modelos/LivroTest.php` foi gerado na parte 6 e hoje termina
com o metodo `testeValidaOsCamposObrigatorios()`, seguido do `}` que fecha a
classe.

**CODIGO** · `testes/modelos/LivroTest.php` · no **fim** do arquivo: depois do
`}` do `testeValidaOsCamposObrigatorios()` e **antes** do `}` final

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

**Entenda:**

| Trecho | O que faz |
|---|---|
| `$this->modelo->validar([...])` | chama a regra direto no model, sem navegador |
| `(int) date('Y') + 1` | o ano que vem. Nao use `2090` fixo: o teste tem que estar certo em qualquer ano |
| `assertTemChave('ano', $erros)` | "tem que existir um erro no campo `ano`" |
| `assertVazio($erros)` | "nao pode existir erro nenhum" |

**O segundo teste e o mais esperto.** O ano atual e o limite da regra: e
exatamente ai que a pessoa troca `<=` por `<` sem querer. Um teste com `1899`
nunca perceberia esse erro; um teste com o ano atual percebe.

**TERMINAL 1**

```bash
php testes/executar.php Livro
```

**Confira:**

```text
Controllers\LivrosControllerTest
  PASSOU executa rotas do crud (28.8ms)
  PASSOU recusa dados invalidos (15.7ms)
  PASSOU recusa formulario sem token (12.8ms)
  PASSOU exclusao nao aceita get (10.4ms)
  PASSOU gera relatorio em pdf (18.4ms)
  PASSOU exige login nas rotas (8.5ms)

Modelos\LivroTest
  PASSOU executa crud completo (8.2ms)
  PASSOU valida os campos obrigatorios (5.7ms)
  PASSOU recusa ano no futuro (7.0ms)
  PASSOU aceita o ano atual (5.5ms)

----------------------------------------------------------
Testes: 10 | Passaram: 10 | Falharam: 0 | Erros: 0 | Assercoes: 37

TUDO CERTO! O sistema esta funcionando.
```

**Prove que o teste presta.**

**CODIGO** · `modelos/Livro.php` · na regra, troque `<=` por `<` (so para o
experimento)

```diff
-                (int) ($dados['ano'] ?? 0) <= (int) date('Y'),
+                (int) ($dados['ano'] ?? 0) < (int) date('Y'),
```

**TERMINAL 1**

```bash
php testes/executar.php Livro
```

**Confira:** `aceita o ano atual` **falha**. Volte o `<=` e rode o mesmo
comando de novo: tudo passa.

### 8.6 Todos os testes

**TERMINAL 1**

```bash
php testes/executar.php
```

**Confira:**

```text
Testes: 89 | Passaram: 89 | Falharam: 0 | Erros: 0 | Assercoes: 249

TUDO CERTO! O sistema esta funcionando.
```

---

## Parte 9 — Arrumacao final (10 min)

### 9.1 Nome do sistema

**CODIGO** · `configuracoes/app.php` · na chave `nome`

```diff
-    'nome' => 'Framework MVC - Curso Tecnico',
+    'nome' => 'Biblioteca da Escola',
```

### 9.2 Pagina inicial

**CODIGO** · `views/home/index.php` · **apague tudo** o que tem no arquivo e
coloque isto

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

**NAVEGADOR** · <http://localhost:8000>

**Confira:** logado, aparece "Ver os livros"; na janela anonima, "Entrar".

### 9.3 Atualize o teste da pagina inicial

**TERMINAL 1**

```bash
php testes/executar.php
```

**Confira:** um teste do proprio framework falha:

```text
1) Nucleo\RoteamentoTest::testeRaizAbreAPaginaInicial
   Esperava encontrar "Bem-vindo ao framework MVC" no texto

----------------------------------------------------------
Testes: 89 | Passaram: 88 | Falharam: 1 | Erros: 0 | Assercoes: 249
```

**Entenda:** e a mesma historia da parte 8. O teste confere se a pagina
inicial mostra o texto antigo, e voce trocou esse texto de proposito.

**CODIGO** · `testes/nucleo/RoteamentoTest.php` · no metodo
`testeRaizAbreAPaginaInicial()`

```diff
         $this->assertIgual(200, $resposta->status);
-        $this->assertContem('Bem-vindo ao framework MVC', $resposta->html);
+        $this->assertContem('Biblioteca da Escola', $resposta->html);
```

**TERMINAL 1**

```bash
php testes/executar.php
```

**Confira:**

```text
Testes: 89 | Passaram: 89 | Falharam: 0 | Erros: 0 | Assercoes: 249

TUDO CERTO! O sistema esta funcionando.
```

### 9.4 Olhe tudo uma ultima vez

**NAVEGADOR** · navegue por todas as telas logado e na janela anonima,
procurando rotulo cru e aviso de PHP na tela.

---

## Checklist de saida

Marque antes de chamar o professor:

- [ ] `php testes/executar.php` termina com `TUDO CERTO!` (89 testes), ja
      com a pagina inicial nova
- [ ] `php banco/criar-administrador.php`, rodado de novo, responde "ja esta
      cadastrado"
- [ ] No phpMyAdmin, a senha da Maria e a do Joao comecam com `$2y$`
- [ ] <http://localhost:8000/banco/criar-administrador.php> mostra "Acesso
      negado."
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
- [ ] Sei explicar, com as minhas palavras: por que a Maria foi cadastrada
      por um arquivo e nao pela tela, por que a senha e um hash, por que a
      trava do `registrar()` nao vai no construtor e por que a regra do ano
      fica no model e nao no controller

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

## Apendice A — Todos os comandos do terminal

Estes sao **todos** os comandos da atividade, na ordem. Nada alem disso e
digitado no terminal.

**TERMINAL 2** · uma vez, na parte 1, e fica rodando ate o fim da aula
(para parar: **Ctrl+C**)

```bash
php -S localhost:8000 roteador.php
```

**TERMINAL 1** · todos os outros

| Parte | Comando | Para que |
|---|---|---|
| 1.3 | `php -v` | conferir a versao do PHP |
| 1.4 | `php instalar.php` | criar os dois bancos |
| 2.2 | `php console.php scaffold:crud bibliotecarios nome:string` | gerar o cadastro de bibliotecarios |
| 3.1 | `php console.php auth:install Bibliotecario` | dar login a tabela de bibliotecarios |
| 4.3 | `php banco/criar-administrador.php` | cadastrar a Maria (depois de criar o arquivo na 4.2) |
| 6.1 | `php console.php scaffold:crud livros titulo:string autor:string ano:integer disponivel:boolean --auth=bibliotecario` | gerar o cadastro de livros ja protegido |
| 6.2 | `php console.php scaffold:pesquisa livros titulo autor disponivel` | acrescentar a pesquisa em livros |
| 8 e 9 | `php testes/executar.php` | rodar todos os testes |
| 8.3 e 8.4 | `php testes/executar.php Bibliotecario` | rodar so os testes de bibliotecario |
| 8.5 | `php testes/executar.php Livro` | rodar so os testes de livro |

O mesmo, em sequencia, para copiar:

```bash
php -v
php instalar.php
php console.php scaffold:crud bibliotecarios nome:string
php console.php auth:install Bibliotecario
php banco/criar-administrador.php
php console.php scaffold:crud livros titulo:string autor:string ano:integer disponivel:boolean --auth=bibliotecario
php console.php scaffold:pesquisa livros titulo autor disponivel
php testes/executar.php
```

> Nao rode esta lista de uma vez: entre um comando e outro ha codigo para
> escrever. Ela serve para conferir se voce nao pulou nenhum.

---

## Apendice B — Todo o codigo que voce escreve

Estes sao **todos** os arquivos que voce abre no editor, na ordem. O codigo
completo de cada mudanca esta na parte indicada.

| Parte | Arquivo | O que voce faz |
|---|---|---|
| 1.4 | `configuracoes/banco.php` | troca os nomes dos dois bancos |
| 4.2 | `banco/criar-administrador.php` | **cria o arquivo** que cadastra a Maria |
| 5.2 | `controllers/BibliotecariosController.php` | 1 linha no `__construct()`: `exigirAutenticacao` |
| 5.3 | `controllers/AuthBibliotecarioController.php` | no `registrar()`: 1 linha no comeco e troca da mensagem e do destino no fim |
| 5.4 | `views/auth/bibliotecario/login.php` | apaga o link "Criar uma conta" |
| 5.4 | `views/auth/bibliotecario/registrar.php` | troca o link do fim por "Voltar para a lista" |
| 5.4 | `views/bibliotecarios/index.php` | o botao vira "Novo bibliotecario", apontando para `registrar` |
| 5.5 | `configuracoes/menu.php` | `'auth' => 'sim'` em Bibliotecarios |
| 6.3 | `configuracoes/menu.php` | `'auth' => 'sim'` em Livros |
| 6.3 | `views/livros/formulario.php`, `index.php`, `ver.php` | rotulos com letra maiuscula |
| 6.3 | `views/bibliotecarios/index.php`, `ver.php` | rotulo `Nome` |
| 7.3 | `modelos/Livro.php` | `->obrigatorio('ano')` e a regra `->personalizada(...)` |
| 8.2 | `testes/controllers/BibliotecariosControllerTest.php` | login no `preparar()` e `'Nome'` no `assertContem` |
| 8.3 | `testes/controllers/AuthBibliotecarioControllerTest.php` | login no comeco de 3 testes e o destino `'bibliotecarios'` |
| 8.4 | `testes/controllers/AuthBibliotecarioControllerTest.php` | teste novo `testeVisitanteNaoCadastraBibliotecario` |
| 8.5 | `testes/modelos/LivroTest.php` | testes novos `testeRecusaAnoNoFuturo` e `testeAceitaOAnoAtual` |
| 9.1 | `configuracoes/app.php` | nome do sistema |
| 9.2 | `views/home/index.php` | troca todo o conteudo |
| 9.3 | `testes/nucleo/RoteamentoTest.php` | texto procurado na pagina inicial |

Todos os outros arquivos do projeto foram **gerados pelos comandos** do
Apendice A. Voce le alguns deles para entender, mas nao escreve neles.

---

## Apendice C — Outro jeito: cadastrar a Maria pelo phpMyAdmin

Use este caminho **no lugar** das partes 4.2 e 4.3, se o professor preferir
que a Maria seja cadastrada com SQL. Se voce ja rodou o
`criar-administrador.php`, **nao** faca este apendice: a Maria ja existe.

**C.1 — Gere o hash da senha.** O banco nao sabe calcular o hash que o PHP usa,
entao o PHP calcula e voce copia.

**TERMINAL 1**

```bash
php -r "echo password_hash('biblioteca123', PASSWORD_DEFAULT) . PHP_EOL;"
```

**Confira:** aparece uma linha parecida com esta (a sua sai **diferente**, e
esta certo — cada vez o PHP mistura um valor aleatorio, o *sal*):

```text
$2y$12$eM.x7vAHotRY5lfy7X/2UuRllxZWDu7GWMt8J8mnWHs7/mG6QVYJW
```

Selecione e copie a linha **inteira** (60 caracteres).

**C.2 — Insira a Maria.**

**NAVEGADOR** · <http://localhost/phpmyadmin>

1. Clique no banco **`minha_biblioteca`**.
2. Abra a aba **SQL**.
3. Cole o comando abaixo e troque `COLE_AQUI_O_HASH` pelo **seu** hash:

```sql
INSERT INTO bibliotecarios (nome, email, senha)
VALUES ('Maria Souza', 'admin@biblioteca.com', 'COLE_AQUI_O_HASH');
```

4. Clique em **Executar**.

Tres cuidados:

- o hash fica **entre aspas simples**, como os outros valores;
- nao pode sobrar **espaco** antes ou depois do hash;
- se faltar um pedaco do hash, o login falha.

Depois, siga normalmente a partir da **parte 4.4**.

| Problema neste caminho | O que fazer |
|---|---|
| erro `#1062` (entrada duplicada) | a Maria ja existe: o e-mail e unico. Nao insira de novo |
| erro `#1146` (tabela nao existe) | voce clicou em outro banco, ou pulou a parte 2 |
| "E-mail ou senha invalidos." com os dados certos | o hash foi colado errado. Apague a linha da Maria e repita C.1 e C.2 |

---

## Socorro rapido

| O que aparece | O que fazer |
|---|---|
| `'php' nao e reconhecido como um comando` (Windows) | o PHP do XAMPP nao esta no PATH. Troque `php` por `C:\xampp\php\php.exe` no comando, ou peca ajuda para colocar `C:\xampp\php` no PATH |
| `Could not open input file: ...` | o terminal nao esta na pasta `minha-biblioteca`, ou o arquivo esta com outro nome/lugar. Abra a pasta certa no VS Code e abra um terminal novo |
| `[ERRO]` ao rodar `php instalar.php` | MySQL desligado, ou usuario/senha errados em `configuracoes/banco.php` |
| o TERMINAL 2 "travou" depois do `php -S` | nao travou: o servidor fica rodando assim. Use o TERMINAL 1 para os outros comandos |
| a pagina nao abre (`localhost:8000`) | o servidor do TERMINAL 2 foi fechado. Rode `php -S localhost:8000 roteador.php` de novo |
| a pagina abre sem estilo | o servidor foi iniciado sem o roteador: `php -S localhost:8000 roteador.php` |
| o phpMyAdmin nao abre | ligue o **Apache** no painel do XAMPP |
| `Failed opening required ... bootstrap.php` ao rodar o `criar-administrador.php` | o arquivo nao esta dentro da pasta `banco/`. Mova-o para la |
| `Call to undefined method ... buscarPorEmail()` | a parte 3 (`auth:install Bibliotecario`) nao foi feita |
| troquei a senha no `criar-administrador.php`, rodei de novo e a senha nova nao entra | o arquivo nao altera quem ja existe ("ja esta cadastrado"). Apague a linha da Maria no phpMyAdmin e rode o arquivo de novo |
| a tela de login fica pedindo login sem parar | voce colocou `exigirAutenticacao()` no **construtor** do `AuthBibliotecarioController`. Tire de la e deixe so dentro do `registrar()` |
| o bibliotecario criado em "Novo registro" nao consegue entrar | ele foi criado sem e-mail e senha. Faca o ultimo ajuste da 5.4 e cadastre de novo pela tela `registrar` |
| `--auth=bibliotecario` da erro ao gerar livros | o login ainda nao foi instalado: faca a parte 3 antes |
| erro 500 com `Incorrect integer value` ao salvar livro | o ano ficou em branco e ainda falta o `->obrigatorio('ano')`: veja a parte 7.3 |
| a regra do ano nao funciona | a regra foi escrita no controller ou fora do `validar()`. Ela vai **dentro** do `validar()` de `modelos/Livro.php`, antes do `->erros();` |
| `syntax error, unexpected token "->"` em `Livro.php` | sobrou um `;` no meio da corrente de regras. So o `->erros();` do fim tem `;` |
| `Esperava 200 mas recebeu 302` nos testes | teste antigo esperando rota publica: veja a parte 8 |
| `Esperava encontrar "nome" no texto` nos testes | voce trocou o rotulo para `Nome` e falta ajustar o teste: veja a parte 8.2 |
| `Esperava encontrar "Bem-vindo ao framework MVC"` nos testes | voce trocou a pagina inicial e o teste antigo ainda procura o texto velho: veja a parte 9.3 |
