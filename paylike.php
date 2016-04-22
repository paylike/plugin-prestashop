<?php

class paylike extends PaymentModule {

	private $_html = '';
	private $_postErrors = array();

	public function __construct(){
		$this->name = 'paylike';
		$this->tab = 'payments_gateways';
		$this->version = '1.0';
		$this->author = 'Team Paylike';
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = 'Paylike';
		$this->description = $this->l('Receive payment with Paylike');
	}

	public function install(){

		return (parent::install() && $this->registerHook('orderConfirmation') && $this->registerHook('payment') && $this->registerHook('header') && $this->registerHook('paymentReturn')  && $this->registerHook('BackOfficeHeader') && $this->installDb());
	}

	/**
 * Paylikes's module database tables installation
 *
 * @return boolean Database tables installation result
 */
public function installDb()
{
	return Db::getInstance()->Execute('
	CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'paylike_transactions` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `paylike_tid` varchar(255) NOT NULL,
				  `order_id` int(11) NOT NULL,
				  `payed_at` datetime NOT NULL,
				  `payed_amount` int(11) NOT NULL,
				  `refunded_amount` int(11) NOT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=13 ;'
			) ;
}

	public function uninstall(){
		return parent::uninstall() && Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'paylike_transactions`')
			&&  Configuration::deleteByName('PAYLIKE_API_KEY') && Configuration::deleteByName('PAYLIKE_APP_KEY') ;
	}

	public function getContent()
	{

		if (Tools::isSubmit('submitPaylike'))
		{
			Configuration::updateValue('PAYLIKE_API_KEY', Tools::getvalue('PAYLIKE_API_KEY'));
			Configuration::updateValue('PAYLIKE_APP_KEY', Tools::getvalue('PAYLIKE_APP_KEY'));

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
						'label' => $this->l('Public API Key'),
						'name' => 'PAYLIKE_API_KEY'
					),
					array(
						'type' => 'text',
						'label' => $this->l('APP Key'),
						'name' => 'PAYLIKE_APP_KEY'
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
	return array(
		'PAYLIKE_API_KEY' => Tools::getValue('PAYLIKE_API_KEY', Configuration::get('PAYLIKE_API_KEY')),
		'PAYLIKE_APP_KEY' => Tools::getValue('PAYLIKE_APP_KEY', Configuration::get('PAYLIKE_APP_KEY'))
	);
}


public function hookBackOfficeHeader()
{

	/* Continue only if we are on the order's details page (Back-office) */
	if (!Tools::getIsset('vieworder') || !Tools::getIsset('id_order'))
		return;

	/* If the "Refund" button has been clicked, check if we can perform a partial or full refund on this order */
	if (Tools::isSubmit('SubmitPaylikeRefund') && Tools::getIsset('paylike_amount_to_refund'))
	{
		$appKey = Configuration::get("PAYLIKE_APP_KEY");
		$paylikeapi = new PaylikeAPI(Configuration::get("PAYLIKE_APP_KEY"));

		$query = 'SELECT * FROM '._DB_PREFIX_.'paylike_transactions WHERE order_id = '.(int)Tools::getValue('id_order');
		$payliketransaction = Db::getInstance()->getRow($query);


		$refundrequest = $paylikeapi->transactions->refund($payliketransaction['paylike_tid'],['amount' => Tools::getValue('paylike_amount_to_refund') * 100]);
		if($refundrequest == TRUE)
			$msg = '<div class=\"alert alert-success\">Refunded successfully. Refunded '.(Tools::getValue('paylike_amount_to_refund')).'</div>';
		elseif($refundrequest == FALSE)
			$msg = '<div class=\"alert alert-danger\">An error occured</div>';
		else
			$msg = '<div class=\"alert alert-danger\">'.$refundrequest.'</div>';


			$output = '
		<script type="text/javascript">
			$(document).ready(function() {
				var appendEl;
				appendEl =  $(\'select[name=id_order_state]\').parents(\'form\').after($(\'<div/>\'));
				$("'.$msg.'").appendTo(appendEl)
			});
			</script>';

		return $output;
}

	/* Check if the order was paid with Paylike and display the transaction details */
	if (Db::getInstance()->getValue('SELECT module FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int)Tools::getValue('id_order')) == $this->name)
	{

		$order = new Order((int)Tools::getValue('id_order'));

		$currency = $this->context->currency;
		$c_char = $currency->sign;
		$output = '
		<script type="text/javascript">
			$(document).ready(function() {
				var appendEl;
				if ($(\'select[name=id_order_state]\').is(":visible")) {
					appendEl = $(\'select[name=id_order_state]\').parents(\'form\').after($(\'<div/>\'));
				} else {
					appendEl = $("#status");
				}
				$(\'<form method="post" ><fieldset'.(_PS_VERSION_ < 1.5 ? ' style="width: 400px;"' : '').'><legend><img src="../img/admin/money.gif" alt="" />'.$this->l('Paylike Payment Refund').'</legend>';

			$output .= '<input name="paylike_amount_to_refund" placeholder="Amount to refund" type="text"/><input name="SubmitPaylikeRefund" type="submit" class="btn btn-primary" value="'.$this->l('Process Refund').'"/></fieldset></form><br />\').appendTo(appendEl);
			});
		</script>';

		return $output;
	}
}

	/**
	* hookPayment($params)
	* Called in Front Office at Payment Screen - displays user this module as payment option
	*/
public function hookPayment($params){

		//ensure paylike key is set
		if (!Configuration::get('PAYLIKE_API_KEY') || !Configuration::get('PAYLIKE_APP_KEY'))
			return false;

		global $smarty,$cookie;

		$products = $params['cart']->getProducts();

		$productnames = array();
		$paylikeproductsarray = '';

		foreach ($products as $product) {
			$productnames[] = $product['name'];
			$paylikeproductsarray .= '{SKU:"'.$product["id_product"].'", quantity:'.$product["cart_quantity"].'},';
		}

		$description = implode(',',$productnames);


		$amount = $params["cart"]->getOrderTotal() * 100;//pad amounts with 100 to handle paylike's decimals


		$currency = Db::getInstance()->getValue('
			SELECT `iso_code`
			FROM `'._DB_PREFIX_.'currency`
			WHERE `id_currency` = '.$params["cart"]->id_currency);


	return '
	<script src="https://sdk.paylike.io/2.js"></script>
	<script>

			var paylike = Paylike("'.Configuration::get("PAYLIKE_API_KEY").'");

			function pay(){
				paylike.popup({
					title: "'.$this->context->shop->name.'",
					description: "'.$description.'",
					currency: "'.$currency.'",
					amount: '.$amount.',
					descriptor: "Payment to '.$this->context->shop->name.'",

						custom: {
							products: [
								'.$paylikeproductsarray.'
							],
						},

				}, function( err , r){
					if (err)
					{
						return console.warn(err);
					}

					location.href = "'.(Configuration::get("PS_SSL_ENABLED") ? "https" : "http").'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/paylike/paymentReturn.php?transactionid="+r.transaction.id;
				});
			}
		</script>'.$this->display(__FILE__, 'payment.tpl');
	}

/**
 * Display a confirmation message after an order has been placed
 *
 * @param array Hook parameters
 */
public function hookPaymentReturn($params)
{
	return $this->display(__FILE__, 'order-confirmation.tpl');
}

public function storeTransactionID($payliketransactionid,$order_id,$total)
{
	return Db::getInstance()->Execute('
			INSERT INTO '._DB_PREFIX_.'paylike_transactions (id, paylike_tid, order_id, payed_amount, payed_at)
			VALUES ("", "'.$payliketransactionid.'", "'.$order_id.'", "'.$total.'" , NOW())');
}


}

//Paylike API
class PaylikeAPI {
	private $key;
	// subsystems
	private $transactions;
	public function __construct( $key ){
		$this->key = $key;
	}
	public function setKey( $key ){
		$this->key = $key;
	}
	public function getKey(){
		return $this->key;
	}
	public function __get( $name ){
		switch ($name) {
			case 'transactions':
				if (!$this->transactions)
					$this->transactions = new PaylikeTransactions($this);
				return $this->transactions;
			default:
				throw new BadPropertyException($this, $name);
		}
    }
}

class PaylikeTransactions extends PaylikeSubsystem {
	public function fetch( $transactionId ){
		return $this->request('GET', '/transactions/'.$transactionId);
	}
	public function capture( $transactionId, $opts ){
		return $this->request('POST', '/transactions/'.$transactionId.'/captures', $opts);
	}
	public function refund( $transactionId, $opts ){
		return $this->request('POST', '/transactions/'.$transactionId.'/refunds', $opts);
	}
}

class PaylikeSubsystem {
	private $paylike;
	public function __construct( $paylike ){
		$this->paylike = $paylike;
	}
	protected function request( $verb, $path, $data = null ){
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, 'https://api.paylike.io'.$path);
		if ($this->paylike->getKey() !== null)
			curl_setopt($c, CURLOPT_USERPWD, ':'.$this->paylike->getKey());
		if (in_array($verb, [ 'POST', 'PUT', 'PATCH' ]))
			curl_setopt($c, CURLOPT_POSTFIELDS, $data);
		if (in_array($verb, [ 'GET', 'POST' ]))
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		$raw = curl_exec($c);
		$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
		curl_close($c);
		if ($code < 200 || $code > 299)
			return false;
		if ($code === 204)	// No Content
			return true;
		return json_decode($raw);
	}
}
