# Configurazione locale URL immagini (API + Panel)

1. Copia `config.local.example.php` in `config.local.php` nella root del repository.
2. Imposta gli URL locali:

```php
<?php
if (!defined('PANEL_URL')) define('PANEL_URL', 'http://localhost:8001');
if (!defined('API_URL')) define('API_URL', 'http://localhost:8002');
// opzionale: trace debug panel_base_url su error_log
// if (!defined('PANEL_BASE_URL_TRACE')) define('PANEL_BASE_URL_TRACE', true);
```

## Note

- `api/_api_bootstrap.php` carica automaticamente `config.local.php` (root) o `api/config.local.php`.
- `panel/config/database.php` carica automaticamente `config.local.php` (root) o `panel/config/config.local.php`.
- In locale il panel **non** aggiunge `/panel`: il path immagini corretto rimane `http://localhost:8001/uploads/...`.
- Per trace rapido del branch locale: aggiungi `?trace_panel_base_url=1` a una chiamata API oppure abilita `PANEL_BASE_URL_TRACE` in `config.local.php`.

## Comandi di verifica

```bash
curl -I http://localhost:8001/uploads/products/generico/product_6909d29922195.jpg
```

Per verificare endpoint autenticati API (es. `pharma-get.php`, `services-list.php`) usa un JWT letto da browser:

1. Apri DevTools sull'app locale.
2. Leggi `localStorage.getItem('jta-app-jwt')`.
3. Esegui:

```bash
TOKEN="<incolla-token>"
curl -s -H "Authorization: Bearer $TOKEN" "http://localhost:8002/pharma-get.php?id=1" | jq '.data | {image_logo, image_avatar, image_cover, image_bot}'
curl -s -H "Authorization: Bearer $TOKEN" "http://localhost:8002/services-list.php?pharma_id=1" | jq '.data.services[]?.cover_image.src'
```
