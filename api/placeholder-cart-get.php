<?php
require_once('_api_bootstrap.php');
setHeadersAPI();
// $decoded = protectFileWithJWT();

// items key

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

//------------------------------------------------

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => NULL,
	'data'    => [
		[
			"id"          => 1,
			"full_label"  => "Avène Spray Solare SPF 50+",
			"image"       => [
				"src"    => "https://api.assistentefarmacia.it/uploads/drugs/1.jpg",
				"alt"    => "Immagine confezione Avène Spray Solare",
				"width"  => 1000,
				"height" => 1000
			],
			"price" => 12.00,
			"quantity"   => 3
		],
		[
			"id"          => 2,
			"full_label"  => "Autan Family Care Spray",
			"image"       => [
				"src"    => "https://api.assistentefarmacia.it/uploads/drugs/2.jpg",
				"alt"    => "Immagine confezione Avène Spray Solare",
				"width"  => 1000,
				"height" => 1000
			],
			"price" => 7.50,
			"quantity"   => 1
		]
	],
]);
