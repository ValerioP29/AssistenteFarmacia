<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
// $decoded = protectFileWithJWT();

// $user = get_my_data();
// if( ! $user ){
// 	echo json_encode([
// 		'code'    => 401
// 		'status'  => false,
// 		'error'   => 'Invalid or expired token',
// 		'message' => 'Accesso negato',
// 	]);
// 	exit();
// }

// // PAYLOAD
// // client → server:
// {
// 	"query": "aspirina", ---la stringa che l’utente ha digitato
// 	"inputFree": true|false  ---true se si tratta di un testo libero, non da lista
// }

//------------------------------------------------

$json = '[
	{
		"id": 1,
		"name": "Aspirina 500mg",
		"type": "drug",
		"code": "ASP500",
		"image": {
			"thumb": {
				"src": "'.site_url().'/uploads/drugs/1.jpg",
				"alt": "Immagine confezione Aspirina 500mg",
				"width": 150,
				"height": 150
			},
			"regular": {
				"src": "'.site_url().'/uploads/drugs/1.jpg",
				"alt": "Immagine confezione Aspirina 500mg",
				"width": 1000,
				"height": 1000
			}
		}
	},
	{
		"id": 2,
		"name": "Aspirina Complex 650mg",
		"type": "drug",
		"code": "ASP650",
		"image": {
			"thumb": {
				"src": "'.site_url().'/uploads/drugs/2.jpg",
				"alt": "Immagine confezione Aspirina Complex 650mg",
				"width": 150,
				"height": 150
			},
			"regular": {
				"src": "'.site_url().'/uploads/drugs/2.jpg",
				"alt": "Immagine confezione Aspirina Complex 650mg",
				"width": 1000,
				"height": 1000
			}
		}
	}
]';
$json = json_decode( $json );

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => NULL,
	'data'    => $json,
]);
