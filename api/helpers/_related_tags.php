<?php
/**
 * api/helpers/_related_tags.php
 *
 * Due funzioni principali esposte all'API:
 *
 *   normalize_product_name(string $raw): string
 *     Converte il nome gestionale winfarm in nome leggibile per l'app.
 *     Es: "EPHYNAL*20CPR RIV MAST 100MG" → "Ephynal – 20 compresse rivestite masticabili 100mg"
 *
 *   related_tags_infer_from_product(string $name, string $desc, string $category): string[]
 *     Inferisce i tag tassonomici dal nome/descrizione/categoria prodotto.
 *     Restituisce slug canonici allineati alla taxonomy (taxonomy/tags.php).
 *
 * SLUG CANONICI usati (dalla taxonomy):
 *   dolore_febbre, gastro, vitamine_integratori, dermocosmesi, bambino,
 *   celiachia, omeopatia, fitoterapia, raffreddore_influenza, tosse, gola, naso,
 *   occhi, igiene_orale, sonno_stress, donna, diabete_supporto, pressione,
 *   allergia, protezione_solare, capelli, probiotici, medicazione,
 *   dispositivi_medici, incontinenza, ortopedia, farmaco_prescrivibile
 *
 * SLUG RIMOSSI rispetto alla versione precedente (alias risolti):
 *   dermocosmetica       → dermocosmesi
 *   gastrointestinale    → gastro
 *   pediatria            → bambino
 *   integratori          → vitamine_integratori
 *   vitamini_minerali    → vitamine_integratori
 *   sonno_stress_ansia   → sonno_stress
 *   diabete_glicemia     → diabete_supporto
 *   oculistica           → occhi
 *   tosse_raffreddore    → tosse / raffreddore_influenza
 *   naso_gola            → naso / gola
 *   ortopedia_fisioterapia → ortopedia
 *   medicazione_ferite   → medicazione
 *   pressione_arteriosa  → pressione
 *   senza_glutine        → celiachia
 *   ginecologia          → donna
 *
 * @package JTA\Pharma
 */

require_once __DIR__ . '/../../taxonomy/tags.php';

// ---------------------------------------------------------------------------
// 0. FUNZIONE RICHIESTA DA product-search.php
//    Sostituisce la vecchia versione hardcoded — ora deriva dalla taxonomy.
// ---------------------------------------------------------------------------

/**
 * Restituisce le keyword da usare nella LIKE-search SQL per un dato tag.
 *
 * Usato da product-search.php nel related mode per cercare prodotti
 * nella colonna tags (JSON), name, description e category.
 *
 * Contiene:
 *   - Lo slug canonico (es. "dermocosmesi") → match nella colonna tags
 *   - Tutti gli alias del tag (es. "dermocosmetica") → match dati legacy in DB
 *   - Le keyword specific + generic della taxonomy → match in name/description
 *
 * @param string $tag  Slug grezzo (canonicalizzato internamente)
 * @return array { keywords: string[] }
 */
function related_tags_get_category_keywords(string $tag): array
{
    $canonical = canonicalizeTag($tag);
    if ($canonical === '') {
        return ['keywords' => []];
    }

    $taxonomy = getTagsTaxonomy();
    $def      = $taxonomy[$canonical] ?? null;

    $keywords = [$canonical];

    if ($def !== null) {
        // Alias → per trovare vecchi slug ancora presenti in DB
        foreach ($def['aliases'] as $alias) {
            $norm = _normalizeRawTag($alias);
            if ($norm !== '') {
                $keywords[] = $norm;
            }
        }
        // Keyword per match in name / description / category
        foreach ($def['keywords']['specific'] as $kw) {
            if (trim($kw) !== '') {
                $keywords[] = trim($kw);
            }
        }
        foreach ($def['keywords']['generic'] as $kw) {
            if (trim($kw) !== '') {
                $keywords[] = trim($kw);
            }
        }
    }

    return ['keywords' => array_values(array_unique($keywords))];
}

