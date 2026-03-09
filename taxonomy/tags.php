<?php
/**
 * taxonomy/tags.php
 *
 * SOURCE OF TRUTH UNICA per la tassonomia tag prodotti farmacia.
 *
 * Tutti i file che seguono derivano da qui — NON duplicare definizioni altrove:
 *   - panel/lib/tags_catalog.php          → usa getTagsCatalogFromTaxonomy()
 *   - panel/includes/product_tags_engine.php → usa buildTagRulesFromTaxonomy()
 *   - api/helpers/_related_tags.php        → usa canonicalizeTag() + getTagAliasMap()
 *   - app JS (relatedTagsPreset + inferRelatedTagFromName) → derivati da getUiTagsForJs()
 *
 * Struttura di ogni voce:
 *   label       string   Etichetta human-readable (UI panel e app)
 *   ui          bool     true = visibile nel dropdown filtro promozioni/prenotazioni in app
 *   inferrable  bool     true = assegnabile automaticamente dal motore di inferenza
 *   aliases     string[] Slug alternativi normalizzati a questo slug canonico
 *   keywords    array    Keyword per inferenza { specific: [...], generic: [...] }
 *                        specific = brand/prodotto preciso → confidence alta
 *                        generic  = keyword tematica → confidence media/bassa
 *
 * Slug canonici (31 totali):
 *   UI (9):     in_evidenza, consiglio_farmacista, consiglio_prenotazione,
 *               dolore_febbre, gastro, vitamine_integratori, dermocosmesi,
 *               bambino, celiachia
 *   Inferibili non-UI (20): omeopatia, fitoterapia, raffreddore_influenza, tosse,
 *               gola, naso, occhi, igiene_orale, sonno_stress, donna,
 *               diabete_supporto, pressione, allergia, protezione_solare, capelli,
 *               medicazione, dispositivi_medici, incontinenza, ortopedia,
 *               farmaco_prescrivibile
 *   Speciali (2): probiotici (inferibile, raggruppato spesso in gastro/vitamine),
 *                 altro (fallback panel)
 */

