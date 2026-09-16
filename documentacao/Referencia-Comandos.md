# Referencia de comandos

Este documento lista os comandos disponiveis no framework. Execute todos a
partir da pasta raiz do projeto.

Para o passo a passo comentado — uma aplicacao construida do zero ao fim —
veja o [Tutorial](Tutorial-Comandos.md).

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
- cria o banco da aplicacao, caso ainda nao exista;
- cria o banco dos testes (`banco_testes`), caso ainda nao exista;
- executa `banco/esquema.sql`;
- nao cria tabelas ou dados de exemplo por conta propria.

O banco e MySQL/MariaDB — o do XAMPP serve. Inicie o servico antes e confira
usuario e senha em `configuracoes/banco.php`. Depois de mexer no esquema,
rode o instalador de novo.

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
banco/esquema.sql
configuracoes/menu.php
```

Depois executa o esquema do banco configurado. Se a tabela ja existir, as
colunas novas sao adicionadas sem apagar os dados.

Nada e gravado antes de toda a validacao passar: se algum passo falhar,
nenhum arquivo fica pela metade e o esquema volta ao estado anterior.

### 3.1 Opcoes

| Opcao | Efeito |
|---|---|
| `--auth` | exige login em todas as acoes do controller: usa o provider padrao (`/auth`) ou, quando so existe uma tela de login instalada, essa tela |
| `--auth=prefixo` | idem, escolhendo o provider (`/auth-prefixo`) |
| `--modelo=Nome` | define a classe do model em vez de derivar do plural |
| `--sem-menu` | nao acrescenta o recurso a `configuracoes/menu.php` |
| `-v` | mostra os detalhes tecnicos quando o comando falha |

`--auth` exige que a tela de login ja exista. Rode `auth:install` antes; caso
contrario o comando para e mostra qual comando falta:

```text
[ERRO] A tela de login /auth ainda nao existe.
Instale-a antes:
  php console.php auth:install
```

Com **duas ou mais** telas instaladas, `--auth` sozinho nao adivinha qual
usar e o comando lista as opcoes:

```text
[ERRO] A tela de login /auth nao existe.
Telas instaladas: /auth-aluno, /auth-professor.
Use --auth=<prefixo> com uma delas ou instale a nova:
  php console.php auth:install
```

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

| Tipo | Coluna no MySQL | Campo HTML |
|---|---|---|
| `string` | `VARCHAR(255)` | `input type="text"` |
| `text` | `TEXT` | `textarea` |
| `integer` | `INT` | `input type="number"` |
| `decimal` | `DECIMAL(12,2)` | `input type="number" step="0.01"` |
| `boolean` | `TINYINT(1)` | checkbox (grava `1` ou `0`) |
| `date` | `DATE` | `input type="date"` |
| `datetime` | `DATETIME` | `input type="datetime-local"` |
| `time` | `TIME` | `input type="time"` |
| `arquivo` | `VARCHAR(255)` | `input type="file"` (guarda o caminho) |
| `imagem` | `VARCHAR(255)` | `input type="file"` so de imagem |

Os dois ultimos tem secao propria: veja [8. Arquivos e imagens](#8-arquivos-e-imagens).

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
- o primeiro campo nao pode ser `arquivo` nem `imagem`: e dele que saem o
  titulo da listagem, a regra obrigatoria e as asercoes dos testes;
- o comando nao sobrescreve arquivos existentes — para mexer em um recurso
  pronto, use o [scaffold:campo](#4-acrescentar-ou-tirar-um-campo).

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
- criar a restricao `FOREIGN KEY` em `banco/esquema.sql`;
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

## 4. Acrescentar ou tirar um campo

```bash
php console.php scaffold:campo <tabela|Modelo> <campo:tipo> [campo2:tipo ...] [opcoes]
php console.php scaffold:campo <tabela|Modelo> <campo> [campo2 ...] --remover
```

Exemplo:

```bash
php console.php scaffold:campo produtos peso:decimal disponivel:boolean
```

O `scaffold:crud` nao sobrescreve arquivos: uma vez gerado o recurso, ele nao
serve mais para acrescentar uma coluna. E o que o `scaffold:campo` faz, sem
apagar nada do que voce escreveu.

Ele **altera** tudo que o CRUD tem:

```text
modelos/Produto.php                              ($preenchiveis e a regra de validacao)
controllers/ProdutosController.php               (o $dados e o filtro do relatorio)
views/produtos/formulario.php                    (o campo do formulario)
views/produtos/index.php                         (a coluna da tabela)
views/produtos/ver.php                           (a linha do detalhe)
testes/modelos/ProdutoTest.php                   (a tabela e os dados do teste)
testes/controllers/ProdutosControllerTest.php    (idem)
banco/esquema.sql                                (o CREATE TABLE)
a tabela no banco                                (ALTER TABLE ADD COLUMN)
```

O resultado e **igual** ao que o `scaffold:crud` teria gerado se o campo
estivesse na linha de comando desde o comeco. Estes dois caminhos produzem
arquivos identicos:

```bash
php console.php scaffold:crud produtos nome:string preco:decimal peso:decimal

