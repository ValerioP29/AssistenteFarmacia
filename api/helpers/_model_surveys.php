<?php

class SurveysModel {

	protected static function data() {
		return array(
			1 => [
				"id"         => 1,
				"pharma_id"  => 1,
				"title"      => "🛡 Sei protetto dall’influenza? Vaccino e Prevenzione – Scoprilo in 60 secondi",
				"subtitle"   => "Rispondi alle 5 domande e scopri con questo test rapido se le tue abitudini ti stanno davvero proteggendo.",
				"summary"    => "La prevenzione funziona solo se fatta prima, non quando i sintomi sono iniziati. La farmacia può consigliarti il percorso più adatto a te.",
				"created_at" => "2025-10-27 00:00:00",
				"start_date" => "2025-10-27 00:00:00",
				"end_date"   => "2025-11-02 23:59:59",

				"category_cta_id" => 10,
				
				"questions" => [
					[
						"question" => "Negli ultimi 3 anni ti sei vaccinato contro l’influenza?",
						"answers" => [
							["value" => "A", "label" => "Ogni anno, regolarmente"],
							["value" => "B", "label" => "Qualche volta, non tutti gli anni"],
							["value" => "C", "label" => "Mai"],
							["value" => "D", "label" => "Non ricordo"],
						]
					],
					[
						"question" => "Dove ti sei vaccinato l’ultima volta?",
						"answers" => [
							["value" => "A", "label" => "In farmacia"],
							["value" => "B", "label" => "Dal medico di famiglia"],
							["value" => "C", "label" => "Presso la ASL o un centro vaccinale"],
							["value" => "D", "label" => "Non mi sono mai vaccinato"],
						]
					],
					[
						"question" => "Come ti proteggi dai virus durante l’inverno?",
						"answers" => [
							["value" => "A", "label" => "Mi vaccino e seguo buone abitudini di prevenzione"],
							["value" => "B", "label" => "Solo buone abitudini, ma senza vaccino"],
							["value" => "C", "label" => "Mi proteggo solo quando mi ammalo"],
							["value" => "D", "label" => "Non faccio nulla di particolare"],
						]
					],
					[
						"question" => "Quanto ritieni utile il vaccino antinfluenzale nella prevenzione?",
						"answers" => [
							["value" => "A", "label" => "Molto utile"],
							["value" => "B", "label" => "Abbastanza utile"],
							["value" => "C", "label" => "Poco utile"],
							["value" => "D", "label" => "Per niente utile / non so"],
						]
					],
					[
						"question" => "Oltre alla vaccinazione, come pensi di poterti proteggere dall’influenza?",
						"answers" => [
							["value" => "A", "label" => "Frutta, verdura, vitamine"],
							["value" => "B", "label" => "Dormo bene e riduco stress e fumo"],
							["value" => "C", "label" => "Lavo spesso le mani e aerando gli ambienti chiusi"],
							["value" => "D", "label" => "Nessuna delle precedenti"],
						]
					],
				],

				"products"     => NULL,
				"products_ids" => [7121, 7122, 7123, 7090, 2697, 7102, 7129, 7124, 7125, 7130, 7126, 7127],

				"profiles" => [
					"A" => [
						"title"   => "🅐 PROFILO A – Sei già sulla strada giusta",
						"text"    => "Hai una buona consapevolezza della prevenzione...",
						"advice"  => "Mantieni il vantaggio, non abbassare la guardia.",
						"products"=> [7121, 7122, 7123],
					],
					"B" => [
						"title"   => "🅑 PROFILO B – Buone basi, ma protezione discontinua",
						"text"    => "Hai consapevolezza della prevenzione...",
						"advice"  => "La differenza, nel tuo caso, la fa la continuità.",
						"products"=> [7090, 2697, 7102],
					],
					"C" => [
						"title"   => "🅒 PROFILO C – Protezione bassa o occasionale",
						"text"    => "Ad oggi la tua prevenzione è debole...",
						"advice"  => "Con poche abitudini in più puoi proteggere te e chi vive con te.",
						"products"=> [7129, 7124, 7125],
					],
					"D" => [
						"title"   => "🅓 PROFILO D – Assente prevenzione, rischio elevato",
						"text"    => "Non stai adottando misure di prevenzione efficaci...",
						"advice"  => "È il momento di cambiare approccio e proteggerti seriamente.",
						"products"=> [7130, 7126, 7127],
					],
				],

				"cta" => [
					"text" => "Prenota subito il tuo vaccino",
					"url"  => "https://app.assistentefarmacia.it/prenotazioni.html",
				],
			],
			2 => [
				"id"         => 2,
				"pharma_id"  => 1,
				"title"       => "❄️ SOS Pelle Inverno – Scoprilo in 60 Secondi",
				"subtitle"    => "Rispondi alle 5 domande e scopri se la tua pelle è davvero pronta ad affrontare freddo, vento e riscaldamento.",
				"summary"     => "Una pelle sana non è solo questione di estetica, ma di equilibrio e protezione. Prenota in farmacia la tua mini-consulenza “Pelle d’Inverno” e scopri la routine ideale per il tuo tipo di pelle.",
				"created_at"  => "2025-11-03 00:00:00",
				"start_date"  => "2025-11-03 00:00:00",
				"end_date"    => "2025-11-09 23:59:59",

				"category_cta_id" => 16,

				"questions" => [
					[
						"question" => "Come cambia la tua beauty routine in inverno?",
						"answers" => [
							["value" => "A", "label" => "Differenzio giorno/notte: crema idratante + SPF al mattino, la sera nutrienti con ceramidi/karité"],
							["value" => "B", "label" => "Aggiungo solo una crema più ricca"],
							["value" => "C", "label" => "Uso tutto l’anno gli stessi prodotti"],
							["value" => "D", "label" => "Non seguo una routine specifica"],
						]
					],
					[
						"question" => "Quanto curi la skincare quotidiana serale?",
						"answers" => [
							["value" => "A", "label" => "Uso oli/detergenti delicati senza solfati o profumi e idrato subito dopo"],
							["value" => "B", "label" => "Uso un buon detergente ma non sempre applico una crema"],
							["value" => "C", "label" => "Lavo con acqua calda e sapone neutro"],
							["value" => "D", "label" => "Non presto molta attenzione alla routine serale"],
						]
					],
					[
						"question" => "In inverno come proteggi la pelle da freddo, vento e raggi UV?",
						"answers" => [
							["value" => "A", "label" => "Applico ogni giorno crema protettiva/anti-pollution con SPF anche in città"],
							["value" => "B", "label" => "Uso SPF solo in montagna o per sport all’aperto"],
							["value" => "C", "label" => "Metto la crema solo se sento tirare la pelle"],
							["value" => "D", "label" => "Non uso protezione: d’inverno non credo serva"],
						]
					],
					[
						"question" => "Come nutri e idrati la pelle dall’interno?",
						"answers" => [
							["value" => "A", "label" => "Bevo ≥1,5L e mangio frutta/verdura e alimenti con omega-3 e vitamine A-C-E"],
							["value" => "B", "label" => "Bevo abbastanza, a volte una spremuta"],
							["value" => "C", "label" => "Mi dimentico spesso di bere e di mangiare frutta/verdura"],
							["value" => "D", "label" => "Non curo idratazione né alimentazione"],
						]
					],
					[
						"question" => "Come gestisci docce, bagni e calore ambientale?",
						"answers" => [
							["value" => "A", "label" => "Docce tiepide e brevi, evito calore diretto e idrato subito"],
							["value" => "B", "label" => "A volte acqua calda ma poi metto la crema"],
							["value" => "C", "label" => "Docce calde e non sempre applico la crema"],
							["value" => "D", "label" => "Acqua bollente e sto vicino a termosifoni/caminetti"],
						]
					],
				],

				"products"     => NULL,
				"products_ids" => [7121, 7122, 7123, 7090, 2697, 7102, 7129, 7124, 7125, 7130, 7126, 7127],

				"profiles" => [
					"A" => [
						"title"   => "🅐 PROFILO A – Pelle forte e protetta",
						"text"    => "Hai una routine completa e consapevole: proteggi la pelle e mantieni l’equilibrio tra idratazione e difesa dagli agenti esterni. Continua così per preservare elasticità, comfort e luminosità.",
						"advice"  => "Idratazione costante e protezione quotidiana sono la tua miglior difesa contro il freddo.",
						"products"=> [7121, 7122, 7123],
					],
					"B" => [
						"title"   => "🅑 PROFILO B – Buone abitudini, ma non sempre costanti",
						"text"    => "Hai una buona base ma a volte dimentichi qualche gesto. La pelle resta sana, ma può apparire più spenta o secca. L’obiettivo è rendere la routine più regolare e mirata.",
						"advice"  => "Sii più costante! La costanza quotidiana è il segreto per una pelle sempre luminosa.",
						"products"=> [7090, 2697, 7102],
					],
					"C" => [
						"title"   => "🅒 PROFILO C – Pelle in difficoltà stagionale",
						"text"    => "Il freddo e l’aria secca ti mettono in difficoltà: la pelle tira, si arrossa e perde morbidezza. Serve una routine lenitiva e ricostituente che restituisca comfort e idratazione profonda.",
						"advice"  => "Evita acqua troppo calda, proteggi dagli sbalzi termici e idrata due volte al giorno.",
						"products"=> [7129, 7124, 7125],
					],
					"D" => [
						"title"   => "🅓 PROFILO D – Pelle stressata e disidratata",
						"text"    => "La pelle è secca, screpolata o arrossata: ha perso la barriera protettiva. Serve una routine semplice ma mirata per riparare e proteggere, con prodotti altamente nutrienti e lenitivi.",
						"advice"  => "Usa prodotti nutrienti e non dimenticare la protezione solare anche in inverno.",
						"products"=> [7130, 7126, 7127],
					],
				],

				"cta" => [
					"text" => "Prenota subito la tua consulenza",
					"url"  => "https://app.assistentefarmacia.it/servizi.html?id=16",
				],
			],
			3 => [
				"id"         => 3,
				"pharma_id"  => 1,
				"title"      => "🧠 Quiz – Salute delle orecchie: verità o falsi miti?",
				"subtitle"   => "Rispondi alle 5 domande e scopri quanto ne sai davvero sulla corretta igiene delle orecchie!",
				"summary"    => "Le orecchie sono delicate e vanno trattate nel modo giusto: niente cotton fioc, niente rimedi improvvisati. In farmacia trovi spray adeguati, consigli e prodotti sicuri.",
				"created_at" => "2025-11-10 00:00:00",
				"start_date" => "2025-11-10 00:00:00",
				"end_date"   => "2025-11-16 23:59:59",

				"category_cta_id" => NULL,

				"questions" => [
					[
						"question" => "Il cerume è:",
						"answers" => [
							["value" => "A", "label" => "Una sostanza protettiva che lubrifica e difende il condotto uditivo"],
							["value" => "B", "label" => "Sporco da eliminare ogni giorno"],
							["value" => "C", "label" => "Una sostanza di scarto senza funzione"],
							["value" => "D", "label" => "Una secrezione da lavare con acqua e sapone"],
						]
					],
					[
						"question" => "Qual è il metodo corretto per pulire le orecchie?",
						"answers" => [
							["value" => "A", "label" => "Con spray o soluzioni auricolari specifiche"],
							["value" => "B", "label" => "Con cotton fioc inseriti nel condotto"],
							["value" => "C", "label" => "Con coni auricolari naturali"],
							["value" => "D", "label" => "Con alcol o aceto per disinfettare"],
						]
					],
					[
						"question" => "L’acido borico nelle orecchie:",
						"answers" => [
							["value" => "A", "label" => "Va usato solo in soluzioni pronte all’uso, su cute integra, per ridurre umidità o prevenire otiti"],
							["value" => "B", "label" => "Si può usare puro per disinfettare"],
							["value" => "C", "label" => "Serve per sciogliere il cerume"],
							["value" => "D", "label" => "È indicato anche in caso di dolore o infezione acuta"],
						]
					],
					[
						"question" => "Cosa si deve evitare assolutamente?",
						"answers" => [
							["value" => "A", "label" => "L’inserimento di oggetti nel condotto (cotton fioc, forcine, ecc.)"],
							["value" => "B", "label" => "L’uso di spray auricolari delicati"],
							["value" => "C", "label" => "L’asciugatura dopo la piscina"],
							["value" => "D", "label" => "I controlli periodici dell’udito"],
						]
					],
					[
						"question" => "Quale affermazione è corretta?",
						"answers" => [
							["value" => "A", "label" => "Una piccola quantità di cerume è fisiologica e protettiva"],
							["value" => "B", "label" => "I coni auricolari eliminano il cerume"],
							["value" => "C", "label" => "L’acido borico cura le otiti acute"],
							["value" => "D", "label" => "Il mal d’orecchio passa sempre da solo"],
						]
					],
				],

				"products"     => NULL,
				"products_ids" => [265,266,3935,448,5277,1251,446,2292,7133,2288,5276],

				"profiles" => [
					"A" => [
						"title"   => "🌿 Profilo A – Orecchie in perfetta armonia",
						"text"    => "Hai consapevolezza di come si gestisce la salute delle orecchie di adulti e bambini. Conosci le pratiche corrette, eviti rimedi casalinghi e scegli i prodotti giusti. Continua così — le tue orecchie ti ringraziano! 👏",
						"advice"  => "Il cerume non è sporco, ma una barriera naturale protettiva.
									  Usare regolarmente soluzioni auricolari delicate mantiene il condotto pulito e previene otiti e tappi.
									  Evita sempre i cotton fioc!",
						"products"=> [265,266,3935],
					],
					"B" => [
						"title"   => "🌸 Profilo B – Buona attenzione, ma con qualche errore",
						"text"    => "Più o meno lo sai, ma qualche volta commetti errori: magari usi i cotton fioc o sottovaluti il controllo uditivo periodico. Chiedi consiglio in farmacia per perfezionare la tua routine di igiene auricolare. 👂💬",
						"advice"  => "Prodotti consigliati per la pulizia settimanale: ",
						"products"=> [448,5277,1251],
					],
					"C" => [
						"title"   => "🌤️ Profilo C – Hai bisogno di qualche consiglio in più",
						"text"    => "Sai che le orecchie sono delicate, ma sbagli alcune pratiche: spray non adatti, uso improprio di soluzioni o rimedi casalinghi. Non preoccuparti: basta poco per imparare a gestirle nel modo giusto! Vieni a parlarne con noi. 💚",
						"advice"  => "Prodotti per sciogliere e ammorbidire il cerume compatto: ",
						"products"=> [446,2292,7133],
					],
					"D" => [
						"title"   => "🌪️ Profilo D – Attenzione: le tue orecchie chiedono aiuto!",
						"text"    => "Assolutamente non attui una corretta gestione delle orecchie. Forse usi cotton fioc, coni auricolari o rimedi improvvisati: rischi infezioni, lesioni e tappi di cerume. Parlane con il farmacista per scoprire come prenderti cura dell’udito in modo sicuro e consapevole. 🩺",
						"advice"  => "Prodotti consigliati per la rimozione profonda e mantenimento: ",
						"products"=> [2288,5276,448],
					],
				],

				"cta" => NULL,
			],
			4 => [
				"id"         => 4,
				"pharma_id"  => 1,
				"title"      => "💧 Lavaggi nasali: quanto ne sai davvero?",
				"subtitle"   => "Rispondi alle 5 domande e scopri il tuo profilo respiratorio.",
				"summary"    => "Un naso pulito respira meglio, si ammala di meno e risponde meglio ai farmaci. La farmacia può aiutarti a scegliere la soluzione più adatta.",
				"created_at" => "2025-11-17 00:00:00",
				"start_date" => "2025-11-17 00:00:00",
				"end_date"   => "2025-11-23 23:59:59",

				"category_cta_id" => 10,
				
				"questions" => [
					[
						"question" => "Quando pensi sia utile fare i lavaggi nasali?",
						"answers" => [
							["value" => "A", "label" => "Tutti i giorni, come igiene e prevenzione"],
							["value" => "B", "label" => "Solo quando ho il raffreddore"],
							["value" => "C", "label" => "Quando proprio non respiro più"],
							["value" => "D", "label" => "Non li faccio mai, non ne vedo l’utilità"],
						]
					],
					[
						"question" => "Quale soluzione useresti per liberare il naso chiuso?",
						"answers" => [
							["value" => "A", "label" => "Una soluzione ipertonica sterile (2–3%)"],
							["value" => "B", "label" => "Una soluzione fisiologica normale"],
							["value" => "C", "label" => "Acqua e sale fatta in casa"],
							["value" => "D", "label" => "Spray decongestionante da banco"],
						]
					],
					[
						"question" => "Se devi fare un lavaggio nasale a un bambino piccolo, cosa scegli?",
						"answers" => [
							["value" => "A", "label" => "Soluzione isotonica sterile e beccuccio morbido"],
							["value" => "B", "label" => "La stessa soluzione che uso io"],
							["value" => "C", "label" => "Un po’ d’acqua con siringa o cucchiaino"],
							["value" => "D", "label" => "Meglio evitare, non so come si fa"],
						]
					],
					[
						"question" => "Dopo il mare o la piscina, cosa fai per la salute del naso?",
						"answers" => [
							["value" => "A", "label" => "Faccio un lavaggio con acqua di mare isotonica"],
							["value" => "B", "label" => "Spruzzo uno spray decongestionante"],
							["value" => "C", "label" => "Non faccio nulla"],
							["value" => "D", "label" => "Aspetto che passi la congestione da sola"],
						]
					],
					[
						"question" => "Quale di queste affermazioni è vera?",
						"answers" => [
							["value" => "A", "label" => "I lavaggi nasali migliorano la respirazione e prevengono infezioni"],
							["value" => "B", "label" => "Si possono fare con acqua del rubinetto"],
							["value" => "C", "label" => "Servono solo in caso di malattia"],
							["value" => "D", "label" => "Possono sostituire l’aerosol"],
						]
					],
				],

				"products"     => NULL,
				"products_ids" => [2679,7084,1274,6220,7134,5872,5216,5576,5577,6211,14,6015,6228],

				"profiles" => [
					"A" => [
						"title"   => "🅐 PROFILO A – Respiro perfetto",
						"text"    => "Hai piena consapevolezza dell’importanza dei lavaggi nasali e li usi nel modo corretto. Hai capito che la prevenzione parte dal naso!",
						"advice"  => "💚 Continua così: le tue mucose sono sane e protette.",
						"products"=> [2679,7084,1274,6220],
					],
					"B" => [
						"title"   => "🅑 PROFILO B – Attento ma migliorabile",
						"text"    => "Hai una buona routine, ma non sempre scegli la soluzione o il momento giusto. Potresti integrare i lavaggi anche quando non sei raffreddato, per migliorare la respirazione e prevenire disturbi.",
						"advice"  => "",
						"products"=> [7134,5872,5216],
					],
					"C" => [
						"title"   => "🅒 PROFILO C – Ti serve un piccolo aiuto",
						"text"    => "Sai che i lavaggi sono utili ma li esegui in modo saltuario o con strumenti non adeguati. Ti conviene chiedere consiglio in farmacia per scegliere il metodo più comodo e sicuro.",
						"advice"  => "",
						"products"=> [5576,5577,6211],
					],
					"D" => [
						"title"   => "🅓 PROFILO D – Naso in difficoltà",
						"text"    => "Non esegui i lavaggi o li fai in modo errato. Questo può favorire infezioni, sinusiti e respirazione difficile, soprattutto nei mesi freddi. Niente paura: con pochi gesti quotidiani puoi migliorare da subito.",
						"advice"  => "",
						"products"=> [14,6015,6228],
					],
				],
				"cta" => null,
				"pharmacist_tip" => [
					"title" => "Il consiglio del farmacista",
					"text"  => [
						"intro" => "Un naso pulito respira meglio, si ammala di meno e risponde meglio ai farmaci.",
						"items" => [
							"Isotonica per l’igiene quotidiana",
							"Ipertonica per la congestione",
							"Termale o naturale per lenire e idratare",
						],
					],
				],
			],
			5 => [
				"id"         => 5,
				"pharma_id"  => 1,
				"title"      => "🌞 Vitamina D – Verità o Falsi Miti?",
				"subtitle"   => "Scopri quanto ne sai e trova il profilo che più ti rappresenta",
				"summary"    => "",
				"created_at" => "2025-11-27 00:00:00",
				"start_date" => "2025-11-27 00:00:00",
				"end_date"   => "2025-12-07 23:59:59",

				"questions" => [
					[
						"question" => "La vitamina D si produce solo con il sole?",
						"answers" => [
							["value" => "A", "label" => "No, anche l’alimentazione e gli integratori possono contribuire"],
							["value" => "B", "label" => "Sì, solo con l’esposizione solare"],
							["value" => "C", "label" => "Solo d’estate"],
							["value" => "D", "label" => "Dipende dal tipo di pelle"],
						]
					],
					[
						"question" => "Chi può avere carenza di vitamina D?",
						"answers" => [
							["value" => "A", "label" => "Tutti, anche chi vive in zone soleggiate"],
							["value" => "B", "label" => "Solo gli anziani"],
							["value" => "C", "label" => "Solo i vegetariani"],
							["value" => "D", "label" => "Nessuno, se si segue una dieta varia"],
						]
					],
					[
						"question" => "A cosa serve principalmente la vitamina D?",
						"answers" => [
							["value" => "A", "label" => "Ad assorbire calcio e fosforo per ossa forti"],
							["value" => "B", "label" => "A far abbronzare meglio"],
							["value" => "C", "label" => "A bruciare i grassi"],
							["value" => "D", "label" => "Solo al sistema nervoso"],
						]
					],
					[
						"question" => "Qual è il momento migliore per assumere la vitamina D?",
						"answers" => [
							["value" => "A", "label" => "Durante un pasto che contenga grassi sani"],
							["value" => "B", "label" => "Sempre a digiuno"],
							["value" => "C", "label" => "Solo la sera"],
							["value" => "D", "label" => "Dopo l’attività fisica"],
						]
					],
					[
						"question" => "Un eccesso di vitamina D fa bene?",
						"answers" => [
							["value" => "A", "label" => "No, può causare ipercalcemia e va assunta con criterio"],
							["value" => "B", "label" => "Sì, più ne prendo meglio è"],
							["value" => "C", "label" => "Dipende dalla stagione"],
							["value" => "D", "label" => "Solo in gravidanza è rischiosa"],
						]
					],
				],

				"products"     => null,
				"products_ids" => [
					7135,4435,2961,3861,
					7107,7136,2050,2055,3859,
					3764,
					7137,2956
				],

				"profiles" => [
					"A" => [
						"title"   => "🅐 PROFILO A – <strong>Attento e ben informato</strong>",
						"text"    => "Hai uno stile di vita equilibrato, vuoi mantenere ossa e muscoli forti.",
						"advice"  => "<strong>Consiglio:</strong> mantieni un buon livello di vitamina D e K2 per ottimizzare il metabolismo del calcio.<br><br><strong>Prodotti suggeriti (adulti):",
						"products"=> [7135,4435,2961],
						"pediatric" => [
							"id"    => 3861,
							"label" => "Pediatrico:",
						],

					],
					"B" => [
						"title"   => "🅑 PROFILO B – <strong>Attento ma dubbioso</strong>",
						"text"    => "Ti prendi cura della tua salute, ma potresti trascurare l’esposizione solare.",
						"advice"  => "<strong>Consiglio:</strong> punta su un apporto costante di vitamina D e micronutrienti.<br><br><strong>Prodotti suggeriti (adulti):</strong>",
						"products"=> [7107,7136,2050,2055],
						"pediatric" => [
							"id"    => 3859,
							"label" => "Pediatrico:",
						],
					],
					"C" => [
						"title"   => "🅒 PROFILO C – <strong>Attivo e sportivo indoor</strong>",
						"text"    => "Ti alleni ma spesso al chiuso, e senti stanchezza o crampi.",
						"advice"  => "<strong>Consiglio:</strong> rafforza muscoli e difese con vitamina D, magnesio e antiossidanti.<br><br><strong>Prodotti suggeriti (adulti):</strong>",
						"products"=> [4435,3764,7136],
						"pediatric" => [
							"id"    => 2058,
							"label" => "Pediatrico:",
						],
					],
					"D" => [
						"title"   => "🅓 PROFILO D – <strong>Difese da rinforzare</strong>",
						"text"    => "Sei soggetto a infezioni ricorrenti o astenia stagionale.",
						"advice"  => "<strong>Consiglio:</strong> sostieni il sistema immunitario e ripristina i livelli di vitamina D.<br><br><strong>Prodotti suggeriti (adulti):</strong>",
						"products"=> [7137,2956,7107],
						"pediatric" => [
							"id"    => 3853,
							"label" => "Pediatrico:",
						],
					],
				],

				"cta" => [
					"text" => "Prenota ora!",
					"url"  => "https://app.assistentefarmacia.it/eventi.html?id=15",
				],

				"pharmacist_tip" => [
					"title" => "🩺 <strong>Consiglio finale del farmacista</strong>",
					"text"  => [
						"intro" => "<strong>Fai il test gratuito della vitamina D in farmacia</strong> (sangue capillare, risultato in 10 minuti).<br>
									La biologa nutrizionista <strong>Salugea</strong> sarà disponibile il 3 dicembre per interpretare il risultato e consigliarti il percorso più adatto.",
						"items" => [],
					],
				],
			],
			6 => [
				"id"         => 6,
				"pharma_id"  => 1,
				"title"      => "💚 QUIZ BENESSERE GOLA",
				"subtitle"   => "Rispondi alle 5 domande e scopri il tuo profilo gola.",
				"summary"    => "Scopri quanto ti prendi cura della tua gola e come proteggerla meglio tra freddo, aria secca e sbalzi di temperatura. Se i sintomi persistono rivolgiti al tuo medico o al tuo farmacista!",
				"created_at" => "2025-12-04 00:00:00",
				"start_date" => "2025-12-08 00:00:00",
				"end_date"   => "2025-12-14 23:59:59",

				"questions" => [
					[
						"question" => "Quando senti i primi fastidi alla gola, cosa fai?",
						"answers" => [
							["value" => "A", "label" => "Bevo di più e uso uno spray/pastiglie specifiche per lenire"],
							["value" => "B", "label" => "Aspetto un giorno e cerco di non prendere freddo"],
							["value" => "C", "label" => "Prendo un antidolorifico generico"],
							["value" => "D", "label" => "Uso un antibiotico che ho già in casa"],
						]
					],
					[
						"question" => "In inverno, come ti proteggi da freddo, aria secca e sbalzi di temperatura?",
						"answers" => [
							["value" => "A", "label" => "Copro sempre collo e bocca e umidifico l’ambiente"],
							["value" => "B", "label" => "Metto una sciarpa quando fa molto freddo"],
							["value" => "C", "label" => "Esco come capita, non ci penso molto"],
							["value" => "D", "label" => "Non riesco ad evitare fumo attivo e passivo"],
						]
					],
					[
						"question" => "Se il mal di gola è forte o accompagnato da febbre, cosa fai?",
						"answers" => [
							["value" => "A", "label" => "Chiedo consiglio al medico o al farmacista"],
							["value" => "B", "label" => "Continuo con rimedi da banco e vedo se migliora"],
							["value" => "C", "label" => "Prendo antipiretici di automedicazione"],
							["value" => "D", "label" => "Passo direttamente all’antibiotico"],
						]
					],
					[
						"question" => "Nella vita quotidiana, come ti prendi cura della gola?",
						"answers" => [
							["value" => "A", "label" => "Idratazione, lavaggi nasali e rimedi naturali quando servono"],
							["value" => "B", "label" => "Bevo abbastanza e ogni tanto prendo qualcosa per la gola"],
							["value" => "C", "label" => "Non faccio nulla di specifico, mi attivo solo se mi viene la febbre"],
							["value" => "D", "label" => "Non mi preoccupo di fare prevenzione né curo i primi sintomi"],
						]
					],
					[
						"question" => "Cosa pensi degli antibiotici per il mal di gola?",
						"answers" => [
							["value" => "A", "label" => "Vanno usati solo se prescritti"],
							["value" => "B", "label" => "A volte servono, ma prima valuto altri rimedi"],
							["value" => "C", "label" => "Credo che facciano guarire più velocemente"],
							["value" => "D", "label" => "Li prendo appena ho dolore"],
						]
					],
				],

				"products"     => NULL,
				"products_ids" => [
					// 7139, // Bromelina 2500 Farmacia Giovinazzi
					// 7138, // IaluGola Spray Farmacia Giovinazzi
					1804,6826,6828,1501,6021,6024,
					274,1502,6020,7140,340,7141,3323
				],


				"profiles" => [
					"A" => [
						"title"   => "🅰️ Profilo A – “Gola Protetta”",
						"text"    => "Prevalenza di risposte A. Sei costante nella prevenzione: idrati, proteggi la gola, usi rimedi naturali.",
						"advice"  => "Ideali per mantenere le mucose protette durante freddo, vento e sbalzi termici.",

						"products" => [
							1804,6826,6828,1501,6021
						],
					],

					"B" => [
						"title"   => "🅱️ Profilo B – “Buone Abitudini, ma migliorabili”",
						"text"    => "Prevalenza di risposte B. Hai una buona routine, ma non sempre costante.",
						"advice"  => "Perfetti per mantenere la mucosa idratata e prevenire irritazioni ricorrenti.",

						"products" => [
							6024,1804,5274
						],
					],

					"C" => [
						"title"   => "🅲 Profilo C – “Sottovaluti i segnali”",
						"text"    => "Prevalenza di risposte C. Intervieni tardi: serve un’azione più mirata sui primi sintomi.",
						"advice"  => "Ottimi per evitare che un semplice fastidio diventi un’infiammazione importante.",

						"products" => [
							1502,6020,3665					
						],
					],

					"D" => [
						"title"   => "🅳 Profilo D – “Gola a rischio (e antibiotico facile)”",
						"text"    => "Prevalenza di risposte D. Usi pochi rimedi preventivi e rischi l’uso scorretto di antibiotici.",
						"advice"  => "Per sintomi più intensi: antinfiammatori mirati, NO antibiotico fai-da-te!",

						"products" => [
							7140,340,7141,3323
						],
					],
				],

				"cta" => NULL,
			],
			7 => [
				"id"         => 6,
				"pharma_id"  => 1,
				"title"      => "🎄 QUIZ DELLA SETTIMANA – FARMACIA GIOVINAZZI",
				"subtitle"   => "Disturbi digestivi durante le feste: come ti comporti davvero?",
				"summary"    => "Durante le feste non conta solo cosa mangiamo,\nma soprattutto come ci comportiamo prima e dopo i pasti.\n👉 Il farmacista è il tuo punto di riferimento\nper capire cosa fare, cosa evitare e quando intervenire.",
				"created_at" => "2025-12-16 00:00:00",
				"start_date" => "2025-12-15 00:00:00",
				"end_date"   => "2025-12-22 23:59:59",

				"questions" => [
					[
						"question" => "Dopo un pasto abbondante, cosa fai di solito?",
						"answers" => [
							["value" => "A", "label" => "Faccio una passeggiata o resto un po in movimento"],
							["value" => "B", "label" => "Mi siedo subito e prendo una tisana"],
							["value" => "C", "label" => "Mi sdraio sul divano o vado a letto"],
							["value" => "D", "label" => "Prendo un caffè sperando di digerire"],
						]
					],
					[
						"question" => "Se senti lo stomaco pesante o gonfio, come reagisci?",
						"answers" => [
							["value" => "A", "label" => "Cerco di mangiare più leggero nel pasto successivo"],
							["value" => "B", "label" => "Prendo qualcosa per aiutare la digestione"],
							["value" => "C", "label" => "Uso un antiacido o un prodotto contro il reflusso"],
							["value" => "D", "label" => "Prendo un gastroprotettore “per prevenire”"],
						]
					],
					[
						"question" => "Quando avverti bruciore o acidità, cosa fai?",
						"answers" => [
							["value" => "A", "label" => "Mangio qualcosa di secco ed evito cibi o bevande acide"],
							["value" => "B", "label" => "Bevo qualcosa o cerco un rimedio naturale"],
							["value" => "C", "label" => "Uso un prodotto specifico contro acidità e reflusso"],
							["value" => "D", "label" => "Prendo farmaci senza chiedere consiglio"],
						]
					],
					[
						"question" => "Durante le feste, come gestisci i pasti abbondanti?",
						"answers" => [
							["value" => "A", "label" => "Cerco di ascoltare il mio corpo e fermarmi in tempo"],
							["value" => "B", "label" => "Mangio velocemente e me ne accorgo dopo"],
							["value" => "C", "label" => "Mangio tanto anche sapendo che poi starò male"],
							["value" => "D", "label" => "Mangio poco per paura dei sintomi"],
						]
					],
					[
						"question" => "Se i disturbi digestivi si ripetono per più giorni, cosa fai?",
						"answers" => [
							["value" => "A", "label" => "Chiedo consiglio al farmacista o al medico"],
							["value" => "B", "label" => "Modifico alimentazione e abitudini"],
							["value" => "C", "label" => "Continuo a prendere prodotti da banco"],
							["value" => "D", "label" => "Uso spesso antiacidi o simili"],
						]
					],
				],

				"products"     => NULL,
				"products_ids" => [1163,7142,380,7143,697,7073,7103,7144,4837,6074,7145,467],

				"profiles" => [
					"A" => [
						"title"  => "PROFILO A",
						"text"   => "Consapevole, riesci a regolarti anche durante le feste <br> 👉 I tuoi disturbi i sono  lievi e legati ad eccessi occasionali",
						"advice" => "Consigli\n\t•\tassaggia un po’ di tutto senza esagerare\n\t•\t fai movimento leggero dopo i pasti\n\t•\tusa rimedi solo se servono\n\nProdotti consigliati (al bisogno)\n\t1.\tEpakur Digestive – tisana – €11,50\nFavorisce la digestione in modo delicato\n\t2.\tGeffer granulato effervescente – €7,90\nUtile per senso di peso post-pasto\n\t3.\tBiochetasi Digestione Pocket – €12,50\nSupporto digestivo pratico e leggero",
						"products" => [1163,7142,380],
					],

					"B" => [
						"title"  => "PROFILO B",
						"text"   => "Buone intenzioni, ma digestione lenta e gonfiore <br> 👉 Ti muovi nella direzione giusta, ma gonfiore e fermentazione intestinale sono frequenti.",
						"advice" => "Consigli\n\t•\tmangia più lentamente\n\t•\tlimita bevande gassate e lievitati\n\t•\tcammina dopo i pasti\n\nProdotti consigliati\n\t1.\tNo Coli Gonfiore – €14,90\nRiduce la formazione di gas intestinali\n\t2.\tColigas Fast – tisana – €11,50\nAllevia gonfiore e tensione addominale\n\t3.\tPiù Flora – fermenti lattici – €14,50\nRiequilibra l’intestino dopo eccessi alimentari",
						"products" => [7143,697,7073],
					],

					"C" => [
						"title"  => "PROFILO C",
						"text"   => "Comportamenti a rischio reflusso <br> 👉 Alcune abitudini favoriscono acidità e reflusso.",
						"advice" => "Consigli\n\t•\tevita di sdraiarti dopo mangiato\n\t•\tcena almeno 2–3 ore prima di dormire\n\t•\tlimita alcol, caffè, cioccolato\n\nProdotti consigliati\n\t1.\tNeoBianacid – €15,90\nProtegge e lenisce la mucosa gastrica\n\t2.\tXanacid stick – €12,90\nAzione barriera rapida, pratico fuori casa\n\t3.\tGaviscon Bruciore e Indigestione – €15,95\nUtile soprattutto la sera\n\n⚠️ I gastroprotettori non sono indicati nei disturbi occasionali.",
						"products" => [7103,7144,4837],
					],

					"D" => [
						"title"  => "PROFILO D",
						"text"   => "Attenzione: gestione scorretta dei disturbi <br> 👉 I sintomi sono persistenti o gestiti in modo non corretto.",
						"advice" => "Consigli\n\t•\tevita l’automedicazione\n\t•\tnon usare gastroprotettori “preventivi”\n\t•\tconfrontati con un professionista\n\nProdotti eventualmente utilizzabili solo come supporto temporaneo\n\t1.\tReflugea Forte – €36,60\nProtezione intensa della mucosa\n\t2.\tMaalox RefluRapid – €13,90\nSollievo rapido dal bruciore\n\t3.\tBuscopan 10 mg – €16,90\nIn caso di dolore o spasmo",
						"products" => [6074,7145,467],
					],
				],

				"cta" => NULL,
			],
		);
	}

