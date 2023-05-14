<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use s9e\Bencode\Exceptions\ComplianceError;

trait NonCompliantTestProvider
{
	public static function getDecodeNonCompliantTests()
	{
		return [
			[
				'3:abcd',
				'abc',
				new ComplianceError('Superfluous content', 5)
			],
			[
				'3:abci',
				'abc',
				new ComplianceError('Superfluous content', 5)
			],
			[
				'3:abc3:abc',
				'abc',
				new ComplianceError('Superfluous content', 5)
			],
			[
				'i0123e',
				123,
				new ComplianceError('Illegal character', 2)
			],
			[
				'i00e',
				0,
				new ComplianceError('Illegal character', 2)
			],
			[
				'i-0e',
				0,
				new ComplianceError('Illegal character', 2)
			],
			[
				'01:a',
				'a',
				new ComplianceError('Illegal character', 1)
			],
			[
				'd3:fooi0e3:foo3:abce',
				new ArrayObject(['foo' => 'abc']),
				new ComplianceError("Duplicate dictionary entry 'foo'", 9)
			],
			[
				'd4:abcdi0e4:abcdli0eee',
				new ArrayObject(['abcd' => [0]]),
				new ComplianceError("Duplicate dictionary entry 'abcd'", 10)
			],
			[
				'd3:fooi0e3:bar3:abce',
				new ArrayObject(['bar' => 'abc', 'foo' => 0]),
				new ComplianceError("Out of order dictionary entry 'bar'", 9)
			],
			[
				'd1:5i0e2:11i0ee',
				new ArrayObject(['11' => 0, '5' => 0]),
				new ComplianceError("Out of order dictionary entry '11'", 7)
			],
		];
	}
}