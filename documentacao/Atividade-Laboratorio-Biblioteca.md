# Atividade de laboratorio — Biblioteca da escola

Atividade pratica de 4 horas, feita **em sala**, individualmente ou em dupla.

Voce vai construir um sistema pequeno mas inteiro: dois cadastros ligados por
uma relacao, tela de login, rotas protegidas, pesquisa, relatorio em PDF, duas
regras de negocio escritas a mao e testes proprios — tudo dentro de um
repositorio Git com branches, commits e uma tag.

> **Por que esta atividade existe.** Ela e a versao reduzida da *Atividade
> Avaliativa — Clinica Veterinaria*. Cada coisa que voce fizer aqui reaparece
> la em escala maior. A diferenca e que aqui os comandos estao dados; la voce
> vai receber os requisitos em linguagem de negocio e decidir tudo sozinho.
> A tabela da secao [O que isso vira na avaliativa](#o-que-isso-vira-na-avaliativa)
> mostra a correspondencia item por item.

**O que entregar no fim da aula:** o `git log --oneline --graph` do seu
repositorio e o sistema rodando na sua maquina, mostrados ao professor.

---

## Roteiro e tempo

| Parte | O que fica pronto | Tempo |
|---|---|---|
| 1 | Ambiente instalado, repositorio iniciado, `.gitignore` revisado | 20 min |
| 2 | Cadastro de categorias funcionando | 25 min |
| 3 | Tela de login e o cadastro de categorias protegido | 30 min |
| 4 | Livros: relacao 1:N e a autoria vinda da sessao | 35 min |
| 5 | Pesquisa na listagem e relatorio em PDF | 20 min |
| 6 | Duas regras de negocio no model | 35 min |
| 7 | Um arquivo de testes escrito por voce | 25 min |
| 8 | Merge, tag e arrumacao final | 15 min |
| — | Desafios | o que sobrar |

Total guiado: cerca de 3h25. Os desafios ficam para quem terminar antes — e
sao exatamente o tipo de coisa que a avaliativa vai cobrar sem ajuda.

---

## O pedido

A biblioteca da escola tem umas duas mil obras controladas em um caderno.
A bibliotecaria pediu:

- *"Quero saber quais livros eu tenho de cada assunto."*
- *"Cada livro tem um numero de tombo colado na lombada, e ele nao pode se
  repetir. Ja aconteceu de colar o mesmo numero em dois livros."*
- *"Preciso achar um livro pelo titulo sem virar o caderno inteiro."*
- *"Quero uma lista impressa para conferir a estante."*
- *"E quero saber quem cadastrou cada livro, porque as vezes o estagiario
  digita errado e ninguem assume."*

## O que voce vai construir

| Rota | O que faz | Protegida? |
|---|---|---|
| `/` | pagina inicial | nao |
| `/auth/registrar` | cria a conta da equipe | nao |
| `/auth/login` | entrada | nao |
| `/auth/sair` | saida | nao |
| `/categorias` | CRUD de categorias | sim (parte 3) |
| `/livros` | CRUD de livros, com pesquisa | sim, desde a parte 4 |
| `/livros/relatorio` | PDF filtravel do acervo | sim |

Modelo de dados:

```text
categorias (1) ----< (N) livros
  id                      id
  nome                    titulo
  descricao               autor
                          tombo           <- nao pode repetir
                          ano             <- nao pode ser no futuro
                          disponivel
                          categoria_id    -> categorias.id
                          cadastrado_por  -> quem lancou (vem da sessao)
```

---

## Parte 1 — Ambiente e repositorio (20 min)

### 1.1 Confira o ambiente

```bash
php -v
```

Precisa ser **8.1 ou maior**. Depois **inicie o MySQL** no painel do XAMPP.

### 1.2 Faca a sua copia do framework

O framework e o ponto de partida, mas o historico dele nao e o seu. Copie a
pasta e comece um repositorio do zero:

```bash
cd ~/Desenvolvimento          # ou a pasta onde voce guarda seus projetos
cp -R framework biblioteca
cd biblioteca
rm -rf .git
```

No Windows, sem o Git Bash, a linha do `rm` vira `rmdir /s /q .git`.

### 1.3 Use um banco so seu

Abra `configuracoes/banco.php` e troque os dois nomes de banco:

```php
'banco'        => 'biblioteca',
'banco_testes' => 'biblioteca_testes',
```

Assim o que voce fizer nesta aula nao encosta no banco do tutorial.

```bash
php instalar.php
```

```text
Instalando o banco de dados (MySQL)...
Servidor: localhost | Banco: biblioteca
[ok] Banco de dados pronto. Nenhuma tabela padrao foi criada.
[ok] Banco de testes pronto: biblioteca_testes
```

Deu erro aqui? O MySQL nao esta ligado, ou o usuario e a senha em
`configuracoes/banco.php` nao batem com os do seu XAMPP.

### 1.4 Suba o servidor

Em **outro terminal**, e deixe rodando ate o fim da aula:

```bash
php -S localhost:8000 roteador.php
```

Abra <http://localhost:8000>. A tela inicial do framework aparece.

### 1.5 Revise o `.gitignore`

Abra o `.gitignore`. Ele ja ignora o `.DS_Store`, o `.vscode/` e o
`configuracoes/banco.local.php`. Falta uma coisa: o comando
`relatorio:pdf` grava arquivos em `relatorios/`, e **PDF gerado nao entra no
repositorio** — ele e resultado, nao codigo. Acrescente:

```gitignore
# PDFs gerados pelo comando relatorio:pdf
relatorios/
```

> Pense antes de sair copiando: por que `banco/esquema.sql` **deve** ser
> versionado, se ele tambem e gerado por comando? Anote a sua resposta — a
> avaliativa pede essa justificativa por escrito (RG03).

### 1.6 Primeiro commit

```bash
git init
git add .
git commit -m "chore: adicionar o framework base do projeto"
git branch -M main
```

**Confira:** `git log --oneline` mostra exatamente um commit.

> **Regra da aula:** daqui para a frente voce **nao trabalha na `main`**.
> Cada parte comeca criando uma branch e termina com um merge.

---

## Parte 2 — O primeiro cadastro: categorias (25 min)

### 2.1 Abra a branch

```bash
git switch -c feat/cadastros
```

### 2.2 Gere o CRUD

```bash
php console.php scaffold:crud categorias nome:string descricao:text
```

```text
CRUD criado: /categorias
  + modelos/Categoria.php
  + controllers/CategoriasController.php
  + views/categorias/index.php
  + views/categorias/formulario.php
  + views/categorias/ver.php
  + testes/modelos/CategoriaTest.php
  + testes/controllers/CategoriasControllerTest.php
  ~ banco/esquema.sql
  ~ configuracoes/menu.php

ATENCAO: todas as rotas de /categorias sao publicas, inclusive excluir e o
relatorio. Para exigir login, gere com --auth ou chame exigirAutenticacao()
no controller.
```

`+` e arquivo criado, `~` e arquivo alterado. **Leia o aviso do fim**: por
enquanto qualquer visitante exclui categorias. E de proposito — voce vai
sentir o custo de consertar isso na parte 3.

### 2.3 Use o que foi gerado

Abra <http://localhost:8000/categorias> e cadastre tres: `Literatura
brasileira`, `Ciencias` e `Historia`. Edite uma, exclua outra, cadastre de
novo.

### 2.4 Leia o model

Abra `modelos/Categoria.php`. Sao poucas linhas e todas importam:

```php
class Categoria extends Model
{
    protected string $tabela = 'categorias';
    protected array $preenchiveis = ['nome', 'descricao'];
    protected string $ordemPadrao = 'id DESC';

    public function validar(array $dados, int|string|null $ignorarId = null): array
    {
        return (new Validador($dados))
            ->obrigatorio('nome')
            ->maximo('nome', 255)
            ->erros();
    }
}
```

- `$preenchiveis` e a lista de colunas que o formulario pode gravar. Uma
  coluna fora dela e ignorada, mesmo que alguem a envie no POST.
- `validar()` roda antes de gravar. Array vazio quer dizer "esta tudo certo".

### 2.5 Ajuste o que o gerador nao tinha como saber

O gerador nao conhece o seu dominio. Em `views/categorias/index.php` e
`views/categorias/formulario.php`, troque o rotulo `descricao` por
**Descricao** e `nome` por **Nome**. Em `configuracoes/menu.php`, deixe o
texto do item como `Categorias`.

> Isso nao e frescura: a avaliativa reprova explicitamente quem entrega telas
> com os rotulos crus do gerador (RT02).

### 2.6 Commit

```bash
git add .
git commit -m "feat: cadastrar as categorias do acervo"
```

Mensagem no padrao **Conventional Commits**: prefixo, dois pontos, verbo no
imperativo, o efeito da mudanca. `ajustes`, `att` e `commit 2` nao contam.

---

## Parte 3 — A tela de login, e o preco de proteger depois (30 min)

### 3.1 Instale o login

```bash
git switch main
git merge --no-ff feat/cadastros -m "chore: integrar o cadastro de categorias"
git switch -c feat/acesso

php console.php auth:install
```

```text
Autenticacao aplicada ao modelo Usuario.
  + modelos/Usuario.php
  + controllers/AuthController.php
  + views/auth/login.php
  + views/auth/registrar.php
  + testes/controllers/AuthControllerTest.php
  ~ banco/esquema.sql

Login em /auth: e o login unico do projeto.

Rotas:
  /auth/registrar   cria uma conta
  /auth/login       entra
  /auth/sair        encerra a sessao
```

Sem argumentos, o comando cria o model `Usuario` (tabela `usuarios`) e o
login unico em `/auth`. Ele tambem sabe instalar o login **sobre um model que
ja existe** (`auth:install Bibliotecario`), e ai as rotas ficam em
`/auth-bibliotecario`. Hoje nao precisamos disso.

### 3.2 Crie a sua conta

Abra <http://localhost:8000/auth/registrar>, crie uma conta e entre em
`/auth/login`. Repare que essas duas telas **nao tem o menu lateral**: elas
usam `views/template/layout-login.php`. Quem ainda nao entrou nao deveria nem
ver a lista de recursos do sistema.

**Confira a senha no banco.** No phpMyAdmin, abra `biblioteca` > `usuarios`.
A coluna `senha` tem um texto comecando com `$2y$` — o hash. Se um dia voce
ver a senha legivel ali, alguem quebrou o sistema.

### 3.3 Proteja o CRUD de categorias

`exigirAutenticacao()` **nao e uma configuracao global**: e uma chamada de
metodo, que vale onde voce escrever. Para valer no controller inteiro, chame
no construtor. Em `controllers/CategoriasController.php`:

```php
public function __construct()
{
    // Vale para todas as acoes deste controller.
    $this->exigirAutenticacao();

    $this->modelo = new Categoria();
}
```

Saia da sessao (`/auth/sair`) e tente abrir `/categorias`: voce cai no login
com o aviso "Entre para continuar.".

### 3.4 Esconda o item do menu

Em `configuracoes/menu.php`:

```php
['rota' => 'categorias', 'texto' => 'Categorias', 'auth' => 'sim'],
```

`'auth' => 'sim'` mostra o item so para quem esta logado.

> Esconder o link **nao protege nada**: quem souber o endereco continua
> entrando. A protecao de verdade e a do controller; a view so evita mostrar
> um botao que nao ia funcionar.

### 3.5 Rode os testes — e veja o estrago

```bash
php testes/executar.php Categorias
```

```text
1) Controllers\CategoriasControllerTest::testeExecutaRotasDoCrud
   Esperava 200 mas recebeu 302