// ---------------------------------------------------------------------------
// 1. MAPPA ABBREVIAZIONI FORMA FARMACEUTICA (gestionale → italiano leggibile)
// ---------------------------------------------------------------------------
const PHARMA_FORM_MAP = [
    // Forme orali solide
    'CPR'       => 'compresse',
    'CPS'       => 'capsule',
    'TAV'       => 'tavolette',
    'PAST'      => 'pastiglie',
    'POLV'      => 'polvere',
    'ORODISP'   => 'compresse orodispersibili',
    'GLOBULI'   => 'globuli',
    'GRANULI'   => 'granuli',
    // Modificatori forma
    'RIV'       => 'rivestite',
    'MAST'      => 'masticabili',
    'EFF'       => 'effervescenti',
    'RETARD'    => 'a rilascio prolungato',
    'RETRD'     => 'a rilascio prolungato',
    // Forme orali liquide
    'SCIR'      => 'sciroppo',
    'SCIROPPO'  => 'sciroppo',
    'SOLUZ'     => 'soluzione',
    'SOL'       => 'soluzione',
    'GTT'       => 'gocce',
    'GOCCE'     => 'gocce',
    'SOSP'      => 'sospensione',
    'EMULS'     => 'emulsione',
    // Forme iniettabili
    'FL'        => '',
    'AMP'       => 'fiale',
    'SIR'       => 'siringa preriempita',
    'INIETT'    => 'iniettabile',
    'EV'        => 'endovenosa',
    'IM'        => 'intramuscolo',
    'SC'        => 'sottocute',
    // Bustine
    'BUST'      => 'bustine',
    'BUSTA'     => 'bustine',
    // Topici
    'GEL'       => 'gel',
    'CREMA'     => 'crema',
    'CR'        => 'crema',
    'POM'       => 'pomata',
    'POMATA'    => 'pomata',
    'UNG'       => 'unguento',
    'LOZIONE'   => 'lozione',
    'LOZ'       => 'lozione',
    'MOUSSE'    => 'mousse',
    'SHAMPOO'   => 'shampoo',
    'BALSAMO'   => 'balsamo',
    'SIERO'     => 'siero',
    // Spray
    'SPRAY'     => 'spray',
    'AEROSOL'   => 'aerosol',
    // Cerotti
    'CER'       => 'cerotti',
    'CEROTTO'   => 'cerotto',
    'PATCH'     => 'patch',
    // Rettale / vaginale
    'SUPP'      => 'supposte',
    'OVULI'     => 'ovuli',
    'OVU'       => 'ovuli',
    // Oculistici
    'COLL'      => 'collirio',
    'COLLIRIO'  => 'collirio',
    'MONOD'     => 'monodose',
    // Vie di somministrazione
    'OS'        => 'orale',
    'NAS'       => 'nasale',
    'OFT'       => 'oftalmico',
    'OTI'       => 'auricolare',
    'VAG'       => 'vaginale',
    'RETT'      => 'rettale',
    'CUT'       => 'cutanea',
];

// ---------------------------------------------------------------------------
// 2. NORMALIZZA NOME PRODOTTO (gestionale → display leggibile)
// ---------------------------------------------------------------------------

/**
 * Converte il nome grezzo da gestionale winfarm in nome leggibile per l'app.
 *
 * @param string $raw  Nome grezzo (uppercase, con asterischi, abbreviazioni)
 * @return string      Nome pulito, title-case, con trattino separatore
 */
function normalize_product_name(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $hasStar = strpos($raw, '*') !== false;
    if ($hasStar) {
        [$brand, $form] = explode('*', $raw, 2);
    } else {
        $brand = $raw;
        $form  = '';
        $formKws = [
            'CPR', 'CPS', 'BUST', 'GTT', 'COLL', 'SPRAY', 'GEL', 'CR ', 'CREMA',
            'POM', 'UNG', 'SCIROPPO', 'SCIR', 'POLV', 'CER ', 'CEROTTO', 'LOZIONE',
            'LOZ', 'SHAMPOO', 'MONOD', 'PAST', 'SUPP', 'SOLUZ', 'SOL ', 'SOSP',
            'SIERO', 'MOUSSE', 'GLOBULI', 'GRANULI', 'EMULS',
        ];
        foreach ($formKws as $kw) {
            $pos = strpos(strtoupper($raw), ' ' . $kw);
            if ($pos !== false) {
                $brand = substr($raw, 0, $pos);
                $form  = substr($raw, $pos + 1);
                break;
            }
        }
    }

    $brandClean = _pharma_title_case(trim($brand));
    $formClean  = _normalize_form_string(trim($form ?? ''));

    return $formClean !== '' ? ($brandClean . ' – ' . $formClean) : $brandClean;
}

