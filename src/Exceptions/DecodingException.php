<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2022 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode\Exceptions;

use RuntimeException;

class DecodingException extends RuntimeException
{
	protected int $offset;

	public function __construct(string $message, int $offset)
	{
		$this->offset = $offset;
		parent::__construct($message . ' at offset ' . $offset);
	}

	public function getOffset(): int
	{
		return $this->offset;
	}
}