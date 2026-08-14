# Framework MVC Didático em PHP

Estrutura base para a disciplina de **Desenvolvimento Web** do curso técnico.

Tudo já vem pré-configurado: o aluno cria uma classe, **herda** de `Nucleo\Controller`
ou `Nucleo\Model`, e já tem rota, banco de dados, validação, template e testes
funcionando — sem instalar nada e sem configurar nada.

```
Requisitos: PHP 8.1 ou superior e MySQL/MariaDB (ambos já vêm no XAMPP).
Nenhuma dependência externa. Nenhum Composer.
```

O projeto vem configurado para **MySQL**. O instalador cria o banco sozinho —
não é preciso mexer no phpMyAdmin.

> 📄 **Material para a turma:** [`documentacao/Guia-do-Aluno.pdf`](documentacao/Guia-do-Aluno.pdf)
> — 42 páginas explicando cada parte do sistema, como adicionar tabelas,
> modelos, controladores e views, e o que dá para configurar.
> Para regerar depois de editar o HTML: `python documentacao/gerar-pdf.py`.

---

## 1. Como colocar para rodar

### Opção A — XAMPP (recomendado)

1. Copie a pasta do projeto para dentro de `htdocs`.
2. No painel do XAMPP, inicie o **Apache** e o **MySQL**.
3. Acesse `http://localhost/phpprojeto/instalar.php` uma vez.
4. Acesse `http://localhost/phpprojeto`.

> O `.htaccess` já está configurado. Se as rotas derem 404, ative o
> `mod_rewrite` no Apache e confira se `AllowOverride All` está ligado.

### Comandos no terminal: use o caminho completo do PHP

O XAMPP **não** registra o PHP no Windows. Digitar só `php` resulta em
`O termo 'php' nao e reconhecido...` — por isso todos os comandos abaixo usam
`C:\xampp\php\php.exe`.

Abra o PowerShell **dentro da pasta do projeto** (botão direito na pasta →
*Abrir no Terminal*).

Para criar um apelido e digitar menos — vale só na janela atual:

```powershell
Set-Alias php C:\xampp\php\php.exe
```

### Opção B — servidor embutido do PHP

Com o MySQL do XAMPP iniciado:

```powershell
C:\xampp\php\php.exe instalar.php                    # cria o banco e os dados
C:\xampp\php\php.exe -S localhost:8000 roteador.php  # liga o servidor
```

Abra <http://localhost:8000>.

### Rodar os testes

```powershell
C:\xampp\php\php.exe testes/executar.php
```

---

## 2. Estrutura de pastas

```
framework/
│
├── index.php              Front controller: toda requisição passa por aqui
├── roteador.php           Faz o papel do .htaccess no servidor embutido
├── instalar.php           Cria as tabelas e os dados de exemplo
├── .htaccess              Reescrita de URL para o Apache
│
├── configuracoes/         CONFIGURAÇÕES
│   ├── app.php              nome do site, debug, fuso horário, rota padrão
│   └── banco.php            driver (sqlite ou mysql), host, usuário, senha
│
├── nucleo/                O FRAMEWORK (o que você herda; não precisa mexer)
│   ├── bootstrap.php        liga tudo: caminhos, autoloader, config, sessão
│   ├── Autoloader.php       carrega as classes sozinho (PSR-4)
│   ├── App.php              roteador: transforma a URL em controlador/método
│   ├── Controller.php       classe base dos controladores
│   ├── Model.php            classe base dos modelos (CRUD pronto)
│   ├── View.php             monta as telas dentro do template
│   ├── Database.php         conexão PDO (SQLite ou MySQL)
│   ├── Validador.php        validação de formulários
│   ├── Sql.php              proteções contra SQL Injection
│   ├── Sessao.php           sessão e mensagens de sucesso/erro
│   ├── Config.php           leitura das configurações
│   └── helpers.php          funções de atalho: e(), url(), asset()...
│
├── controllers/           CONTROLADORES (o que você escreve)
│   ├── HomeController.php
│   └── AlunosController.php   CRUD completo de exemplo
│
├── modelos/               MODELOS (o que você escreve)
│   └── Aluno.php
│
├── views/                 TELAS
│   ├── template/            layout, cabeçalho, rodapé, mensagens
│   ├── css/                 estilo.css
│   ├── javascripts/         app.js
│   ├── imagens/             logos, fotos, ícones
│   ├── home/                telas do HomeController
│   ├── alunos/              telas do AlunosController
│   └── erros/               404 e 500
│
├── testes/                TESTES
│   ├── executar.php         roda todos os testes de uma vez
│   ├── bootstrap.php        usa um banco descartável, em memória
│   ├── suporte/             o motor de testes (TesteBase, Executor...)
│   ├── modelos/             testes dos modelos
│   ├── controllers/         testes dos controladores
│   ├── nucleo/              testes do framework (rotas, validação, segurança)
│   └── exemplos/            modelo comentado para o aluno copiar
│
└── banco/                 BANCO DE DADOS
    ├── esquema.mysql.sql    estrutura para MySQL (o padrão do projeto)
    ├── esquema.sqlite.sql   estrutura para SQLite (driver alternativo)
    ├── dados_exemplo.sql    registros iniciais (serve para os dois)
    └── dados.sqlite         arquivo usado só no driver sqlite
```