function _normalize_form_string(string $form): string
{
    if ($form === '') {
        return '';
    }

    $out    = [];
    $tokens = preg_split('/\s+/', strtoupper(trim($form)));

    foreach ($tokens as $tok) {
        if (preg_match('/^(\d+[\d,.]*)([A-Z]{2,})$/', $tok, $m)) {
            $num  = strtolower($m[1]);
            $abbr = $m[2];
            $exp  = PHARMA_FORM_MAP[$abbr] ?? strtolower($abbr);
            $out[] = $exp !== '' ? ($num . ' ' . $exp) : $num;
            continue;
        }
        if (isset(PHARMA_FORM_MAP[$tok])) {
            $v = PHARMA_FORM_MAP[$tok];
            if ($v !== '') {
                $out[] = $v;
            }
            continue;
        }
        if (preg_match('/^\d+[\d,.]*[A-Z%\/]*$/i', $tok)) {
            $out[] = strtolower($tok);
            continue;
        }
        $out[] = strtolower($tok);
    }

    $filtered = [];
    $prev     = null;
    foreach ($out as $w) {
        $w = trim($w);
        if ($w !== '' && $w !== $prev) {
            $filtered[] = $w;
            $prev       = $w;
        }
    }

    return implode(' ', $filtered);
}

function _pharma_title_case(string $str): string
{
    $keepUpper = [
        'CH', 'DH', 'UV', 'SPF', 'UVA', 'UVB', 'UVM', 'DNA', 'AHA', 'BHA',
        'CBD', 'DHT', 'EPA', 'DHA', 'Q10', 'B12', 'B6', 'B1', 'B2', 'B3',
        'C', 'D3', 'K2', 'NAC', 'GABA',
    ];
    $words = preg_split('/\s+/', trim($str));
    $out   = [];
    foreach ($words as $w) {
        if ($w === '') {
            continue;
        }
        $out[] = in_array(strtoupper($w), $keepUpper, true)
            ? strtoupper($w)
            : (mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1)));
    }
    return implode(' ', $out);
}

// ---------------------------------------------------------------------------
// 3. REGOLE KEYWORD → TAG (locale, estende la taxonomy con pattern winfarm)
// ---------------------------------------------------------------------------

/**
 * Regole specifiche per nomi gestionali winfarm (tutto uppercase, abbreviazioni,
 * pattern come asterisco per RX, potenze omeopatiche, brand italiani).
 *
 * Struttura: [ 'PATTERN_UPPERCASE' => ['tag1', 'tag2'], ... ]
 * Il pattern viene cercato nella stringa haystack = " NAME DESC CATEGORY "
 * in uppercase. Non è regex: è strpos() per performance su 47k prodotti.
 *
 * NOTA: i tag qui usano solo slug canonici dalla taxonomy.
 */
