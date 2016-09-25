<?php
/**
* Team Paylike
*
*  @author    Team Paylike
*  @copyright Team Paylike
*  @license   MIT license: https://opensource.org/licenses/MIT
*/

class PaylikeSubsystem
{
	private $paylike;
	public function __construct($paylike)
	{
		$this->paylike = $paylike;
	}

	protected function request($verb, $path, $data = null)
	{
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, 'https://api.paylike.io'.$path);
		if ($this->paylike->getKey() !== null)
			curl_setopt($c, CURLOPT_USERPWD, ':'.$this->paylike->getKey());
		if (in_array($verb, ['POST', 'PUT', 'PATCH']))
			curl_setopt($c, CURLOPT_POSTFIELDS, $data);
		if (in_array($verb, ['GET', 'POST']))
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		$raw = curl_exec($c);
		$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
		curl_close($c);
		if ($code < 200 || $code > 299)
			return false;
		if ($code === 204)	// No Content
			return true;
		return Tools::jsonDecode($raw);
	}
}
?>