php console.php scaffold:crud produtos nome:string preco:decimal
php console.php scaffold:campo produtos peso:decimal
```

Os tipos sao os mesmos do `scaffold:crud`, `belongs_to=` incluido:

```bash
php console.php scaffold:campo produtos categoria_id:belongs_to=categorias
```

Numa relacao o comando faz o pacote completo: a chave estrangeira no banco, o
metodo `categorias()` no model, o `<select>` no formulario, a lista no
`criar()`/`editar()` do controller e a tabela pai dentro do teste.

### 4.1 Opcoes

| Opcao | O que faz |
|---|---|
| `--obrigatorio` | o campo nasce com `->obrigatorio()` no `validar()` do model |
| `--remover` | tira o campo do CRUD e **apaga a coluna do banco** |
| `--forcar` | com `--remover`, nao pergunta antes de apagar |

Sem `--obrigatorio`, o campo novo e opcional e ganha so a regra do tipo
(`->numerico()` para numero, `->maximo(255)` para texto). Um `belongs_to` e
sempre obrigatorio, como no `scaffold:crud`.

### 4.2 Desfazer

```bash
php console.php scaffold:campo produtos peso --remover
```

O `--remover` desfaz tudo, inclusive o `ALTER TABLE DROP COLUMN` — por isso
ele pergunta antes:

```text
Isto vai APAGAR a(s) coluna(s) peso da tabela produtos, com os dados que estiverem la.
Continuar? [s/N]:
```

Use `--forcar` para nao perguntar (em script, por exemplo). A ida e a volta
devolvem os arquivos exatamente ao estado anterior.

O primeiro campo do recurso nao pode ser removido: e dele que saem a regra
obrigatoria do model e as asercoes dos testes gerados.

### 4.3 Quando o comando avisa em vez de alterar

O `scaffold:campo` procura no arquivo a mesma estrutura que o `scaffold:crud`
gerou. Se voce reescreveu um trecho a ponto de ele nao ser mais reconhecido, o
comando avisa o arquivo e segue com os outros:

```text
AVISO: Nao consegui alterar views/produtos/ver.php - ajuste a mao.
```

A unica excecao e o `$preenchiveis` do model: sem ele o campo nao seria
gravado, entao o comando para e nao altera nada.

Se o recurso ja tem `scaffold:pesquisa`, o formulario de pesquisa continua com
os campos antigos — o comando lembra disso no fim e mostra a linha para rodar
de novo.

## 5. Pesquisa na listagem

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

### 5.1 Campo e filtro de cada tipo

Os tipos vem de `banco/esquema.sql`; ninguem precisa informa-los de novo.

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

### 5.2 Como a pesquisa se comporta

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

### 5.3 Rodar de novo e desfazer

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

## 6. Paginacao na listagem

```bash
php console.php scaffold:paginacao <tabela|Modelo> [--por-pagina=N] [--remover]
```

Exemplo:

```bash
php console.php scaffold:paginacao produtos --por-pagina=15
```

A listagem gerada traz a tabela inteira. Com trinta registros ninguem
percebe; com trinta mil, o banco devolve tudo, o PHP guarda tudo e a tela nao
abre. O comando quebra a listagem em paginas — o corte acontece no banco, com
`LIMIT`.

Ele **altera** dois arquivos que ja existem:

```text
controllers/ProdutosController.php   (o metodo index)
views/produtos/index.php             (a barra abaixo da tabela)
```

O `index()` passa a pedir uma pagina:

```php
$pagina = $this->modelo->paginar($this->get('pagina'), 15);
```

e a view ganha a barra de navegacao:

```php
<?= paginacao($pagina ?? null) ?>
```

A navegacao vai pela query string: `/produtos?pagina=3`.

### 6.1 Junto com a pesquisa

Os dois comandos se encaixam, **em qualquer ordem**. Com pesquisa instalada, o
paginador recebe a consulta ja filtrada:

```php
$pagina = $this->modelo->paginarConsulta($sql, $parametros, $this->get('pagina'), 15);
```

Isso muda o que a barra mostra: o total de paginas sai do **resultado do
filtro**, e nao da tabela inteira. Trocar de pagina tambem nao perde o que foi
digitado — `paginacao()` mantem o resto da query string.

Rodar os dois comandos em ordens diferentes produz exatamente o mesmo arquivo.

### 6.2 O que o model ganha

Os dois metodos ficam disponiveis em qualquer model, com ou sem o comando:

```php
$pagina = $this->modelo->paginar($this->get('pagina'), 20);
$pagina = $this->modelo->paginarConsulta($sql, $parametros, $this->get('pagina'), 20);