	/**
	 * Cerca un sondaggio per ID
	 * @return array|false
	 */
	public static function findById($id, ?int $pharma_id = null) {
		$surveys = self::data();
		$survey = $surveys[$id] ?? FALSE;
		if( $survey && $pharma_id && isset($survey['pharma_id']) && $survey['pharma_id'] != $pharma_id ){
			return FALSE;
		}
		return $survey;
	}

	/**
	 * Cerca un sondaggio per giorno
	 * @return array|false
	 */
	public static function findByDate($date, ?int $pharma_id = null) {
		$surveys = self::data();
		$monday = get_week_start_date($date);
		$survey = array_filter($surveys, function($_survey) use ($monday, $pharma_id) {
			if( $pharma_id && isset($_survey['pharma_id']) && $_survey['pharma_id'] != $pharma_id ){
				return FALSE;
			}
			return $_survey['start_date'] ? (date('Y-m-d', strtotime($_survey['start_date'])) == $monday) : FALSE;
		});
		$survey = array_values($survey);
		$survey = count($survey) == 1 ? $survey[0] : FALSE;
		return $survey;
	}

	/**
	 * Cerca i sondaggi "aperti" (basandosi su data inizio e data fine)
	 * @return array|false
	 */
	public static function getAllOpen($date_time, ?int $pharma_id = null) {
		$surveys = self::data();
		$survey = array_filter($surveys, function($_survey) use ($date_time, $pharma_id) {
			if( $pharma_id && isset($_survey['pharma_id']) && $_survey['pharma_id'] != $pharma_id ){
				return FALSE;
			}
			if( isset($_survey['start_date']) && isset($_survey['end_date']) ){
				return $_survey['start_date'] <= $date_time && $date_time <= $_survey['end_date'];
			}elseif( isset($_survey['start_date']) ){
				return $_survey['start_date'] <= $date_time;
			}elseif( isset($_survey['end_date']) ){
				return $date_time <= $_survey['end_date'];
			}
			return FALSE;
		});
		$surveys = array_values($survey);
		return $surveys;
	}