---

## 3. Como uma requisição funciona

```
Navegador  →  .htaccess / roteador.php  →  index.php  →  Nucleo\App
                                                             ↓
                              descobre o controlador pela URL
                                                             ↓
                                       Controllers\AlunosController::ver(5)
                                              ↓                    ↓
                                     Modelos\Aluno  ←→  banco de dados
                                              ↓
                        views/alunos/ver.php dentro de views/template/layout.php
                                              ↓
                                       HTML no navegador
```

### Regra das rotas

O endereço sempre segue `/controlador/metodo/parametros`:

| URL                  | Executa                                |
|----------------------|----------------------------------------|
| `/`                  | `HomeController::index()`              |
| `/alunos`            | `AlunosController::index()`            |
| `/alunos/criar`      | `AlunosController::criar()`            |
| `/alunos/ver/7`      | `AlunosController::ver(7)`             |
| `/alunos/editar/7`   | `AlunosController::editar(7)`          |
| `/nota-final`        | `NotaFinalController::index()`         |

Não existe arquivo de rotas para configurar: **criou o arquivo, a rota funciona.**

Só viram rota os métodos `public`. Métodos `private`/`protected` ficam
protegidos, então use-os para o código de apoio do controlador.

---

## 4. Criando o seu primeiro CRUD

### Passo 1 — a tabela

Adicione em `banco/esquema.mysql.sql`:

```sql
CREATE TABLE professores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    disciplina VARCHAR(60)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> Se também for usar o SQLite, repita a tabela em `banco/esquema.sqlite.sql`
> com a sintaxe dele: `id INTEGER PRIMARY KEY AUTOINCREMENT`, `TEXT` no lugar
> de `VARCHAR` e sem o `ENGINE`.

Depois rode o `instalar.php` de novo.

### Passo 2 — o modelo

`modelos/Professor.php`:

```php
<?php

namespace Modelos;

use Nucleo\Model;

class Professor extends Model
{
    protected string $tabela       = 'professores';
    protected array  $preenchiveis = ['nome', 'disciplina'];
    protected string $ordemPadrao  = 'nome ASC';
}
```

Pronto — por herança você já tem:

| Método                          | O que faz                              |
|---------------------------------|----------------------------------------|
| `todos()`                       | lista tudo                             |
| `buscar($id)`                   | um registro, ou `null`                 |
| `onde('disciplina', 'Fisica')`  | filtra por uma coluna                  |
| `primeiroOnde($col, $valor)`    | o primeiro que casar, ou `null`        |
| `criar([...])`                  | insere e devolve o id                  |
| `atualizar($id, [...])`         | edita                                  |
| `excluir($id)`                  | apaga                                  |
| `contar()`                      | quantos registros existem              |
| `existe($id)`                   | true/false                             |
| `consultar($sql, $parametros)`  | SELECT livre (com `?`)                 |
| `executar($sql, $parametros)`   | INSERT/UPDATE/DELETE livre (com `?`)   |

### Passo 3 — o controlador

`controllers/ProfessoresController.php`:

```php
<?php

namespace Controllers;

use Nucleo\Controller;