$pagina->registros          // os registros desta pagina
$pagina->total              // quantos existem ao todo
$pagina->pagina             // numero da pagina atual
$pagina->paginas()          // quantas paginas dao
$pagina->resumo()           // "21 a 40 de 137"
$pagina->temProxima()
```

Detalhes que evitam tela quebrada:

- `?pagina=abc`, `?pagina=-3` e `?pagina=` viram a pagina 1;
- pedir a pagina 90 de uma lista que tem 4 devolve a **ultima**, e nao uma
  tela vazia;
- o tamanho da pagina tem teto (`Paginacao::MAXIMO`, 200), entao
  `?por_pagina=999999` nao consegue pedir a tabela inteira;
- com uma pagina so, a barra nao aparece.

## 7. Dados iniciais (semeadura)

```bash
php console.php db:semear [--limpar]
```

Os dados ficam em **`banco/semear.php`**, um arquivo PHP comum do projeto:
voce escreve os dados, o comando cria. E onde moram as categorias do
catalogo, os status de um pedido, a conta de administrador — e alguns
registros de exemplo, para as telas nao nascerem vazias.

Como e um arquivo do projeto, ele vai para o git junto com o codigo: quem
clonar o repositorio roda um comando e tem o mesmo banco que voce.

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

```text
Semeado a partir de banco/semear.php:
  categorias             2 registro(s)
  produtos               52 registro(s)
  usuarios               1 registro(s)

