<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2022 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use const SORT_STRING;
use function get_object_vars, is_array, is_bool, is_float, is_int, is_object, is_string, ksort, strlen;
use s9e\Bencode\Exceptions\EncodingException;
use stdClass;

class Encoder
{
	public static function encode($value): string
	{
		if (is_string($value))
		{
			return strlen($value) . ':' . $value;
		}
		if (is_array($value))
		{
			return self::encodeArray($value);
		}
		if (is_int($value))
		{
			return "i{$value}e";
		}
		if (is_object($value))
		{
			return self::encodeObject($value);
		}

		return self::encode(self::coerceUnsupportedValue($value));
	}

	protected static function arrayIsList(array $array): bool
	{
		$expectedKey = 0;
		foreach ($array as $k => $v)
		{
			if ($k !== $expectedKey)
			{
				return false;
			}
			++$expectedKey;
		}

		return true;
	}

	protected static function coerceBool(bool $value): int
	{
		return (int) $value;
	}

	protected static function coerceFloat(float $value): int
	{
		$int = (int) $value;
		if ((float) $int === $value)
		{
			return $int;
		}

		throw new EncodingException('Unsupported value', $value);
	}

	protected static function coerceUnsupportedValue($value): array|int|string
	{
		if (is_float($value))
		{
			return self::coerceFloat($value);
		}
		if (is_bool($value))
		{
			return self::coerceBool($value);
		}

		throw new EncodingException('Unsupported value', $value);
	}

	/**
	* Encode a PHP array into either a list of a dictionary
	*/
	protected static function encodeArray(array $value): string
	{
		return self::arrayIsList($value)
			? self::encodeIndexedArray($value)
			: self::encodeAssociativeArray($value);
	}

	protected static function encodeAssociativeArray(array $array): string
	{
		ksort($array, SORT_STRING);

		$str = 'd';
		foreach ($array as $k => $v)
		{
			$str .= strlen((string) $k) . ':' . $k . self::encode($v);
		}
		$str .= 'e';

		return $str;
	}

	protected static function encodeIndexedArray(array $array): string
	{
		$str = 'l';
		foreach ($array as $v)
		{
			$str .= self::encode($v);
		}
		$str .= 'e';

		return $str;
	}

	protected static function encodeObject(object $value): string
	{
		if ($value instanceof ArrayObject)
		{
			return self::encodeAssociativeArray($value->getArrayCopy());
		}
		if ($value instanceof stdClass)
		{
			return self::encodeAssociativeArray(get_object_vars($value));
		}

		throw new EncodingException('Unsupported value', $value);
	}
}