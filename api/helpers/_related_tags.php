<?php

if (!function_exists('related_tags_normalize_text')) {
	function related_tags_normalize_text($value){
		$value = strtolower(trim((string)$value));
		$value = str_replace(['-', ' '], '_', $value);
		return $value;
	}
}

if (!function_exists('related_tags_get_keywords_map')) {
	function related_tags_get_keywords_map(){
		return [
			'dolore_febbre' => ['dolore', 'febbre', 'antidolorifici', 'analgesici', 'antinfiammatori'],
			'raffreddore_influenza' => ['raffreddore', 'influenza', 'decongestionanti', 'respiratorio'],
			'gola' => ['gola', 'mal di gola', 'spray gola', 'pastiglie', 'faringe', 'orofaringeo'],
			'tosse' => ['tosse', 'sciroppo', 'sciroppi', 'sedativo', 'espettorante', 'espettoranti', 'gola e tosse'],
			'gastro' => ['gastro', 'digestione', 'reflusso', 'intestino'],
			'dermocosmesi' => ['dermocosmesi', 'pelle', 'viso', 'beauty'],
			'vitamine_integratori' => ['vitamine', 'integratori', 'benessere', 'minerali'],
			'bambino' => ['bambino', 'infanzia', 'baby', 'pediatrico'],
			'medicazione' => ['medicazione', 'cerotti', 'disinfettanti', 'garze'],
			'igiene_orale' => ['igiene orale', 'orale', 'dentifricio', 'collutorio', 'gengive'],
			'naso' => ['naso', 'nasale', 'decongestionante', 'spray', 'sinus', 'rinite', 'sinusite'],
			'occhi' => ['occhi', 'oculare', 'colliri'],
		];
	}
}

if (!function_exists('related_tags_get_alias_map')) {
	function related_tags_get_alias_map(){
		return [
			'evidenza' => 'in_evidenza',
			'featured' => 'in_evidenza',
			'in_evidenza' => 'in_evidenza',
			'tosse' => 'tosse',
			'naso' => 'naso',
			'gola' => 'gola',
		];
	}
}

if (!function_exists('related_tags_normalize_keyword')) {
	function related_tags_normalize_keyword($value){
		$value = strtolower(trim((string)$value));
		$value = preg_replace('/\s+/u', ' ', $value);
		return $value;
	}
}

if (!function_exists('related_tags_get_category_keywords')) {
	function related_tags_get_category_keywords($relatedTag){
		$tag = related_tags_normalize_text($relatedTag);
		$aliases = related_tags_get_alias_map();
		$canonicalTag = $aliases[$tag] ?? $tag;

		$map = related_tags_get_keywords_map();
		$keywords = $map[$canonicalTag] ?? [];
		if ($canonicalTag !== '') {
			$keywords[] = str_replace('_', ' ', $canonicalTag);
			$keywords[] = $canonicalTag;
		}

		$keywords = array_values(array_unique(array_filter(array_map('related_tags_normalize_keyword', $keywords), function($kw){
			return $kw !== '';
		})));

		return [
			'canonical_tag' => $canonicalTag,
			'keywords' => $keywords,
		];
	}
}

if (!function_exists('related_tags_infer_from_product')) {
	function related_tags_infer_from_product($name, $description = '', $category = ''){
		$name = related_tags_normalize_keyword($name);
		$description = related_tags_normalize_keyword($description);
		$category = related_tags_normalize_keyword($category);
		$haystack = trim($name . ' ' . $description . ' ' . $category);
		if ($haystack === '') return [];

		$matched = [];
		foreach (related_tags_get_keywords_map() as $tag => $keywords) {
			$allTerms = $keywords;
			$allTerms[] = str_replace('_', ' ', $tag);
			$allTerms[] = $tag;
			foreach ($allTerms as $term) {
				$term = related_tags_normalize_keyword($term);
				if ($term === '') continue;
				if (mb_stripos($haystack, $term, 0, 'UTF-8') !== false) {
					$matched[] = $tag;
					break;
				}
			}
		}

		return array_values(array_unique($matched));
	}
}
