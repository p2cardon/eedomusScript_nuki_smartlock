<?php
/** ****************************************************************************
* Nikya eedomus Script Nuki Smartlock
********************************************************************************
* Plugin version : 1.3
* Author : Nikya
* Origine : https://github.com/Nikya/eedomusScript_nuki_smartlock
* Nuki Bridge HTTP-API : 1.6
*******************************************************************************/

/** Utile en cours de dev uniquement */
//$eedomusScriptsEmulatorDatasetPath = "eedomusScriptsEmulator_dataset.json";
//require_once ("eedomusScriptsEmulator.php");

/** Initialisation de la réponse */
$response = null;

/** Lecture de la fonction */
$function = getArg('function');

/** ****************************************************************************
* Routeur de fonction
*/
switch($function) {
	case 'setup':
		sdk_setup(getArg('nukihost_port'), getArg('token'));
		break;
	case 'register':
		sdk_register(getArg('eedomushost'), getArg('nukiid'), getArg('periph_id_state'), getArg('periph_id_batterycritical'));
		break;
	case 'list':
		sdk_callAPI('list');
		break;
	case 'callback_list':
		sdk_callAPI('callback/list');
		break;
	case 'callback_remove':
		sdk_callAPI('callback/remove', array('id'=> getArg('id')));
		break;
	case 'incomingcall':
		sdk_incomingCall();
		break;
	default:
		$response = '{ "success" : "false", "message" : "Unknown function '.$function.' " }';
}

/** ****************************************************************************
* Enregister les informations pour communiquer avec le Bridge Nuki et affiche
* la liste des serrures connues sur le pont ciblé.
*
* @param $nukihost Host IP du Nuki
* @param $nukiport Port du Nuki
* @param $token Token du Nuki
*/
function sdk_setup($nukihost_port, $token) {
	saveVariable('nukihost_port', $nukihost_port);
	saveVariable('token', $token);

	sdk_callAPI('list');
}

/** ****************************************************************************
* Enregister les informations
* - Côté eedomus : Les id des 2 périphériques d'informations
* - Côté Nuki : Enregistre ce script en tant que callBack
*/
function sdk_register($eedomushost, $nukiid, $periph_id_state, $periph_id_batterycritical) {
	global $response;

	$eScript = explode( '/' , __FILE__);
	$scriptName = $eScript[count($eScript)-1];

	$callbackUrl = "http://$eedomushost/script/";
	$callbackUrlQuery = array(
		'exec' => $scriptName,
		'function' => 'incomingcall'
	);
	$fullUrl = "$callbackUrl?".http_build_query($callbackUrlQuery);

	saveVariable('nukiid', $nukiid);
	saveVariable("periph_id_state$nukiid", $periph_id_state);
	saveVariable("periph_id_batterycritical$nukiid", $periph_id_batterycritical);

	sdk_callAPI('callback/add', array('url' => $fullUrl));
}

/** ****************************************************************************
* Fonction appelée par un callback de la part de Nuki.
* Est rappeler à chaque changement d'état.
*/
function sdk_incomingCall() {
	global $response;

	// Le callback est accompagné d'un Json contenant les nouvelles valeurs
	//		{"nukiId": 11, "state": 1, "stateName": "locked", "batteryCritical": false}
	$backData = sdk_json_decode(sdk_get_input());
	$nukiid = $backData['nukiId'];
	$periph_value_state = $backData['state'];
	// $periph_value_batterycritical = $backData['batteryCritical'];
	// My comments : $backData['batteryCritical'] contains false (default) or true
	// this prevents to update directly the eedomus batterycritical peripheral, which
	// is waiting for 0 (Bon) or 100 (Critique) … I suggest this change:
	$periph_value_batterycritical = $backData['batteryCritical'] ? 100 : 0;

	$periph_id_state = loadVariable("periph_id_state$nukiid");
	$periph_id_batterycritical = loadVariable("periph_id_batterycritical$nukiid");

	setValue($periph_id_state, $periph_value_state);
	setValue($periph_id_batterycritical, $periph_value_batterycritical);

	$response = ' { ';
	$response.= ' "nukiid" : "'. $nukiid .'", ';
	$response.= ' "periph_id_state" : "'. $periph_id_state .'", ';
	$response.= ' "periph_id_batterycritical" : "'. $periph_id_batterycritical .'", ';
	$response.= ' "periph_value_state" : "'. $periph_value_state .'", ';
	$response.= ' "periph_value_batterycritical" : "'. $periph_value_batterycritical .'" ';
	$response.= ' } ';
}

/** ****************************************************************************
* Appeler l'API de Nuki
*
* @param $endpoint Endpoint ciblé
* @param $params Tableau de paramétre à envoyer sur la cible
*
* @return le résulat de l'appel au format Json
*/
function sdk_callAPI($endpoint, $params=array()) {
	global $response;

	$nukihost_port = loadVariable('nukihost_port');
	$token = loadVariable('token');

	if(empty($nukihost_port) or empty($token)) {
		$response = '{ "success" : "false", "message" : "Need an execution of function:setup before !" }';
		return;
	}

	$params['token'] =$token;
	$url = "http://$nukihost_port/$endpoint?".http_build_query($params);

	$response = httpQuery($url);

	return $response;
}

/** ****************************************************************************
* Fin du script, affichage du résultat au format XML
*/
sdk_header('text/xml');
echo jsonToXML($response);