53 registro(s) inserido(s).
```

O arquivo inteiro roda dentro de uma **transacao**: um erro na linha 40 nao
deixa as linhas 1 a 39 gravadas pela metade.

### 7.1 As tres funcoes

```php
semear('tabela', [ ['coluna' => valor], ... ])          // cria e devolve os ids
semear('tabela', [ ... ], 'coluna')                     // nao duplica; ids indexados
falsos('tabela', 50)                                    // enche com dados inventados
limpar()                                                // apaga tudo
limpar('produtos', 'categorias')                        // apaga so estas
```

O terceiro argumento do `semear()` e o que permite **rodar o comando quantas
vezes quiser**: um registro cujo valor daquela coluna ja exista e pulado, e o
id dele volta assim mesmo — entao as relacoes de quem depende dele continuam
funcionando na segunda execucao.

Sem esse argumento, rodar duas vezes cria tudo de novo (e o mesmo
comportamento do `db/seeds.rb` do Rails). Para recomecar do zero:

```bash
php console.php db:semear --limpar
```

Uma coluna chamada `senha` recebe `password_hash()` sozinha: escreva a senha
em texto puro no arquivo, que ela chega cifrada ao banco e a conta entra pela
tela de login. Uma senha que ja venha cifrada nao e cifrada de novo.

A ordem importa: crie a tabela pai antes da filha.

### 7.2 Dados inventados

`falsos()` nao substitui o `semear()` — ele existe para o **volume**. Sem
registros nao da para ver a listagem, a pesquisa, o relatorio nem a paginacao
funcionando.

Os valores combinam com o **nome** e o **tipo** de cada coluna:

| Coluna | O que entra |
|---|---|
| `nome` numa tabela de gente (`alunos`, `clientes`, `usuarios`...) | `Ana Souza`, `Bruno Lima` |
| `nome` nas outras tabelas | `Produto 1`, `Categoria 2` — legivel dentro de um `<select>` |
| `email` | `ana1@example.com`, sempre diferente |
| `senha` | hash de `123456` |
| `preco`, `valor`, `salario`, `total` | dinheiro |
| `quantidade`, `estoque`, `vagas` | numero inteiro |
| `telefone`, `cpf`, `cnpj`, `cep` | no formato brasileiro |
| `cidade`, `uf`, `endereco` | cidade, sigla do estado, rua e numero |
| `descricao`, `observacao`, `resumo` | uma frase |
| `foto`, `anexo`, `documento` | vazio — inventar caminho so daria imagem quebrada |
| `campo_id` (relacao) | sorteia um registro que existe na tabela pai |
| qualquer outra | pelo tipo: data, hora, `0`/`1`, numero ou texto |

Colunas com indice UNIQUE nunca repetem, nem entre si nem com o que ja estava
gravado. Se a tabela pai estiver vazia, o comando para e diz qual semear
antes.

### 7.3 O atalho

Para encher uma tela depressa, sem abrir o arquivo:

```bash
php console.php db:semear produtos 30
php console.php db:semear --tudo 20 --limpar
```

E so um atalho de desenvolvimento. O que o sistema precisa ter de verdade vai
no `banco/semear.php`, que e o que fica versionado.

### 7.4 Dados iguais para a turma inteira

```bash
php console.php db:semear --semente=7
```

Com `--semente=N` o sorteio do `falsos()` e sempre o mesmo, entao todo mundo
fica com os mesmos registros na tela.

## 8. Arquivos e imagens

Dois tipos de campo guardam arquivo enviado pelo formulario:

```bash
php console.php scaffold:crud produtos nome:string foto:imagem ficha:arquivo
php console.php scaffold:campo produtos foto:imagem
```

| Tipo | Aceita | Na tela |
|---|---|---|
| `imagem` | jpg, jpeg, png, gif, webp | miniatura clicavel |
| `arquivo` | pdf, doc(x), odt, xls(x), ods, ppt(x), csv, txt, zip | link "abrir" |

A coluna no banco e um `VARCHAR(255)`: ela guarda o **caminho** do arquivo. O
arquivo vai para `views/uploads/<recurso>/`, com nome sorteado.

O primeiro campo do recurso nao pode ser de arquivo — e dele que saem o titulo
da listagem, a regra obrigatoria e as asercoes dos testes.

### 8.1 O que o scaffold gera

O formulario ganha `enctype="multipart/form-data"` (sem ele o navegador manda
so o nome do arquivo) e o controller ganha o tratamento completo:

```php
$arquivoFoto = $this->imagem('foto');

$erros = $this->modelo->validar($dados);

if ($arquivoFoto !== null && ($problema = $arquivoFoto->problema()) !== null) {
    $erros['foto'] = $problema;
}

if ($erros !== []) {
    $this->voltarComErros($erros, 'produtos/criar');
}

