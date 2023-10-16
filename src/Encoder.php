<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use const SORT_STRING;
use function array_is_list, get_object_vars, gettype, ksort, strlen;
use s9e\Bencode\Exceptions\EncodingException;
use stdClass;

class Encoder
{
	public static function encode(mixed $value): string
	{
		return match (gettype($value))
		{
			'array'   => static::encodeArray($value),
			'integer' => "i{$value}e",
			'object'  => static::encodeObject($value),
			'string'  => strlen($value) . ':' . $value,
			default   => static::encode(static::coerceUnsupportedValue($value))
		};
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

	protected static function coerceUnsupportedValue(mixed $value): array|int|string
	{
		return match (gettype($value))
		{
			'boolean' => static::coerceBool($value),
			'double'  => static::coerceFloat($value),
			default   => throw new EncodingException('Unsupported value', $value)
		};
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
		if ($value instanceof BencodeSerializable)
		{
			return static::encode($value->bencodeSerialize());
		}
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