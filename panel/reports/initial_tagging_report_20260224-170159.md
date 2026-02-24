# Report Tagging Iniziale

## Parametri
- Fonte: `/workspace/AssistenteFarmacia/panel/scripts/../import/prodotti-giovinazzi.csv`
- Pharma ID: `1`
- Pool richiesto: `200`
- Featured target: `100`

## Risultati
- Prodotti analizzati: **200**
- Prodotti taggati: **18**
- Prodotti senza tag: **182**
- Prodotti featured: **100**

## Distribuzione per tag
- dolore_febbre: 9
- sonno_stress: 4
- occhi: 2
- medicazione: 2
- dermocosmesi: 1
- allergia: 1

## 20 esempi (nome -> tag)
- VALERIANA ALFA*30CPR RIV 100MG -> [sonno_stress]
- ALLERGAN*CREMA 30G 2G/100G -> [dermocosmesi, allergia]
- COLLIRIO ALFA DEC*GTT FL 10ML -> [occhi]
- COLLIRIO ALFA DEC*10CONT 0,3ML -> [occhi]
- ASPIRINA*AD 20CPR 0,5G -> [dolore_febbre]
- ASPIRINA C*10CPR EFF 400+240MG -> [dolore_febbre]
- ASPIRINA*OS GRAT 10BUST400+240 -> [dolore_febbre]
- ASPIRINA*10CPR 325MG -> [dolore_febbre]
- ASPIRINA C*20CPR EFF 400+240MG -> [dolore_febbre]
- ASPIRINA RAPIDA*10CPRMAST500MG -> [dolore_febbre]
- ASPIRINA*OS GRAT 10BUST 500MG -> [dolore_febbre]
- ASPIRINA*OS GRAT 20BUST 500MG -> [dolore_febbre]
- ASPIRINA*20CPR 500MG FL -> [dolore_febbre]
- CEROTTO BERTELLI*MEDIO CM16X12 -> [medicazione]
- CEROTTO BERTELLI*GRANDECM16X24 -> [medicazione]
- VALERIANA DISPERT*30CPR 45MG -> [sonno_stress]
- VALERIANA DISPERT*60CPR 45MG -> [sonno_stress]
- VALERIANA DISPERT*20CPR 125MG -> [sonno_stress]

## Criteri usati
- Tagging: `high` sempre applicato, `medium` applicato se tag != `altro`, `low` lasciato vuoto per review.
- Featured (fallback): priorità ai prodotti taggati con confidence `high|medium`; riempimento fino al target con nomi >= 8 caratteri (presentabilità base).
- Nota: dal CSV non sono deducibili campi `is_active`/`image`, quindi applicato criterio fallback documentato.

## File generati
- Payload bulk: `/workspace/AssistenteFarmacia/panel/scripts/../reports/initial_tagging_payload_20260224-170159.json`
- Report: `/workspace/AssistenteFarmacia/panel/scripts/../reports/initial_tagging_report_20260224-170159.md`