class ProfessoresController extends Controller
{
    public function index(): void
    {
        $this->view('professores/index', [
            'titulo'      => 'Professores',
            'professores' => $this->modelo('Professor')->todos(),
        ]);
    }
}
```

Métodos que você ganha por herança:

| Método                            | O que faz                                  |
|-----------------------------------|--------------------------------------------|
| `$this->view($tela, $dados)`      | desenha a tela dentro do template          |
| `$this->viewSemLayout(...)`       | desenha sem template (AJAX)                |
| `$this->modelo('Professor')`      | instancia um modelo                        |
| `$this->post('nome')`             | lê um campo do formulário (com `trim`)     |
| `$this->get('busca')`             | lê da query string (`?busca=...`)          |
| `$this->todosOsCampos()`          | todo o `$_POST` limpo                      |
| `$this->ehPost()`                 | o formulário foi enviado?                  |
| `$this->validador()`              | valida os dados recebidos                  |
| `$this->redirecionar('alunos')`   | manda para outra rota                      |
| `$this->mensagem('sucesso', ...)` | mensagem que aparece na próxima tela       |
| `$this->json([...])`              | responde em JSON                           |
| `$this->naoEncontrado()`          | mostra a tela 404                          |

### Passo 4 — a tela

`views/professores/index.php`:

```php
<h1>Professores</h1>

<ul>
    <?php foreach ($professores as $professor): ?>
        <li><?= e($professor['nome']) ?> — <?= e($professor['disciplina']) ?></li>
    <?php endforeach ?>
</ul>
```

Acesse `/professores`. Funcionando.

---

## 5. Funções de atalho nas views

| Função                        | Para que serve                                      |
|-------------------------------|-----------------------------------------------------|
| `e($texto)`                   | **escapa HTML — use sempre ao exibir dados!**       |
| `url('alunos/ver/7')`         | monta link interno                                  |
| `asset('css/estilo.css')`     | caminho de css/js/imagem dentro de `views/`         |
| `parcial('template/rodape')`  | inclui um pedaço de tela reaproveitável             |
| `antigo('nome')`              | repõe o que foi digitado após erro de validação     |
| `erro_de('email')`            | mensagem de erro daquele campo                      |
| `tem_erro('email')`           | true/false, para destacar o campo                   |
| `mensagens()`                 | mensagens de sucesso/erro (flash)                   |
| `data_br('2026-08-12')`       | `12/08/2026`                                        |
| `moeda_br(1234.5)`            | `1.234,50`                                          |
| `dd($variavel)`               | depuração: mostra o conteúdo e para a execução      |

---

## 6. Validação de formulários

No modelo, sobrescreva `validar()`:

```php
public function validar(array $dados, int|string|null $ignorarId = null): array
{
    $v = new Validador($dados);

    $v->obrigatorio('nome', 'Nome')
      ->minimo('nome', 3)
      ->maximo('nome', 100)
      ->obrigatorio('email')
      ->email('email')
      ->numerico('nota')
      ->entre('nota', 0, 10)
      ->dentroDe('curso', self::CURSOS)
      ->personalizada('idade', $dados['idade'] >= 16, 'Precisa ter 16 anos.');

    return $v->erros();
}
```

No controlador:

```php
$dados = $this->todosOsCampos();
$erros = $this->modelo('Aluno')->validar($dados);

if ($erros !== []) {
    Sessao::guardarEntrada($dados);   // repõe o que foi digitado
    Sessao::guardarErros($erros);     // mostra os erros no formulário
    $this->mensagem('erro', 'Corrija os campos destacados.');
    $this->redirecionar('alunos/criar');
}
```

Na view, os helpers `antigo()`, `erro_de()` e `tem_erro()` cuidam do resto —
veja `views/alunos/formulario.php`.

---

## 7. Segurança

O framework já vem protegido. Vale explicar o porquê em aula:

### SQL Injection

Existem **dois tipos** de coisa dentro de um comando SQL, e cada um tem o seu
tratamento:

**1. Valores** (o que o usuário digita) — nunca entram no texto do SQL:

```php
// CERTO: o valor vai separado, como parâmetro
$this->consultar('SELECT * FROM alunos WHERE nome = ?', [$nome]);