// O arquivo so vai para o disco depois que o resto passou.
if ($arquivoFoto !== null) {
    $dados['foto'] = $arquivoFoto->salvar('produtos');
}
```

Tres comportamentos vem junto:

- **campo em branco nao apaga nada** — na edicao, quem nao escolhe arquivo
  novo mantem o que estava;
- **arquivo novo apaga o anterior** do disco;
- **excluir o registro apaga os arquivos dele**.

### 8.2 As tres travas

Receber arquivo e a porta de entrada mais perigosa de um site. A classe
`Nucleo\Arquivo` exige tres coisas:

1. **veio mesmo de um upload** (`is_uploaded_file`), senao daria para apontar
   para um arquivo que ja estava no servidor;
2. **a extensao esta na lista fechada** — o "tipo" informado pelo navegador e
   escrito pelo proprio navegador, entao nao prova nada. Em campo `imagem`,
   o conteudo ainda passa por `getimagesize()`: um `.php` renomeado para
   `.jpg` e recusado;
3. **o nome gravado e sorteado aqui**, nunca o que veio junto — um nome como
   `../../index.php` sairia da pasta de uploads.

Alem disso, `views/uploads/.htaccess` recusa qualquer `.php`, e o roteador do
servidor embutido tambem. Um executavel que escapasse da lista de extensoes
ainda assim nao rodaria.

### 8.3 Usando fora do scaffold

```php
$anexo = $this->arquivo('contrato');            // lista de documentos
$foto  = $this->imagem('foto', 1024);           // so imagem, ate 1 MB

$anexo->problema();        // mensagem do que esta errado, ou null
$anexo->salvar('contratos');  // move e devolve o caminho a gravar no banco

