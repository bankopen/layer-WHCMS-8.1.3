<?php

# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
include('../lib/layer_api.php');

$gatewaymodule = "layer"; # Enter your gateway module name here replacing template

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

$error="";
$status="";
if(!isset($_POST['layer_payment_id']) || empty($_POST['layer_payment_id']))
	$error = "Invalid response.";
if(empty($error)) {	
	$response = array();
	$response = $_POST;
	
	$data = array(
        'layer_pay_token_id'    => $response['layer_pay_token_id'],
        'layer_order_amount'    => $response['layer_order_amount'],
        'tranid'     			=> $response['tranid'],
    );
	
	$layer_api = new LayerApi(strtolower($GATEWAY['Environment']),$GATEWAY['Accesskey'],$GATEWAY['Secretkey']);
	
	if($layer_api->verify_hash($data,$response['hash'],$GATEWAY['Accesskey'],$GATEWAY['Secretkey']) && !empty($data['tranid'])){
        $payment_data = $layer_api->get_payment_details($response['layer_payment_id']);

        if(isset($payment_data['error'])){
            $error = "Layer: an error occurred E14".$payment_data['error'];
        }

        if(empty($error) && isset($payment_data['id']) && !empty($payment_data)){
            if($payment_data['payment_token']['id'] != $data['layer_pay_token_id'])
                $error = "Layer: received layer_pay_token_id and collected layer_pay_token_id doesnt match";
            elseif($data['layer_order_amount'] != $payment_data['amount'])
                $error = "Layer: received amount and collected amount doesnt match";
            else {
                if($payment_data['status']=='authorized' || $payment_data['status']=='captured')
                    $status = "Payment captured: Payment ID ". $payment_data['id'];
                elseif($payment_data['status']=='failed' || $payment_data['status']=='cancelled') 
                    $status = "Payment cancelled/failed: Payment ID ". $payment_data['id'];                    
            }
        } else {
            $error = "invalid payment data received E98";
        }
	}
} else {
    $error = "hash validation failed";
}

if(!empty($error))
	logTransaction($GATEWAY["name"],$response,$error);

if(empty($error)) {	
	$invoiceid = checkCbInvoiceID($response['tranid'], $gatewaymodule); # Checks invoice ID is a valid invoice number or ends processing

	checkCbTransID($payment_data['id']); # Checks transaction number isn't already in the database and ends processing if it does

	if(!empty($status)) {	
		addInvoicePayment($invoiceid, $payment_data['id'], $response['layer_order_amount'],0,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
		update_query("tblinvoices",array("notes"=>"Layer"),array("id"=>$invoiceid));
		logTransaction($GATEWAY["name"],$response,$status); # Save to Gateway Log: name, data array, status	
	}
}
$filename = $GATEWAY['systemurl'].'/viewinvoice.php?id=' . $invoiceid;     // path of your viewinvoice.php
HEADER("location:$filename");

?>