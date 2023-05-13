<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode;

use ArrayObject;
use const SORT_STRING, false, true;
use function strcmp;

class NonCompliantDecoder extends Decoder
{
	/**
	* @var bool Whether current dictionary needs to be sorted
	*/
	protected bool $sortDictionary = false;

	protected function complianceError(string $message, int $offset): void
	{
		// Do nothing
	}

	protected function decodeDictionary(): ArrayObject
	{
		$previousState        = $this->sortDictionary;
		$this->sortDictionary = false;
		$dictionary           = parent::decodeDictionary();
		if ($this->sortDictionary)
		{
			$dictionary->ksort(SORT_STRING);
		}
		$this->sortDictionary = $previousState;

		return $dictionary;
	}

	protected function dictionaryComplianceError(string $key, string $lastKey): void
	{
		$this->sortDictionary = true;
	}
}