<?php
/**
 * panel/lib/tags_catalog.php
 *
 * Whitelist canonicale dei tag prodotto per il panel.
 * Deriva da taxonomy/tags.php — NON aggiungere tag qui direttamente.
 *
 * Compatibilità: mantiene le stesse firme delle funzioni esistenti
 * (getTagsCatalog, getAllowedCanonicalTags, normalizeTagsListCanonical,
 *  canonicalizeTagValue) per non rompere le chiamate nel panel.
 */

require_once __DIR__ . '/../../taxonomy/tags.php';

/**
 * Mappa slug → label per tutti i tag nel catalogo.
 * (In passato conteneva solo 9 tag hardcoded; ora include tutti quelli
 *  della taxonomy, con le label già definite centralmente.)
 */
function getTagsCatalog(): array
{
    return getTagsLabelMap();
}

/**
 * Slug canonici ammessi.
 * Il panel usa questo per validare i tag in ingresso.
 */
function getAllowedCanonicalTags(): array
{
    return array_keys(getTagsTaxonomy());
}

/**
 * Canonicalizza il formato di un singolo tag grezzo.
 * Alias + normalizzazione formato, identico alla logica JS.
 *
 * @deprecated Usa canonicalizeTag() da taxonomy/tags.php direttamente.
 */
function canonicalizeTagValue(string $tag): string
{
    return canonicalizeTag($tag);
}

/**
 * Canonicalizza un array di tag grezzi, deduplica, rimuove vuoti.
 * Risolve automaticamente gli alias (es. 'dermocosmetica' → 'dermocosmesi').
 */
function normalizeTagsListCanonical(array $tags): array
{
    $normalized = [];
    foreach ($tags as $tag) {
        $canonical = canonicalizeTag((string) $tag);
        if ($canonical !== '') {
            $normalized[$canonical] = true;
        }
    }
    return array_keys($normalized);
}

/**
 * Solo i tag visibili nel dropdown filtro dell'app (UI).
 * Usato dal panel per costruire la select "filtro promozioni".
 */
function getUiTagsCatalog(): array
{
    return array_map(fn($v) => $v['label'], getUiTags());
}