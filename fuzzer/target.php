<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$fuzzer->setTarget(
	function (string $input)
	{
		s9e\Bencode\Bencode::decode($input);
	}
);
$fuzzer->addDictionary(__DIR__ . '/dict.txt');

$fuzzer->setMaxLen(100);