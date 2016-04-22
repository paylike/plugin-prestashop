<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include_once(dirname(__FILE__).'/paylike.php');


$paylike = new paylike();

$total = $cart->getOrderTotal(true, Cart::BOTH);
$customer = new Customer((int)$cart->id_customer);

$paylikeapi = new PaylikeAPI(Configuration::get("PAYLIKE_APP_KEY"));


$capture = $paylikeapi->transactions->capture($_GET['transactionid'], [
	'currency' => Db::getInstance()->getValue('
		SELECT `iso_code`
		FROM `'._DB_PREFIX_.'currency`
		WHERE `id_currency` = '.$cart->id_currency),
	'amount' => $total * 100,
]);

if ($capture) {
	$status = Configuration::get('PS_OS_PAYMENT');

	$paylike->validateOrder((int)$cart->id, $status, $total, $paylike->displayName, NULL, array(), NULL, false, $customer->secure_key);
}


$paylike->storeTransactionID($_GET['transactionid'], $paylike->currentOrder, $total);

Tools::redirectLink(__PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cart->id .'&id_module='. $paylike->id .'&id_order=' . $paylike->currentOrder . '&key=' . $customer->secure_key);