...
Testes: 5 | Passaram: 1 | Falharam: 4 | Erros: 0

ATENCAO: 4 teste(s) com problema.
```

**Quatro testes quebraram.** Eles foram gerados quando `/categorias` era
publico e continuam esperando um `200`; agora a rota devolve `302` — o
redirecionamento para o login. O codigo esta certo; o teste e que ficou velho.

Conserte: em `testes/controllers/CategoriasControllerTest.php`, no fim do
`preparar()`, deixe o teste ja logado:

```php
$this->modelo = new Categoria();
Sessao::definir(Sessao::chaveAutenticacao(), 1);
```

O `use Nucleo\Sessao;` ja esta la no topo do arquivo. Rode de novo: 5 de 5.

> **A licao vale mais que o conserto.** Gerar um recurso ja protegido custa
> uma opcao (`--auth`); proteger depois custou uma edicao no controller, uma
> no menu e quatro testes quebrados — em um sistema com um unico cadastro.
> Na avaliativa sao seis modulos. Por isso o enunciado manda fazer a etapa de
> acesso **antes** dos modulos grandes.

### 3.6 Commit e merge

```bash
git add .
git commit -m "feat: exigir login da equipe para acessar as categorias"
git switch main
git merge --no-ff feat/acesso -m "chore: integrar o acesso da equipe"
```

---

## Parte 4 — Livros: a relacao 1:N e a autoria (35 min)

### 4.1 Pense nas colunas ANTES de gerar

```bash
git switch -c feat/livros
```

O `scaffold:crud` **se recusa a regerar** um recurso cujos arquivos ja
existem — ele nao sobrescreve o seu trabalho:

```text
[ERRO] Estes arquivos ja existem e nao serao sobrescritos:
  modelos/Livro.php
  controllers/LivrosController.php
  ...
