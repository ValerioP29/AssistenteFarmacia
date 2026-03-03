<?php

function canonicalizeTagValue($tag): string {
    $tagValue = strtolower(trim((string)$tag));
    if ($tagValue === '') {
        return '';
    }

    $tagValue = str_replace(['-', ' '], '_', $tagValue);
    $tagValue = preg_replace('/_+/', '_', $tagValue);
    $tagValue = preg_replace('/[^a-z0-9_]/', '', $tagValue);

    return trim($tagValue, '_');
}

function getTagsCatalog(): array {
    return [
        'celiachia' => 'Celiachia',
        'consiglio_farmacista' => 'Consiglio farmacista',
        'consiglio_prenotazione' => 'Consiglio prenotazione',
        'in_evidenza' => 'In evidenza',
        'dermocosmesi' => 'Dermocosmesi',
        'bambino' => 'Bambino',
        'gastro' => 'Gastro',
        'dolore_febbre' => 'Dolore e febbre',
        'vitamine_integratori' => 'Vitamine e integratori',
    ];
}

function getAllowedCanonicalTags(): array {
    return array_keys(getTagsCatalog());
}

function normalizeTagsListCanonical(array $tags): array {
    $normalized = [];

    foreach ($tags as $tag) {
        $canonicalTag = canonicalizeTagValue($tag);
        if ($canonicalTag === '') {
            continue;
        }

        $normalized[$canonicalTag] = true;
    }

    return array_keys($normalized);
}