// ERRADO: nunca faça isso
$this->consultar("SELECT * FROM alunos WHERE nome = '$nome'");
```

Todos os métodos herdados (`buscar`, `onde`, `criar`, `atualizar`, `excluir`)
já usam *prepared statements*, com `PDO::ATTR_EMULATE_PREPARES = false`.

**2. Identificadores** (nome de tabela, de coluna, `ASC`/`DESC`, operadores) —
a linguagem SQL não permite que sejam parâmetro, então passam pela classe
`Nucleo\Sql`, que só aceita letras, números e `_`, e operadores de uma lista
fechada. Isso vale inclusive para as chaves do `$_POST` que viram colunas no
`INSERT`.

```php
$modelo->onde('nome; DROP TABLE alunos', 'x');   // InvalidArgumentException
$modelo->todos('nota; DELETE FROM alunos');      // InvalidArgumentException
```

Para buscas com `LIKE`, use `Sql::comoLike($termo)` — ele neutraliza os
curingas `%` e `_` que o usuário possa digitar.

Os ataques estão demonstrados em `testes/nucleo/SegurancaSqlTest.php`: rode
`C:\xampp\php\php.exe testes/executar.php SegurancaSql` para ver o framework resistindo a
`DROP TABLE`, `OR 1=1`, `--` e injeção por nome de coluna.

### XSS

Sempre use `e()` ao exibir dados vindos do usuário:

```php
<?= e($aluno['nome']) ?>
```

### Mass assignment

`$preenchiveis` no modelo define quais campos podem ser gravados. Um formulário
adulterado com `id=999` não consegue sobrescrever a chave primária.

### Acesso direto aos arquivos

`nucleo/`, `modelos/`, `controllers/`, `configuracoes/`, `testes/` e `banco/`
têm cada um o seu `.htaccess` bloqueando acesso pelo navegador, e o
`roteador.php` faz o mesmo no servidor embutido.

---

## 8. Testes

Estrutura:

```
testes/
├── executar.php       roda os testes
├── suporte/           o motor (TesteBase, Executor, Resposta)
├── exemplos/          ExemploTest.php — todas as verificações comentadas
├── modelos/           AlunoTest.php
├── controllers/       AlunosControllerTest.php
└── nucleo/            RoteamentoTest, ValidadorTest, ViewTest, SegurancaSqlTest
```

Regras: arquivo terminando em `Test.php`, classe herdando de `TesteBase`,
métodos começando com `teste`. A subpasta vira o namespace.

```php
<?php

namespace Testes\Modelos;

use Testes\Suporte\TesteBase;

class ProfessorTest extends TesteBase
{
    public function preparar(): void       // roda antes de cada teste
    {
        $this->limparTabela('professores');
    }

    public function testeCadastraProfessor(): void
    {
        $modelo = new \Modelos\Professor();

        $id = $modelo->criar(['nome' => 'Marcos', 'disciplina' => 'Fisica']);

        $this->assertVerdadeiro($id > 0);
        $this->assertIgual('Marcos', $modelo->buscar($id)['nome']);
    }
}
```

### Verificações disponíveis

| Assertion                              | Verifica                          |
|----------------------------------------|-----------------------------------|
| `assertIgual($a, $b)`                  | iguais (`==`)                     |
| `assertIdentico($a, $b)`               | iguais e do mesmo tipo (`===`)    |
| `assertDiferente($a, $b)`              | diferentes                        |
| `assertVerdadeiro(...)` / `assertFalso(...)` | booleano                    |
| `assertNulo(...)` / `assertNaoNulo(...)`     | null                        |
| `assertVazio(...)` / `assertNaoVazio(...)`   | vazio                       |
| `assertContem($trecho, $texto)`        | texto contém                      |
| `assertNaoContem($trecho, $texto)`     | texto não contém                  |
| `assertTotal(3, $array)`               | quantidade de itens               |
| `assertTemChave('nome', $array)`       | chave existe                      |
| `assertTemValor(7, $array)`            | valor existe                      |
| `assertInstanciaDe(Classe::class, $o)` | tipo do objeto                    |
| `assertExcecao(Classe::class, fn)`     | dispara a exceção esperada        |

### Testando controladores

`requisitar()` simula o navegador de ponta a ponta:

```php
$resposta = $this->requisitar('alunos');                       // GET
$resposta = $this->postar('alunos/salvar', ['nome' => 'Ana']); // POST

