<?php
/**
 * Rule-based tag engine helpers per prodotti farmacia.
 */

if (!function_exists('normalizeProductTagsInput')) {
    function normalizeProductTagsInput($rawTags): ?array {
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

            $tagValue = strtolower(trim((string)$tag));
            if ($tagValue === '') {
                continue;
            }

            $normalized[$tagValue] = true;
        }

        if (empty($normalized)) {
            return null;
        }

        return array_keys($normalized);
    }
}

if (!function_exists('normalizeTagArray')) {
    function normalizeTagArray($rawTags): array {
        $normalized = normalizeProductTagsInput($rawTags);
        return $normalized ?? [];
    }
}

if (!function_exists('parseBoolishValue')) {
    function parseBoolishValue($value): int {
        return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true) ? 1 : 0;
    }
}

if (!function_exists('normalizeTextForMatch')) {
    function normalizeTextForMatch(string $text): string {
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

if (!function_exists('buildTagRules')) {
    function buildTagRules(): array {
        return [
            'dolore_febbre' => [
                'specific' => ['tachipirina', 'paracetamol', 'ibuprofene', 'aspirina', 'nurofen', 'ketoprofene'],
                'generic' => ['dolore', 'antidolor', 'febbre', 'analges']
            ],
            'raffreddore_influenza' => [
                'specific' => ['fluimucil', 'actigrip', 'momentflu', 'vivin c'],
                'generic' => ['influenza', 'raffreddore', 'decongestion', 'congestion']
            ],
            'gola' => [
                'specific' => ['benagol', 'neo borocillina', 'tantum verde', 'golamir'],
                'generic' => ['gola', 'faring', 'mal di gola', 'pastiglie']
            ],
            'tosse' => [
                'specific' => ['bisolvon', 'sinecod', 'grintuss', 'sedotuss'],
                'generic' => ['tosse', 'sciroppo', 'espettor']
            ],
            'gastro' => [
                'specific' => ['maalox', 'gaviscon', 'omeprazolo', 'pantoprazolo'],
                'generic' => ['gastr', 'acidita', 'reflusso', 'digest']
            ],
            'probiotici' => [
                'specific' => ['enterolactis', 'dicoflor', 'codex', 'yovis'],
                'generic' => ['probiot', 'fermenti lattici']
            ],
            'vitamine_integratori' => [
                'specific' => ['supradyn', 'multicentrum', 'berocca', 'vitamina c'],
                'generic' => ['vitamin', 'integrator', 'magnesio', 'potassio']
            ],
            'dermocosmesi' => [
                'specific' => ['cerave', 'la roche', 'avene', 'eucerin', 'rilastil'],
                'generic' => ['crema', 'pelle', 'detergente viso', 'idratante', 'dermo']
            ],
            'igiene_orale' => [
                'specific' => ['elmex', 'meridol', 'parodontax', 'listerine'],
                'generic' => ['dentifricio', 'collutorio', 'orale', 'gengive']
            ],
            'medicazione' => [
                'specific' => ['cerotto', 'garza sterile', 'disinfettante', 'betadine'],
                'generic' => ['medicazione', 'benda', 'garza', 'cerotti']
            ],
            'bambino' => [
                'specific' => ['pediatrico', 'neonato', 'baby', 'bimbi'],
                'generic' => ['bambino', 'bimbi', 'infanzia']
            ],
            'donna' => [
                'specific' => ['ovuli', 'vaginale', 'menopausa'],
                'generic' => ['donna', 'femmin', 'intimo donna']
            ],
            'sonno_stress' => [
                'specific' => ['melatonina', 'valeriana', 'passiflora'],
                'generic' => ['sonno', 'stress', 'rilass']
            ],
            'pressione' => [
                'specific' => ['misuratore pressione', 'sfigmomanometro'],
                'generic' => ['pressione', 'ipertensione']
            ],
            'diabete_supporto' => [
                'specific' => ['glucometro', 'strisce glicemia', 'insulina'],
                'generic' => ['diabete', 'glicemia', 'glucosio']
            ],
            'naso' => [
                'specific' => ['spray nasale', 'soluzione fisiologica nasale'],
                'generic' => ['naso', 'nasale', 'rinite', 'sinus']
            ],
            'occhi' => [
                'specific' => ['collirio', 'lacrime artificiali'],
                'generic' => ['occhi', 'oculare', 'congiuntiv']
            ],
            'allergia' => [
                'specific' => ['cetirizina', 'loratadina', 'desloratadina'],
                'generic' => ['allerg', 'antistamin']
            ],
        ];
    }
}

if (!function_exists('suggestTagsFromName')) {
    function suggestTagsFromName(string $name): array {
        $normalizedName = normalizeTextForMatch($name);
        $rules = buildTagRules();

        $matchedByTag = [];
        foreach ($rules as $tag => $groups) {
            $specificMatches = [];
            foreach ($groups['specific'] as $keyword) {
                $keywordNorm = normalizeTextForMatch($keyword);
                if ($keywordNorm !== '' && strpos($normalizedName, $keywordNorm) !== false) {
                    $specificMatches[] = $keyword;
                }
            }

            $genericMatches = [];
            foreach ($groups['generic'] as $keyword) {
                $keywordNorm = normalizeTextForMatch($keyword);
                if ($keywordNorm !== '' && strpos($normalizedName, $keywordNorm) !== false) {
                    $genericMatches[] = $keyword;
                }
            }

            if (!empty($specificMatches) || !empty($genericMatches)) {
                $matchedByTag[$tag] = [
                    'specific' => $specificMatches,
                    'generic' => $genericMatches,
                ];
            }
        }

        if (empty($matchedByTag)) {
            return [
                'suggested_tags' => ['altro'],
                'confidence' => 'low',
                'matched_keywords' => [],
            ];
        }

        $suggestedTags = array_keys($matchedByTag);
        $matchedKeywords = [];
        $specificCount = 0;
        $genericCount = 0;

        foreach ($matchedByTag as $matches) {
            $specificCount += count($matches['specific']);
            $genericCount += count($matches['generic']);
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
            'suggested_tags' => $suggestedTags,
            'confidence' => $confidence,
            'matched_keywords' => $matchedKeywords,
        ];
    }
}