function _pharma_winfarm_rules(): array
{
    return [

        // ── OMEOPATIA ────────────────────────────────────────────────────
        'SCHUSS'            => ['omeopatia'],
        'BOIRON'            => ['omeopatia'],
        'RECKEWEG'          => ['omeopatia'],
        'GALENIC'           => ['omeopatia'],
        'ZZZ '              => ['omeopatia'],
        'GLOBULI'           => ['omeopatia'],
        'GRANULI'           => ['omeopatia'],
        'BACH ORIG'         => ['omeopatia'],
        'BACH FL'           => ['omeopatia'],
        'RESCUE'            => ['omeopatia'],

        // ── FITOTERAPIA ──────────────────────────────────────────────────
        'TISANA'            => ['fitoterapia'],
        'TINTURA MADRE'     => ['fitoterapia'],
        ' TM '             => ['fitoterapia'],
        'VALERIANA'         => ['fitoterapia', 'sonno_stress'],
        'ARNICA'            => ['fitoterapia', 'ortopedia'],
        'SERENOA'           => ['fitoterapia'],
        'ECHINACEA'         => ['fitoterapia'],
        'GINKGO'            => ['fitoterapia'],
        'PROPOLI'           => ['fitoterapia'],
        'CENTELLA'          => ['fitoterapia'],
        'CARDO MARIANO'     => ['fitoterapia'],
        'RODIOLA'           => ['fitoterapia', 'sonno_stress'],
        'ASHWAGANDHA'       => ['fitoterapia', 'sonno_stress'],
        'IPERICO'           => ['fitoterapia', 'sonno_stress'],
        'MELISSA'           => ['fitoterapia', 'sonno_stress'],
        'PASSIFLORA'        => ['fitoterapia', 'sonno_stress'],
        'BIANCOSPINO'       => ['fitoterapia', 'pressione'],
        'TARASSACO'         => ['fitoterapia', 'gastro'],
        'CARCIOFO'          => ['fitoterapia', 'gastro'],
        'HAMAMELIS'         => ['fitoterapia'],

        // ── VITAMINE E INTEGRATORI ───────────────────────────────────────
        'MULTICENTRUM'      => ['vitamine_integratori'],
        'POLASE'            => ['vitamine_integratori'],
        'LONGLIFE'          => ['vitamine_integratori'],
        'ENERZONA'          => ['vitamine_integratori'],
        'BETOTAL'           => ['vitamine_integratori'],
        'SIDERAL'           => ['vitamine_integratori'],
        'LOVREN'            => ['vitamine_integratori'],
        'BLUE TONIC'        => ['vitamine_integratori'],
        'MGK VIS'           => ['vitamine_integratori'],
        'ARKOROYAL'         => ['vitamine_integratori', 'fitoterapia'],
        'PAPPA REALE'       => ['vitamine_integratori', 'fitoterapia'],
        'SUPPL NUTR'        => ['vitamine_integratori'],
        'VITAMINA'          => ['vitamine_integratori'],
        'VITAMI'            => ['vitamine_integratori'],
        'OMEGA 3'           => ['vitamine_integratori', 'pressione'],
        'OMEGA3'            => ['vitamine_integratori', 'pressione'],
        'COENZIMA'          => ['vitamine_integratori'],
        'Q10'               => ['vitamine_integratori'],
        'COLESTEROL'        => ['vitamine_integratori', 'pressione'],
        'RISO ROSSO'        => ['vitamine_integratori', 'pressione'],
        'BERBERINA'         => ['vitamine_integratori', 'diabete_supporto'],
        'LUTEINA'           => ['vitamine_integratori', 'occhi'],
        'LUTEIN'            => ['vitamine_integratori', 'occhi'],
        'SELENIUM'          => ['vitamine_integratori'],
        'CARNITINA'         => ['vitamine_integratori'],
        'CREATINA'          => ['vitamine_integratori'],
        'COLLAGENE'         => ['vitamine_integratori', 'ortopedia'],
        'GLUCOSAMINA'       => ['vitamine_integratori', 'ortopedia'],
        'CONDROITIN'        => ['vitamine_integratori', 'ortopedia'],
        'FOLICO'            => ['vitamine_integratori', 'bambino'],
        'ACIDO FOLICO'      => ['vitamine_integratori', 'bambino'],
        'MELATONIN'         => ['vitamine_integratori', 'sonno_stress'],
        'DIMAGR'            => ['vitamine_integratori'],
        'DRENANTE'          => ['vitamine_integratori'],
        'DIURET'            => ['vitamine_integratori'],
        'DIURERBE'          => ['vitamine_integratori'],
        'ADIPROX'           => ['vitamine_integratori'],
        'FITOMAGRA'         => ['vitamine_integratori'],
        'PRONTO RECUPERO'   => ['vitamine_integratori'],
        'IDRAMIN'           => ['vitamine_integratori'],

        // ── PROBIOTICI ───────────────────────────────────────────────────
        'PROBIOT'           => ['probiotici', 'gastro'],
        'PREBIOT'           => ['probiotici', 'gastro'],
        'FERMENTI'          => ['probiotici', 'gastro'],
        'LACTOBACIL'        => ['probiotici'],
        'BIFIDOBACT'        => ['probiotici'],
        'FLORILAC'          => ['probiotici'],
        'ENTEROGERM'        => ['probiotici'],

        // ── BAMBINO / PEDIATRIA ──────────────────────────────────────────
        'MELLIN'            => ['bambino'],
        'PLASMON'           => ['bambino'],
        'HUMANA'            => ['bambino'],
        'NIDINA'            => ['bambino'],
        'APTAMIL'           => ['bambino'],
        'MILTINA'           => ['bambino'],
        'OMNEO'             => ['bambino'],
        'HIPP '             => ['bambino'],
        'SIMILAC'           => ['bambino'],
        'SUAVINEX'          => ['bambino'],
        'CHICCO'            => ['bambino'],
        'CH GIOCO'          => ['bambino'],
        'CH SUCCH'          => ['bambino'],
        'CH BIB'            => ['bambino'],
        'CH SEGG'           => ['bambino'],
        'CH GOMM'           => ['bambino'],
        'BIMBI'             => ['bambino'],
        'BAMBINI'           => ['bambino'],
        'JUNIOR'            => ['bambino'],
        'NEONAT'            => ['bambino'],
        'INFANT'            => ['bambino'],
        ' MAMMA'            => ['bambino'],
        'MAMMA '            => ['bambino'],
        'GRAVIDANZ'         => ['bambino'],
        'ALLATTAM'          => ['bambino'],
        'LATTE POLV'        => ['bambino'],
        'LATTE 1'           => ['bambino'],
        'LATTE 2'           => ['bambino'],

        // ── DERMOCOSMESI ─────────────────────────────────────────────────
        'RILASTIL'          => ['dermocosmesi'],
        'AVENE'             => ['dermocosmesi'],
        'EUCERIN'           => ['dermocosmesi'],
        'AVEENO'            => ['dermocosmesi'],
        'URIAGE'            => ['dermocosmesi'],
        'VICHY'             => ['dermocosmesi'],
        'BIODERMA'          => ['dermocosmesi'],
        'LICHTENA'          => ['dermocosmesi'],
        'TRIDERM'           => ['dermocosmesi'],
        'DERMOFLAN'         => ['dermocosmesi'],
        'IDRATAL'           => ['dermocosmesi'],
        'SARNOL'            => ['dermocosmesi'],
        'PSORILEN'          => ['dermocosmesi'],
        'NIVEA'             => ['dermocosmesi'],
        'SAUBER'            => ['dermocosmesi'],
        'BIOCLIN'           => ['dermocosmesi'],
        'KLORANE'           => ['dermocosmesi', 'capelli'],
        'MARGUTTA'          => ['dermocosmesi'],
        'DEFENCE COLOR'     => ['dermocosmesi'],
        'CHETOSIL'          => ['dermocosmesi', 'medicazione'],
        'ACNE'              => ['dermocosmesi'],
        'ECZEMA'            => ['dermocosmesi'],
        'PSORIASI'          => ['dermocosmesi'],
        'DERMATITE'         => ['dermocosmesi'],
        'WARTNER'           => ['dermocosmesi'],
        'VERRUX'            => ['dermocosmesi'],
        ' CREMA '           => ['dermocosmesi'],
        ' GEL '             => ['dermocosmesi'],
        ' LOZIONE '         => ['dermocosmesi'],
        ' MOUSSE '          => ['dermocosmesi'],
        ' FLUIDO '          => ['dermocosmesi'],
        'IDRATANTE'         => ['dermocosmesi'],
        'EMOLLIENTE'        => ['dermocosmesi'],
        'LENITIVO'          => ['dermocosmesi'],

        // ── PROTEZIONE SOLARE ────────────────────────────────────────────
        'ANTHELIOS'         => ['protezione_solare', 'dermocosmesi'],
        'DERMASOL'          => ['protezione_solare', 'dermocosmesi'],
        'ANGSTROM'          => ['protezione_solare'],
        'SOMAT'             => ['protezione_solare'],
        'SOLARE'            => ['protezione_solare'],
        'SPF '              => ['protezione_solare'],
        'ABBRONZ'           => ['protezione_solare'],
        'DOPOSOLE'          => ['protezione_solare'],
        'DEFENCE SUN'       => ['protezione_solare', 'dermocosmesi'],
        'RILASTIL SUN'      => ['protezione_solare', 'dermocosmesi'],

        // ── CAPELLI ──────────────────────────────────────────────────────
        'BIOSCALIN'         => ['capelli'],
        'PLANTERS'          => ['capelli'],
        'ANACAPS'           => ['capelli'],
        'BIOTHYMUS'         => ['capelli'],
        'CAPELL'            => ['capelli'],
        'HAIR'              => ['capelli'],
        'FORFORA'           => ['capelli'],
        'FORF '             => ['capelli'],
        'ALOPECIA'          => ['capelli'],
        'ANTICADUT'         => ['capelli'],
        'ANTI-CADU'         => ['capelli'],

        // ── IGIENE ORALE ─────────────────────────────────────────────────
        'CURASEPT'          => ['igiene_orale'],
        'CURAPROX'          => ['igiene_orale'],
        'ORALB'             => ['igiene_orale'],
        'GUM '              => ['igiene_orale'],
        'VITIS'             => ['igiene_orale'],
        'KUKIDENT'          => ['igiene_orale'],
        'COREGA'            => ['igiene_orale'],
        'ELMEX'             => ['igiene_orale'],
        'SENSODYNE'         => ['igiene_orale'],
        'PARODONTAX'        => ['igiene_orale'],
        'DENTIFRICIO'       => ['igiene_orale'],
        'DENTIF'            => ['igiene_orale'],
        'COLLUTORIO'        => ['igiene_orale'],

        // ── OCCHI ────────────────────────────────────────────────────────
        'COLL '             => ['occhi'],
        'COLLIRIO'          => ['occhi'],
        'GOCCE OCULARI'     => ['occhi'],
        'LACRIM'            => ['occhi'],
        'IRILENS'           => ['occhi'],
        'CATIONORM'         => ['occhi'],
        'OCCHIALE'          => ['occhi'],
        'OCCHIALUX'         => ['occhi'],
        ' OFT'              => ['occhi'],

        // ── NASO ─────────────────────────────────────────────────────────
        'RINAZINA'          => ['naso', 'raffreddore_influenza'],
        'RINOVIT'           => ['naso'],
        'RINOGUTT'          => ['naso'],
        'RINOFLUX'          => ['naso'],
        'CHIRENOL'          => ['naso'],
        'SPRAY NAS'         => ['naso'],
        'GTT RINO'          => ['naso'],
        'NASALE'            => ['naso'],
        'RINITE'            => ['naso', 'allergia'],
        'SINUSITE'          => ['naso'],

        // ── GOLA ─────────────────────────────────────────────────────────
        'BENAGOL'           => ['gola'],
        ' GOLA'             => ['gola'],
        'FARING'            => ['gola'],
        ' OTI'              => ['gola'],
        'ORECCH'            => ['gola'],

        // ── TOSSE / RAFFREDDORE ──────────────────────────────────────────
        'TOSSE'             => ['tosse'],
        'BRONCOVIT'         => ['tosse', 'raffreddore_influenza'],
        'FLUIFORT'          => ['tosse'],
        'RAFFREDDORE'       => ['raffreddore_influenza'],
        'INFLUENZA'         => ['raffreddore_influenza'],
        'ANTINFLUENZ'       => ['raffreddore_influenza'],
        'MUCOLIT'           => ['tosse', 'raffreddore_influenza'],
        'ESPETTORANT'       => ['tosse'],

        // ── GASTRO ───────────────────────────────────────────────────────
        'NEOBIANACID'       => ['gastro'],
        'MOLAXOLE'          => ['gastro'],
        'LASSATIV'          => ['gastro'],
        'INTESTIN'          => ['gastro'],
        'DIARREA'           => ['gastro'],
        'GASTRIC'           => ['gastro'],
        'LANSOPRAZOL'       => ['gastro'],
        'PANTOPRAZOL'       => ['gastro'],
        'OMEPRAZOL'         => ['gastro'],
        'DIGERFAST'         => ['gastro'],
        'REFLUSSO'          => ['gastro'],
        'ACIDITA'           => ['gastro'],
        'GONFIORE'          => ['gastro'],
        'COLON'             => ['gastro'],
        'COLITE'            => ['gastro'],
        'EMORROIDI'         => ['gastro'],
        'NAUSEA'            => ['gastro'],
        'EPATICO'           => ['gastro'],
        'FEGATO'            => ['gastro'],
        'BILIARE'           => ['gastro'],

        // ── DOLORE E FEBBRE ──────────────────────────────────────────────
        'PARACETAM'         => ['dolore_febbre'],
        'IBUPROFENE'        => ['dolore_febbre'],
        'IBUPROFEN'         => ['dolore_febbre'],
        'KETOPROFEN'        => ['dolore_febbre'],
        'NAPROSSENE'        => ['dolore_febbre'],
        'DICLOFENAC'        => ['dolore_febbre'],
        'NIMESULIDE'        => ['dolore_febbre'],
        'KETOROLAC'         => ['dolore_febbre'],
        'TACHIPIRINA'       => ['dolore_febbre'],
        'AULIN'             => ['dolore_febbre'],
        'OKI '              => ['dolore_febbre'],
        'NOVALGINA'         => ['dolore_febbre'],
        'BUSCOPAN'          => ['dolore_febbre', 'gastro'],
        'PARECID'           => ['dolore_febbre'],
        'MOMENTACT'         => ['dolore_febbre'],

        // ── ALLERGIA ─────────────────────────────────────────────────────
        'ALLERGIA'          => ['allergia'],
        'ANTIISTIAMIN'      => ['allergia'],
        'CETIRIZINA'        => ['allergia'],
        'LORATADINA'        => ['allergia'],
        'FEXOFENADINA'      => ['allergia'],
        'DESLORATAD'        => ['allergia'],
        'MONTELUKAST'       => ['allergia'],
        'ASMA'              => ['allergia'],
        'BRONCHITE'         => ['allergia'],
        'SALBUTAMOL'        => ['allergia'],
        'BUDESONIDE'        => ['allergia'],

        // ── PRESSIONE / CARDIOVASCOLARE ──────────────────────────────────
        'VARICI'            => ['pressione'],
        'VARIMED'           => ['pressione'],
        'CIRCOLAZ'          => ['pressione'],
        'CARDIO'            => ['pressione'],
        'CLOPIDOGREL'       => ['pressione', 'farmaco_prescrivibile'],
        'WARFARIN'          => ['pressione', 'farmaco_prescrivibile'],
        'CALZE COMP'        => ['pressione', 'ortopedia'],
        'ERAVEN'            => ['pressione'],

        // ── SONNO / STRESS ───────────────────────────────────────────────
        'ANXITANE'          => ['sonno_stress'],
        'DELORAZEPAM'       => ['sonno_stress', 'farmaco_prescrivibile'],
        'SONNO'             => ['sonno_stress'],
        'STRESS'            => ['sonno_stress'],
        'INSONNIA'          => ['sonno_stress'],

        // ── DONNA / GINECOLOGIA ──────────────────────────────────────────
        'SAUGELLA'          => ['donna'],
        'VIDERMINA'         => ['donna'],
        'CANESTEN'          => ['donna'],
        'GYNOCANESTEN'      => ['donna'],
        'OZOGIN'            => ['donna'],
        'VAGINALE'          => ['donna'],
        'MENOPAUSA'         => ['donna'],
        'GINECOL'           => ['donna'],
        'INTIMA'            => ['donna'],

        // ── DIABETE ──────────────────────────────────────────────────────
        'DIABETE'           => ['diabete_supporto'],
        'GLICEMIA'          => ['diabete_supporto'],
        'GLUCOSIO'          => ['diabete_supporto'],
        'INSULINA'          => ['diabete_supporto', 'farmaco_prescrivibile'],
        'LANCETTE'          => ['diabete_supporto', 'dispositivi_medici'],
        'GLUCOMETRO'        => ['diabete_supporto', 'dispositivi_medici'],
        'CONTOUR XT'        => ['diabete_supporto', 'dispositivi_medici'],
        'STRIPS'            => ['diabete_supporto', 'dispositivi_medici'],

        // ── TIROIDE / ENDOCRINOLOGIA ─────────────────────────────────────
        'TIROIDE'           => ['vitamine_integratori'],
        'VISTER'            => ['vitamine_integratori'],
        'LEVOTIROX'         => ['farmaco_prescrivibile'],
        'EUTIROX'           => ['farmaco_prescrivibile'],

        // ── DISPOSITIVI MEDICI ───────────────────────────────────────────
        'AGO '              => ['dispositivi_medici'],
        'SIRINGA'           => ['dispositivi_medici'],
        'EXTRAFINE SIR'     => ['dispositivi_medici'],
        'TERMOMETRO'        => ['dispositivi_medici'],
        'SFIGMO'            => ['dispositivi_medici'],
        'MISURATORE'        => ['dispositivi_medici'],
        'FPR '              => ['dispositivi_medici'],
        'PIC '              => ['dispositivi_medici'],
        'PROFAR'            => ['dispositivi_medici'],

        // ── MEDICAZIONE ──────────────────────────────────────────────────
        'BENDA '            => ['medicazione'],
        'GARZA'             => ['medicazione'],
        'CEROTTO'           => ['medicazione'],
        'DISINFET'          => ['medicazione'],
        'ANTISETT'          => ['medicazione'],
        'AMUCHINA'          => ['medicazione'],
        'BETADINE'          => ['medicazione'],

        // ── ORTOPEDIA ────────────────────────────────────────────────────
        'GIBAUD'            => ['ortopedia'],
        'TUTORE'            => ['ortopedia'],
        'CAVIGL'            => ['ortopedia'],
        'GINOCCH'           => ['ortopedia'],
        'COLETT'            => ['ortopedia'],
        'BENDAGGIO'         => ['ortopedia'],
        'FASCIA ELAST'      => ['ortopedia'],
        'LOMBARE'           => ['ortopedia'],
        'ARTROS'            => ['ortopedia'],
        'ARTRITE'           => ['ortopedia'],

        // ── INCONTINENZA ─────────────────────────────────────────────────
        'TENA '             => ['incontinenza'],
        'PANNOLONE'         => ['incontinenza'],
        'SLIP CONTR'        => ['incontinenza'],
        'INCONTINENZ'       => ['incontinenza'],
        'PULL UP'           => ['incontinenza'],

        // ── CELIACHIA ────────────────────────────────────────────────────
        'SCHAR'             => ['celiachia'],
        'NUTRIFREE'         => ['celiachia'],
        'BIAGLUT'           => ['celiachia'],
        'SENZA GLUTINE'     => ['celiachia'],
        'SGLUT'             => ['celiachia'],

    ];
}