Arquivo::apagar($registro['contrato'] ?? null);
```

Nas views:

```php
<?= miniatura($registro['foto'] ?? null) ?>
<?= link_arquivo($registro['ficha'] ?? null) ?>
```

A pasta `views/uploads/` fica no `.gitignore` (o `.htaccess` dela, nao).

## 9. Gerar relatorio PDF

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

## 10. Gerar autenticacao

```bash
php console.php auth:install [Modelo|tabela] [Prefixo]
```

Cada tela de login instalada e um **provider**, com controller, views, rotas
e chaves de sessao proprias.

Sem argumentos, o comando cria o model `Usuario` (tabela `usuarios`) e o
login unico do projeto em `/auth`. Para dar login a um model que ja existe,
informe o nome dele — **o prefixo das rotas sai do proprio modelo**, sem
precisar repetir:

```bash
php console.php scaffold:crud clientes nome:string
php console.php auth:install Cliente        # rotas em /auth-cliente
```

| Comando | Model usado | Rotas |
|---|---|---|
| `auth:install` | cria `Usuario` | `/auth/login` |
| `auth:install Cliente` | `Cliente` | `/auth-cliente/login` |
| `auth:install clientes` | idem: o nome da tabela tambem serve | `/auth-cliente/login` |
| `auth:install Usuario` | `Usuario` | `/auth/login` |
| `auth:install Cliente equipe` | `Cliente` | `/auth-equipe/login` |
| `auth:install Cliente auth` | `Cliente` | `/auth/login` |

O segundo argumento so serve para escolher outro prefixo; `auth` nele devolve
o login unico em `/auth`. Um model em PascalCase vira snake_case no prefixo
(`ProfessorSubstituto` -> `/auth-professor-substituto`).

O comando:

- adiciona as colunas `email` e `senha` quando elas nao existem;
- cria um indice UNIQUE em `email`, para nao existirem duas contas com o mesmo
  e-mail;
- adiciona `use Nucleo\Autenticavel;` ao model, preservando a formatacao do
  arquivo, e inclui os campos em `$preenchiveis`;
- acrescenta ao `validar()` do model as regras de e-mail (valido e sem
  repetir, com `emailEmUso()`) e de senha (6+ caracteres);
- se o model ja tem CRUD, leva `email` e `senha` para ele: o `salvar()` e o
  `atualizar()` do controller recebem os dois campos, o formulario ganha os
  inputs (a senha em `type="password"`, sem mostrar a gravada), a tela `ver`
  mostra o e-mail e o teste do controller recria a tabela com as colunas
  novas;
- gera o controller, as telas de login/cadastro e um teste de integracao
  (que tambem cadastra pelo CRUD, quando ele existe).

Gera ou atualiza (exemplo de `auth:install Cliente`):

```text
modelos/Cliente.php                              (atualizado)
controllers/ClientesController.php               (atualizado, se o CRUD existir)
views/clientes/formulario.php                    (atualizado, se o CRUD existir)
views/clientes/ver.php                           (atualizado, se o CRUD existir)
testes/controllers/ClientesControllerTest.php    (atualizado, se o CRUD existir)
controllers/AuthClienteController.php
views/auth/cliente/login.php
views/auth/cliente/registrar.php
testes/controllers/AuthClienteControllerTest.php
banco/esquema.sql
```

Rotas criadas:

```text
/auth-cliente/registrar    cria uma conta
/auth-cliente/login        mostra e processa o login
/auth-cliente/sair         encerra a sessao
```

Sem prefixo nenhum (`auth:install`), os mesmos arquivos ficam em
`controllers/AuthController.php`, `views/auth/` e as rotas em `/auth/...`.

As duas telas sao desenhadas em `views/template/layout-login.php`: uma pagina
isolada, com o formulario centralizado e **sem o menu lateral** — quem ainda
nao entrou nao abriria nenhum daqueles atalhos. O controller escolhe o
template no terceiro argumento de `view()`:

```php
$this->view('auth/login', ['titulo' => 'Entrar'], 'template/layout-login');
```

### 10.1 Como a senha e tratada

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
| `emailEmUso($email, $ignorarId)` | diz se outro registro ja usa o e-mail (o `validar()` do CRUD usa) |

`autenticar($email, $senha)` confere com `password_verify()` e devolve o
registro ou `null`.

O CRUD gerado antes do `auth:install` passa a receber `email` e `senha`, mas
os dois continuam opcionais: `salvar()` ainda aceita so `nome` e `telefone`.
As colunas sao adicionadas como `NULL` justamente por isso — quem exige
credenciais e a tela de cadastro, nao a tabela. E-mail em branco e gravado
como `NULL`: o indice UNIQUE aceita varios `NULL`, mas nao dois textos vazios.

Para o model `Usuario` criado do zero, as colunas nascem `NOT NULL` com
`UNIQUE` no e-mail, porque a unica porta de entrada e a tela de cadastro.

### 10.2 Varios providers

Cada provider recebe controller, telas, rotas e chaves de sessao proprios:

```bash
php console.php scaffold:crud professores nome:string
php console.php auth:install Professor
php console.php scaffold:crud aulas titulo:string --auth=professor
```

As telas ficam em `views/auth/professor/` e as rotas sao
`/auth-professor/login`, `/auth-professor/registrar` e `/auth-professor/sair`.

Para proteger uma rota pelo provider nomeado:

```php
$this->exigirAutenticacao('professor');
```

`autenticado('professor')` e `usuario_id('professor')` consultam a mesma
sessao. As chaves sao separadas por provider (`autenticacao_professor_id`),
entao entrar em um login nao da acesso as telas do outro.

Sem argumento, `exigirAutenticacao()` usa o provider padrao (`/auth`); se ele
nao existir e houver apenas um provider instalado, usa esse — a mesma regra do
`--auth`. Se nenhuma tela de login existir, o erro diz qual comando rodar em
vez de redirecionar para uma pagina inexistente.

`exigirAutenticacao()` e uma chamada de metodo comum, nao uma configuracao
global: ela vale so na acao onde estiver escrita. Para cobrir o controller
inteiro, chame-a no construtor:

```php
public function __construct()
{
    $this->exigirAutenticacao('professor');

    $this->modelo = new Aula();
}
```

Isso nao afeta a tela de login, que e atendida por outro controller
(`AuthProfessorController`).

Depois de um login bem-sucedido, o controller gerado redireciona para a
pagina inicial (`$this->redirecionar();`). Se a `/` exigir outro provider,
troque o destino para uma rota que o provider recem-conectado possa abrir.

## 11. Perfis de acesso

```bash
php console.php auth:perfis <perfil1,perfil2,...> [Modelo|prefixo]
php console.php auth:perfis --remover [Modelo|prefixo]
```

Exemplo:

```bash
php console.php auth:perfis admin,coordenador,professor
```

`exigirAutenticacao()` responde "quem e voce?". Perfil responde "voce pode?".
Sao perguntas diferentes: o aluno e o coordenador estao os dois logados, e so
um deles pode apagar uma turma.

O comando:

- escreve a lista em `configuracoes/perfis.php`;
- cria a coluna `perfil` na tabela das contas (esquema + banco);
- poe `'perfil'` em `$preenchiveis` e a regra `dentroDe()` no model;
- quando o model tem CRUD, poe a lista como `<select>` no formulario e a
  coluna na listagem e no detalhe;
- acerta os testes gerados que recriam a tabela.

Cada tela de login tem a sua lista. Sem argumento ele usa `/auth`; para outro
provider, informe o model ou o prefixo:

```bash
php console.php auth:perfis admin,gerente Cliente     # login /auth-cliente
php console.php auth:perfis admin,gerente cliente     # mesma coisa
```

### 11.1 Protegendo as rotas

```php
$this->exigirPerfil('admin');                  // so admin
$this->exigirPerfil(['admin', 'coordenador']); // qualquer um dos dois
$this->exigirPerfil('admin', 'professor');     // no provider /auth-professor
```

Ela ja chama `exigirAutenticacao()` antes, entao quem nem entrou vai para o
login — e nao para a mensagem de "sem permissao". Como
`exigirAutenticacao()`, vale so na acao onde estiver escrita; para o
controller inteiro, chame no construtor.

### 11.2 Nas views e no menu

```php
<?php if (tem_perfil('admin')): ?>
    <a href="<?= url('usuarios') ?>">Usuarios</a>
