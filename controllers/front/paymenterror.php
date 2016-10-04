<?php
/**
* Team Paylike
*
*  @author    Team Paylike
*  @copyright Team Paylike
*  @license   MIT license: https://opensource.org/licenses/MIT
*/

class PaylikePaymenterrorModuleFrontController extends ModuleFrontController
{
	public function __construct()
	{
		parent::__construct();
		$this->display_column_right = false;
		$this->display_column_left = false;
		$this->context = Context::getContext();
	}

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();
		$cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		if (Tools::getIsset('transactionid') && Tools::getIsset('paylike_redirect'))
		{
			$transactionid = Tools::getValue('transactionid');

			// void transaction and display error message
			$amount = Tools::getValue('amount');
			$paylikeapi = new PaylikeAPI(Configuration::get('PAYLIKE_SECRET_KEY'));
			$void = $paylikeapi->transactions->voids($transactionid, ['amount' => $amount]);

			$this->context->smarty->assign('paylike_order_error', 1);
			return $this->setTemplate('payment_error.tpl');
		}
		else
			Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id);
	}
}
