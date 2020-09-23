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
		$callback = get_called_class() . '::encode' . ucfirst(gettype($value));
		if (!is_callable($callback))
		{
			throw new InvalidArgumentException('Unsupported value');
		}

		return $callback($value);
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
			return 'l' . implode('', array_map(get_called_class() . '::encode', $value)) . 'e';
		}

		// Encode associative arrays as dictionaries
		return static::encodeArrayObject(new ArrayObject($value));
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
			$str .= strlen($k) . ':' . $k . static::encode($v);
		}
		$str .= 'e';

		return $str;
	}

	protected static function encodeObject(object $value): string
	{
		if ($value instanceof stdClass)
		{
			$value = new ArrayObject(get_object_vars($value));
		}

		if ($value instanceof ArrayObject)
		{
			return static::encodeArrayObject($value);
		}

		throw new InvalidArgumentException('Unsupported value');
	}

	protected static function encodeBoolean(bool $value): string
	{
		return static::encodeInteger((int) $value);
	}

	protected static function encodeDouble(float $value): string
	{
		return static::encodeInteger((int) $value);
	}

	protected static function encodeInteger(int $value): string
	{
		return sprintf('i%de', round($value));
	}

	protected static function encodeString(string $value): string
	{
		return strlen($value) . ':' . $value;
	}
}