<?php
 
date_default_timezone_set('Europe/Amsterdam');
setlocale(LC_MONETARY, 'nl_NL'); //doet dit iets?
 
// File setup
$dropboxPath = "/home/susan/Dropbox/DFadmin/";
$podioPath = "/var/www/podio/";
$log = $podioPath . "log/webhook.log";
$logId = basename($_SERVER['SCRIPT_FILENAME']) . ": ";
 
// Include dependancies
require_once $podioPath . 'podio-php/PodioAPI.php';
include $podioPath . 'podio-php/utils/config.client.php';
include $podioPath . 'podio-php/utils/config.kasboek.php';
include $podioPath . 'MySQL/utils/config.client.php';
 
// API key setup
$client_id = CLIENT_ID;
$client_secret = CLIENT_SECRET;
 
// Authentication setup
$app_id_Kasboek = APP_ID_KASBOEK;
$app_token_Kasboek = APP_TOKEN_KASBOEK;
 
//script function
function cleanText($string) {
    return str_replace("&", "&amp;", $string);
}
 
$MySQL_Server = MYSQL_SERVER;
$MySQL_User = MYSQL_USER;
$MySQL_PWD = MYSQL_PWD;
$MySQL_DB = MYSQL_DB;
 
// Setup Database connection
$con = mysqli_connect($MySQL_Server, $MySQL_User, $MySQL_PWD, $MySQL_DB)or die(mysqli_error($con)); //or die("Not connected.")
// Setup client (no session manager, multiple app authentications)
// Session handling by 'authenticate' function
Podio::setup($client_id, $client_secret, array('session_manager' => false));
 
 
// Debuggin On
Podio::set_debug(true, 'file');
 
function authenticate($app_id, $app_token) {
 
    global $con;
 
    //get last auth from DB
    $query = "call proc_appToken('" . $app_id . "','" . $app_token
            . "',@accessToken, @refreshToken, @expiresIn, @refType, @refID)";
    $result = mysqli_query($con, $query);
    $result2 = mysqli_query($con, "select @accessToken as accessToken"
            . ", @refreshToken as refreshToken"
            . ", @expiresIn as expiresIn"
            . ", @refType as refType"
            . ", @refID as refID");
 
    while ($row = mysqli_fetch_assoc($result2)) {
        $ref = new stdClass(); //create a new object
        $ref->type = $row["refType"];
        $ref->id = $row["refID"];
 
        $appAccessToken = new stdClass(); //create a new object
        $appAccessToken->access_token = $row["accessToken"];
        $appAccessToken->refresh_token = $row["refreshToken"];
        $appAccessToken->expires_in = $row["expiresIn"];
        $appAccessToken->expires_in = $ref;
    }
 
    //authenticate with found token
    Podio::$oauth = $appAccessToken;
 
    if (!Podio::is_authenticated()) {
        //If not, you must re-authenticat
        Podio::authenticate_with_app($app_id, $app_token);
        $newAccessToken = Podio::$oauth;
 
        //store new token to DB
        mysqli_query($con, "insert into auth "
                . "(authAccessToken"
                . ",authRefreshToken"
                . ",authExpiresIn"
                . ",authRefType"
                . ",authRefAppID) "
                . "values "
                . "('" . $newAccessToken->access_token
                . "','" . $newAccessToken->refresh_token
                . "'," . $newAccessToken->expires_in
                . ",'" . $newAccessToken->ref['type']
                . "'," . $newAccessToken->ref['id'] . ");");
    } else {
        //do nothing...
    }
}
 
// Setup fields by external_id
$omschrijving = 'omschrijving';
$factuur = 'factuur';
$type = 'type-3';
$datum = 'factuurdatum';
$boekstuknummer = 'boekstuknummer';
$grootboekrubriek = 'type';
$bedrag = 'totaal-2';
$BTWbedrag = 'btw-bedrag-2';
$betaling = 'betaling-2';
$door = 'betaald-door';
$tenBehoeveVan = 'ten-behoeve-van';
$aandeelRick = 'aandeel-rick-2';
$aandeelJasper = 'aandeel-jasper-2';
$aandeelErik = 'aandeel-erik-2';
$aandeelMiranda = 'aandeel-miranda-2';
$aandeelBank = 'aandeel-bank-2';
 
 
//$kasboek_itemID = 798849173;
 
