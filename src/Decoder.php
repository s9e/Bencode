<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) 2014-2021 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use const PHP_INT_MAX;
use function is_float, str_contains, strcmp, strlen, strspn, substr;
use s9e\Bencode\Exceptions\ComplianceError;
use s9e\Bencode\Exceptions\DecodingException;

class Decoder
{
	/**
	* @var string Bencoded string being decoded
	*/
	protected string $bencoded;

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

	public static function decode(string $bencoded): ArrayObject|array|int|string
	{
		$decoder = new static($bencoded);
		$value   = $decoder->decodeAnything();

		$decoder->checkCursorPosition();

		return $value;
	}

	protected function __construct(string $bencoded)
	{
		$this->bencoded = $bencoded;
		$this->len      = strlen($bencoded);

		$this->computeSafeBoundary();
		$this->checkBoundary();
	}

	protected function checkBoundary(): void
	{
		if ($this->max < 1)
		{
			throw match (substr($this->bencoded, 0, 1))
			{
				'-', 'e' => new DecodingException('Illegal character',     0),
				''       => new DecodingException('Premature end of data', 0),
				default  => new DecodingException('Premature end of data', $this->len - 1)
			};
		}
	}

	/**
	* Check the cursor's position after decoding is done
	*/
	protected function checkCursorPosition(): void
	{
		if ($this->offset !== $this->len)
		{
			if ($this->offset > $this->len)
			{
				throw new DecodingException('Premature end of data', $this->len - 1);
			}

			$this->complianceError('Superfluous content', $this->offset);
		}
	}

	protected function checkDictionaryCompliance(int $offset, string $key, string $lastKey): void
	{
		$cmp = strcmp($key, $lastKey);
		if ($cmp <= 0)
		{
			$msg = ($cmp === 0) ? 'Duplicate' : 'Out of order';
			$this->complianceError($msg . " dictionary entry '" . $key . "'", $offset);
		}
	}

	protected function checkIntegerOverflow(string $str): void
	{
		if (is_float(+$str))
		{
			throw new DecodingException('Integer overflow', $this->offset);
		}
	}

	protected function complianceError(string $message, int $offset): void
	{
		throw new ComplianceError($message, $offset);
	}

	/**
	* Adjust the rightmost boundary to the last safe character that can start a value
	*
	* Will rewind the boundary to skip the rightmost digits, optionally preceded by "i" or "i-"
	*/
	protected function computeSafeBoundary(): void
	{
		$boundary = $this->len - 1;
		do
		{
			$c = substr($this->bencoded, $boundary, 1);
		}
		while (str_contains('0123456789', $c) && --$boundary >= 0);

		$this->max = match ($c)
		{
			'-'     => $boundary - 2,
			'i'     => $boundary - 1,
			default => $boundary
		};
	}

	protected function decodeAnything(): ArrayObject|array|int|string
	{
		return match ($this->bencoded[$this->offset])
		{
			'd'     => $this->decodeDictionary(),
			'i'     => $this->decodeInteger(),
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
			if ($this->bencoded[$this->offset] === 'e')
			{
				++$this->offset;

				return new ArrayObject($values, ArrayObject::ARRAY_AS_PROPS);
			}

			$offset = $this->offset;
			$key    = $this->decodeString();
			if (isset($lastKey))
			{
				$this->checkDictionaryCompliance($offset, $key, $lastKey);
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

	protected function decodeDigits(string $terminator): int
	{
		// Digits sorted by decreasing frequency as observed on a random sample of torrent files
		$spn = strspn($this->bencoded, '4615302879', $this->offset);
		if (!$spn)
		{
			throw new DecodingException('Illegal character', $this->offset);
		}
		if ($this->bencoded[$this->offset] === '0' && $spn !== 1)
		{
			$this->complianceError('Illegal character', 1 + $this->offset);
		}

		// Capture the value and cast it as an integer
		$value = (int) substr($this->bencoded, $this->offset, $spn);
		if ($value === PHP_INT_MAX)
		{
			$this->checkIntegerOverflow(substr($this->bencoded, $this->offset, $spn));
		}

		$this->offset += $spn;
		if ($this->bencoded[$this->offset] !== $terminator)
		{
			throw new DecodingException('Illegal character', $this->offset);
		}
		++$this->offset;

		return $value;
	}

	protected function decodeInteger(): int
	{
		$negative = ($this->bencoded[++$this->offset] === '-');
		if ($negative && $this->bencoded[++$this->offset] === '0')
		{
			$this->complianceError('Illegal character', $this->offset);
		}

		$value = $this->decodeDigits('e');

		return ($negative) ? -$value : $value;
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
		$len = $this->decodeDigits(':');
		if ($this->offset + $len >= PHP_INT_MAX)
		{
			throw new DecodingException('String length overflow', $this->offset - 1 - strlen((string) $len));
		}

		$string = substr($this->bencoded, $this->offset, $len);
		$this->offset += $len;

		return $string;
	}
}