<?php endif ?>

<?= e(rotulo_perfil($registro['perfil'] ?? null)) ?>   <!-- 'admin' -> 'Admin' -->
<?= e(perfil() ?? 'sem perfil') ?>
```

No `configuracoes/menu.php`:

```php
['rota' => 'usuarios', 'texto' => 'Usuarios', 'perfil' => 'admin'],
```

Esconder o item e cortesia com quem usa, **nao seguranca**: quem souber o
endereco continua chegando la. Quem protege a rota e o `exigirPerfil()`.

### 11.3 De onde vem o perfil

Do banco, a cada requisicao (guardado em memoria ate o fim dela) — e nao da
sessao. E um acesso a mais, e em troca tirar o `admin` de alguem passa a valer
na hora, sem esperar o proximo login.

Conta sem perfil nao passa em nenhum `exigirPerfil()`. Depois de rodar o
comando **nenhuma conta tem perfil ainda**: defina pelo CRUD, ou direto no
banco:

```sql
UPDATE usuarios SET perfil = 'admin' WHERE id = 1;
```

Os rotulos em `configuracoes/perfis.php` podem ser editados a vontade. Mudar
uma **chave** exige atualizar os registros que usavam a chave antiga.

## 12. Protecao dos formularios (CSRF)

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

## 13. Menu de navegacao

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

## 14. Executar todos os testes

```bash
php testes/executar.php
```

Executa todos os arquivos terminados em `Test.php` dentro de `testes/`.

Os testes rodam no banco indicado por `banco_testes` em
`configuracoes/banco.php` (por padrao `framework_aula_testes`), que e apagado
e recriado a cada execucao — os dados da aplicacao nunca sao tocados. O MySQL
precisa estar ligado; se nao estiver, o comando avisa antes de rodar qualquer
teste.

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

## 15. Executar um teste especifico

```bash
php testes/executar.php ProdutoTest
php testes/executar.php ViewTest
php testes/executar.php ProdutoTest::testeExecutaCrudCompleto
```

## 16. Iniciar o servidor PHP

```bash
php -S localhost:8000 roteador.php
```

O arquivo `roteador.php` permite que as rotas do framework funcionem no
servidor embutido do PHP.

## 17. Validar a sintaxe de um arquivo PHP

```bash
php -l console.php
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

## 18. Fluxo completo para um projeto novo

```bash
php instalar.php
php console.php auth:install
php console.php scaffold:crud clientes nome:string email:string telefone:string --auth
php console.php scaffold:campo clientes cidade:string foto:imagem
php console.php scaffold:pesquisa clientes nome email cidade
php console.php scaffold:paginacao clientes --por-pagina=15
php console.php auth:perfis admin,atendente
php console.php db:semear
php testes/executar.php
php -S localhost:8000 roteador.php
```

Abra `/auth/registrar`, crie o primeiro usuario e acesse `/clientes`.