```

E o `php instalar.php` so cria tabelas que faltam; ele **nao acrescenta
coluna** em tabela que ja existe. Ou seja: esquecer uma coluna agora custa
SQL na mao depois. Decida tudo antes.

Nossa lista sai direto do pedido da bibliotecaria: titulo, autor, tombo, ano,
se esta disponivel, a categoria e **quem cadastrou**.

### 4.2 Gere o CRUD ja protegido

```bash
php console.php scaffold:crud livros titulo:string autor:string tombo:string ano:integer disponivel:boolean categoria_id:belongs_to=categorias cadastrado_por:integer --auth
```

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

Rotas protegidas pelo login /auth.
```

Nenhum aviso de rota publica, e nenhum teste quebrado: nasceu protegido.

O pedaco `categoria_id:belongs_to=categorias` fez o console:

- criar `categoria_id` como inteiro com `FOREIGN KEY`;
- criar no model o metodo `categorias()`, que devolve as opcoes;
- gerar um `<select>` no formulario, em vez de uma caixa para digitar o numero;
- marcar o campo como obrigatorio na validacao.

**A tabela pai precisa existir antes.** Se voce tivesse invertido a ordem, o
comando recusaria e diria qual comando rodar primeiro.

Abra <http://localhost:8000/livros/criar> e cadastre tres livros.

