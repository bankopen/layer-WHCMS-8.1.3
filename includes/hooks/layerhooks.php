<?php

add_hook('ClientAreaHeadOutput', 1, function ($vars)
{
	$moduleoptions = select_query('tblpaymentgateways', 'setting,value', array('gateway' => 'layer'));
	$params = array();
	while($m = mysql_fetch_assoc($moduleoptions)){
		$params[$m['key']] = $m['value'];
	}

    $head_return = '<script type="application/javascript" id="open_money_layer" src="https://sandbox-payments.open.money/layer/js"></script>';
    if($params['Environment'] == 'Live')
    	$head_return = '<script type="application/javascript" id="open_money_layer" src="https://payments.open.money/layer/js"></script>';
    
    return $head_return;
});

add_hook('ViewInvoiceDetailsPage', 1, function ($vars)
{
	$moduleoptions = select_query('tblpaymentgateways', 'setting,value', array('gateway' => 'payum'));
	$params = array();
	while($m = mysql_fetch_assoc($moduleoptions)){
		$params[$m['key']] = $m['value'];
	}

    $head_return = '<script type="application/javascript" id="open_money_layer" src="https://sandbox-payments.open.money/layer/js"></script>';
    if($params['Environment'] == 'Live')
    	$head_return = '<script type="application/javascript" id="open_money_layer" src="https://payments.open.money/layer/js"></script>';
    
    return $head_return;
});

add_hook('InvoiceChangeGateway', 1, function ($vars)
{
	$moduleoptions = select_query('tblpaymentgateways', 'setting,value', array('gateway' => 'payum'));
	$params = array();
	while($m = mysql_fetch_assoc($moduleoptions)){
		$params[$m['key']] = $m['value'];
	}
	$head_return = '<script type="application/javascript" id="open_money_layer" src="https://sandbox-payments.open.money/layer/js"></script>';
    if($params['Environment'] == 'Live')
    	$head_return = '<script type="application/javascript" id="open_money_layer" src="https://payments.open.money/layer/js"></script>';
    
    return $head_return;
});

?>