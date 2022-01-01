<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2022 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode\Exceptions;

use InvalidArgumentException;

class EncodingException extends InvalidArgumentException
{
	/**
	* @var mixed Value that caused this exception to be thrown
	*/
	protected $value;

	public function __construct(string $message, $value)
	{
		$this->value = $value;
		parent::__construct($message);
	}

	/**
	* Return the value that caused this exception to be thrown
	*
	* @return mixed
	*/
	public function getValue()
	{
		return $this->value;
	}
}