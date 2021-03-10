<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2021 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

class Bencode
{
	public static function decode(string $bencoded)
	{
		return Decoder::decode($bencoded);
	}

	public static function encode($value): string
	{
		return Encoder::encode($value);
	}
}