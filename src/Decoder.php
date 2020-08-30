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
	protected int $pos;

	public static function decode(string $bencoded)
	{
		$decoder = new static($bencoded);
		$value   = $decoder->decodeAnything();

		$decoder->checkCursorPosition();

		return $value;
	}

	protected function __construct(string $bencoded)
	{
		if ($bencoded === '')
		{
			throw new InvalidArgumentException;
		}

		$this->bencoded = $bencoded;
		$this->len      = strlen($bencoded);
		$this->pos      = 0;

		$this->computeSafeBoundary();
	}

	protected function checkBoundary(): void
	{
		if ($this->max < 1)
		{
			if (strpos('-e', $this->bencoded[0]) !== false)
			{
				throw new RuntimeException('Illegal character found at offset 0');
			}

			throw new RuntimeException('Premature end of data');
		}
	}

	/**
	* Check the cursor's position after decoding is done
	*/
	protected function checkCursorPosition(): void
	{
		if ($this->pos !== $this->len)
		{
			if ($this->pos > $this->len)
			{
				throw new RuntimeException('Premature end of data');
			}

			$this->complianceError('Superfluous content found at offset ' . $this->pos);
		}
	}

	protected function complianceError(string $message): void
	{
		throw new RuntimeException($message);
	}

	/**
	* Adjust the rightmost boundary to the last safe character that can start a value
	*
	* Will rewind the boundary to skip the rightmost digits, optionally preceded by "i" or "i-"
	*/
	protected function computeSafeBoundary(): void
	{
		$boundary = $this->len - 1;
		$c = $this->bencoded[$boundary];
		while (is_numeric($c) && --$boundary >= 0)
		{
			$c = $this->bencoded[$boundary];
		}
		if ($c === '-')
		{
			$boundary -= 2;
		}
		elseif ($c === 'i')
		{
			--$boundary;
		}

		$this->max = $boundary;
		$this->checkBoundary();
	}

	protected function decodeAnything()
	{
		$c = $this->bencoded[$this->pos];
		if ($c === 'i')
		{
			return $this->decodeInteger();
		}
		if ($c === 'd')
		{
			return $this->decodeDictionary();
		}
		if ($c === 'l')
		{
			return $this->decodeList();
		}

		return $this->decodeString();
	}

	protected function decodeDictionary(): ArrayObject
	{
		$dictionary = new ArrayObject;
		$dictionary->setFlags(ArrayObject::ARRAY_AS_PROPS);

		++$this->pos;
		while ($this->pos <= $this->max)
		{
			if ($this->bencoded[$this->pos] === 'e')
			{
				++$this->pos;

				return $dictionary;
			}

			$pos = $this->pos;
			$key = $this->decodeString();
			if (isset($dictionary->$key))
			{
				$this->complianceError("Duplicate dictionary entry '" . $key . "' at pos " . $pos);
			}
			if ($this->pos > $this->max)
			{
				break;
			}
			$dictionary->$key = $this->decodeAnything();
		}

		throw new RuntimeException('Premature end of data');
	}

	protected function decodeDigits(string $terminator): int
	{
		// Digits sorted by decreasing frequency as observed on a random sample of torrent files
		$spn = strspn($this->bencoded, '4615302879', $this->pos);
		if (!$spn)
		{
			throw new RuntimeException('Illegal character found at offset ' . $this->pos);
		}
		if ($this->bencoded[$this->pos] === '0' && $spn > 1)
		{
			$this->complianceError('Illegal character found at offset ' . (1 + $this->pos));
		}

		// Capture the value and cast it as an integer
		$value = (int) substr($this->bencoded, $this->pos, $spn);

		$this->pos += $spn;
		if ($this->bencoded[$this->pos] !== $terminator)
		{
			throw new RuntimeException('Illegal character found at offset ' . $this->pos);
		}
		++$this->pos;

		return $value;
	}

	protected function decodeInteger(): int
	{
		$negative = ($this->bencoded[++$this->pos] === '-');
		if ($negative && $this->bencoded[++$this->pos] === '0')
		{
			$this->complianceError('Illegal character found at offset ' . $this->pos);
		}

		$value = $this->decodeDigits('e');

		return ($negative) ? -$value : $value;
	}

	protected function decodeList(): array
	{
		++$this->pos;

		$list = [];
		while ($this->pos <= $this->max)
		{
			if ($this->bencoded[$this->pos] === 'e')
			{
				++$this->pos;

				return $list;
			}

			$list[] = $this->decodeAnything();
		}

		throw new RuntimeException('Premature end of data');
	}

	protected function decodeString(): string
	{
		$len        = $this->decodeDigits(':');
		$string     = substr($this->bencoded, $this->pos, $len);
		$this->pos += $len;

		return $string;
	}
}