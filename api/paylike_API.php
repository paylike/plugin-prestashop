<?php
/**
* Team Paylike
*
*  @author    Team Paylike
*  @copyright Team Paylike
*  @license   MIT license: https://opensource.org/licenses/MIT
*/

include_once(dirname(__FILE__).'/paylike_Transations.php');
class PaylikeAPI
{
	private $key;
	private $transactions;
	public function __construct($key)
	{
		$this->key = $key;
	}

	public function setKey($key)
	{
		$this->key = $key;
	}

	public function getKey()
	{
		return $this->key;
	}

	public function __get($name)
	{
		switch ($name)
		{
			case 'transactions':
				if (!$this->transactions)
					$this->transactions = new PaylikeTransactions($this);
				return $this->transactions;
			default:
				throw new PrestaShopException(sprintf('Property \'%s\' doesn\'t exist on \'%s\'', $name, get_class($this)));
				// throw new BadPropertyException($this, $name);
		}
	}
}