### 4.3 O gerador acertou seis campos e errou um

Olhe o formulario: `cadastrado_por` virou uma **caixa de numero**. Faz
sentido para o gerador, que so viu `integer` — e esta errado para o sistema.
Autoria digitada em formulario e autoria forjavel: qualquer um escreve o
numero de outra pessoa ali.

O dado certo ja existe e esta na sessao de quem esta logado.

**Primeiro**, apague o bloco inteiro do campo em
`views/livros/formulario.php`:

```php
    <div class="col-md-6">
        <label class="form-label" for="cadastrado_por">cadastrado_por</label>
        <input class="form-control ..." id="cadastrado_por" type="number" name="cadastrado_por" ...>
        <?php if ($mensagem = erro_de('cadastrado_por')): ?>...<?php endif ?>
    </div>
```

**Depois**, em `controllers/LivrosController.php`, no `salvar()`, troque a
origem do dado:

```php
$dados = [
    'titulo' => $this->post('titulo'),
    // ...
    'categoria_id'   => $this->post('categoria_id'),
    'cadastrado_por' => usuario_id(),   // da sessao, nunca do formulario
];
```

**E por fim**, no `atualizar()`, **apague** a linha do `cadastrado_por`. Quem
cadastrou cadastrou: editar o livro nao muda a autoria.

Cadastre **mais um** livro e abra a tela de detalhes dele: o numero em
`cadastrado_por` e o id da sua conta. Os tres livros da secao 4.2 ficaram com
o que voce digitou ali - exclua-os e cadastre de novo, agora que a autoria
vem do lugar certo.

> Esse e o RF18 da avaliativa, em miniatura — e o proprio enunciado avisa que
> nenhum gerador faz isso por voce.

### 4.4 Ajuste os rotulos e faca o commit

Troque `titulo`, `autor`, `tombo`, `ano`, `disponivel` e `categoria_id` por
rotulos de gente nas tres views. Em `configuracoes/menu.php`, acrescente
`'auth' => 'sim'` no item Livros.

```bash
php testes/executar.php
git add .
git commit -m "feat: cadastrar livros com categoria e autoria do lancamento"
```

---

## Parte 5 — Pesquisa e relatorio em PDF (20 min)

### 5.1 Pesquisa na listagem

```bash
php console.php scaffold:pesquisa livros titulo categoria_id disponivel
```

```text
Pesquisa criada em /livros
  ~ controllers/LivrosController.php
  ~ views/livros/index.php

Campos pesquisaveis:
  titulo             texto         contem o trecho digitado (LIKE)
  categoria_id       categorias    lista suspensa com os registros de categorias
  disponivel         Sim/Nao       valor exato
```

Repare que este comando **altera dois arquivos que ja existiam**, entre
marcadores `// ----- scaffold:pesquisa inicio -----`. O que estiver fora dos
marcadores continua como voce deixou, e
`php console.php scaffold:pesquisa livros --remover` desfaz.