function getTagsTaxonomy(): array
{
    return [

        // ── 1. TAG VISIBILI NEL DROPDOWN UI DELL'APP ─────────────────────────

        'in_evidenza' => [
            'label'      => 'In evidenza',
            'ui'         => true,
            'inferrable' => false,
            'aliases'    => ['evidenza', 'featured', 'in_evidence', 'in evidenza'],
            'keywords'   => ['specific' => [], 'generic' => []],
        ],

        'consiglio_farmacista' => [
            'label'      => 'Consiglio farmacista',
            'ui'         => true,
            'inferrable' => false,
            'aliases'    => ['consiglio'],
            'keywords'   => ['specific' => [], 'generic' => []],
        ],

        'consiglio_prenotazione' => [
            'label'      => 'Consiglio prenotazione',
            'ui'         => true,
            'inferrable' => false,
            'aliases'    => [],
            'keywords'   => ['specific' => [], 'generic' => []],
        ],

        'dolore_febbre' => [
            'label'      => 'Dolore e febbre',
            'ui'         => true,
            'inferrable' => true,
            'aliases'    => ['dolore', 'febbre', 'antidolorifici', 'analgesici'],
            'keywords'   => [
                'specific' => [
                    'tachipirina', 'paracetamol', 'ibuprofene', 'ibuprofen',
                    'nurofen', 'ketoprofene', 'ketoprofen', 'aulin', 'oki',
                    'diclofenac', 'nimesulide', 'naprossene', 'ketorolac',
                    'novalgina', 'buscopan', 'aspirin', 'parecid', 'momentact',
                ],
                'generic'  => [
                    'dolore', 'antidolor', 'febbre', 'analges', 'antinfiamm', 'antipiret',
                ],
            ],
        ],

        'gastro' => [
            'label'      => 'Gastro',
            'ui'         => true,
            'inferrable' => true,
            'aliases'    => [
                'gastrointestinale', 'digestione', 'stomaco',
                'epatico_biliare', 'lassativi', 'nausea_vomito',
            ],
            'keywords'   => [
                'specific' => [
                    'maalox', 'gaviscon', 'omeprazolo', 'pantoprazolo', 'lansoprazolo',
                    'neobianacid', 'molaxole', 'digerfast', 'normix', 'klostenal',
                ],
                'generic'  => [
                    'gastr', 'acidita', 'reflusso', 'digest', 'colon', 'intestin',
                    'diarrea', 'nausea', 'vomito', 'lassativ', 'stitichezza',
                    'gonfiore', 'meteorismo', 'colite', 'emorroidi',
                    'epatico', 'biliare', 'fegato',
                ],
            ],
        ],

        'vitamine_integratori' => [
            'label'      => 'Vitamine e integratori',
            'ui'         => true,
            'inferrable' => true,
            'aliases'    => [
                'integratori', 'vitamine', 'vitamini_minerali', 'integratore',
                'antiossidanti', 'energia_sport', 'dimagrimento', 'colesterolo_trigliceridi',
            ],
            'keywords'   => [
                'specific' => [
                    'supradyn', 'multicentrum', 'berocca', 'polase', 'longlife',
                    'sideral', 'enerzona', 'lovren', 'betotal', 'mgk',
                    'arkoroyal', 'vital proteins', 'pronto recupero',
                ],
                'generic'  => [
                    'vitamin', 'integrat', 'magnesio', 'potassio', 'zinco', 'calcio',
                    'ferro', 'folico', 'omega', 'collagene', 'glucosamina', 'carnitina',
                    'creatina', 'antiossid', 'q10', 'luteina', 'selenium', 'suppl nutr',
                    'colesterol', 'riso rosso', 'berberina', 'dimagr', 'drenante',
                ],
            ],
        ],

        'dermocosmesi' => [
            'label'      => 'Dermocosmesi',
            'ui'         => true,
            'inferrable' => true,
            'aliases'    => [
                'dermocosmetica', 'cosmetica', 'dermo', 'skincare',
                'viso', 'corpo', 'cicatrici_smagliature', 'make_up', 'igiene_personale',
            ],
            'keywords'   => [
                'specific' => [
                    'cerave', 'la roche', 'avene', 'eucerin', 'rilastil', 'uriage',
                    'vichy', 'bioderma', 'aveeno', 'neutrogena', 'lichtena', 'triderm',
                    'sauber', 'bioclin', 'klorane', 'rougj', 'twins', 'dermoflan',
                    'idratal', 'sarnol', 'nivea',
                ],
                'generic'  => [
                    'crema', 'pelle', 'detergente viso', 'idratante', 'dermo',
                    'emolliente', 'lenitiv', 'siero', 'lozione', 'mousse', 'strucc',
                    'acne', 'eczema', 'psoriasi', 'dermatite', 'smagliature', 'cicatrice',
                    'fondot', 'cipria', 'make up',
                ],
            ],
        ],

        'bambino' => [
            'label'      => 'Bambino',
            'ui'         => true,
            'inferrable' => true,
            'aliases'    => [
                'pediatria', 'pediatrico', 'neonati', 'infanzia',
                'latte_formula', 'pappe_svezzamento', 'mamma_allattamento',
            ],
            'keywords'   => [
                'specific' => [
                    'mellin', 'plasmon', 'humana', 'nidina', 'aptamil', 'miltina',
                    'omneo', 'similac', 'suavinex', 'chicco',
                ],
                'generic'  => [
                    'bambino', 'bimbi', 'infanzia', 'neonat', 'infant', 'pediatr',
                    'junior', 'baby', 'latte polv', 'latte 1', 'latte 2', 'mamma',
                    'gravidanz', 'allattam',
                ],
            ],
        ],

        'celiachia' => [
            'label'      => 'Celiachia',
            'ui'         => true,
            'inferrable' => true,
            'aliases'    => ['senza_glutine', 'glutine', 'celiaci', 'dietetica', 'senza_lattosio'],
            'keywords'   => [
                'specific' => ['schar', 'nutrifree', 'biaglut'],
                'generic'  => ['celiac', 'celiaci', 'senza glutine', 'sglut', 'glutine'],
            ],
        ],

        // ── 2. TAG INFERIBILI (assegnati dal motore, non nel dropdown UI) ───

        'omeopatia' => [
            'label'      => 'Omeopatia',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['omeo', 'omeopatia_sali_schussler', 'omeopatia_bach'],
            'keywords'   => [
                'specific' => ['boiron', 'reckeweg', 'galenic'],
                'generic'  => [
                    'globuli', 'granuli', 'schuss', 'bach orig', 'rescue',
                    'omeop', '30ch', '15ch', '9ch', '6ch', '200ch', 'mk ',
                ],
            ],
        ],

        'fitoterapia' => [
            'label'      => 'Fitoterapia',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['erboristeria', 'fitoterapico', 'erbe'],
            'keywords'   => [
                'specific' => [],
                'generic'  => [
                    'tisana', 'tintura madre', 'valeriana', 'arnica', 'serenoa',
                    'echinacea', 'ginkgo', 'propoli', 'centella', 'cardo mariano',
                    'rodiola', 'ashwagandha', 'iperico', 'melissa', 'passiflora',
                    'biancospino', 'tarassaco',
                ],
            ],
        ],

        'raffreddore_influenza' => [
            'label'      => 'Raffreddore e influenza',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['raffreddore', 'influenza', 'antiinfluenzale', 'tosse_raffreddore'],
            'keywords'   => [
                'specific' => ['fluimucil', 'actigrip', 'vivin c', 'broncovit', 'fluifort'],
                'generic'  => [
                    'influenza', 'raffreddore', 'decongestion', 'antinfluenz',
                    'mucolitico', 'espettorante', 'catarro',
                ],
            ],
        ],

        'tosse' => [
            'label'      => 'Tosse',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => [],
            'keywords'   => [
                'specific' => ['bisolvon', 'sinecod', 'grintuss', 'sedotuss'],
                'generic'  => ['tosse', 'mucolitico', 'espettorant'],
            ],
        ],

        'gola' => [
            'label'      => 'Gola',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['mal_di_gola', 'faringite', 'naso_gola', 'otorinolaringoiatria'],
            'keywords'   => [
                'specific' => ['benagol', 'neo borocillina', 'tantum verde', 'golamir'],
                'generic'  => ['gola', 'faring', 'mal di gola'],
            ],
        ],

        'naso' => [
            'label'      => 'Naso',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['nasale', 'rinite'],
            'keywords'   => [
                'specific' => ['rinazina', 'rinovit', 'rinogutt', 'rinoflux', 'chirenol'],
                'generic'  => ['naso', 'nasale', 'rinite', 'sinusite', 'spray nas', 'gtt rino'],
            ],
        ],

        'occhi' => [
            'label'      => 'Occhi',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['oculistica', 'oculare', 'lenti_a_contatto'],
            'keywords'   => [
                'specific' => ['irilens', 'cationorm', 'occhiale', 'occhialux'],
                'generic'  => [
                    'collirio', 'lacrime artificiali', 'oculare', 'congiuntiv',
                    'coll ', 'gocce oculari', 'lacrim',
                ],
            ],
        ],

        'igiene_orale' => [
            'label'      => 'Igiene orale',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['orale', 'denti', 'protesi_dentali'],
            'keywords'   => [
                'specific' => [
                    'elmex', 'meridol', 'parodontax', 'listerine', 'sensodyne',
                    'curasept', 'curaprox', 'gum ', 'kukident', 'corega', 'oralb',
                ],
                'generic'  => [
                    'dentifricio', 'collutorio', 'gengive', 'alito',
                    'spazzolino', 'protesi dent',
                ],
            ],
        ],

        'sonno_stress' => [
            'label'      => 'Sonno e stress',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['sonno_stress_ansia', 'sonno', 'stress', 'ansia', 'relax'],
            'keywords'   => [
                'specific' => ['anxitane', 'delorazepam'],
                'generic'  => ['sonno', 'stress', 'rilass', 'insonnia', 'ansia', 'melatonin'],
            ],
        ],

        'donna' => [
            'label'      => 'Donna',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['ginecologia', 'femminile', 'igiene_intima'],
            'keywords'   => [
                'specific' => ['saugella', 'vidermina', 'canesten', 'gynocanesten', 'ozogin'],
                'generic'  => [
                    'vaginale', 'menopausa', 'femmin', 'intimo', 'ginecol', 'ciclo',
                ],
            ],
        ],

        'diabete_supporto' => [
            'label'      => 'Diabete e glicemia',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['diabete', 'glicemia', 'diabete_glicemia'],
            'keywords'   => [
                'specific' => ['glucometro', 'contour'],
                'generic'  => [
                    'diabete', 'glicemia', 'glucosio', 'insulina', 'lancette', 'strips',
                ],
            ],
        ],

        'pressione' => [
            'label'      => 'Pressione arteriosa',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => [
                'pressione_arteriosa', 'ipertensione', 'cardiovascolare',
                'cardioprotettori',
            ],
            'keywords'   => [
                'specific' => ['sfigmomanometro'],
                'generic'  => [
                    'pressione', 'ipertension', 'cardiovasc', 'circolaz',
                    'varici', 'cardio', 'cuore', 'anticoagul',
                ],
            ],
        ],

        'allergia' => [
            'label'      => 'Allergia',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['antiallergico', 'antistaminico', 'apparato_respiratorio', 'asma_bpco'],
            'keywords'   => [
                'specific' => ['cetirizina', 'loratadina', 'desloratadina', 'montelukast'],
                'generic'  => ['allerg', 'antistamin', 'asma', 'bronchite', 'bpco'],
            ],
        ],

        'protezione_solare' => [
            'label'      => 'Protezione solare',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['solare', 'abbronzante'],
            'keywords'   => [
                'specific' => ['anthelios', 'dermasol', 'angstrom', 'somat'],
                'generic'  => ['solare', 'spf', 'uva', 'uvb', 'abbronz', 'doposole'],
            ],
        ],

        'capelli' => [
            'label'      => 'Capelli',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['tricologia'],
            'keywords'   => [
                'specific' => ['bioscalin', 'planters', 'anacaps', 'biothymus'],
                'generic'  => ['capell', 'hair', 'forfora', 'alopecia', 'anticadut'],
            ],
        ],

        'probiotici' => [
            'label'      => 'Probiotici',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['fermenti_lattici', 'prebiotici'],
            'keywords'   => [
                'specific' => ['enterolactis', 'dicoflor', 'codex', 'yovis', 'florilac'],
                'generic'  => ['probiot', 'prebiot', 'fermenti lattici', 'lactobacil'],
            ],
        ],

        'medicazione' => [
            'label'      => 'Medicazione',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['medicazione_ferite', 'wound_care', 'pronto_soccorso'],
            'keywords'   => [
                'specific' => ['betadine', 'amuchina'],
                'generic'  => ['medicazione', 'benda', 'garza', 'cerotti', 'disinfet', 'antisett'],
            ],
        ],

        'dispositivi_medici' => [
            'label'      => 'Dispositivi medici',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => [
                'presidi_medici', 'aghi_siringhe', 'apparecchi_elettromedicali',
            ],
            'keywords'   => [
                'specific' => [],
                'generic'  => [
                    'ago ', 'siringa', 'termometro', 'sfigmo', 'nebulizzat',
                    'misuratore', 'glucometro', 'extrafine sir',
                ],
            ],
        ],

        'incontinenza' => [
            'label'      => 'Incontinenza',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['pannoloni'],
            'keywords'   => [
                'specific' => ['tena'],
                'generic'  => ['pannolone', 'incontinenz', 'slip contr', 'assorbente uri'],
            ],
        ],

        'ortopedia' => [
            'label'      => 'Ortopedia',
            'ui'         => false,
            'inferrable' => true,
            'aliases'    => ['ortopedia_fisioterapia', 'fisioterapia', 'apparato_muscolo_scheletrico'],
            'keywords'   => [
                'specific' => ['gibaud'],
                'generic'  => [
                    'tutore', 'cavigl', 'ginocch', 'colett', 'bendaggio',
                    'fascia elast', 'calze comp', 'lombare', 'artros', 'artrite',
                ],
            ],
        ],

        'farmaco_prescrivibile' => [
            'label'      => 'Farmaco con ricetta',
            'ui'         => false,
            'inferrable' => true,
            // Assegnato dalla presenza di '*' nel nome winfarm, non da keyword.
            'aliases'    => ['ricetta', 'prescription', 'rx'],
            'keywords'   => ['specific' => [], 'generic' => []],
        ],

        // ── 3. TAG SPECIALI ───────────────────────────────────────────────

        'altro' => [
            'label'      => 'Altro',
            'ui'         => false,
            'inferrable' => false,
            'aliases'    => [],
            'keywords'   => ['specific' => [], 'generic' => []],
        ],

    ];
}