// ---------------------------------------------------------------------------
// 4. FUNZIONE PRINCIPALE INFERENZA TAG
// ---------------------------------------------------------------------------

/**
 * Inferisce array di tag per un prodotto farmacia.
 * Restituisce slug canonici (allineati alla taxonomy/tags.php).
 *
 * @param string $name      Nome prodotto DB (es. "EPHYNAL*20CPR RIV MAST 100MG")
 * @param string $desc      Descrizione estesa (può essere vuota)
 * @param string $category  Categoria DB (può essere vuota)
 * @return string[]         Array di tag univoci, es. ['vitamine_integratori']
 */
function related_tags_infer_from_product(
    string $name,
    string $desc = '',
    string $category = ''
): array {
    $haystack = strtoupper(' ' . $name . ' ' . $desc . ' ' . $category . ' ');

    $tags  = [];
    $rules = _pharma_winfarm_rules();

    foreach ($rules as $pattern => $ruleTags) {
        if (strpos($haystack, strtoupper($pattern)) !== false) {
            foreach ($ruleTags as $t) {
                $tags[$t] = true;
            }
        }
    }

    // -- Asterisco winfarm = farmaco prescrivibile (RX)
    if (strpos($name, '*') !== false) {
        $tags['farmaco_prescrivibile'] = true;
    }

    // -- Potenza omeopatica numerica (5CH, 7CH, 200CH, 6DH, MK…)
    if (preg_match('/\b\d+\s*CH\b|\b\d+\s*DH\b|\bMK\b/i', $haystack)) {
        $tags['omeopatia'] = true;
    }

    // -- Sali di Schussler
    if (preg_match('/SALE DR SCHUSSLER|SCHUSS/i', $name)) {
        $tags['omeopatia'] = true;
    }

    // -- ZZZ = prefisso prodotti non classificabili dal gestionale (quasi sempre omeo)
    if (preg_match('/\bZZZ\b/', strtoupper($name))) {
        $tags['omeopatia'] = true;
    }

    // -- Collirio monodose
    if (preg_match('/\bMONOD\b|\bMONODOSE\b/i', $name)
        && (stripos($name, 'LACRIMI') !== false || stripos($name, 'COLL') !== false)) {
        $tags['occhi'] = true;
    }

    // -- Latte formula per neonati
    if (preg_match('/LATTE\s+(POLV|INIZIO|CONTINUA|\d)/i', $name)) {
        $tags['bambino'] = true;
    }

    // -- Categorie DB → tag
    if ($category !== '') {
        $catU = strtoupper(trim($category));
        $catMapping = [
            'INTEGR'   => ['vitamine_integratori'],
            'OMEOPATIA'=> ['omeopatia'],
            'COSMET'   => ['dermocosmesi'],
            'PEDIATR'  => ['bambino'],
            'FARMACO'  => ['farmaco_prescrivibile'],
            'MEDICAZ'  => ['medicazione'],
            'ORTOPED'  => ['ortopedia'],
            'ERBORIS'  => ['fitoterapia'],
        ];
        foreach ($catMapping as $catKw => $catTags) {
            if (strpos($catU, $catKw) !== false) {
                foreach ($catTags as $ct) {
                    $tags[$ct] = true;
                }
            }
        }
    }

    // -- Fallback: forme farmaceutiche topiche generiche → dermocosmesi
    if (empty($tags)) {
        $dermForms = ['SHAMPOO', 'BALSAMO', 'SIERO', 'STRUCCANTE', 'DETERGENTE', 'SAPONE'];
        foreach ($dermForms as $df) {
            if (stripos($haystack, $df) !== false) {
                $tags['dermocosmesi'] = true;
                break;
            }
        }
    }

    // -- Fallback finale: compresse/capsule senza RX e senza altri tag → probabile integratore
    if (empty($tags) && strpos($name, '*') === false) {
        if (preg_match('/\b\d+CPR\b|\b\d+CPS\b|\b\d+BUST\b/i', $name)) {
            $tags['vitamine_integratori'] = true;
        }
    }

    return array_keys($tags);
}