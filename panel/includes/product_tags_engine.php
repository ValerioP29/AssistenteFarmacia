<?php
/**
 * panel/includes/product_tags_engine.php
 *
 * Motore rule-based per il suggerimento tag prodotti farmacia (panel).
 * Le regole keyword→tag derivano da taxonomy/tags.php — non duplicarle qui.
 *
 * API pubblica (invariata rispetto alla versione precedente):
 *   normalizeProductTagsInput($raw)  → ?array
 *   normalizeTagArray($raw)          → array
 *   parseBoolishValue($value)        → int
 *   normalizeTextForMatch($text)     → string
 *   suggestTagsFromName($name)       → array { suggested_tags, confidence, matched_keywords }
 *
 * Rimossa: buildTagRules() hardcoded — usa buildTagRulesFromTaxonomy() dalla taxonomy.
 */

require_once __DIR__ . '/../../taxonomy/tags.php';

// ── Normalizzazione input ─────────────────────────────────────────────────────

if (!function_exists('normalizeProductTagsInput')) {
    function normalizeProductTagsInput($rawTags): ?array
    {
        if ($rawTags === null) {
            return null;
        }

        $tags = [];

        if (is_string($rawTags)) {
            $rawTags = trim($rawTags);
            if ($rawTags === '') {
                return null;
            }
            $decoded = json_decode($rawTags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $tags = $decoded;
            } else {
                $tags = explode(',', $rawTags);
            }
        } elseif (is_array($rawTags)) {
            $tags = $rawTags;
        } else {
            $tags = [$rawTags];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            if (is_array($tag) || is_object($tag)) {
                continue;
            }
            $canonical = canonicalizeTag(trim((string) $tag));
            if ($canonical !== '') {
                $normalized[$canonical] = true;
            }
        }

        return empty($normalized) ? null : array_keys($normalized);
    }
}

if (!function_exists('normalizeTagArray')) {
    function normalizeTagArray($rawTags): array
    {
        return normalizeProductTagsInput($rawTags) ?? [];
    }
}

if (!function_exists('parseBoolishValue')) {
    function parseBoolishValue($value): int
    {
        return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true) ? 1 : 0;
    }
}

// ── Normalizzazione testo per match keyword ───────────────────────────────────

if (!function_exists('normalizeTextForMatch')) {
    function normalizeTextForMatch(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = strtolower($transliterated);
        }

        $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}

// ── Suggerimento tag da nome prodotto ─────────────────────────────────────────

if (!function_exists('suggestTagsFromName')) {
    /**
     * Suggerisce tag per un prodotto a partire dal nome.
     *
     * @param  string $name  Nome prodotto (raw, qualsiasi case)
     * @return array {
     *   suggested_tags:   string[]   Tag suggeriti (slug canonici)
     *   confidence:       string     'high' | 'medium' | 'low'
     *   matched_keywords: string[]   Keyword che hanno generato il match
     * }
     */
    function suggestTagsFromName(string $name): array
    {
        $normalizedName = normalizeTextForMatch($name);
        $rules          = buildTagRulesFromTaxonomy();

        $matchedByTag  = [];

        foreach ($rules as $tag => $groups) {
            $specificMatches = [];
            foreach ($groups['specific'] as $keyword) {
                $kNorm = normalizeTextForMatch($keyword);
                if ($kNorm !== '' && strpos($normalizedName, $kNorm) !== false) {
                    $specificMatches[] = $keyword;
                }
            }

            $genericMatches = [];
            foreach ($groups['generic'] as $keyword) {
                $kNorm = normalizeTextForMatch($keyword);
                if ($kNorm !== '' && strpos($normalizedName, $kNorm) !== false) {
                    $genericMatches[] = $keyword;
                }
            }

            if (!empty($specificMatches) || !empty($genericMatches)) {
                $matchedByTag[$tag] = [
                    'specific' => $specificMatches,
                    'generic'  => $genericMatches,
                ];
            }
        }

        if (empty($matchedByTag)) {
            return [
                'suggested_tags'   => ['altro'],
                'confidence'       => 'low',
                'matched_keywords' => [],
            ];
        }

        $suggestedTags   = array_keys($matchedByTag);
        $matchedKeywords = [];
        $specificCount   = 0;
        $genericCount    = 0;

        foreach ($matchedByTag as $matches) {
            $specificCount += count($matches['specific']);
            $genericCount  += count($matches['generic']);
            $matchedKeywords = array_merge($matchedKeywords, $matches['specific'], $matches['generic']);
        }

        $matchedKeywords = array_values(array_unique(array_map('strtolower', $matchedKeywords)));

        $confidence = 'low';
        if ($specificCount >= 2 || ($specificCount >= 1 && count($suggestedTags) >= 2)) {
            $confidence = 'high';
        } elseif ($specificCount >= 1 || $genericCount >= 2) {
            $confidence = 'medium';
        }

        return [
            'suggested_tags'   => $suggestedTags,
            'confidence'       => $confidence,
            'matched_keywords' => $matchedKeywords,
        ];
    }
}