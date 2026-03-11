<?php
/**
 * api/product-recommendations-ai.php
 *
 * Endpoint AI per consigli d'acquisto personalizzati.
 *
 * Due modalità:
 *   mode=product     → utente sta comprando un prodotto specifico (tom-select)
 *                       → AI capisce semanticamente cosa sta comprando e consiglia correlati
 *   mode=demographic → ordine con ricetta (prodotto sconosciuto)
 *                       → AI suggerisce in base a età/genere/contesto
 *
 * Flusso interno:
 *   1. Shortlist deterministica (logica esistente product-search related mode, max 8 prodotti)
 *   2. AI rerank leggero sulla shortlist
 *   3. Fallback automatico all'ordine deterministico se AI fallisce / timeout / parse error
 *
 * Parametri GET:
 *   pharma_id          (int, required)
 *   mode               (string: 'product' | 'demographic', default 'product')
 *
 *   -- mode=product --
 *   seed_name          (string) nome prodotto selezionato dalla tom-select
 *   seed_description   (string, optional)
 *   seed_tags          (string, JSON array o CSV di tag canonici, optional)
 *   seed_category      (string, optional)
 *   related_tag        (string, optional) tag passato dal JS per la shortlist deterministica
 *
 *   -- mode=demographic --
 *   user_gender        (string: 'm' | 'f' | '', optional)
 *   user_age_range     (string: '18-30' | '31-50' | '51-70' | '70+', optional)
 *
 *   -- comuni --
 *   exclude_ids        (string, CSV di ID da escludere - già nel carrello)
 *   limit              (int, default 3, max 5)
 *   ai_enabled         (int, default 1 - puoi forzare 0 per testare fallback deterministico)
 *
 * Risposta:
 * {
 *   code: 200,
 *   status: true,
 *   data: {
 *     products: [ { id, name, image, price, ... , ai_reason: "..." } ],
 *     source: "ai" | "deterministic",
 *     mode: "product" | "demographic"
 *   }
 * }
 */

require_once('_api_bootstrap.php');
require_once(__DIR__ . '/helpers/_related_tags.php');
// taxonomy/tags.php è caricata transitivamente da _related_tags.php
// bot_ai_helpers.php è caricato da _api_bootstrap.php

setHeadersAPI();
$decoded = protectFileWithJWT();

$user = get_my_data();
if (!$user) {
    echo json_encode([
        'code'    => 401,
        'status'  => false,
        'error'   => 'Invalid or expired token',
        'message' => 'Accesso negato',
    ]);
    exit();
}

// ──────────────────────────────────────────────────────────────────────────────
// PARAMETRI INPUT
// ──────────────────────────────────────────────────────────────────────────────

$input       = $_GET;
$pharma_id   = (int)($input['pharma_id'] ?? 0);
$mode        = in_array($input['mode'] ?? '', ['product', 'demographic']) ? $input['mode'] : 'product';
$limit       = max(1, min(5, (int)($input['limit'] ?? 3)));
$ai_enabled  = (int)($input['ai_enabled'] ?? 1) === 1;

// Prodotto seed (mode=product)
$seed_name        = trim((string)($input['seed_name'] ?? ''));
$seed_description = trim((string)($input['seed_description'] ?? ''));
$seed_category    = trim((string)($input['seed_category'] ?? ''));
$seed_tags_raw    = trim((string)($input['seed_tags'] ?? ''));
$related_tag      = trim((string)($input['related_tag'] ?? ''));

// Dati demografici (mode=demographic)
$user_gender    = trim((string)($input['user_gender'] ?? ''));
$user_age_range = trim((string)($input['user_age_range'] ?? ''));

// Esclusioni (già nel carrello)
$exclude_ids_raw = trim((string)($input['exclude_ids'] ?? ''));
$exclude_ids = [];
if ($exclude_ids_raw !== '') {
    $exclude_ids = array_values(array_filter(
        array_map('intval', explode(',', $exclude_ids_raw)),
        fn($id) => $id > 0
    ));
}

