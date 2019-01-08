<?php

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2019 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

class Bencode
{
	/**
	* Decode a bencoded string
	*
	* @param  string $bencoded Bencoded string
	* @return mixed            Decoded value
	*/
	public static function decode($bencoded)
	{
		if (!is_string($bencoded) || $bencoded === '')
		{
			throw new InvalidArgumentException;
		}

		$dictionary = new ArrayObject;
		$dictionary->setFlags(ArrayObject::ARRAY_AS_PROPS);

		$pos = 0;
		$max = strlen($bencoded) - 1;

		// Pad the bencoded string with a NUL byte so we don't have to check for boundary
		$bencoded .= "\0";

		$current     = null;
		$currentKey  = null;
		$currentType = null;
		$depth       = 0;
		$structures  = [];

		while ($pos <= $max)
		{
			$c = $bencoded[$pos];
			if ($c === 'i')
			{
				$negative = false;
				if ($bencoded[++$pos] === '-')
				{
					$negative = true;
					++$pos;
				}

				$spn = strspn($bencoded, '1234567890', $pos);
				if (!$spn)
				{
					$pos -= ($negative) ? 2 : 1;

					throw new RuntimeException('Invalid integer found at offset ' . $pos);
				}

				// Capture the value and cast it as an integer/float
				$value = (int) substr($bencoded, $pos, $spn);
				if ($negative)
				{
					$value = -$value;
				}

				$pos += $spn;
				if ($bencoded[$pos] !== 'e')
				{
					$pos -= $spn;
					$pos -= ($negative) ? 2 : 1;

					throw new RuntimeException('Invalid integer found at offset ' . $pos);
				}

				++$pos;
			}
			elseif ($c === 'e')
			{
				if (isset($currentKey))
				{
					throw new RuntimeException('Premature end of dictionary at offset ' . $pos);
				}

				if ($depth <= 1)
				{
					break;
				}

				++$pos;
				--$depth;
				$current = &$structures[$depth];
				$currentType = $types[$depth - 1];

				continue;
			}
			elseif ($c === 'd')
			{
				++$pos;
				$value = clone $dictionary;
			}
			elseif ($c === 'l')
			{
				++$pos;
				$value = [];
			}
			else
			{
				$spn = strspn($bencoded, '1234567890', $pos);
				if (!$spn)
				{
					throw new RuntimeException('Invalid character found at offset ' . $pos);
				}

				$len = (int) substr($bencoded, $pos, $spn);
				$pos += $spn;
				if ($bencoded[$pos] !== ':')
				{
					throw new RuntimeException('Invalid character found at offset ' . $pos);
				}

				$value = substr($bencoded, ++$pos, $len);
				$pos += $len;
			}

			if (isset($currentKey))
			{
				$current->$currentKey = &$value;
				$currentKey = null;
			}
			elseif ($currentType === 'd')
			{
				if (!is_string($value))
				{
					throw new RuntimeException('Invalid dictionary key type "' . $c . '"');
				}

				$currentKey = $value;
			}
			elseif ($currentType === 'l')
			{
				$current[] = &$value;
			}
			elseif (isset($current))
			{
				throw new RuntimeException('Unexpected content ending at offset ' . $pos);
			}
			else
			{
				$current = &$value;
			}

			if ($c === 'd' || $c === 'l')
			{
				$structures[$depth] = &$current;
				$types[$depth] = $c;
				$currentType = $c;
				++$depth;

				$current = &$value;
			}

			unset($value);
		}

		if ($pos < $max)
		{
			throw new RuntimeException('Superfluous content found at offset ' . ++$pos);
		}

		if ($pos > $max && $depth)
		{
			throw new RuntimeException('Premature end of data');
		}

		return $current;
	}

	/**
	* Bencode a value
	*
	* @param  mixed  $value Original value
	* @return string        Bencoded string
	*/
	public static function encode($value)
	{
		if (is_scalar($value))
		{
			return self::encodeScalar($value);
		}

		if (is_array($value))
		{
			return self::encodeArray($value);
		}

		if ($value instanceof stdClass || $value instanceof ArrayObject)
		{
			return self::encodeDictionary($value);
		}

		throw new InvalidArgumentException('Unsupported value');
	}

	/**
	* Encode an array into either an array of a dictionary
	*
	* @param  array $value
	* @return string
	*/
	protected static function encodeArray(array $value)
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
		return self::encodeDictionary((object) $value);
	}

	/**
	* Encode given object instance into a dictionary
	*
	* @param  object $dict
	* @return string
	*/
	protected static function encodeDictionary($dict)
	{
		$vars = get_object_vars($dict);
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
	*
	* @param  mixed  $value
	* @return string
	*/
	protected static function encodeScalar($value)
	{
		if (is_int($value) || is_float($value) || is_bool($value))
		{
			return sprintf('i%de', round($value));
		}

		return strlen($value) . ':' . $value;
	}
}