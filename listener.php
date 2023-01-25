<?php
error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
include_once('ebay-config.php');

//Getting notification input as xml from eBay
if (!isset($HTTP_RAW_POST_DATA)) {
	$HTTP_RAW_POST_DATA = file_get_contents("php://input");
	//$HTTP_RAW_POST_DATA = file_get_contents("output.xml");
}

$rawData = $GLOBALS['HTTP_RAW_POST_DATA'];
$rawData = str_replace('ebl:RequesterCredentials','dzRequesterCredentials',$rawData); 
$rawData = str_replace('ebl:NotificationSignature','dzNotificationSignature',$rawData);


if($rawData != ""){	
	//Soap Connectivity to load xml
	$soap     	= simplexml_load_string($rawData);
	$response 	= $soap->children('http://schemas.xmlsoap.org/soap/envelope/')->Body->children()->GetItemTransactionsResponse;
	$header 	= $soap->children('http://schemas.xmlsoap.org/soap/envelope/')->Header->children()->dzRequesterCredentials;


	//Fetch Details from the XML
	$Timestamp 				= (string) $response->Timestamp;
	$NotificationSignature 	= (string) $header->dzNotificationSignature;
	$CalculatedSignature	= CalculateSignature($Timestamp, $DevID, $AppID, $Cert); //Calculate the signature with HASH


	//Testing a data manually with current time
		//$NotificationSignature 	= $CalculatedSignature; //Need to put current time is within 10mins of timestamp and test.
		//$CalculatedSignature = "PoaDGFudHMRk7rlV==Sna";	//Mismatch signature test.


	//Validation
	$validateSignature = ValidateSignature($Timestamp, $CalculatedSignature, $NotificationSignature);

	//Printing the data
	echo $Timestamp."</br>";
	echo $CalculatedSignature."</br>";
	echo $NotificationSignature."</br>";

	//$validateSignature = "validTime";

	//Writing the data into file 
	if($validateSignature == "validTime"){
		$GetItemDetails = GetItemDetails($response);
		print_r($GetItemDetails);

		$myFile = "dataFile.txt";
		date_default_timezone_set("Asia/Calcutta");
		$fh = fopen($myFile, 'a') or die("can't open file");
		$stringData = date("d-m-Y h:i:sa")." ==> ".$GetItemDetails."\r\n\r\n\r\n";
		fwrite($fh, $stringData);
		fclose($fh);
		echo "Done";
	}
	elseif($validateSignature == "signatureFailed"){	
		echo "Signature Mismatched";
	}
	elseif($validateSignature == "NotValidTime") {
		echo "Time Drift is too large. It should be only in 10mins difference";
	}
	else{
		echo "Invalid XML";
	}

}else{
	echo "Input is empty";
}




//Functions for calculation and validation

function CalculateSignature($Timestamp, $DevID, $AppID, $Cert) {
    // Not quite sure why we need the pack('H*'), but we do //This is done as per eBay guidelines
    $Signature = base64_encode(pack('H*', md5("{$Timestamp}{$DevID}{$AppID}{$Cert}")));
    return $Signature;
}


function ValidateSignature($Timestamp, $CalculatedSignature, $NotificationSignature){  
	if($CalculatedSignature != $NotificationSignature) {
	    return "signatureFailed";
	} 
	else{  
	    // Check that Timestamp is within 10 minutes of now
	    $timezone = date_default_timezone_get();
	    date_default_timezone_set('UTC');

	    $then = strtotime($Timestamp);
	    $now = time();
	    date_default_timezone_set($timezone);

	    $drift = $now - $then;
	    $ten_minutes = 60 * 10;
	    
	    if ($drift > $ten_minutes) {      
	      return "NotValidTime";
	    } 
	    else {
	      return "validTime";
	    }  
    } 
}


function GetItemDetails($response){
	$data = '{
		"User":{
			"SellerID": "'.$response->Item->Seller->UserID.'",
			"BuyerID": "'.$response->TransactionArray->Transaction->Buyer->UserID.'",
		},
		"Item":{
			"ItemID": "'.$response->Item->ItemID.'",
			"SKU": "'.$response->Item->SKU.'",
			"Title": "'.$response->Item->Title.'",
			"QuantitySold": "'.$response->Item->SellingStatus->QuantitySold.'",
			"QuantityPurchased": "'.$response->TransactionArray->Transaction->QuantityPurchased.'",
			"OrderID": "'.$response->TransactionArray->Transaction->ContainingOrder->OrderID.'"
		}
	}';

	return $data;
}
?>