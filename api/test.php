<?php
require_once('_api_bootstrap.php');
/*
setHeadersAPI();

$input = json_decode(file_get_contents("php://input"), true);

echo json_encode([
	'code'    => 200,
	'status'  => true,
	'message' => NULL,
	'input'   => $input,
]);
*/

print("<pre>");
print_r($_SERVER);
print("</pre>");