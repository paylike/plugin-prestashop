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
		$this->display_column_right = false;
		$this->display_column_left = false;
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
		$transactionid = Tools::getValue('transactionid');
		$params = array(
			'paylike_redirect' => 1,
			'transactionid' => $transactionid,
			'amount' => $amount
		);

		$transaction_failed = false;
		if (Configuration::get('PAYLIKE_CHECKOUT_MODE') == 'delayed')
		{
			$fetch = $paylikeapi->transactions->fetch($transactionid);
			if ($fetch && $fetch->transaction->currency == $currency->iso_code && $fetch->transaction->custom->cartId == $cart->id && $fetch->transaction->amount == $amount)
			{
				$message =
				'Trx ID: '.$transactionid.'
				Authorized Amount: '.($fetch->transaction->amount / 100).'
				Captured Amount: '.($fetch->transaction->capturedAmount / 100).'
				Order time: '.$fetch->transaction->created.'
				Currency code: '.$fetch->transaction->currency;

				if ($paylike->validateOrder((int)$cart->id, $status_paid, $total, $paylike->displayName, $message, array(), null, false, $customer->secure_key))
				{
					$paylike->storeTransactionID($transactionid, $paylike->currentOrder, $total);
					Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$paylike->id.'&id_order='.$paylike->currentOrder.'&key='.$customer->secure_key);
				}
			}
			else
			{
				$transaction_failed = true;
				$paylikeapi->transactions->cancel($transactionid, ['amount' => $amount]);
			}
		}
		else
		{
			if ($paylike->validateOrder((int)$cart->id, $status_paid, $total, $paylike->displayName, null, array(), null, false, $customer->secure_key))
			{
				$capture = $paylikeapi->transactions->capture($transactionid,
				[
				'currency' => $currency->iso_code,
				'amount' => $amount,
				]);

				if ($capture)
				{
					$message =
					'Trx ID: '.$transactionid.'
					Authorized Amount: '.($capture->transaction->amount / 100).'
					Captured Amount: '.($capture->transaction->capturedAmount / 100).'
					Order time: '.$capture->transaction->created.'
					Currency code: '.$capture->transaction->currency;

					$msg = new Message();
					$message = strip_tags($message, '<br>');
					if (Validate::isCleanHtml($message))
					{
						if (self::DEBUG_MODE)
							PrestaShopLogger::addLog('PaymentModule::validateOrder - Message is about to be added', 1, null, 'Cart', (int)$cart->id, true);

						$msg->message = $message;
						$msg->id_cart = (int)$cart->id;
						$msg->id_customer = (int)$cart->id_customer;
						$msg->id_order = (int)$paylike->currentOrder;
						$msg->private = 1;
						$msg->add();
					}

					$paylike->storeTransactionID($transactionid, $paylike->currentOrder, $total);
						Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$paylike->id.'&id_order='.$paylike->currentOrder.'&key='.$customer->secure_key);
				}
				else
				{
					$transaction_failed = true;
					$paylikeapi->transactions->cancel($transactionid, ['amount' => $amount]);
				}
			}
			else
				$transaction_failed = true;
		}

		if ($transaction_failed)
		{
			$this->context->smarty->assign('paylike_order_error', 1);
			return $this->setTemplate('payment_error.tpl');
		}
	}
}
