# Framework MVC Didatico em PHP

Base MVC para criar aplicacoes PHP sem dependencias externas. O projeto comeca
sem tabelas, dados ou entidades predefinidas.

Requisitos: PHP 8.1 ou superior e SQLite ou MySQL/MariaDB.

## Inicio rapido

```bash
php instalar.php
php console.php scaffold:crud produtos nome:string preco:decimal estoque:integer
php console.php auth:install
php -S localhost:8000 roteador.php
```

Acesse `http://localhost:8000/produtos` e
`http://localhost:8000/auth/registrar`.

## Comandos

### Scaffold CRUD

```bash
php console.php scaffold:crud tabela campo:tipo campo2:tipo
```

Tipos aceitos: `string`, `text`, `integer`, `decimal`, `boolean`, `date`,
`datetime` e `time`. O comando gera modelo, controller, views, teste e esquema para
SQLite/MySQL. Ele nao sobrescreve arquivos existentes.

Quando a tabela ja existe, o scaffold preserva os dados e adiciona as colunas
que ainda estiverem ausentes.

### Autenticacao

```bash
php console.php auth:install
```

Gera a tabela `usuarios`, o modelo, as telas de login/cadastro e as rotas
`/auth/login`, `/auth/registrar` e `/auth/sair`. As senhas usam
`password_hash()` e `password_verify()`.

Para proteger uma pagina:

```php
public function index(): void
{
    $this->exigirAutenticacao();
    $this->view('relatorios/index', ['titulo' => 'Relatorios']);
}
```

## Estrutura

```text
controllers/       Controllers da aplicacao
modelos/           Models da aplicacao
views/             Views e arquivos estaticos
nucleo/             Classes do framework MVC
banco/             Esquemas gerados para SQLite e MySQL
documentacao/      Tutoriais
testes/             Suite de testes do framework
console.php        Comandos de geracao
instalar.php       Inicializacao do banco
```

## Testes

```bash
php testes/executar.php
```

Consulte [documentacao/Tutorial-Comandos.md](documentacao/Tutorial-Comandos.md)
para um passo a passo ou a [Referencia de comandos](documentacao/Referencia-Comandos.md)
para a lista completa de comandos disponiveis.
