<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$fuzzer->setTarget(
	function (string $input)
	{
		try
		{
			$decoded = s9e\Bencode\Bencode::decode($input);
			if (s9e\Bencode\Bencode::encode($decoded) !== $input)
			{
				trigger_error('Does not match', E_USER_ERROR);
			}
		}
		catch (s9e\Bencode\Exceptions\DecodingException $e)
		{
		}

		s9e\Bencode\Bencode::decodeNonCompliant($input);
	}
);
$fuzzer->addDictionary(__DIR__ . '/dict.txt');

$fuzzer->setMaxLen(100);