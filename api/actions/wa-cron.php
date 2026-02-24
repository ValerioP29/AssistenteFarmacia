<?php

require_once('../_api_bootstrap.php');
require_once('../helpers/_model_comm_history.php');

$success = 0;
$error = 0;

// exit;

$comms = CommModel::getAllToSend(1);
foreach( $comms AS $_comm ){
	$delay = mt_rand(60, 120) / 10;
	$_resp = CommModel::send($_comm, $delay * 1000 );
	if( $_resp ) $success++;
	if( !$_resp ) $error++;
}

// var_dump($comms);
// exit;

echo 'L\'impostazione di invio WhatsApp è '. (get_option('wa_send_enabled', TRUE) ? 'abilitata' : 'disabilitata' ).'<br>';
echo 'L\'esito del test di invio è: <br>';
echo 'Inviati: ' . $success . '/' . count($comms) . '<br>';
echo 'Falliti: ' . $error . '/' . count($comms);