// ── Helper pubblici ───────────────────────────────────────────────────────────

/** slug → label (tutti i tag) */
function getTagsLabelMap(): array
{
    return array_map(fn($v) => $v['label'], getTagsTaxonomy());
}

/** Solo i tag visibili nel dropdown UI */
function getUiTags(): array
{
    return array_filter(getTagsTaxonomy(), fn($v) => $v['ui'] === true);
}

/** Solo i tag inferibili automaticamente */
function getInferrableTags(): array
{
    return array_filter(getTagsTaxonomy(), fn($v) => $v['inferrable'] === true);
}

/** Mappa alias → slug canonico */
function getTagAliasMap(): array
{
    $map = [];
    foreach (getTagsTaxonomy() as $canonical => $def) {
        foreach ($def['aliases'] as $alias) {
            $normalized = _normalizeRawTag($alias);
            if ($normalized !== '') {
                $map[$normalized] = $canonical;
            }
        }
    }
    return $map;
}

/**
 * Normalizza il formato di un tag grezzo (lowercase, underscore, solo a-z0-9_).
 * Stesso algoritmo di canonicalizeTagValue in tags_catalog.php e JS.
 */
function _normalizeRawTag(string $tag): string
{
    $v = mb_strtolower(trim($tag), 'UTF-8');
    $v = str_replace(['-', ' '], '_', $v);
    $v = preg_replace('/_+/', '_', $v);
    $v = preg_replace('/[^a-z0-9_]/', '', $v);
    return trim($v, '_');
}

