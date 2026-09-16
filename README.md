# Framework MVC Didatico em PHP

Base MVC para criar aplicacoes PHP sem dependencias externas. O projeto comeca
sem tabelas, dados ou entidades predefinidas.

Requisitos: PHP 8.1 ou superior e MySQL/MariaDB (o do XAMPP serve).

## Inicio rapido

Antes: inicie o MySQL (painel do XAMPP) e confira usuario e senha em
`configuracoes/banco.php`.

```bash
php instalar.php
php console.php auth:install
php console.php scaffold:crud produtos nome:string preco:decimal estoque:integer --auth
php console.php scaffold:pesquisa produtos nome preco
php console.php scaffold:paginacao produtos
php console.php db:semear produtos 50   # atalho; os dados do sistema vao em banco/semear.php
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
`datetime`, `time`, `arquivo` e `imagem`. O comando gera modelo (com regras de validacao),
controller, views, testes de model e de controller, e a tabela em
`banco/esquema.sql`. Ele nao sobrescreve arquivos existentes.

Opcoes:

| Opcao | O que faz |
|---|---|
| `--auth` | exige login em todas as rotas do recurso: usa `/auth` ou, quando so existe uma tela de login instalada, essa tela |
| `--auth=prefixo` | mesma coisa, escolhendo o provider (`/auth-prefixo`) |
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

### Acrescentar um campo depois

```bash
php console.php scaffold:campo produtos peso:decimal disponivel:boolean
```

O `scaffold:crud` nao sobrescreve arquivos, entao ele nao serve para mexer num
recurso que ja existe. O `scaffold:campo` faz isso sem apagar o que voce
escreveu: model, controller, as tres views, os dois testes, `banco/esquema.sql`
e a tabela no banco.

O resultado e igual ao que o `scaffold:crud` teria gerado com o campo desde o
comeco — os dois caminhos abaixo produzem arquivos identicos:

```bash
php console.php scaffold:crud produtos nome:string preco:decimal peso:decimal

php console.php scaffold:crud produtos nome:string preco:decimal
php console.php scaffold:campo produtos peso:decimal
```

Vale para relacao tambem, com chave estrangeira, `<select>` no formulario e
tabela pai dentro do teste:

```bash
php console.php scaffold:campo produtos categoria_id:belongs_to=categorias
```

| Opcao | O que faz |
|---|---|
| `--obrigatorio` | o campo nasce com `->obrigatorio()` no `validar()` |
| `--remover` | tira o campo do CRUD e **apaga a coluna do banco** |
| `--forcar` | com `--remover`, nao pergunta antes de apagar |

```bash
php console.php scaffold:campo produtos peso --remover
```

Como o `--remover` apaga dados, ele pergunta antes de continuar. A ida e a
volta devolvem os arquivos exatamente ao estado anterior. O primeiro campo do
recurso nao sai: e dele que saem a regra obrigatoria e as asercoes dos testes.

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

### Paginacao na listagem

```bash
php console.php scaffold:paginacao produtos --por-pagina=15
```

A listagem gerada traz a tabela inteira — com trinta mil registros, a tela nao
abre. O comando quebra a listagem em paginas, cortando no banco com `LIMIT`, e
poe a barra de navegacao embaixo da tabela (`/produtos?pagina=3`).

Ele se encaixa com a pesquisa **em qualquer ordem**. Com as duas instaladas, o
total de paginas sai do resultado do filtro, e trocar de pagina nao perde o que
foi digitado.

Os metodos ficam disponiveis em qualquer model, com ou sem o comando:

```php
$pagina = $this->modelo->paginar($this->get('pagina'), 20);
$pagina = $this->modelo->paginarConsulta($sql, $parametros, $this->get('pagina'), 20);