	/**
	 * Normalizza un record survey per l'output API JSON.
	 */
	public static function normalize(array $survey) {
		if( ! $survey ) return FALSE;

		// foreach ($survey as $key => $val) {
		// 	if (is_array($val)) {
		// 		$survey[$key] = self::normalize($val);
		// 		continue;
		// 	}
		// 	if (!is_string($val)) {
		// 		$survey[$key] = $val;
		// 		continue;
		// 	}
		// 	if (preg_match('/(url|href|src|link)/i', $key)) {
		// 		$survey[$key] = esc_url($val);
		// 		continue;
		// 	}
		// 	if (preg_match('/(value|alt|title|id|name)/i', $key)) {
		// 		$survey[$key] = esc_attr($val);
		// 		continue;
		// 	}
		// 	$survey[$key] = esc_html($val);
		// }

		$products = ProductsModel::findByIds($survey['products_ids']);

		return [
			'id'             => (int) $survey['id'],
			'title'          => $survey['title'],
			'subtitle'       => $survey['subtitle'],
			'summary'        => $survey['summary'],
			'profiles'       => $survey['profiles'],
			'questions'      => $survey['questions'],
			'cta'            => $survey['cta'],
			'pharmacist_tip' => $survey['pharmacist_tip'] ?? NULL,
			'products'       => array_map('normalize_product_data', $products),
		];

		return $survey;
	}

}

function normalize_survey_data(array $survey) {
	return SurveysModel::normalize($survey);
}
