<?php

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2017 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use InvalidArgumentException;
use RuntimeException;
use stdClass;

class Bencode
{
	/**
	* Decode a bencoded string
	*
	* @param  string $bencoded Bencoded string
	* @param  bool   $useArray Whether to use arrays as dictionaries (otherwise, use objects)
	* @return mixed            Decoded value
	*/
	public static function decode($bencoded, $useArray = false)
	{
		if (!is_string($bencoded) || $bencoded === '')
		{
			throw new InvalidArgumentException;
		}

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
			if ($c === 'e')
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

			if ($c === 'd')
			{
				++$pos;
				$value = ($useArray) ? [] : new stdClass;
			}
			elseif ($c === 'l')
			{
				++$pos;
				$value = [];
			}
			elseif ($c === 'i')
			{
				if ($pos === $max)
				{
					throw new RuntimeException('Premature end of data');
				}

				$negative = false;
				if ($bencoded[++$pos] === '-')
				{
					$negative = true;
					++$pos;
				}

				$spn = strspn($bencoded, '1234567890', $pos);
				if (!$spn)
				{
					throw new RuntimeException('Invalid integer found at offset ' . $pos);
				}

				// Capture the value and cast it as an integer/float
				$value = substr($bencoded, $pos, $spn);
				$value = ($negative) ? -$value : +$value;

				$pos += $spn;
				if ($bencoded[$pos] !== 'e')
				{
					throw new RuntimeException('Invalid integer end found at offset ' . $pos);
				}

				++$pos;
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
				if ($useArray)
				{
					$current[$currentKey] = &$value;
				}
				else
				{
					$current->$currentKey = &$value;
				}

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
				throw new RuntimeException('Unexpected value around offset ' . $pos);
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
		if (is_string($value))
		{
			return strlen($value) . ':' . $value;
		}

		if (is_numeric($value) || is_bool($value))
		{
			return sprintf('i%de', round($value));
		}
		
		if (is_array($value))
		{
			if (empty($value))
			{
				return 'le';
			}

			if (array_keys($value) === range(0, count($value) - 1))
			{
				return 'l' . implode('', array_map(__METHOD__, $value)) . 'e';
			}

			// Cast as object to force it to be represented as a dictionary
			$value = (object) $value;
		}

		if ($value instanceof stdClass)
		{
			$vars = get_object_vars($value);
			ksort($vars);

			$str = 'd';
			foreach ($vars as $k => $v)
			{
				$str .= strlen($k) . ':' . $k . self::encode($v);
			}
			$str .= 'e';

			return $str;
		}

		throw new InvalidArgumentException('Unsupported value');
	}
}