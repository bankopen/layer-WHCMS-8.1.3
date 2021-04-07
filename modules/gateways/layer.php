<?php

require_once __DIR__.'/lib/layer_api.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function layer_MetaData()
{
    return array(
        'DisplayName' => 'Layer Payment',
        'APIVersion' => '8.1', 
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function layer_config() {
	global $CONFIG;
	 
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Layer Payment"),
	 "Environment" => array("FriendlyName" => "Gateway Mode", "Type" => "dropdown", "Options" => "Test,Live", ),
     "Accesskey" => array("FriendlyName" => "Access Key", "Type" => "text", "Size" => "50", ),
     "Secretkey" => array("FriendlyName" => "Secret Key", "Type" => "text", "Size" => "50",),
    );
	return $configarray;
}

function layer_link($params) {	
	# Make sure that routine executed only once in viewinvoice page
	if(strpos($_SERVER['REQUEST_URI'],'viewinvoice.php') <= 0)
	{
		return true;
	}
	# Gateway Specific Variables	
	$accesskey = $params['Accesskey'];
	$secretkey = $params['Secretkey'];
	$environment = $params['Environment'];
	
	$remotescript = "https://sandbox-payments.open.money/layer/js";
	if($environment == 'Live') 
		$remotescript = "https://payments.open.money/layer/js";
	
	$surl = $params['systemurl'] . '/modules/gateways/callback/layer_response.php';
		
	$data = [
                'amount' => $params['amount'],
                'currency' => $params['currency'],
                'name'  => $params['clientdetails']['firstname'].' '.$params['clientdetails']['lastname'],
                'email_id' => $params['clientdetails']['email'],
                'contact_number' => $params['clientdetails']['phonenumber'],
				'mtx' => $params['invoiceid']
            ];	
	$layer_api = new LayerApi(strtolower($environment),$accesskey,$secretkey);
	$layer_payment_token_data = $layer_api->create_payment_token($data);
	
	$error="";
	if(empty($error) && isset($layer_payment_token_data['error'])){
		$error = 'E55 Payment error. ' . ucfirst($layer_payment_token_data['error']);  
		if(isset($layer_payment_token_data['error_data']))
		{
			foreach($layer_payment_token_data['error_data'] as $d)
				$error .= " ".ucfirst($d[0]);
		}
	}

	if(empty($error) && (!isset($layer_payment_token_data["id"]) || empty($layer_payment_token_data["id"])))				
		$error = 'Payment error. ' . 'Layer token ID cannot be empty.';        
	
	if(!empty($layer_payment_token_data["id"]))
		$payment_token_data = $layer_api->get_payment_token($layer_payment_token_data["id"]);	

	if(empty($error) && !empty($payment_token_data)) {
				
		if(isset($layer_payment_token_data['error']))
			$error = 'E56 Payment error. ' . $payment_token_data['error'];            
    
		if(empty($error) && $payment_token_data['status'] == "paid")
			$error = "Layer: this order has already been paid.";            
    
		if(empty($error) && round($payment_token_data['amount'],2) != round($params['amount'],2))
			$error = "Layer: an amount mismatch occurred.";
    }

	if(!empty($error)) 
		return $error;
	else {
		$hash = $layer_api->create_hash(array(
			'layer_pay_token_id'    => $payment_token_data['id'],
			'layer_order_amount'    => $payment_token_data['amount'],
			'tranid'    => $params['invoiceid']
			),$accesskey,$secretkey);
		$jsdata['payment_token_id'] = html_entity_decode((string) $payment_token_data['id'],ENT_QUOTES,'UTF-8');
		$jsdata['accesskey']  = html_entity_decode((string) $accesskey,ENT_QUOTES,'UTF-8');	
		
		$html =  "<form action='$surl' method='post' style='display: none' name='layer_payment_int_form'>
		<input type='hidden' name='layer_pay_token_id' value='".$payment_token_data['id']."'>
        <input type='hidden' name='tranid' value='".$params['invoiceid']."'>
        <input type='hidden' name='layer_order_amount' value='".$payment_token_data['amount']."'>
        <input type='hidden' id='layer_payment_id' name='layer_payment_id' value=''>
        <input type='hidden' id='fallback_url' name='fallback_url' value=''>
        <input type='hidden' name='hash' value='".$hash."'>
        </form>";
		
		$html .= "<script src=\"https://code.jquery.com/jquery-3.5.1.min.js\"></script>	
			<script type=\"text/javascript\"> 
				$(document).ready(function() {					
				var s = document.createElement('script');
				s.id = 'open_money_layer';
				s.src = '$remotescript';
				$('head').append(s);
			});
			</script>";	
		
		$html .= "<script  type='text/javascript'>";
		$html .= "var layer_params = " . json_encode( $jsdata ) . ';'; 
		$html .= "function triggerLayer() {";
		$html .= "Layer.checkout(";
		$html .= "{";
        $html .= "token: layer_params.payment_token_id,";
        $html .= "accesskey: layer_params.accesskey";
        $html .= "},";
        $html .= "function (response) {";
        $html .= "console.log(response);";
        $html .= "if(response !== null || response.length > 0 ){";

                $html .= "if(response.payment_id !== undefined){";

                    $html .= "document.getElementById('layer_payment_id').value = response.payment_id;";

                $html .= "}";

            $html .= "}";

            $html .= "document.layer_payment_int_form.submit();";
        $html .= "},";
        $html .= "function (err) {";
            $html .= "alert(err.message);";
        $html .= "}";
    $html .= ");";
	$html .= "}";

	$html .= "var checkExist = setInterval(function() {";
		$html .= "if (typeof Layer !== 'undefined') {";
		$html .= "console.log('Layer Loaded...');";
		$html .= "clearInterval(checkExist);";
		$html .= "triggerLayer();";
	$html .= "}";
	$html .= "else {";
		$html .= "console.log('Layer undefined...');";
	$html .= "}";
		$html .= "}, 1000);";
    	$html .="</script>";
		//$html .= '<script src="'.$params['systemurl'] . __DIR__.'/lib/layer_checkout.js'.'"></script>';				
		
		return $html;
	}
}
?>