switch ($_POST['type']) {
 
// Validate the webhook. This is a special case where we verify newly created webhooks.
    case 'hook.verify':
        PodioHook::validate($_POST['hook_id'], array('code' => $_POST['code']));
        break;
 
 
    // An item was created
    default:
    //case 'item.update':
 
        if (file_exists($podioPath . "log/webhook.log")) {
            unlink($podioPath . "log/webhook.log");
        }
 
        error_reporting(E_ALL);
        ini_set("log_errors", 1);
        ini_set("error_log", $podioPath . "log/webhook.log");
        error_log("Hello, errors!");
 
 
//authenticate with Kasboek app
        authenticate($app_id_Kasboek, $app_token_Kasboek);
 
        $kasboek_itemID = (int) $_POST['item_id'];
		file_put_contents($log, $logId . "kasboek_itemID: ". $kasboek_itemID . "\n", FILE_APPEND | LOCK_EX);
        $kasboek_item = PodioItem::get_basic($kasboek_itemID);
 
        $podioOmschrijving = $kasboek_item->fields[$omschrijving]->values;
        file_put_contents($log, $logId . $podioOmschrijving . "\n", FILE_APPEND | LOCK_EX);
 
//$podioFactuur = $kasboek_item->fields[$factuur]->values;
 
        foreach ($kasboek_item->fields[$type]->values as $option) {
            $podioType = $option['text'];
            file_put_contents($log, $logId . $podioType . "\n", FILE_APPEND | LOCK_EX);
        }
        if ($podioType == 'Debet') {
            $valueSign = 1;
        }
        else 
        {
            $valueSign = -1;
        }
            
        
 
//        $podioDatum = $kasboek_item->fields[$datum]->start->format('Y-m-d');
//        file_put_contents($log, $logId . $podioDatum . "\n", FILE_APPEND | LOCK_EX);
 
        $podioBoekstuknummer = $kasboek_item->fields[$boekstuknummer]->values;
        file_put_contents($log, $logId . $podioBoekstuknummer . "\n", FILE_APPEND | LOCK_EX);
 
        foreach ($kasboek_item->fields[$grootboekrubriek]->values as $option) {
            $podioGrootboekrubriek = $option['text'];
            file_put_contents($log, $logId . $podioGrootboekrubriek . "\n", FILE_APPEND | LOCK_EX);
        }
 
        $podioBedrag = $kasboek_item->fields[$bedrag]->amount * $valueSign;
        file_put_contents($log, $logId . "podioBedrag: " . $podioBedrag . "\n", FILE_APPEND | LOCK_EX);
 
 
        $podioBTWbedrag = $kasboek_item->fields[$BTWbedrag]->amount * $valueSign;
        file_put_contents($log, $logId . $podioBTWbedrag . "\n", FILE_APPEND | LOCK_EX);
 
        foreach ($kasboek_item->fields[$betaling]->values as $option) {
            $podioBetaling = $option['text'];
            file_put_contents($log, $logId . $podioBetaling . "\n", FILE_APPEND | LOCK_EX);
        }
 
        $new_field = new PodioMoneyItemField($aandeelRick); // new field with the same(!) name
        $new_field->currency = "EUR";
        $new_field->amount = 0;
        
        $new_field = new PodioMoneyItemField($aandeelJasper); // new field with the same(!) name
        $new_field->currency = "EUR";
        $new_field->amount = 0;
        
        $new_field = new PodioMoneyItemField($aandeelErik); // new field with the same(!) name
        $new_field->currency = "EUR";
        $new_field->amount = 0;
        
        $new_field = new PodioMoneyItemField($aandeelMiranda); // new field with the same(!) name
        $new_field->currency = "EUR";
        $new_field->amount = 0;
        
        $new_field = new PodioMoneyItemField($aandeelBank); // new field with the same(!) name
        $new_field->currency = "EUR";
        $new_field->amount = 0;
//        $kasboek_item->fields[$aandeelRick]->amount = 0;
//        $kasboek_item->fields[$aandeelJasper]->amount = 0;
//        $kasboek_item->fields[$aandeelErik]->amount = 0;
//        $kasboek_item->fields[$aandeelMiranda]->amount = 0;
//        $kasboek_item->fields[$aandeelBank]->amount = 0;

 
        switch ($podioGrootboekrubriek) {
            case 'Gage':
 
                if ($_POST['type'] == 'item.create') {
                                //Only at item 'create'!!
                $new_field = new PodioCategoryItemField($tenBehoeveVan); // new field with the same(!) name
                $new_field->values = array(1,2,3,4,8); // 8 = kas
                $kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                //************
                }

                $i = 4;
 
                foreach ($kasboek_item->fields[$tenBehoeveVan]->values as $option) {
                    $podiotenBehoeveVan = $option['id'];
                    file_put_contents($log, $logId . $podiotenBehoeveVan . $option['id']."\n", FILE_APPEND | LOCK_EX);
                    switch ($podiotenBehoeveVan) {
                        case 1: //'Rick':
                            $new_field = new PodioMoneyItemField($aandeelRick); // new field with the same(!) name
                            $new_field->currency = "EUR";
                            $new_field->amount = 0.125 * $podioBedrag;
                            file_put_contents($log, $logId . "rick: " . $new_field . "\n", FILE_APPEND | LOCK_EX);
                            $kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                            $i--;
                            break;
                        case 2: //'Jasper':
                            $new_field = new PodioMoneyItemField($aandeelJasper); // new field with the same(!) name
                            $new_field->currency = "EUR";
                            $new_field->amount = 0.125 * $podioBedrag;
                            file_put_contents($log, $logId . "jasper: " . $new_field . "\n", FILE_APPEND | LOCK_EX);
                            $kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                            $i--;
                            break;
                        case 3: //'Erik':
                            $new_field = new PodioMoneyItemField($aandeelErik); // new field with the same(!) name
                            $new_field->currency = "EUR";
                            $new_field->amount = 0.125 * $podioBedrag;
                            file_put_contents($log, $logId . "erik: " . $new_field . "\n", FILE_APPEND | LOCK_EX);
                            $kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                            $i--;
                            break;
                        case 4: //'Miranda':
                            $new_field = new PodioMoneyItemField($aandeelMiranda); // new field with the same(!) name
                            $new_field->currency = "EUR";
                            $new_field->amount = 0.125 * $podioBedrag;
                            file_put_contents($log, $logId . "miranda: " . $new_field . "\n", FILE_APPEND | LOCK_EX);
                            $kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                            $i--;
                            break;
                    }
                }
				
				//$kasboek_item->fields[$tenBehoeveVan]->values = 8;
 
                $new_field = new PodioMoneyItemField($aandeelBank); // new field with the same(!) name
                $new_field->currency = "EUR";
                $new_field->amount = (0.5 + ($i * 0.125)) * $podioBedrag;
                file_put_contents($log, $logId . "kas: " . $new_field . "\n", FILE_APPEND | LOCK_EX);
                $kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
 
                $kasboek_item->save(array(
                    'hook' => false,
                    'silent' => false
                ));
                break;
             case 'Reservering':
                //if ($_POST['type'] == 'item.create') {
                	//Only at item 'create'!!
                	$new_field = new PodioCategoryItemField($type); // new field with the same(!) name
                	$new_field->values = 0; // 1 = debet, 0 = credit
                	$kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                 
                	//************
            	//}
                 break;
             case 'Kas':
                //if ($_POST['type'] == 'item.create') {
                	//Only at item 'create'!!
//                	$new_field = new PodioCategoryItemField($type); // new field with the same(!) name
//                	$new_field->values = 0; // 1 = debet, 0 = credit
//                	$kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                 
                	//************
            	//}
                 break;
            default:
            	if ($_POST['type'] == 'item.create') {
                	//Only at item 'create'!!
//                	$new_field = new PodioCategoryItemField($type); // new field with the same(!) name
//                	$new_field->values = 1; // 0 = debet, 1 = credit
//                	$kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
                 
                	//************
            	}
 
            	$new_field = new PodioCategoryItemField($tenBehoeveVan); // new field with the same(!) name
	            $new_field->values = 8; // 8 = kas
    	        $kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
 
            	$new_field = new PodioMoneyItemField($aandeelBank); // new field with the same(!) name
            	$new_field->currency = "EUR";
            	$new_field->amount = $podioBedrag;
            	file_put_contents($log, $logId . "kas: " . $new_field . "\n", FILE_APPEND | LOCK_EX);
            	$kasboek_item->fields[] = $new_field; // the new field will replace the exsisting (empty) one
				break;
         }
	$kasboek_item->save(array(
    'hook' => false,
    'silent' => false
    ));		 
		 
}
 
file_put_contents($log, $logId . "</pre>", FILE_APPEND | LOCK_EX);
?>