Abra `/livros` e teste: um trecho do titulo acha pelo meio da palavra; dois
campos preenchidos se somam (`E`, nao `OU`); campo em branco nao filtra nada.
O endereco guarda a pesquisa: `/livros?titulo=dom&disponivel=1`.

Abra o `index()` do controller e ache a linha que monta a condicao:

```php
$condicoes[]  = 'titulo LIKE ? ESCAPE ' . Sql::ESCAPE_LIKE;
$parametros[] = Sql::comoLike((string) $termo);
```

O que a pessoa digitou vai como parametro (`?`), **nunca dentro do texto do
SQL**. Pesquise por `%`: voce ve os livros que tem `%` no titulo, e nao a
tabela inteira.

### 5.2 Relatorio em PDF

A rota ja existe desde o scaffold, e a listagem traz o link:

```text
/livros/relatorio
/livros/relatorio?titulo=dom&disponivel=1
```

Os filtros sao os mesmos da pesquisa. O PDF e montado na memoria e devolvido
pela rota — por isso ele **respeita o `exigirAutenticacao()`** do controller.
Um arquivo solto numa pasta publica continuaria acessivel para qualquer um.

Para gerar um arquivo pelo terminal:

```bash
php console.php relatorio:pdf livros
```

Ele cai em `relatorios/livros.pdf` — a pasta que voce ignorou na parte 1.

```bash
git status        # o PDF nao deve aparecer aqui
git add .
git commit -m "feat: pesquisar o acervo e emitir o relatorio em pdf"
```

---

## Parte 6 — Duas regras que nenhum gerador escreve (35 min)

Ate aqui tudo saiu de comando. Agora vem a parte que e sua.

```bash
git switch main
git merge --no-ff feat/livros -m "chore: integrar o acervo de livros"
git switch -c feat/regras
```

A validacao gerada cobre o obvio (obrigatorio, tamanho, numerico). As duas
regras que a bibliotecaria pediu nao sao obvias:

1. o **ano** nao pode estar no futuro;
2. o **tombo** nao pode repetir — mas editar um livro sem mudar o tombo
   dele tem que continuar funcionando.

### 6.1 Onde a regra mora

No **model**, dentro de `validar()`. Nao no controller, nao na view. O
controller so recebe, delega e responde:

```php
$erros = $this->modelo->validar($dados);

if ($erros !== []) {
    $this->voltarComErros($erros, 'livros/criar');
}
```

`voltarComErros()` devolve o formulario com a mensagem embaixo do campo certo
**sem perder o que ja foi digitado**. Voce ganha isso de graca desde que a
regra esteja no lugar certo.

### 6.2 Escreva as regras

Em `modelos/Livro.php`, o `validar()` fica assim:

```php
public function validar(array $dados, int|string|null $ignorarId = null): array
{
    $ano   = (string) ($dados['ano'] ?? '');
    $tombo = trim((string) ($dados['tombo'] ?? ''));

    return (new Validador($dados))
        ->obrigatorio('titulo')
        ->maximo('titulo', 255)
        ->maximo('autor', 255)
        ->obrigatorio('tombo')
        ->maximo('tombo', 255)
        ->numerico('ano')
        ->obrigatorio('categoria_id')
        ->numerico('categoria_id')
        ->numerico('cadastrado_por')
        ->personalizada(
            'ano',
            $ano === '' || (int) $ano <= (int) date('Y'),
            'O ano de publicacao nao pode estar no futuro.'
        )
        ->personalizada(
            'tombo',
            ! $this->tomboJaUsado($tombo, $ignorarId),
            'Ja existe um livro com esse tombo.'
        )
        ->erros();
}

/** Verdadeiro quando OUTRO livro ja usa esse tombo. */
private function tomboJaUsado(string $tombo, int|string|null $ignorarId): bool
{
    if ($tombo === '') {
        return false;
    }

    $livro = $this->primeiroOnde('tombo', $tombo);

    return $livro !== null && (int) $livro['id'] !== (int) $ignorarId;
}
```

Duas coisas para entender antes de seguir:

- **`personalizada($campo, $condicaoValida, $mensagem)`** recebe a condicao do
  que e **valido**. Se ela for `true`, nao ha erro.
