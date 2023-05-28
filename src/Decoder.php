<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use Throwable;
use TypeError;
use const PHP_INT_MAX, PHP_INT_MIN, false;
use function is_float, str_contains, strcmp, strlen, strspn, substr, substr_compare;
use s9e\Bencode\Exceptions\ComplianceError;
use s9e\Bencode\Exceptions\DecodingException;

class Decoder
{
	/**
	* @var int Length of the bencoded string
	*/
	protected int $len;

	/**
	* @var int Safe rightmost boundary
	*/
	protected int $max;

	/**
	* @var int Position of the cursor while decoding
	*/
	protected int $offset = 0;

	/**
	* @param string $bencoded Bencoded string to decode
	*/
	public static function decode(string $bencoded): ArrayObject|array|int|string
	{
		$decoder = new static($bencoded);
		try
		{
			$value = $decoder->decodeAnything();
		}
		catch (TypeError $e)
		{
			throw static::convertTypeError($e, $decoder->offset);
		}

		$decoder->checkCursorPosition();

		return $value;
	}

	/**
	* @param string $bencoded Bencoded string being decoded
	*/
	protected function __construct(protected readonly string $bencoded)
	{
		$this->len = strlen($bencoded);
		$this->max = $this->getSafeBoundary();

		$this->checkBoundary();
	}

	/**
	* Cast given string as an integer and check for clamping
	*/
	protected function castInteger(string $string, int $clamp): int
	{
		$value = (int) $string;
		if ($value === $clamp && is_float(+$string))
		{
			throw new DecodingException('Integer overflow', $this->offset - 1 - strlen($string));
		}

		return $value;
	}

	protected function checkBoundary(): void
	{
		if ($this->max < 1)
		{
			throw match (substr($this->bencoded, 0, 1))
			{
				''       => new DecodingException('Premature end of data', 0),
				'-'      => new DecodingException('Illegal character',     0),
				'e'      => new DecodingException('Illegal character',     0),
				default  => new DecodingException('Premature end of data', $this->len - 1)
			};
		}
	}

	/**
	* Check the cursor's position after decoding is done
	*/
	protected function checkCursorPosition(): void
	{
		if ($this->offset === $this->len)
		{
			return;
		}
		if ($this->offset > $this->len)
		{
			throw new DecodingException('Premature end of data', $this->len - 1);
		}

		$this->complianceError('Superfluous content', $this->offset);
	}

	protected function complianceError(string $message, int $offset): void
	{
		throw new ComplianceError($message, $offset);
	}

	protected static function convertTypeError(TypeError $e, int $offset): Throwable
	{
		// A type error can occur in decodeString() if the string length exceeds an int
		$frame  = $e->getTrace()[0];
		$caller = $frame['class'] . $frame['type'] . $frame['function'];
		if ($caller === __CLASS__ . '->decodeString')
		{
			return new DecodingException('String length overflow', $offset - 1);
		}

		// Return any other error as-is
		return $e;
	}

	protected function decodeAnything(): ArrayObject|array|int|string
	{
		return match ($this->bencoded[$this->offset])
		{
			'i'     => $this->decodeInteger(),
			'd'     => $this->decodeDictionary(),
			'l'     => $this->decodeList(),
			default => $this->decodeString()
		};
	}

	protected function decodeDictionary(): ArrayObject
	{
		$values  = [];
		$lastKey = null;

		++$this->offset;
		while ($this->offset <= $this->max)
		{
			$c = $this->bencoded[$this->offset];
			if ($c === 'e')
			{
				++$this->offset;

				return new ArrayObject($values, ArrayObject::ARRAY_AS_PROPS);
			}

			// Quickly match the most common keys found in dictionaries
			$key = match ($c)
			{
				'4'     => $this->decodeFastString('4:path',   6, 'path'),
				'6'     => $this->decodeFastString('6:length', 8, 'length'),
				default => $this->decodeString()
			};
			if (isset($lastKey) && strcmp($key, $lastKey) <= 0)
			{
				$this->dictionaryComplianceError($key, $lastKey);
			}
			if ($this->offset > $this->max)
			{
				break;
			}
			$values[$key] = $this->decodeAnything();
			$lastKey      = $key;
		}

		throw new DecodingException('Premature end of data', $this->len - 1);
	}

	/**
	* @param string $match Bencoded string to match
	* @param int    $len   Length of the bencoded string
	* @param string $value String value to return if the string matches
	*/
	protected function decodeFastString(string $match, int $len, string $value): string
	{
		if (substr_compare($this->bencoded, $match, $this->offset, $len, false) === 0)
		{
			$this->offset += $len;

			return $value;
		}

		return $this->decodeString();
	}

	protected function decodeInteger(): int
	{
		if ($this->bencoded[++$this->offset] === '-')
		{
			if ($this->bencoded[++$this->offset] === '0')
			{
				$this->complianceError('Illegal character', $this->offset);
			}

			$clamp  = PHP_INT_MIN;
			$string = '-' . $this->readDigits('e');
		}
		else
		{
			$clamp  = PHP_INT_MAX;
			$string = $this->readDigits('e');
		}

		return $this->castInteger($string, $clamp);
	}

	protected function decodeList(): array
	{
		++$this->offset;

		$list = [];
		while ($this->offset <= $this->max)
		{
			if ($this->bencoded[$this->offset] === 'e')
			{
				++$this->offset;

				return $list;
			}

			$list[] = $this->decodeAnything();
		}

		throw new DecodingException('Premature end of data', $this->len - 1);
	}

	protected function decodeString(): string
	{
		$len = (int) $this->readDigits(':');
		$string = substr($this->bencoded, $this->offset, $len);
		$this->offset += $len;

		return $string;
	}

	protected function dictionaryComplianceError(string $key, string $lastKey): void
	{
		// Compute the offset of the start of the string used as key
		$offset = $this->offset - strlen(strlen($key) . ':') - strlen($key);

		$msg = ($key === $lastKey) ? 'Duplicate' : 'Out of order';
		$this->complianceError($msg . " dictionary entry '" . $key . "'", $offset);
	}

	protected function digitException(): DecodingException
	{
		return (str_contains('0123456789', $this->bencoded[$this->offset]))
		     ? new ComplianceError('Illegal character', $this->offset)
		     : new DecodingException('Illegal character', $this->offset);
	}

	/**
	* Return the rightmost boundary to the last safe character that can start a value
	*
	* Will rewind the boundary to skip the rightmost digits, optionally preceded by "i" or "i-"
	*/
	protected function getSafeBoundary(): int
	{
		if (str_ends_with($this->bencoded, 'e'))
		{
			return $this->len - 1;
		}

		preg_match('(i?-?[0-9]*+$)D', $this->bencoded, $m);

		return $this->len - 1 - strlen($m[0] ?? '');
	}

	protected function readDigits(string $terminator): string
	{
		if ($this->bencoded[$this->offset] === '0')
		{
			++$this->offset;
			$string = '0';
		}
		else
		{
			// Digits sorted by decreasing frequency as observed on a random sample of torrent files
			$spn = strspn($this->bencoded, '1463720859', $this->offset);
			if ($spn === 0)
			{
				throw new DecodingException('Illegal character', $this->offset);
			}
			$string = substr($this->bencoded, $this->offset, $spn);
			$this->offset += $spn;
		}

		if ($this->bencoded[$this->offset] !== $terminator)
		{
			throw $this->digitException();
		}
		++$this->offset;

		return $string;
	}
}