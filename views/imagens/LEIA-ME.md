# Pasta de imagens

Coloque aqui as imagens do site (logo, fotos, icones...).

Para usar em uma view, chame a funcao `asset()`:

```php
<img src="<?= asset('imagens/logo.png') ?>" alt="Logo da escola">
```

Isso gera o caminho correto tanto em `http://localhost:8000` quanto em
`http://localhost/framework` (XAMPP em subpasta).

O mesmo vale para as outras pastas de arquivos estaticos:

| Chamada                              | Arquivo                        |
|--------------------------------------|--------------------------------|
| `asset('css/estilo.css')`            | `views/css/estilo.css`         |
| `asset('javascripts/app.js')`        | `views/javascripts/app.js`     |
| `asset('imagens/logo.png')`          | `views/imagens/logo.png`       |
