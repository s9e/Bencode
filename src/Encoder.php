<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2023 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use const SORT_STRING;
use function array_is_list, get_object_vars, is_array, is_bool, is_float, is_int, is_object, is_string, ksort, strlen;
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
			return static::encodeArray($value);
		}
		if (is_int($value))
		{
			return "i{$value}e";
		}
		if (is_object($value))
		{
			return static::encodeObject($value);
		}

		return static::encode(static::coerceUnsupportedValue($value));
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
			return static::coerceFloat($value);
		}
		if (is_bool($value))
		{
			return static::coerceBool($value);
		}

		throw new EncodingException('Unsupported value', $value);
	}

	/**
	* Encode a PHP array into either a list of a dictionary
	*/
	protected static function encodeArray(array $value): string
	{
		return array_is_list($value)
			? static::encodeIndexedArray($value)
			: static::encodeAssociativeArray($value);
	}

	protected static function encodeAssociativeArray(array $array): string
	{
		ksort($array, SORT_STRING);

		$str = 'd';
		foreach ($array as $k => $v)
		{
			$str .= strlen((string) $k) . ':' . $k . static::encode($v);
		}
		$str .= 'e';

		return $str;
	}

	protected static function encodeIndexedArray(array $array): string
	{
		$str = 'l';
		foreach ($array as $v)
		{
			$str .= static::encode($v);
		}
		$str .= 'e';

		return $str;
	}

	protected static function encodeObject(object $value): string
	{
		if ($value instanceof ArrayObject)
		{
			return static::encodeAssociativeArray($value->getArrayCopy());
		}
		if ($value instanceof stdClass)
		{
			return static::encodeAssociativeArray(get_object_vars($value));
		}

		throw new EncodingException('Unsupported value', $value);
	}
}