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
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'paylike')
			{
				$authorized = true;
				break;
			}

		if (!$authorized)
			die($this->module->l('Paylike payment method is not available.', 'paymentreturn'));

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirect('index.php?controller=order&step=1');

		$paylike = new Paylike();
		$total = $cart->getOrderTotal(true, Cart::BOTH);
		$currency = new Currency((int)$cart->id_currency);
		$paylikeapi = new PaylikeAPI(Configuration::get('PAYLIKE_SECRET_KEY'));
		$amount = Tools::ps_round($total, 2) * 100;
		$status_paid = Configuration::get('PS_OS_PAYMENT');
		$status_error = Configuration::get('PS_OS_ERROR');
		$params = array(
			'paylike_redirect' => 1,
			'transactionid' => Tools::getValue('transactionid'),
			'amount' => $amount
		);

		if (Configuration::get('PAYLIKE_CHECKOUT_MODE') == 'delayed')
		{
			// $fetch = $paylikeapi->transactions->fetch(Tools::getValue('transactionid'));
	$fetch = $paylikeapi->transactions->fetch('12312asda42342fsdfsd34534');

			if ($fetch->transaction->currency == $currency->iso_code && $fetch->transaction->custom->cartId == $cart->id && $fetch->transaction->amount == $amount)
			{
				if ($paylike->validateOrder((int)$cart->id, $status_paid, $total, $paylike->displayName, null, array(), null, false, $customer->secure_key))
				{
					$paylike->storeTransactionID(Tools::getValue('transactionid'), $paylike->currentOrder, $total);
					Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$paylike->id.'&id_order='.$paylike->currentOrder.'&key='.$customer->secure_key);
				}
			}
		}
		else
		{
			$capture = $paylikeapi->transactions->capture(Tools::getValue('transactionid'),
				[
				'currency' => $currency->iso_code,
				'amount' => $amount,
				]);

			if ($capture)
			{
				if ($paylike->validateOrder((int)$cart->id, $status_paid, $total, $paylike->displayName, null, array(), null, false, $customer->secure_key))
				{
					$paylike->storeTransactionID(Tools::getValue('transactionid'), $paylike->currentOrder, $total);
					Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$paylike->id.'&id_order='.$paylike->currentOrder.'&key='.$customer->secure_key);
				}
			}
		}
		Tools::redirectLink($this->context->link->getModuleLink('paylike', 'paymenterror', $params, true));
	}
}
