<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2020 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use s9e\Bencode\Exceptions\EncodingException;
use stdClass;

class Encoder
{
	public static function encode($value): string
	{
		$callback = get_called_class() . '::encode' . ucfirst(gettype($value));
		if (is_callable($callback))
		{
			return $callback($value);
		}

		throw new EncodingException('Unsupported value', $value);
	}

	/**
	* Encode a PHP array into either an array of a dictionary
	*/
	protected static function encodeArray(array $value): string
	{
		if (empty($value))
		{
			return 'le';
		}

		if (array_keys($value) === range(0, count($value) - 1))
		{
			return 'l' . implode('', array_map(get_called_class() . '::encode', $value)) . 'e';
		}

		// Encode associative arrays as dictionaries
		return static::encodeInstanceOfArrayObject(new ArrayObject($value));
	}

	protected static function encodeAssociativeArray(array $array): string
	{
		ksort($array);

		$str = 'd';
		foreach ($array as $k => $v)
		{
			$str .= strlen($k) . ':' . $k . static::encode($v);
		}
		$str .= 'e';

		return $str;
	}

	protected static function encodeBoolean(bool $value): string
	{
		return static::encodeInteger((int) $value);
	}

	protected static function encodeDouble(float $value): string
	{
		$int = (int) $value;
		if ((float) $int !== $value)
		{
			throw new EncodingException('Unsupported value', $value);
		}

		return static::encodeInteger($int);
	}

	protected static function encodeInstanceOfArrayObject(ArrayObject $dict): string
	{
		return static::encodeAssociativeArray($dict->getArrayCopy());
	}

	protected static function encodeInstanceOfStdClass(stdClass $value): string
	{
		return static::encodeAssociativeArray(get_object_vars($value));
	}

	protected static function encodeInteger(int $value): string
	{
		return sprintf('i%de', round($value));
	}

	protected static function encodeObject(object $value): string
	{
		if ($value instanceof ArrayObject)
		{
			return static::encodeInstanceOfArrayObject($value);
		}
		if ($value instanceof stdClass)
		{
			return static::encodeInstanceOfStdClass($value);
		}

		throw new EncodingException('Unsupported value', $value);
	}

	protected static function encodeString(string $value): string
	{
		return strlen($value) . ':' . $value;
	}
}