$pagina->registros   $pagina->total   $pagina->paginas()   $pagina->resumo()
```

Na view: `<?= paginacao($pagina ?? null) ?>`.

`?pagina=abc` vira a pagina 1, pedir a pagina 90 de uma lista com 4 devolve a
ultima, e o tamanho da pagina tem teto — nenhum deles quebra a tela.

Para desfazer: `php console.php scaffold:paginacao produtos --remover`.

### Dados iniciais (semeadura)

```bash
php console.php db:semear
```

Os dados ficam em **`banco/semear.php`**, um arquivo PHP do projeto: voce
escreve, o comando cria. E onde moram as categorias do catalogo, os status de
um pedido, a conta de administrador. Como e um arquivo versionado, quem clonar
o repositorio roda um comando e tem o mesmo banco que voce.

```php
<?php

$categorias = semear('categorias', [
    ['nome' => 'Eletronicos'],
    ['nome' => 'Moveis'],
], 'nome');

semear('produtos', [
    ['nome' => 'Teclado', 'preco' => 149.90, 'categoria_id' => $categorias['Eletronicos']],
    ['nome' => 'Mesa',    'preco' => 450.00, 'categoria_id' => $categorias['Moveis']],
]);

semear('usuarios', [
    ['nome' => 'Administrador', 'email' => 'admin@example.com', 'senha' => 'segredo123'],
], 'email');

falsos('produtos', 50);
```

As tres funcoes disponiveis dentro dele:

| Funcao | O que faz |
|---|---|
| `semear('tabela', [...])` | cria os registros e devolve os ids |
| `semear('tabela', [...], 'coluna')` | pula quem ja existe e devolve os ids indexados por ela |
| `falsos('tabela', 50)` | enche com dados inventados, para a tela nao ficar vazia |
| `limpar()` / `limpar('produtos')` | apaga antes de comecar |

O terceiro argumento do `semear()` e o que deixa **rodar o comando quantas
vezes quiser sem duplicar** — e o id volta assim mesmo, entao as relacoes
continuam funcionando. Sem ele, rodar duas vezes cria tudo de novo (como o
`db/seeds.rb` do Rails); para recomecar, `php console.php db:semear --limpar`.

O arquivo inteiro roda dentro de uma transacao: um erro na linha 40 nao deixa
metade gravada. E uma coluna chamada `senha` recebe `password_hash()` sozinha,
entao a conta semeada entra pela tela de login.

O `falsos()` inventa os valores a partir do nome e do tipo de cada coluna:
`preco` vira dinheiro, `email` vira e-mail sempre diferente, `telefone` sai no
formato brasileiro, `categoria_id` sorteia uma categoria que existe. O `nome`
de uma tabela de gente recebe nome de pessoa; nas outras vira `Produto 1`,
`Categoria 2` — que se le bem dentro de um `<select>`.

Para encher uma tela depressa sem abrir o arquivo, ha o atalho:

```bash
php console.php db:semear produtos 30
php console.php db:semear --tudo 20 --limpar
```

E `--semente=7` repete sempre os mesmos dados, util quando a turma inteira
precisa ver a mesma tela.

### Arquivos e imagens

```bash
php console.php scaffold:crud produtos nome:string foto:imagem ficha:arquivo
php console.php scaffold:campo produtos foto:imagem
```

| Tipo | Aceita | Na tela |
|---|---|---|
| `imagem` | jpg, jpeg, png, gif, webp | miniatura clicavel |
| `arquivo` | pdf, doc(x), xls(x), odt, ods, csv, txt, zip | link "abrir" |

A coluna guarda o **caminho**; o arquivo vai para `views/uploads/`, com nome
sorteado. O formulario ganha o `enctype` (sem ele nada chega ao servidor), e o
controller ganha o tratamento inteiro: campo em branco mantem o arquivo atual,
arquivo novo apaga o anterior do disco, e excluir o registro apaga os arquivos
dele.

Receber arquivo e a porta de entrada mais perigosa de um site, entao
`Nucleo\Arquivo` exige tres coisas:

- **veio mesmo de um upload** (`is_uploaded_file`), e nao de um caminho
  qualquer do servidor;
- **a extensao esta em uma lista fechada** — o tipo informado pelo navegador e
  escrito pelo proprio navegador. Em campo `imagem` o conteudo ainda passa por
  `getimagesize()`, entao um `.php` renomeado para `.jpg` e recusado;
- **o nome gravado e sorteado**, nunca o que veio junto: `../../index.php`
  sairia da pasta de uploads.

Alem disso, o `.htaccess` de `views/uploads/` e o roteador recusam qualquer
`.php` — um executavel que escapasse da lista ainda assim nao rodaria.

Fora do scaffold:

```php
$foto = $this->imagem('foto', 1024);   // so imagem, ate 1 MB
$foto->problema();                     // o que esta errado, ou null
$dados['foto'] = $foto->salvar('produtos');
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