$resposta->html                     // o HTML gerado
$resposta->status                   // 200, 302, 404
$resposta->foiRedirecionado()
$resposta->redirecionouPara('alunos')
$resposta->json()                   // decodifica resposta JSON
```

Ajudantes de banco: `limparTabela()`, `contarNaTabela()`, `limparSessao()`.

### Rodando parte dos testes

```powershell
# Abreviado com Set-Alias php C:\xampp\php\php.exe (veja a secao 1)
php testes/executar.php                    # tudo
php testes/executar.php Modelos            # só a pasta modelos
php testes/executar.php Controllers        # só a pasta controllers
php testes/executar.php SegurancaSql       # só um arquivo
php testes/executar.php validacao          # qualquer teste com "validacao" no nome
```

> Os testes usam um **SQLite em memória**, criado do zero a cada execução.
> Rodar os testes nunca mexe no banco do sistema.

---

## 9. O banco de dados

O sistema já vem no **MySQL**, configurado em `configuracoes/banco.php`:

| Item    | Valor padrão (XAMPP) |
|---------|----------------------|
| host    | `localhost:3306`     |
| banco   | `framework_aula`     |
| usuário | `root`               |
| senha   | *(vazia)*            |

O `instalar.php` faz tudo sozinho:

1. cria o banco `framework_aula` se ele ainda não existir;
2. executa `banco/esquema.mysql.sql` (tabelas);
3. executa `banco/dados_exemplo.sql` (8 alunos de exemplo).

Pode rodar quantas vezes quiser — a tabela é recriada do zero.
Para ver os dados no phpMyAdmin: <http://localhost/phpmyadmin>.

### Voltar para o SQLite

Em `configuracoes/banco.php` troque `'driver' => 'sqlite'` e rode
o `instalar.php` de novo. O banco vira um único arquivo em
`banco/dados.sqlite`, sem precisar de servidor.

Nada mais muda: os modelos e controladores continuam iguais nos dois casos.

---

## 10. Sugestões de atividades para a turma

1. Criar o CRUD de **Professores** copiando o de Alunos.
2. Criar o CRUD de **Disciplinas** e ligar cada aluno a uma disciplina.
3. Adicionar o campo `telefone` com validação de formato (regra personalizada).
4. Fazer uma tela de relatório com os alunos aprovados e reprovados.
5. Escrever os testes de cada item acima — a entrega só vale com os testes
   passando.
6. Trocar as cores do sistema editando as variáveis no topo de
   `views/css/estilo.css`.
7. Consumir `/alunos/api` com `fetch()` e montar a lista sem recarregar a página.

---

## 11. Problemas comuns

| Sintoma                                   | Causa provável                                                        |
|-------------------------------------------|-----------------------------------------------------------------------|
| Todas as rotas dão 404 no XAMPP           | `mod_rewrite` desativado ou `AllowOverride None`                      |
| CSS não carrega                           | use sempre `asset('css/estilo.css')`, nunca caminho fixo              |
| "Controlador não encontrado"              | nome da classe precisa terminar em `Controller` e casar com o arquivo |
| "Método não acessível"                    | o método precisa ser `public`                                         |
| "View não encontrada"                     | confira o caminho: `alunos/index` → `views/alunos/index.php`          |
| "O termo 'php' não é reconhecido"         | use o caminho completo: `C:\xampp\php\php.exe testes/executar.php`     |
| "Could not open input file"               | o terminal está em outra pasta; entre em `htdocs/phpprojeto` primeiro |
| "Não foi possível conectar ao banco"      | inicie o **MySQL** no painel do XAMPP e rode o `instalar.php`         |
| "Base table or view not found"            | rode o `instalar.php` para criar as tabelas                           |
| "Access denied for user 'root'"           | ajuste usuário/senha em `configuracoes/banco.php`                     |
| Erro de coluna inválida                   | é a proteção contra SQL Injection: nome de coluna só com letras/`_`   |
| Página em branco                          | veja o terminal; com `debug => true` o erro aparece na tela           |
