<?php
/**
* Team Paylike
*
*  @author    Team Paylike
*  @copyright Team Paylike
*  @license   MIT license: https://opensource.org/licenses/MIT
*/

if (!defined('_PS_VERSION_'))
	exit;

include_once(_PS_MODULE_DIR_.'paylike/api/paylike_API.php');
class Paylike extends PaymentModule
{
	private $_html = '';
	public function __construct()
	{
		$this->name = 'paylike';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0';
		$this->author = 'Team Paylike';
		$this->bootstrap = true;

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		parent::__construct();

		$this->displayName = $this->l('Paylike');
		$this->description = $this->l('Receive payment with Paylike');
	}

	public function install()
	{
		Configuration::updateValue('PAYLIKE_CHECKOUT_MODE', 'instant');
		return (parent::install()
			&& $this->registerHook('orderConfirmation')
			&& $this->registerHook('payment')
			&& $this->registerHook('header')
			&& $this->registerHook('paymentReturn')
			&& $this->registerHook('BackOfficeHeader')
			&& $this->registerHook('displayAdminOrder')
			&& $this->registerHook('actionOrderStatusPostUpdate')
			&& $this->installDb());
	}

	public function installDb()
	{
		return Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'paylike_transactions` (
			`id`				int(11) NOT NULL AUTO_INCREMENT,
			`paylike_tid`		varchar(255) NOT NULL,
			`order_id`			int(11) NOT NULL,
			`payed_at`			datetime NOT NULL,
			`payed_amount`		DECIMAL(20,6) NOT NULL,
			`refunded_amount`	DECIMAL(20,6) NOT NULL,
			PRIMARY KEY			(`id`)
			) ENGINE=MyISAM		DEFAULT CHARSET=latin1 AUTO_INCREMENT=13 ;');
	}

	public function uninstall()
	{
		return (parent::uninstall()
			&& Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'paylike_transactions`')
			&& Configuration::deleteByName('PAYLIKE_PUBLIC_KEY')
			&& Configuration::deleteByName('PAYLIKE_SECRET_KEY')
			&& Configuration::deleteByName('PAYLIKE_CHECKOUT_MODE'));
	}

	public function getContent()
	{
		if (Tools::isSubmit('submitPaylike'))
		{
			if (Tools::getvalue('PAYLIKE_PUBLIC_KEY') && Tools::getvalue('PAYLIKE_SECRET_KEY'))
			{
				Configuration::updateValue('PAYLIKE_PUBLIC_KEY', Tools::getvalue('PAYLIKE_PUBLIC_KEY'));
				Configuration::updateValue('PAYLIKE_SECRET_KEY', Tools::getvalue('PAYLIKE_SECRET_KEY'));
				Configuration::updateValue('PAYLIKE_CHECKOUT_MODE', Tools::getValue('PAYLIKE_CHECKOUT_MODE', 'instant'));
				$this->context->controller->confirmations[] = $this->l('Settings saved successfully');
			}
			else
				$this->context->controller->errors[] = $this->l('Public key and Secret key cannot be empty.');
		}
		$this->_html = $this->renderForm();
		return $this->_html;
	}

	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Paylike Payments Settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Public Key'),
						'name' => 'PAYLIKE_PUBLIC_KEY'
					),
					array(
						'type' => 'text',
						'label' => $this->l('Secret Key'),
						'name' => 'PAYLIKE_SECRET_KEY'
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Capture Mode'),
						'name' => 'PAYLIKE_CHECKOUT_MODE',
						'values' => array(
						array(
							'id' => 'PAYLIKE_CHECKOUT_MODE_on',
							'value' => 'instant',
							'label' => $this->l('Instant Capture'),
						),
						array(
							'id' => 'PAYLIKE_CHECKOUT_MODE_off',
							'value' => 'delayed',
							'label' => $this->l('Delayed Capture')
							)
						),

						'desc' => $this->l('Instant capture: Amount is captured as soon as the order is confirmed by customer.').'<br>'.$this->l('Delayed capture: Amount is captured after order status is changed to shipped.')
					)
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();

		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitPaylike';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array('PAYLIKE_PUBLIC_KEY' => Tools::getValue('PAYLIKE_PUBLIC_KEY', Configuration::get('PAYLIKE_PUBLIC_KEY')),
			'PAYLIKE_SECRET_KEY' => Tools::getValue('PAYLIKE_SECRET_KEY', Configuration::get('PAYLIKE_SECRET_KEY')),
			'PAYLIKE_CHECKOUT_MODE' => Tools::getValue('PAYLIKE_CHECKOUT_MODE', Configuration::get('PAYLIKE_CHECKOUT_MODE')));
	}

	public function hookHeader()
	{
		$this->context->controller->addCss($this->_path.'views/css/paylike.css');
		$this->context->controller->addJs('https://sdk.paylike.io/3.js');
	}

	public function hookBackOfficeHeader()
	{
		/* Continue only if we are on the order's details page (Back-office) */
		if (!Tools::getIsset('vieworder') || !Tools::getIsset('id_order'))
			return;

		/* If the "Refund" button has been clicked, check if we can perform a partial or full refund on this order */
		if (Tools::isSubmit('SubmitPaylikeRefund') && Tools::getIsset('paylike_amount_to_refund'))
		{
			$id_order = (int)Tools::getValue('id_order');
			$paylikeapi = new PaylikeAPI(Configuration::get('PAYLIKE_SECRET_KEY'));
			$payliketransaction = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'paylike_transactions WHERE order_id = '.(int)$id_order);

			if (!Validate::isPrice(Tools::getValue('paylike_amount_to_refund')))
				$this->context->controller->errors[] = Tools::displayError('Invalid amount to refund.');
			else
			{
				if (isset($payliketransaction))
				{
					$fetch = $paylikeapi->transactions->fetch($payliketransaction['paylike_tid']);
					$refunded = ($fetch)? $fetch->transaction->refundedAmount / 100 : 0;
					$captured = ($fetch)? $fetch->transaction->capturedAmount / 100 : 0;
					$refunded = $refunded + Tools::getValue('paylike_amount_to_refund');

					if ($refunded > $captured)
						$this->context->controller->errors[] = Tools::displayError('Refunding amount must be smaller than captured amount.');
					else
					{
						$refundrequest = $paylikeapi->transactions->refund($payliketransaction['paylike_tid'], ['amount' => Tools::getValue('paylike_amount_to_refund') * 100]);
						if ($refundrequest == true)
						{
							$message =
							'Trx ID: '.$payliketransaction['paylike_tid'].'
							Authorized Amount: '.($refundrequest->transaction->amount / 100).'
							Captured Amount: '.($refundrequest->transaction->capturedAmount / 100).'
							Refunded Amount: '.($refundrequest->transaction->refundedAmount / 100).'
							Order time: '.$refundrequest->transaction->created.'
							Currency code: '.$refundrequest->transaction->currency;

							// change status to refunded
							$order = new Order((int)$id_order);
							$order->setCurrentState((int)Configuration::get('PS_OS_REFUND'), $this->context->employee->id);

							$id_cart = $refundrequest->transaction->custom->cartId;
							$msg = new Message();
							$message = strip_tags($message, '<br>');
							if (Validate::isCleanHtml($message))
							{
								if (self::DEBUG_MODE)
									PrestaShopLogger::addLog('PaymentModule::validateOrder - Message is about to be added', 1, null, 'Cart', (int)$id_cart, true);

								$msg->message = $message;
								$msg->id_cart = (int)$id_cart;
								$msg->id_customer = (int)($order->id_customer);
								$msg->id_order = (int)$order->id;
								$msg->private = 1;
								$msg->add();
							}

							$this->context->controller->confirmations[] = $this->l('Refunded successfully').'. '.$this->l('Refunded Amount : ').' '.$refundrequest->transaction->currency.' '.Tools::getValue('paylike_amount_to_refund');
						}
						elseif ($refundrequest == false)
							$this->context->controller->errors[] = Tools::displayError('Refund Request Failed.');
					}
				}
				else
					$this->context->controller->errors[] = Tools::displayError('Invalid paylike transaction.');
			}
		}
	}

	public function hookDisplayAdminOrder($params)
	{
		$id_order = $params['id_order'];
		$order = new Order((int)$id_order);
		if ($order->module == $this->name)
		{
			$order_token = Tools::getAdminToken('AdminOrders'.(int)Tab::getIdFromClassName('AdminOrders').(int)$this->context->employee->id);
			$this->context->smarty->assign(array(
				'ps_version' => _PS_VERSION_,
				'id_order' => $id_order,
				'order_token' => $order_token
			));
			return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
		}
	}

	public function hookActionOrderStatusPostUpdate($params)
	{
		$id_order			= (int)$params['id_order'];
		$cart				= $params['cart'];
		$order				= new Order((int)$id_order);
		$order_state		= new OrderState(Tools::getValue('id_order_state'));
		$order_currency 	= new Currency((int)$cart->id_currency);
		$total				= Tools::ps_round($cart->getOrderTotal(true, Cart::BOTH), 2);

		$total = Tools::convertPriceFull($total, $this->context->currency, $order_currency);
		if ($order->module == $this->name && Configuration::get('PAYLIKE_CHECKOUT_MODE') == 'delayed' && ($order_state->shipped || $order_state->delivery))
		{
			$paylikeapi = new PaylikeAPI(Configuration::get('PAYLIKE_SECRET_KEY'));
			$payliketransaction = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'paylike_transactions WHERE order_id = '.(int)$id_order);

			if (isset($payliketransaction))
			{
				$capture = $paylikeapi->transactions->capture($payliketransaction['paylike_tid'],
					[
					'currency' => $order_currency->iso_code,
					'amount' => $total * 100,
					]);

				if (!$capture || $capture->error)
					$this->context->controller->errors[] = Tools::displayError('Error capturing transaction.');
				else
				{
					$message =
					'Trx ID: '.$payliketransaction['paylike_tid'].'
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
						$msg->id_customer = (int)($order->id_customer);
						$msg->id_order = (int)$order->id;
						$msg->private = 1;
						$msg->add();
					}
					$this->context->controller->confirmations[] = $this->l('Transction captured successfully.');
				}
			}
		}
	}

	public function hookPayment($params)
	{
		//ensure paylike key is set
		if (!Configuration::get('PAYLIKE_PUBLIC_KEY') || !Configuration::get('PAYLIKE_SECRET_KEY'))
			return false;

		$products = $params['cart']->getProducts();
		$customer = new Customer((int)$params['cart']->id_customer);
		$productnames = array();
		$paylikeproductsarray = array();
		$customer_data = array();
		$other_data = array();
		$customer_data[] = array(
			$this->l('First Name') => $customer->firstname,
			$this->l('Last Name') => $customer->lastname,
			$this->l('Email') => $customer->email
			);
		$other_data[] = array(
			$this->l('Shop Name') => $this->context->shop->name,
			$this->l('Prestashop version') => _PS_VERSION_,
			$this->l('Module version') => $this->version,
			);
		foreach ($products as $product)
		{
			$productnames[] = $product['name'];
			$paylikeproductsarray[] = array(
				$this->l('Product Name') => $product['name'],
				$this->l('SKU') => $product['id_product'],
				$this->l('Quantity') => $product['cart_quantity']
			);
		}

		$description = implode(',', $productnames);
		$amount = $params['cart']->getOrderTotal() * 100;//paid amounts with 100 to handle paylike's decimals

		$currency = new Currency((int)$params['cart']->id_currency);
		$redirect_url = $this->context->link->getModuleLink('paylike', 'paymentreturn', [], true, (int)$this->context->language->id);

		if (Configuration::get('PS_REWRITING_SETTINGS') == 1)
			$redirect_url = Tools::strReplaceFirst('&', '?', $redirect_url);

		$this->context->smarty->assign(array(
			'PAYLIKE_PUBLIC_KEY'	=> Configuration::get('PAYLIKE_PUBLIC_KEY'),
			'PS_SSL_ENABLED'		=> (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http'),
			'id_cart'				=> Tools::jsonEncode($params['cart']->id),
			'customer_data'			=> Tools::jsonEncode($customer_data),
			'other_data'			=> Tools::jsonEncode($other_data),
			'paylikeproductsarray'	=> Tools::jsonEncode($paylikeproductsarray),
			'http_host'				=> Tools::getHttpHost(),
			'shop_name'				=> $this->context->shop->name,
			'iso_code'				=> $currency->iso_code,
			'amount'				=> $amount,
			'redirect_url'			=> $redirect_url,
			'qry_str'				=> (Configuration::get('PS_REWRITING_SETTINGS')? '?' : '&'),
			'description'			=> $description,
			'base_uri'				=> __PS_BASE_URI__,
		));
		return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
	}

	public function hookpaymentReturn($params)
	{
		if (!$this->active || !isset($params['objOrder']) || $params['objOrder']->module != $this->name)
			return false;

		if (isset($params['objOrder']) && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid) && isset($params['objOrder']->reference))
		{
			$this->smarty->assign(
				'paylike_order', array(
					'id' => $params['objOrder']->id,
					'reference' => $params['objOrder']->reference,
					'valid' => $params['objOrder']->valid
					)
				);

			return $this->display(__FILE__, 'views/templates/hook/order-confirmation.tpl');
		}
	}

	public function storeTransactionID($paylike_id_transaction, $order_id, $total)
	{
		return Db::getInstance()->Execute('INSERT INTO '._DB_PREFIX_.'paylike_transactions (`paylike_tid`, `order_id`, `payed_amount`, `payed_at`)
			VALUES ("'.pSQL($paylike_id_transaction).'", "'.pSQL($order_id).'", "'.pSQL($total).'" , NOW())');
	}
}
