<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;

class Bencode
{
	public static function decode(string $bencoded): ArrayObject|array|int|string
	{
		return Decoder::decode($bencoded);
	}

	public static function decodeNonCompliant(string $bencoded): ArrayObject|array|int|string
	{
		return NonCompliantDecoder::decode($bencoded);
	}

	public static function encode(mixed $value): string
	{
		return Encoder::encode($value);
	}
}