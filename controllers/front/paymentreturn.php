<?php
/**
* Team Paylike
*
*  @author    Team Paylike
*  @copyright Team Paylike
*  @license   MIT license: https://opensource.org/licenses/MIT
*/

class PaylikePaymentReturnModuleFrontController extends ModuleFrontController
{
	public function __construct()
	{
		parent::__construct();
		$this->context = Context::getContext();
	}

	public function init()
	{
		parent::init();
		$cart = $this->context->cart;
		$paylike = new Paylike();
		$total = $cart->getOrderTotal(true, Cart::BOTH);
		$customer = new Customer((int)$cart->id_customer);
		$currency = new Currency((int)$cart->id_currency);
		$paylikeapi = new PaylikeAPI(Configuration::get('PAYLIKE_APP_KEY'));

		if (Configuration::get('PAYLIKE_CHECKOUT_MODE') == 'delayed')
		{
			$capture = $paylikeapi->transactions->fetch(Tools::getValue('transactionid'),
			[
			'currency' => $currency->iso_code,
			'amount' => Tools::ps_round($total, 2) * 100,
			]);
			
		}
		else
		{
			$capture = $paylikeapi->transactions->capture(Tools::getValue('transactionid'),
			[
			'currency' => $currency->iso_code,
			'amount' => Tools::ps_round($total, 2) * 100,
			]);
		}

		if ($capture)
		{
			$status = Configuration::get('PS_OS_PAYMENT');
			if ($paylike->validateOrder((int)$cart->id, $status, $total, $paylike->displayName, null, array(), null, false, $customer->secure_key))
			{
				$paylike->storeTransactionID(Tools::getValue('transactionid'), $paylike->currentOrder, $total);
				Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$paylike->id.'&id_order='.$paylike->currentOrder.'&key='.$customer->secure_key);
			}
		}
		Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$paylike->id.'&id_order='.$paylike->currentOrder.'&key='.$customer->secure_key);
	}
}