/**
 * Canonicalizza un tag: normalizza il formato e risolve gli alias.
 * Da usare ovunque si riceva input esterno (panel, API, import).
 */
function canonicalizeTag(string $tag): string
{
    $v = _normalizeRawTag($tag);
    if ($v === '') {
        return '';
    }
    $aliasMap = getTagAliasMap();
    return $aliasMap[$v] ?? $v;
}

/** Regole keyword nel formato { tag => { specific: [...], generic: [...] } } */
function buildTagRulesFromTaxonomy(): array
{
    $rules = [];
    foreach (getInferrableTags() as $slug => $def) {
        if (!empty($def['keywords']['specific']) || !empty($def['keywords']['generic'])) {
            $rules[$slug] = [
                'specific' => $def['keywords']['specific'],
                'generic'  => $def['keywords']['generic'],
            ];
        }
    }
    return $rules;
}

/**
 * Genera la struttura JSON per il JS (relatedTagsPreset + inferMap).
 * Usato per sincronizzare il frontend senza duplicare le definizioni.
 *
 * @param bool $uiOnly  true = solo tag UI (per il dropdown), false = tutti gli inferibili
 */
function getTagsForJs(bool $uiOnly = false): array
{
    $taxonomy = $uiOnly ? getUiTags() : getInferrableTags();
    $result   = [];

    foreach ($taxonomy as $slug => $def) {
        $entry = ['value' => $slug, 'label' => $def['label']];
        if (!$uiOnly) {
            $entry['keywords'] = array_merge(
                $def['keywords']['specific'],
                $def['keywords']['generic']
            );
        }
        $result[] = $entry;
    }

    return $result;
}