As telas de entrar e criar conta usam `views/template/layout-login.php` — uma
pagina isolada, sem o menu lateral do sistema.

Se o model ja tinha CRUD, o comando tambem leva `email` e `senha` para ele: o
formulario ganha os dois campos, o `salvar()`/`atualizar()` do controller
passa a recebe-los e o `validar()` do model confere e-mail valido e sem
repetir e senha com 6+ caracteres. Os dois continuam opcionais (um registro
sem eles so ainda nao tem conta), e senha em branco na edicao mantem a atual.

As senhas nunca vao para o banco em texto puro: o trait aplica
`password_hash()` em `criar()`, `atualizar()` e `criarComSenha()`, e o login
confere com `password_verify()`.

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

### Perfis de acesso

```bash
php console.php auth:perfis admin,coordenador,professor
```

`exigirAutenticacao()` responde "quem e voce?"; perfil responde "voce pode?".
Sao perguntas diferentes: o aluno e o coordenador estao os dois logados, e so
um deles pode apagar uma turma.

O comando escreve a lista em `configuracoes/perfis.php`, cria a coluna
`perfil` na tabela das contas e, quando o model tem CRUD, poe a lista como
`<select>` no formulario e a coluna na listagem.

```php
$this->exigirPerfil('admin');                  // so admin
$this->exigirPerfil(['admin', 'coordenador']); // qualquer um dos dois
$this->exigirPerfil('admin', 'professor');     // no login /auth-professor
```

Ela ja chama `exigirAutenticacao()` antes, entao quem nem entrou vai para o
login — e nao para a mensagem de "sem permissao".

Nas views e no menu:

```php
<?php if (tem_perfil('admin')): ?> ... <?php endif ?>

['rota' => 'usuarios', 'texto' => 'Usuarios', 'perfil' => 'admin'],
```

Esconder o item e cortesia com quem usa, **nao seguranca**: quem protege a
rota e o `exigirPerfil()` no controller.

O perfil vem do banco a cada requisicao, e nao da sessao — tirar o `admin` de
alguem vale na hora, sem esperar o proximo login. Depois de rodar o comando
nenhuma conta tem perfil ainda; defina pelo CRUD ou no banco:

```sql
UPDATE usuarios SET perfil = 'admin' WHERE id = 1;
```

Cada tela de login tem a sua lista: `php console.php auth:perfis admin,gerente Cliente`.

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
  perder o que ja tinha sido digitado;
- os arquivos enviados passam por uma lista fechada de extensoes, ganham nome
  sorteado e caem em uma pasta onde nenhum `.php` e executado;
- `exigirPerfil()` separa "esta logado" de "pode fazer isso";
- todo nome de tabela e de coluna sai entre crases no SQL, entao nomes
  reservados pelo MySQL (`rank`, `system`, `groups`, `manual`) podem ser
  usados sem quebrar nada.

## Testes

```bash
php testes/executar.php              # tudo
php testes/executar.php ProdutoTest  # filtro por classe ou metodo
```

Os testes rodam em um banco MySQL proprio (`banco_testes` em
`configuracoes/banco.php`), apagado e recriado a cada execucao, e cada classe
recria as proprias tabelas — a ordem de execucao nao muda o resultado e os
dados da aplicacao nunca sao tocados.

## Documentacao

- [Tutorial: do zero a um sistema completo](documentacao/Tutorial-Comandos.md) — constroi
  uma aplicacao inteira, passo a passo
- [Referencia de comandos](documentacao/Referencia-Comandos.md) — sintaxe completa
