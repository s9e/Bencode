<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2020 The s9e authors
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
	public static function decode(string $bencoded)
	{
		if ($bencoded === '')
		{
			throw new InvalidArgumentException;
		}

		$dictionary = new ArrayObject;
		$dictionary->setFlags(ArrayObject::ARRAY_AS_PROPS);

		$pos = 0;
		$max = strlen($bencoded) - 1;

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
				if (++$pos > $max)
				{
					throw new RuntimeException('Premature end of data');
				}

				$negative = ($bencoded[$pos] === '-');
				if ($negative)
				{
					++$pos;
				}

				$spn = strspn($bencoded, '1234567890', $pos);
				if ($spn > 1)
				{
					if ($bencoded[$pos] === '0')
					{
						throw new RuntimeException('Illegal character found at offset ' . $pos);
					}
				}
				elseif (!$spn)
				{
					if ($pos > $max)
					{
						throw new RuntimeException('Premature end of data');
					}
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
				if ($pos > $max)
				{
					throw new RuntimeException('Premature end of data');
				}
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

				++$pos;
				--$depth;
				if ($depth < 1)
				{
					break;
				}

				$current     = &$structures[$depth];
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
				if ($spn > 1)
				{
					if ($bencoded[$pos] === '0')
					{
						throw new RuntimeException('Illegal character found at offset ' . $pos);
					}
				}
				elseif (!$spn)
				{
					throw new RuntimeException('Illegal character found at offset ' . $pos);
				}

				$len  = (int) substr($bencoded, $pos, $spn);
				$pos += $spn;
				if ($pos > $max)
				{
					throw new RuntimeException('Premature end of data');
				}
				if ($bencoded[$pos] !== ':')
				{
					throw new RuntimeException('Illegal character found at offset ' . $pos);
				}
				if ($pos + $len > $max)
				{
					throw new RuntimeException('Premature end of data');
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
				$pos -= strlen(static::encode($value));

				throw new RuntimeException('Superfluous content found at offset ' . $pos);
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

		if ($depth > 0)
		{
			throw new RuntimeException('Premature end of data');
		}
		if ($pos <= $max)
		{
			throw new RuntimeException('Superfluous content found at offset ' . $pos);
		}

		return $current;
	}

	/**
	* Bencode a value
	*/
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
		if (is_int($value) || is_float($value) || is_bool($value))
		{
			return sprintf('i%de', round($value));
		}

		return strlen($value) . ':' . $value;
	}
}