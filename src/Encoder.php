<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2020 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use InvalidArgumentException;
use stdClass;

class Encoder
{
	public static function encode($value): string
	{
		if (is_scalar($value))
		{
			return self::encodeScalar($value);
		}
		if (is_array($value))
		{
			return self::encodeArray($value);
		}
		if ($value instanceof stdClass)
		{
			$value = new ArrayObject(get_object_vars($value));
		}
		if ($value instanceof ArrayObject)
		{
			return self::encodeArrayObject($value);
		}

		throw new InvalidArgumentException('Unsupported value');
	}

	/**
	* Encode an array into either an array of a dictionary
	*/
	protected static function encodeArray(array $value): string
	{
		if (empty($value))
		{
			return 'le';
		}

		if (array_keys($value) === range(0, count($value) - 1))
		{
			return 'l' . implode('', array_map([__CLASS__, 'encode'], $value)) . 'e';
		}

		// Encode associative arrays as dictionaries
		return self::encodeArrayObject(new ArrayObject($value));
	}

	/**
	* Encode given ArrayObject instance into a dictionary
	*/
	protected static function encodeArrayObject(ArrayObject $dict): string
	{
		$vars = $dict->getArrayCopy();
		ksort($vars);

		$str = 'd';
		foreach ($vars as $k => $v)
		{
			$str .= strlen($k) . ':' . $k . self::encode($v);
		}
		$str .= 'e';

		return $str;
	}

	/**
	* Encode a scalar value
	*/
	protected static function encodeScalar($value): string
	{
		if (is_int($value) || is_float($value))
		{
			return sprintf('i%de', round($value));
		}
		if (is_bool($value))
		{
			return ($value) ? 'i1e' : 'i0e';
		}

		return strlen($value) . ':' . $value;
	}
}