- **`$ignorarId`** e a chave da segunda regra. O controller chama
  `validar($dados)` ao criar e `validar($dados, $id)` ao editar. Sem esse
  parametro, salvar um livro sem mexer no tombo acusaria "tombo repetido"
  contra ele mesmo.

### 6.3 Prove na tela

1. Cadastre um livro com tombo `L-001`. Funciona.
2. Cadastre outro com o mesmo `L-001`. O formulario volta com a mensagem
   embaixo do campo e **com o resto preenchido**.
3. Abra o primeiro livro, mude so o titulo e salve. Tem que funcionar.
4. Cadastre um livro com ano `2090`. Recusado.

O passo 3 e o que separa a regra certa da regra ingenua. Teste mesmo.

```bash
php testes/executar.php
git add .
git commit -m "feat: recusar tombo repetido e ano de publicacao no futuro"
```

---

## Parte 7 — Um teste escrito por voce (25 min)

Clicar na tela prova que funciona hoje. Teste prova que continua funcionando
depois que outra pessoa mexer no codigo.

Um arquivo de teste termina em `Test.php`, fica em uma subpasta de `testes/`,
o namespace acompanha a pasta e **todo metodo publico comecado por `teste`
roda sozinho**.

Crie `testes/modelos/LivroRegrasTest.php`:

```php
<?php

namespace Testes\Modelos;

use Modelos\Livro;
use Nucleo\Database;
use Testes\Suporte\TesteBase;

class LivroRegrasTest extends TesteBase
{
    private Livro $modelo;
    private int $categoriaId;

    public function preparar(): void
    {
        // O banco de testes nasce vazio: monte aqui as tabelas que o teste usa.
        $this->recriarTabelas([
            'categorias' => 'CREATE TABLE categorias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NULL
            )',
            'livros' => 'CREATE TABLE livros (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(255) NULL,
                autor VARCHAR(255) NULL,
                tombo VARCHAR(255) NULL,
                ano INT NULL,
                disponivel TINYINT(1) NULL,
                categoria_id INT NULL,
                cadastrado_por INT NULL
            )',
        ]);

        Database::conexao()->exec("INSERT INTO categorias (nome) VALUES ('Romance')");
        $this->categoriaId = (int) Database::conexao()
            ->query('SELECT id FROM categorias ORDER BY id ASC LIMIT 1')
            ->fetchColumn();

        $this->modelo = new Livro();
    }

    /** Um livro de exemplo, com o tombo e o ano que o teste quiser. */
    private function livro(string $tombo, int $ano = 2020): array
    {
        return [
            'titulo'       => 'Dom Casmurro',
            'autor'        => 'Machado de Assis',
            'tombo'        => $tombo,
            'ano'          => $ano,
            'disponivel'   => 1,
            'categoria_id' => $this->categoriaId,
        ];
    }

    public function testeRecusaTomboJaUsado(): void
    {
        $this->modelo->criar($this->livro('L-001'));

        $erros = $this->modelo->validar($this->livro('L-001'));

        $this->assertTemChave('tombo', $erros);
    }

    public function testeAceitaOMesmoTomboAoEditarOProprioLivro(): void
    {
        $id = $this->modelo->criar($this->livro('L-001'));

        $erros = $this->modelo->validar($this->livro('L-001'), $id);

        $this->assertVazio($erros);
    }

    public function testeRecusaAnoNoFuturo(): void
    {
        $anoQueVem = (int) date('Y') + 1;

        $erros = $this->modelo->validar($this->livro('L-002', $anoQueVem));

        $this->assertTemChave('ano', $erros);
    }
}
```

```bash
php testes/executar.php LivroRegras
```

```text
Modelos\LivroRegrasTest
  PASSOU recusa tombo ja usado (17.5ms)
  PASSOU aceita o mesmo tombo ao editar o proprio livro (20.7ms)
  PASSOU recusa ano no futuro (10.8ms)

Testes: 3 | Passaram: 3 | Falharam: 0 | Erros: 0

TUDO CERTO! O sistema esta funcionando.
```

Tres detalhes que valem para qualquer teste seu:

- **`preparar()` monta as tabelas.** O banco de testes (`biblioteca_testes`) e
  apagado e recriado a cada execucao, e cada classe monta o que vai usar.
  Esquecer isso da erro de tabela inexistente.
- **Os seus dados nunca sao tocados.** Rodar os testes nao apaga os livros que
  voce cadastrou no navegador.
