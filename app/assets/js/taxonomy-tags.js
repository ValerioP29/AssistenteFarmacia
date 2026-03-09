/**
 * assets/js/taxonomy-tags.js
 *
 * Source of truth JS per la tassonomia tag prodotti.
 * Deriva dalla stessa struttura di taxonomy/tags.php (lato PHP).
 *
 * COME USARE in page-prenotazioni.js:
 *   1. Includi questo file PRIMA di page-prenotazioni.js
 *   2. Sostituisci:
 *        - relatedTagsPreset   → ProductTagsTaxonomy.getUiPreset()
 *        - inferRelatedTagFromName(name) → ProductTagsTaxonomy.inferTag(name)
 *   3. Rimuovi le definizioni hardcoded in ReservationForm
 *
 * Per aggiungere/modificare tag: modifica taxonomy/tags.php (PHP)
 * e rispecchia la modifica qui. Le due strutture DEVONO restare allineate.
 */

const ProductTagsTaxonomy = (() => {

    /**
     * Catalogo completo dei tag.
     * ui: true        → appare nel dropdown filtro dell'app
     * inferrable: true → usato da inferTag()
     * keywords        → array flat di keyword (specific + generic uniti)
     *
     * SLUG CANONICI — non cambiare i 'value' senza aggiornare taxonomy/tags.php
     */
    const TAXONOMY = [
        // ── UI (dropdown) ──────────────────────────────────────────────
        {
            value: 'in_evidenza',
            label: 'In evidenza',
            ui: true, inferrable: false,
            aliases: ['evidenza', 'featured', 'in_evidence'],
            keywords: [],
        },
        {
            value: 'consiglio_farmacista',
            label: 'Consiglio farmacista',
            ui: true, inferrable: false,
            aliases: ['consiglio'],
            keywords: [],
        },
        {
            value: 'consiglio_prenotazione',
            label: 'Consiglio prenotazione',
            ui: true, inferrable: false,
            aliases: [],
            keywords: [],
        },
        {
            value: 'dolore_febbre',
            label: 'Dolore e febbre',
            ui: true, inferrable: true,
            aliases: ['dolore', 'febbre', 'antidolorifici', 'analgesici'],
            keywords: [
                'dolore', 'febbre', 'antidolorifici', 'analgesici', 'antinfiammatori',
                'paracetamolo', 'ibuprofene', 'tachipirina', 'ketoprofene', 'aulin', 'aspirina',
                'antidolorifico', 'antipiret',
            ],
        },
        {
            value: 'gastro',
            label: 'Gastro',
            ui: true, inferrable: true,
            aliases: ['gastrointestinale', 'digestione', 'stomaco'],
            keywords: [
                'gastro', 'digestione', 'acidita', 'reflusso', 'probiotici', 'diarrea',
                'nausea', 'intestino', 'colon', 'gonfiore', 'lassativo', 'stomaco',
            ],
        },
        {
            value: 'vitamine_integratori',
            label: 'Vitamine e integratori',
            ui: true, inferrable: true,
            aliases: ['integratori', 'vitamine', 'vitamini_minerali', 'integratore'],
            keywords: [
                'vitamine', 'integratori', 'magnesio', 'ferro', 'difese immunitarie',
                'vitamina c', 'vitamina d', 'zinco', 'potassio', 'calcio', 'folico',
                'omega', 'collagene', 'melatonina', 'antiossidanti',
            ],
        },
        {
            value: 'dermocosmesi',
            label: 'Dermocosmesi',
            ui: true, inferrable: true,
            aliases: ['dermocosmetica', 'cosmetica', 'dermo', 'skincare'],
            keywords: [
                'dermo', 'cosmesi', 'pelle', 'crema', 'solare', 'idratante', 'cicatrice',
                'viso', 'corpo', 'acne', 'eczema', 'dermatite',
            ],
        },
        {
            value: 'bambino',
            label: 'Bambino',
            ui: true, inferrable: true,
            aliases: ['pediatria', 'pediatrico', 'neonati', 'infanzia'],
            keywords: [
                'bambino', 'pediatrico', 'neonato', 'infanzia', 'bimbo', 'bimbi',
                'junior', 'baby', 'mamma', 'latte neonato',
            ],
        },
        {
            value: 'celiachia',
            label: 'Celiachia',
            ui: true, inferrable: true,
            aliases: ['senza_glutine', 'glutine', 'celiaci'],
            keywords: [
                'celiachia', 'senza glutine', 'celiaci', 'glutine',
            ],
        },

        // ── Inferibili (non nel dropdown) ──────────────────────────────
        {
            value: 'omeopatia',
            label: 'Omeopatia',
            ui: false, inferrable: true,
            aliases: ['omeo', 'omeopatia_sali_schussler', 'omeopatia_bach'],
            keywords: ['omeopatia', 'globuli', 'granuli', 'rimedio omeopatico'],
        },
        {
            value: 'fitoterapia',
            label: 'Fitoterapia',
            ui: false, inferrable: true,
            aliases: ['erboristeria', 'erbe'],
            keywords: [
                'tisana', 'fitoterapia', 'erboristeria', 'tintura madre', 'valeriana',
                'echinacea', 'arnica', 'propoli',
            ],
        },
        {
            value: 'raffreddore_influenza',
            label: 'Raffreddore e influenza',
            ui: false, inferrable: true,
            aliases: ['raffreddore', 'influenza', 'tosse_raffreddore'],
            keywords: [
                'raffreddore', 'influenza', 'decongestionante', 'seno', 'paranasali',
                'antiinfluenzale', 'mucolitico',
            ],
        },
        {
            value: 'tosse',
            label: 'Tosse',
            ui: false, inferrable: true,
            aliases: [],
            keywords: ['tosse', 'mucolitico', 'espettorante', 'sedativo tosse', 'sciroppo tosse'],
        },
        {
            value: 'gola',
            label: 'Gola',
            ui: false, inferrable: true,
            aliases: ['mal_di_gola', 'otorinolaringoiatria', 'naso_gola'],
            keywords: ['gola', 'mal di gola', 'pastiglie gola', 'spray gola', 'angina', 'faringite'],
        },
        {
            value: 'naso',
            label: 'Naso',
            ui: false, inferrable: true,
            aliases: ['nasale', 'rinite'],
            keywords: ['naso', 'spray nasale', 'rinite', 'sinusite', 'nasale', 'congestione nasale'],
        },
        {
            value: 'occhi',
            label: 'Occhi',
            ui: false, inferrable: true,
            aliases: ['oculistica', 'oculare', 'lenti_a_contatto'],
            keywords: [
                'occhi', 'oculare', 'lacrimazione', 'congiuntivite', 'collirio', 'lacrime artificiali',
            ],
        },
        {
            value: 'igiene_orale',
            label: 'Igiene orale',
            ui: false, inferrable: true,
            aliases: ['orale', 'denti', 'protesi_dentali'],
            keywords: ['igiene orale', 'dentifricio', 'collutorio', 'gengive', 'alito', 'spazzolino'],
        },
        {
            value: 'sonno_stress',
            label: 'Sonno e stress',
            ui: false, inferrable: true,
            aliases: ['sonno_stress_ansia', 'sonno', 'stress', 'ansia'],
            keywords: ['sonno', 'stress', 'rilassante', 'insonnia', 'ansia', 'melatonina'],
        },
        {
            value: 'donna',
            label: 'Donna',
            ui: false, inferrable: true,
            aliases: ['ginecologia', 'femminile', 'igiene_intima'],
            keywords: ['donna', 'femmin', 'intimo', 'vaginale', 'menopausa', 'ginecologia'],
        },
        {
            value: 'diabete_supporto',
            label: 'Diabete e glicemia',
            ui: false, inferrable: true,
            aliases: ['diabete', 'glicemia', 'diabete_glicemia'],
            keywords: ['diabete', 'glicemia', 'glucosio', 'insulina', 'glucometro', 'strisce glicemia'],
        },
        {
            value: 'pressione',
            label: 'Pressione arteriosa',
            ui: false, inferrable: true,
            aliases: ['pressione_arteriosa', 'ipertensione', 'cardiovascolare'],
            keywords: ['pressione', 'misuratore pressione', 'ipertensione', 'cardiovascolare'],
        },
        {
            value: 'allergia',
            label: 'Allergia',
            ui: false, inferrable: true,
            aliases: ['antiallergico', 'antistaminico', 'asma_bpco'],
            keywords: ['allergia', 'antistaminico', 'cetirizina', 'loratadina', 'asma'],
        },
        {
            value: 'protezione_solare',
            label: 'Protezione solare',
            ui: false, inferrable: true,
            aliases: ['solare', 'abbronzante'],
            keywords: ['solare', 'protezione solare', 'spf', 'abbronzante', 'doposole'],
        },
        {
            value: 'capelli',
            label: 'Capelli',
            ui: false, inferrable: true,
            aliases: ['tricologia'],
            keywords: ['capelli', 'hair', 'forfora', 'alopecia', 'caduta capelli'],
        },
        {
            value: 'probiotici',
            label: 'Probiotici',
            ui: false, inferrable: true,
            aliases: ['fermenti_lattici', 'prebiotici'],
            keywords: ['probiotici', 'fermenti lattici', 'prebiotici', 'lactobacillus'],
        },
        {
            value: 'medicazione',
            label: 'Medicazione',
            ui: false, inferrable: true,
            aliases: ['medicazione_ferite', 'wound_care'],
            keywords: ['medicazione', 'cerotti', 'garze', 'disinfettante', 'benda'],
        },
        {
            value: 'dispositivi_medici',
            label: 'Dispositivi medici',
            ui: false, inferrable: true,
            aliases: ['presidi_medici', 'aghi_siringhe'],
            keywords: ['ago', 'siringa', 'termometro', 'sfigmomanometro', 'glucometro'],
        },
        {
            value: 'incontinenza',
            label: 'Incontinenza',
            ui: false, inferrable: true,
            aliases: ['pannoloni'],
            keywords: ['incontinenza', 'pannolone', 'assorbente'],
        },
        {
            value: 'ortopedia',
            label: 'Ortopedia',
            ui: false, inferrable: true,
            aliases: ['ortopedia_fisioterapia', 'fisioterapia'],
            keywords: ['ortopedia', 'tutore', 'ginocchiera', 'cavigliera', 'calze compressione'],
        },
        {
            value: 'farmaco_prescrivibile',
            label: 'Farmaco con ricetta',
            ui: false, inferrable: true,
            aliases: ['ricetta', 'prescription', 'rx'],
            keywords: [],
        },
        {
            value: 'altro',
            label: 'Altro',
            ui: false, inferrable: false,
            aliases: [],
            keywords: [],
        },
    ];

    // ── Mappa alias → slug canonico (costruita una volta sola) ────────────
    const _aliasMap = (() => {
        const map = {};
        TAXONOMY.forEach(({ value, aliases }) => {
            aliases.forEach((alias) => {
                map[_normalize(alias)] = value;
            });
        });
        return map;
    })();

    /** Normalizza formato slug: lowercase, underscore, solo a-z0-9_ */
    function _normalize(tag) {
        return String(tag || '')
            .trim()
            .toLowerCase()
            .replace(/[-\s]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/[^a-z0-9_]/g, '')
            .replace(/^_+|_+$/g, '');
    }

    return {

        /**
         * Canonicalizza un tag grezzo: normalizza formato e risolve alias.
         * Identico a canonicalizeTag() in taxonomy/tags.php.
         *
         * @param {string} raw
         * @returns {string} slug canonico, o stringa vuota se non valido
         */
        canonicalize(raw) {
            const n = _normalize(raw);
            return _aliasMap[n] ?? n;
        },

        /**
         * Preset per il dropdown filtro dell'app (solo tag UI).
         * Sostituisce relatedTagsPreset hardcoded in ReservationForm.
         *
         * @returns {{ value: string, label: string }[]}
         */
        getUiPreset() {
            const empty = [{ value: '', label: 'Seleziona una categoria' }];
            const ui = TAXONOMY
                .filter((t) => t.ui)
                .map(({ value, label }) => ({ value, label }));
            return [...empty, ...ui];
        },

        /**
         * Inferisce il tag più probabile dal nome di un prodotto.
         * Sostituisce inferRelatedTagFromName() hardcoded in ReservationForm.
         *
         * @param {string} name  Nome prodotto (qualsiasi case)
         * @returns {string}     Slug canonico, o '' se nessun match
         */
        inferTag(name) {
            const n = String(name || '').toLowerCase();
            if (!n) return '';

            for (const entry of TAXONOMY) {
                if (!entry.inferrable || !entry.keywords.length) continue;
                if (entry.keywords.some((k) => n.includes(k.toLowerCase()))) {
                    return entry.value;
                }
            }
            return '';
        },

        /**
         * Verifica se un tag è nella whitelist (canonical o alias).
         *
         * @param {string} tag
         * @returns {boolean}
         */
        isKnown(tag) {
            const canonical = this.canonicalize(tag);
            return TAXONOMY.some((t) => t.value === canonical);
        },

        /**
         * Tutti i tag (per debug / admin).
         */
        getAll() {
            return TAXONOMY.map(({ value, label, ui, inferrable }) =>
                ({ value, label, ui, inferrable })
            );
        },
    };

})();

// Esposizione globale
window.ProductTagsTaxonomy = ProductTagsTaxonomy;