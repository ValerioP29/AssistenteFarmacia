# Asset audit

## A) Fallback images (frontend)

| File | Fallback | Trigger condition | Notes |
|---|---|---|---|
| `app/assets/js/app.js` (`createResponsiveImage`) | `https://via.placeholder.com/150` | `image.src` empty OR image load error (`img.onerror`) | Fallback is now one-shot; it does not replace valid URLs pre-emptively. |
| `app/assets/js/page-dashboard.js` | `https://api.assistentefarmacia.it/uploads/images/placeholder-logo-farmacia.jpg` | `pharma.image_logo` empty OR logo load error (`heroLogo.onerror`) | Applies only to pharmacy logo in dashboard hero. |
| `app/assets/js/page-prenotazioni.js` | `${AppURLs.api.base}/uploads/images/placeholder-product.jpg` | related product image empty OR error in card image load | Not Picsum; fixed placeholder asset. |

### Where Picsum comes from

`picsum.photos` is present in DB seed/demo data (`panel/database/assistente_farmacia_set.sql`) inside `jta_pharma_prods.image`, not as frontend fallback code.

## B) Asset Matrix

| Asset type | DB field | DB format | Builder | Base expected | Local URL example |
|---|---|---|---|---|---|
| product image | `jta_pharma_prods.image` | `uploads/...` or absolute URL | `normalize_product_data` -> `normalize_asset_url` | panel base for `uploads/...` | `http://localhost:8001/uploads/...` |
| pharmacy logo | `jta_pharmacies.logo` | `uploads/...` or absolute URL | `normalize_pharma_data` -> `normalize_asset_url` | panel base | `http://localhost:8001/uploads/...` |
| avatar | `jta_pharmacies.img_avatar` | filename or `uploads/...` | `get_pharma_img_src` | panel for `uploads/...`, api for legacy filename path | `http://localhost:8001/uploads/...` |
| cover | `jta_pharmacies.img_cover` | filename or `uploads/...` | `get_pharma_img_src` | panel for `uploads/...`, api for legacy filename path | `http://localhost:8001/uploads/...` |
| bot | `jta_pharmacies.img_bot` | filename or `uploads/...` | `get_pharma_img_src` | panel for `uploads/...`, api for legacy filename path | `http://localhost:8001/uploads/...` |
| service cover | `jta_services.img_cover` | JSON (`{"src":"..."}`) or string | `normalize_service_data` -> `get_service_img_src` | panel for `uploads/...`, api for legacy filename path | `http://localhost:8001/uploads/...` |
| event cover | `jta_events.img_cover` | JSON (`{"src":"..."}`) or string | `normalize_event_data` -> `get_service_img_src` | panel for `uploads/...`, api for legacy filename path | `http://localhost:8001/uploads/...` |

## C) Repeatable check

Run:

```bash
TEST_JWT='...' API_BASE_URL='http://localhost:8002' php scripts/audit_assets.php
```

The report flags:
- `EMPTY_URL`
- `LOCAL_PANEL_PREFIX_ERROR` (contains `/panel/uploads`)
- `HTTP_404` and other HTTP errors
- `UNREACHABLE`