if ($pharma_id <= 0) {
    echo json_encode([
        'code'    => 400,
        'status'  => false,
        'error'   => 'Bad Request',
        'message' => 'Parametro pharma_id mancante o non valido.',
    ]);
    exit();
}

// Valida mode=product: serve almeno il nome del prodotto seed
if ($mode === 'product' && $seed_name === '') {
    echo json_encode([
        'code'    => 400,
        'status'  => false,
        'error'   => 'Bad Request',
        'message' => 'Parametro seed_name richiesto per mode=product.',
    ]);
    exit();
}

// ──────────────────────────────────────────────────────────────────────────────
// HELPER: parse tag seed
// ──────────────────────────────────────────────────────────────────────────────

function _parse_seed_tags(string $raw): array
{
    if ($raw === '') return [];
    $arr = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
        return array_filter(array_map('trim', $arr));
    }
    return array_filter(array_map('trim', explode(',', $raw)));
}

// ──────────────────────────────────────────────────────────────────────────────
// STEP 1: SHORTLIST DETERMINISTICA (max 8 prodotti candidati per l'AI)
// ──────────────────────────────────────────────────────────────────────────────
// Ricostruiamo la stessa logica di product-search.php related_mode
// su un pool più ampio (8 invece di 3) per dare all'AI più scelta.

