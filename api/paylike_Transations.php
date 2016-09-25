<?php
/**
* Team Paylike
*
*  @author    Team Paylike
*  @copyright Team Paylike
*  @license   MIT license: https://opensource.org/licenses/MIT
*/

include_once(dirname(__FILE__).'/paylike_Subsystem.php');
class PaylikeTransactions extends PaylikeSubsystem
{
	public function fetch($id_transaction)
	{
		return $this->request('GET', '/transactions/'.$id_transaction);
	}

	public function capture($id_transaction, $opts)
	{
		return $this->request('POST', '/transactions/'.$id_transaction.'/captures', $opts);
	}

	public function refund($id_transaction, $opts)
	{
		return $this->request('POST', '/transactions/'.$id_transaction.'/refunds', $opts);
	}
}
?>