<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

interface BencodeSerializable
{
	/**
	* Serialize this object that can be encoded with Bencode::encode()
	*
	* @return array|int|string
	*/
	public function bencodeSerialize(): array|int|string;
}