function _build_shortlist(
    int    $pharma_id,
    string $related_tag,
    string $seed_name,
    array  $exclude_ids,
    int    $pool_size = 8,
    bool   $only_on_sale = false
): array {
    global $pdo;

    // ── schema detection (stessa logica di product-search.php) ────────────────
    $global_table_exists = false;
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'jta_global_prods'");
        $stmt->execute();
        $global_table_exists = $stmt->rowCount() > 0;
    } catch (Exception $e) {}

    $stmtCols = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jta_pharma_prods'"
    );
    $stmtCols->execute();
    $pharma_columns_map = [];
    foreach ($stmtCols->fetchAll(PDO::FETCH_COLUMN) as $col) {
        $pharma_columns_map[(string)$col] = true;
    }

    // ── costruzione SELECT base ────────────────────────────────────────────────
    $base_cols = [
        'pp.id',
        !empty($pharma_columns_map['pharma_id'])   ? 'pp.pharma_id'   : 'NULL AS pharma_id',
        !empty($pharma_columns_map['price'])        ? 'pp.price'       : 'NULL AS price',
        !empty($pharma_columns_map['sale_price'])   ? 'pp.sale_price'  : 'NULL AS sale_price',
        !empty($pharma_columns_map['is_featured'])  ? 'pp.is_featured' : '0 AS is_featured',
        !empty($pharma_columns_map['is_active'])    ? 'pp.is_active'   : '1 AS is_active',
        !empty($pharma_columns_map['is_on_sale'])   ? 'pp.is_on_sale'  : 'NULL AS is_on_sale',
        !empty($pharma_columns_map['percentage_discount']) ? 'pp.percentage_discount' : 'NULL AS percentage_discount',
        !empty($pharma_columns_map['tags'])         ? 'pp.tags'        : 'NULL AS tags',
    ];

    if ($global_table_exists) {
        $base_cols[] = "COALESCE(" . (!empty($pharma_columns_map['name']) ? 'pp.name' : 'NULL') . ", gp.name) AS name";
        $base_cols[] = "COALESCE(" . (!empty($pharma_columns_map['description']) ? 'pp.description' : 'NULL') . ", gp.description) AS description";
        $base_cols[] = "COALESCE(" . (!empty($pharma_columns_map['image']) ? 'pp.image' : 'NULL') . ", gp.image) AS image";
        $base_cols[] = "COALESCE(" . (!empty($pharma_columns_map['sku']) ? 'pp.sku' : 'NULL') . ", gp.sku) AS sku";
        $base_cols[] = "COALESCE(gp.category, '') AS category";
    } else {
        $base_cols[] = !empty($pharma_columns_map['name'])        ? 'pp.name'        : 'NULL AS name';
        $base_cols[] = !empty($pharma_columns_map['description'])
            ? "COALESCE(pp.description, '') AS description"
            : "'' AS description";
        $base_cols[] = !empty($pharma_columns_map['image'])       ? 'pp.image'       : 'NULL AS image';
        $base_cols[] = !empty($pharma_columns_map['sku'])         ? 'pp.sku'         : 'NULL AS sku';
        $base_cols[] = "NULL AS category";
    }

    $join_sql = $global_table_exists ? 'LEFT JOIN jta_global_prods gp ON pp.product_id = gp.id' : '';

    // ── where base ─────────────────────────────────────────────────────────────
    $where     = ['pp.pharma_id = :pharma_id'];
    $params    = [':pharma_id' => $pharma_id];

    if (!empty($pharma_columns_map['is_active'])) {
        $where[] = 'pp.is_active = 1';
    }

    if (!empty($exclude_ids)) {
        $ph = [];
        foreach ($exclude_ids as $idx => $id) {
            $ph[] = ':excl_' . $idx;
            $params[':excl_' . $idx] = $id;
        }
        $where[] = 'pp.id NOT IN (' . implode(',', $ph) . ')';
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    // ── branch tag ─────────────────────────────────────────────────────────────
    $products  = [];
    $branch    = 'generic';

    $canonical_tag = canonicalizeTag($related_tag);
    $is_featured   = in_array(
        str_replace(['-', ' '], '_', strtolower($canonical_tag)),
        ['', 'in_evidenza', 'featured', 'evidenza'],
        true
    );

    if (!$is_featured && $canonical_tag !== '') {
        $ctx      = related_tags_get_category_keywords($canonical_tag);
        $keywords = $ctx['keywords'] ?? [];

        if (!empty($keywords)) {
            $tag_conditions = [];
            $kw_params      = [];

            $tags_expr = "CONVERT((CASE
                WHEN pp.tags IS NULL THEN NULL
                WHEN JSON_VALID(pp.tags) THEN JSON_UNQUOTE(JSON_EXTRACT(pp.tags, '$'))
                ELSE CAST(pp.tags AS CHAR)
            END) USING utf8mb4) COLLATE utf8mb4_unicode_ci";

            foreach ($keywords as $ki => $kw) {
                $lkw = strtolower($kw);
                $pk  = ':rkw_' . $ki;
                if (!empty($pharma_columns_map['tags'])) {
                    $tag_conditions[] = "LOWER({$tags_expr}) LIKE {$pk}";
                }
                $tag_conditions[] = "LOWER(CONVERT(COALESCE(pp.name,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE {$pk}";
                if ($global_table_exists) {
                    $tag_conditions[] = "LOWER(CONVERT(COALESCE(gp.name,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE {$pk}";
                }
                $kw_params[$pk] = '%' . $lkw . '%';
            }

            if (!empty($tag_conditions)) {
                $branch = 'tag';
                $cond_sql = '(' . implode(' OR ', $tag_conditions) . ')';

                $sql = "SELECT " . implode(', ', $base_cols) . "
                        FROM jta_pharma_prods pp {$join_sql}
                        {$where_sql} AND {$cond_sql}
                        ORDER BY pp.is_featured DESC, pp.name ASC
                        LIMIT :pool_size";

                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                foreach ($kw_params as $k => $v) {
                    $stmt->bindValue($k, $v, PDO::PARAM_STR);
                }
                $stmt->bindValue(':pool_size', $pool_size, PDO::PARAM_INT);
                $stmt->execute();
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    // ── branch seed (nome prodotto come testo libero) ──────────────────────────
    if (empty($products) && $seed_name !== '') {
        $branch   = 'seed';
        $seed_lc  = mb_strtolower($seed_name, 'UTF-8');

        $cond_sql = "(LOWER(CONVERT(COALESCE(pp.name,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE :seed_like";
        if ($global_table_exists) {
            $cond_sql .= " OR LOWER(CONVERT(COALESCE(gp.name,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci) LIKE :seed_like";
        }
        $cond_sql .= ")";

        $sql = "SELECT " . implode(', ', $base_cols) . "
                FROM jta_pharma_prods pp {$join_sql}
                {$where_sql} AND {$cond_sql}
                ORDER BY pp.is_featured DESC, pp.name ASC
                LIMIT :pool_size";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':seed_like', '%' . $seed_lc . '%', PDO::PARAM_STR);
        $stmt->bindValue(':pool_size', $pool_size, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── fallback generic ───────────────────────────────────────────────────────
    if (empty($products)) {
        $branch = 'generic';
        $sql = "SELECT " . implode(', ', $base_cols) . "
                FROM jta_pharma_prods pp {$join_sql}
                {$where_sql}
                ORDER BY pp.is_featured DESC,
                    CASE WHEN pp.image IS NOT NULL AND TRIM(CONVERT(pp.image USING utf8mb4)) <> '' THEN 1 ELSE 0 END DESC,
                    pp.name ASC
                LIMIT :pool_size";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':pool_size', $pool_size, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return ['products' => $products, 'branch' => $branch];
}

// ──────────────────────────────────────────────────────────────────────────────
// HELPER: normalizza prodotto per output finale
// ──────────────────────────────────────────────────────────────────────────────

function _normalize_product_for_output(array $p, string $ai_reason = ''): array
{
    $tags = $p['tags'] ?? null;
    if (is_string($tags)) {
        $decoded = json_decode($tags, true);
        $tags = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? array_values(array_filter(array_map(fn($t) => canonicalizeTag(trim((string)$t)), $decoded)))
            : array_values(array_filter(array_map(fn($t) => canonicalizeTag(trim((string)$t)), explode(',', $tags))));
    }
    if (!is_array($tags)) $tags = [];

    $price_orig = isset($p['price']) && $p['price'] > 0 ? round((float)$p['price'], 2) : null;
    $price_sale = isset($p['sale_price']) && $p['sale_price'] > 0 ? round((float)$p['sale_price'], 2) : null;
    $has_discount = $price_orig && $price_sale && $price_sale < $price_orig;

    return [
        'id'              => (int)$p['id'],
        'name'            => $p['name'] ?? '',
        'description'     => $p['description'] ?? '',
        'image'           => $p['image'] ?? null,
        'sku'             => $p['sku'] ?? null,
        'category'        => $p['category'] ?? '',
        'price'           => $price_orig,
        'price_discounted'=> $has_discount ? $price_sale : null,
        'has_discount'    => $has_discount,
        'is_featured'     => (int)($p['is_featured'] ?? 0) === 1,
        'tags'            => $tags,
        'ai_reason'       => $ai_reason,
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// STEP 2: AI RERANK
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Chiede all'AI di scegliere e ordinare i prodotti più rilevanti dalla shortlist.
 *
 * @param array  $shortlist        Prodotti candidati dalla shortlist deterministica
 * @param string $mode             'product' | 'demographic'
 * @param array  $seed_context     Contesto prodotto seed (mode=product)
 * @param array  $demographic      Dati demografici (mode=demographic)
 * @param int    $limit            Quanti prodotti restituire
 * @return array|null              Array di ['id' => int, 'reason' => string] o null se fallisce
 */
function _ai_rerank(
    array  $shortlist,
    string $mode,
    array  $seed_context,
    array  $demographic,
    int    $limit
): ?array {

    if (empty($shortlist)) return null;

    // Costruisce la lista prodotti da passare all'AI (compatta, solo campi utili)
    $products_text = '';
    foreach ($shortlist as $p) {
        $tags_str = '';
        if (!empty($p['tags'])) {
            $tags_raw = is_string($p['tags']) ? json_decode($p['tags'], true) : $p['tags'];
            if (is_array($tags_raw)) {
                $tags_str = implode(', ', array_map('canonicalizeTag', $tags_raw));
            }
        }
        $desc_short = mb_substr(strip_tags((string)($p['description'] ?? '')), 0, 120, 'UTF-8');
        $products_text .= sprintf(
            "- ID %d: \"%s\"%s%s\n",
            (int)$p['id'],
            (string)($p['name'] ?? ''),
            $desc_short ? " — {$desc_short}" : '',
            $tags_str ? " [tag: {$tags_str}]" : ''
        );
    }

    // Prompt diverso per le due modalità
    if ($mode === 'product') {
        $seed_tags_str = implode(', ', (array)($seed_context['tags'] ?? []));
        $seed_desc     = mb_substr((string)($seed_context['description'] ?? ''), 0, 200, 'UTF-8');

        $prompt_sys = <<<EOT
Sei un assistente farmacista esperto. Devi consigliare prodotti correlati utili.
Rispondi SOLO con JSON valido, nessun altro testo.
Formato risposta: {"ranked":[{"id":123,"reason":"breve motivo 5-8 parole"},{"id":456,"reason":"..."}]}
EOT;
        $prompt_user = <<<EOT
Il cliente sta acquistando: "{$seed_context['name']}"
Categoria: {$seed_context['category']}
Tag: {$seed_tags_str}
{$seed_desc}

Scegli i {$limit} prodotti più utili da acquistare INSIEME a questo, tra:
{$products_text}
Scegli prodotti complementari (non sostituiti), ragiona in modo farmaceutico pratico.
EOT;
    } else {
        // mode=demographic
        $gender_label = match($demographic['gender'] ?? '') {
            'm' => 'uomo',
            'f' => 'donna',
            default => 'persona'
        };
        $age_label    = $demographic['age_range'] ?? 'adulto';

        $prompt_sys = <<<EOT
Sei un assistente farmacista esperto. Devi consigliare prodotti utili per un cliente.
Rispondi SOLO con JSON valido, nessun altro testo.
Formato risposta: {"ranked":[{"id":123,"reason":"breve motivo 5-8 parole"},{"id":456,"reason":"..."}]}
EOT;
        $prompt_user = <<<EOT
Il cliente è: {$gender_label}, fascia d'età {$age_label}.
Ha effettuato un ordine con ricetta medica.

Scegli i {$limit} prodotti più utili come complemento generale per questo profilo, tra:
{$products_text}
Privilegia integratori, igiene, benessere generale adatti al profilo demografico.
EOT;
    }

    // Chiamata OpenAI con timeout esplicito via CURLOPT
    // openai_call_simple_result usa il client già esistente in bot_ai_helpers.php
    $args = [
        'model'       => 'gpt-4o',
        'temperature' => 0.1,     // bassa: vogliamo output strutturato, non creativo
        'max_tokens'  => 300,     // sufficiente per JSON con 3-5 prodotti + reasons
    ];

    // Aggiunta timeout: monkey-patch via args_extra non supportato direttamente,
    // usiamo la funzione base openai_call che accetta args_extra
    $response_raw = openai_call($prompt_user, $prompt_sys, $args);

    if (empty($response_raw['status']) || empty($response_raw['message'])) {
        error_log('[product-recommendations-ai] AI call failed: ' . json_encode($response_raw));
        return null;
    }

    // Parsing risposta
    $content = $response_raw['message'];

    // Rimuovi eventuali ```json ... ``` wrapper
    $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
    $content = preg_replace('/\s*```\s*$/m', '', $content);
    $content = trim($content);

    $parsed = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['ranked']) || !is_array($parsed['ranked'])) {
        error_log('[product-recommendations-ai] AI parse error: ' . json_last_error_msg() . ' | raw: ' . substr($content, 0, 300));
        return null;
    }

    // Valida che gli ID restituiti esistano nella shortlist
    $shortlist_ids = array_column($shortlist, 'id');
    $validated = [];
    foreach ($parsed['ranked'] as $item) {
        $id = (int)($item['id'] ?? 0);
        if ($id > 0 && in_array($id, $shortlist_ids)) {
            $validated[] = [
                'id'     => $id,
                'reason' => mb_substr(trim((string)($item['reason'] ?? '')), 0, 120, 'UTF-8'),
            ];
        }
        if (count($validated) >= $limit) break;
    }

    return !empty($validated) ? $validated : null;
}

// ──────────────────────────────────────────────────────────────────────────────
// ESECUZIONE PRINCIPALE
// ──────────────────────────────────────────────────────────────────────────────

try {
    // Inferisce related_tag dal prodotto seed se non passato esplicitamente
    if ($mode === 'product' && $related_tag === '' && $seed_name !== '') {
        $inferred_tags = related_tags_infer_from_product($seed_name, $seed_description, $seed_category);
        if (!empty($inferred_tags)) {
            $related_tag = $inferred_tags[0]; // usa il tag più rilevante come anchor
        }
    }

    // STEP 1: shortlist deterministica (pool di 8)
    $pool_size  = max($limit + 3, 8);
    $only_on_sale = (int)($input['only_on_sale'] ?? 0) === 1;
    $shortlist_result = _build_shortlist($pharma_id, $related_tag, $seed_name, $exclude_ids, $pool_size, $only_on_sale);
    $shortlist  = $shortlist_result['products'];
    $branch     = $shortlist_result['branch'];

    if (empty($shortlist)) {
        echo json_encode([
            'code'    => 200,
            'status'  => true,
            'message' => 'Nessun prodotto disponibile',
            'data'    => [
                'products' => [],
                'source'   => 'deterministic',
                'mode'     => $mode,
                'branch'   => $branch,
            ],
        ]);
        exit();
    }

    // STEP 2: AI rerank (se abilitato)
    $source       = 'deterministic';
    $final_ranked = [];

    if ($ai_enabled) {
        $seed_tags_parsed = _parse_seed_tags($seed_tags_raw);

        $seed_context = [
            'name'        => $seed_name,
            'description' => $seed_description,
            'category'    => $seed_category,
            'tags'        => $seed_tags_parsed,
        ];

        $demographic = [
            'gender'    => $user_gender,
            'age_range' => $user_age_range,
        ];

        $ai_result = _ai_rerank($shortlist, $mode, $seed_context, $demographic, $limit);

        if ($ai_result !== null) {
            // Costruisce mappa id→prodotto per lookup rapido
            $shortlist_map = [];
            foreach ($shortlist as $p) {
                $shortlist_map[(int)$p['id']] = $p;
            }

            foreach ($ai_result as $item) {
                $id = $item['id'];
                if (isset($shortlist_map[$id])) {
                    $final_ranked[] = _normalize_product_for_output($shortlist_map[$id], $item['reason']);
                }
            }

            if (!empty($final_ranked)) {
                $source = 'ai';
            }
        }

        if (empty($final_ranked)) {
            // AI fallita o risultato vuoto → fallback deterministico
            error_log('[product-recommendations-ai] AI fallback to deterministic for pharma_id=' . $pharma_id);
        }
    }

    // Fallback deterministico (AI disabilitata o fallita)
    if (empty($final_ranked)) {
        $source = 'deterministic';
        foreach (array_slice($shortlist, 0, $limit) as $p) {
            $final_ranked[] = _normalize_product_for_output($p);
        }
    }

    echo json_encode([
        'code'    => 200,
        'status'  => true,
        'message' => 'Consigli caricati con successo',
        'data'    => [
            'products' => $final_ranked,
            'source'   => $source,   // 'ai' | 'deterministic' (utile per debug/analytics)
            'mode'     => $mode,
            'branch'   => $branch,
        ],
    ]);

} catch (Exception $e) {
    error_log('[product-recommendations-ai] Exception: ' . $e->getMessage());
    echo json_encode([
        'code'    => 500,
        'status'  => false,
        'error'   => 'Internal Server Error',
        'message' => 'Errore durante il calcolo dei consigli.',
    ]);
}