- **O segundo teste e o mais importante.** Ele e o unico que pegaria a versao
  ingenua da regra do tombo — a que esqueceu o `$ignorarId`. Prove que o
  teste presta: comente a checagem do `$ignorarId` no model, rode, veja
  falhar, e desfaca.

Agora rode tudo:

```bash
php testes/executar.php
```

```text
Testes: 87 | Passaram: 87 | Falharam: 0 | Erros: 0 | Assercoes: 246

TUDO CERTO! O sistema esta funcionando.
```

```bash
git add .
git commit -m "test: cobrir as regras de tombo unico e ano de publicacao"
```

---

## Parte 8 — Fechar o trabalho (15 min)

### 8.1 Arrumacao

1. **Nome do sistema.** `configuracoes/app.php`, chave `nome`:
   `'Biblioteca da Escola'`.
2. **Pagina inicial.** `views/home/index.php` ainda e a tela do framework.
   Troque por duas frases sobre a biblioteca e o link de entrada da equipe.
3. **Menu.** Ordem e textos coerentes, com `'auth' => 'sim'` nos dois
   cadastros.
4. **Sem aviso de PHP nenhum na tela.** Navegue pelas telas uma vez olhando.

### 8.2 Merge e tag

```bash
php testes/executar.php

git add .
git commit -m "docs: apresentar a biblioteca na pagina inicial"
git switch main
git merge --no-ff feat/regras -m "chore: integrar as regras de negocio do acervo"

git tag -a v0.1 -m "Biblioteca funcionando: cadastros, acesso, regras e testes"
```

### 8.3 Olhe o seu historico

```bash
git log --oneline --graph --all
```

Voce deve ver **quatro branches** saindo e voltando para a `main`, **oito
commits seus** e os **quatro merges**, com mensagens que contam o que
aconteceu. E isso que voce mostra ao professor.

---

## Checklist de saida da aula

Marque antes de chamar o professor:

- [ ] `php testes/executar.php` termina com `TUDO CERTO!`
- [ ] Deslogado, `/categorias` e `/livros` levam ao login
- [ ] O menu muda conforme voce entra e sai
- [ ] Cadastrar dois livros com o mesmo tombo e recusado, com a mensagem no
      campo certo e sem perder o que foi digitado
- [ ] Editar um livro sem mudar o tombo funciona
- [ ] Ano `2090` e recusado
- [ ] `cadastrado_por` **nao** aparece no formulario e **aparece** preenchido
      na tela de detalhes
- [ ] `/livros/relatorio` abre o PDF, e deslogado ele leva ao login
- [ ] `git log --oneline --graph --all` mostra as branches e a tag `v0.1`
- [ ] `git status` limpo, e nenhum PDF versionado

---

## Desafios

Sem passo a passo — a ajuda esta na
[Referencia de comandos](Referencia-Comandos.md) e no
[Tutorial](Tutorial-Comandos.md).

**D1 — Mostre o nome de quem cadastrou.** Hoje a tela de detalhes mostra o
*numero* em `cadastrado_por`. Troque pelo nome da pessoa. Dica: o `ver()` do
controller e quem busca os dados; `Usuario` e um model como qualquer outro e
tem `buscar($id)`. Faca em uma branch `feat/autoria-legivel`.

**D2 — Prove que a autoria nao pode ser forjada.** Escreva
`testes/controllers/LivroAutoriaTest.php` que: coloca um usuario na sessao
(`Sessao::definir(Sessao::chaveAutenticacao(), 7)`), envia um POST para
`livros/salvar` com `postar()` incluindo um `cadastrado_por` **falso** no
meio dos dados, e verifica que o livro gravado ficou com o id **da sessao**.
Acrescente um segundo teste provando que um visitante deslogado e
redirecionado para `auth/login`.

**D3 — Emprestimos.** Crie o cadastro de emprestimos: cada emprestimo e de um
livro, com data de saida, data prevista de devolucao e o nome de quem levou.
Depois escreva a regra: **nao se empresta um livro que nao esta disponivel**,
com o teste correspondente. Lembre da ordem — a tabela pai precisa existir
antes, e gere o CRUD ja com `--auth`.

**D4 — Conte o acervo.** Na listagem filtrada de livros, mostre acima da
tabela quantos registros o filtro atual encontrou. Isso vai um passo alem do
que o `scaffold:pesquisa` gera.

**D5 — Provoque um conflito de merge e resolva.** Em duas branches
diferentes, edite a **mesma linha** de `configuracoes/menu.php`. Faca os dois
merges na `main` e resolva o conflito do segundo. Anote o que conflitou e por
que voce escolheu aquela resolucao — a avaliativa pede isso por escrito
(RG08).

---

## O que isso vira na avaliativa

| O que voce fez hoje | Onde reaparece na Clinica Veterinaria |
|---|---|
| CRUD de categorias | RF01-RF04: especies, tutores, veterinarios, procedimentos |
| `livros` com `categoria_id` | RF05-RF06: o animal, com tutor **e** especie |
| tela de detalhes do livro | RF07: detalhes do animal, com historico |
| `scaffold:pesquisa` em livros | RF08 e RF12: pesquisa de animais e de atendimentos |
| contagem do acervo (D4) | RF13: quantidade e soma dos valores filtrados |
| `auth:install` + rotas protegidas | RF16, RF17 e RF19: acesso da equipe |
| menu com `'auth' => 'sim'` | RF20: menu coerente com a situacao |
| regra do **ano no futuro** | RF11: recusar agendamento com data no passado |
| regra do **tombo repetido** | RF10: recusar dois atendimentos no mesmo horario |
| `cadastrado_por` vindo da sessao | RF18: autoria do lancamento, nunca do formulario |
| `/livros/relatorio` | RF21 e RF22: agenda e carteira de vacinacao em PDF |
| rotulos ajustados a mao | RT02: o gerado e o ponto de partida, nao a entrega |
| regra dentro de `validar()` | RT03: regra no model, controller so delega |
| `LivroRegrasTest` | RT08: no minimo 4 testes proprios |
| `banco/esquema.sql` versionado | RT07: recriar o sistema do zero em outra maquina |
| `.gitignore` revisado | RG03: justificar o que fica de fora |
| branch por parte, merge `--no-ff` | RG06 e RG07: 6 branches, `main` sempre funcionando |
| mensagens `feat:` / `fix:` / `test:` | RG05: Conventional Commits, 25 commits minimos |
| tag `v0.1` | RG10: `v0.1`, `v0.5` e `v1.0` |
| conflito provocado (D5) | RG08: um conflito real, resolvido e explicado |

O que a avaliativa acrescenta e que **nao** tem aqui: traduzir requisitos em
linguagem de negocio para um modelo de dados sozinho, seis modulos em vez de
dois, repositorio remoto com o professor como colaborador, commits
distribuidos em varios dias, relatorio escrito, video e defesa oral.

---

## Socorro rapido

| O que aparece | O que fazer |
|---|---|
| `[ERRO]` ao rodar `php instalar.php` | MySQL desligado, ou usuario/senha errados em `configuracoes/banco.php` |
| `[ERRO] Estes arquivos ja existem e nao serao sobrescritos` | o recurso ja foi gerado. Apague os arquivos listados ou edite a mao — nunca gere por cima |
| `[ERRO] A tabela pai "categorias" nao existe` | gere o CRUD do pai antes do filho |
| 4 testes falhando com `Esperava 200 mas recebeu 302` | voce protegeu o controller depois; ajuste o `preparar()` do teste (parte 3.5) |
| erro de tabela inexistente so nos testes | faltou montar a tabela no `recriarTabelas()` do `preparar()` |
| o `<select>` da categoria aparece vazio | nao ha categoria cadastrada; cadastre uma |
| o campo `boolean` grava sempre 0 | o formulario gerado ja trata isso; se voce reescreveu o campo, mantenha o `<input type="hidden">` que acompanha a caixa |
| a tela de login pede login (laco) | voce colocou `exigirAutenticacao()` dentro do `AuthController` |

Auxiliares que voce vai usar o tempo todo:

| Nas views | Para que |
|---|---|
| `e($valor)` | escapar HTML — use em tudo que vier do banco |
| `campo_csrf()` | o campo escondido com o token, obrigatorio em todo formulario |
| `data_br()` / `moeda_br()` / `sim_nao()` | formatacao brasileira |
| `antigo()` / `erro_de()` / `tem_erro()` | devolver o formulario com erros e dados |
| `autenticado()` / `usuario_id()` | quem esta logado |
| `url()` / `asset()` / `parcial()` | enderecos e pedacos de view |

| No controller | Para que |
|---|---|
| `exigirAutenticacao()` | manda ao login quem nao entrou — vale so onde e chamada |
| `exigirFormularioValido()` | exige POST e token valido de uma vez |
| `voltarComErros($erros, $rota)` | devolve ao formulario com as mensagens por campo |
