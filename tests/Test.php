<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;
use s9e\Bencode\Bencode;
use stdClass;

class Test extends TestCase
{
	public function testUnsupported()
	{
		$this->expectException('InvalidArgumentException');
		Bencode::encode(function(){});
	}

	public function testMemory()
	{
		$reference = memory_get_peak_usage();

		// Create a bencoded value that will be decoded into a string that is 2e6 characters long.
		// The overhead from bencoding is 8 for "2000000:" and we avoid creating copies of the
		// string by modifying it in place
		$str    = str_repeat('0', 2000008);
		$str[0] = '2';
		$str[7] = ':';

		$before = memory_get_peak_usage();
		if ($before === $reference)
		{
			$this->markTestSkipped('Cannot measure peak memory before the reference value is too high');
		}

		$decoded  = Bencode::decode($str);
		$after    = memory_get_peak_usage();
		$delta    = $after - $before;
		$overhead = $delta - strlen($decoded);

		// Test that the overhead was less than ~30 KB
		$this->assertLessThan(30e3, $overhead);
		$this->assertEquals(2000000, strlen($decoded));
	}

	/**
	* @dataProvider getEncodeTests
	*/
	public function testEncode($bencoded, $value)
	{
		$this->assertSame($bencoded, Bencode::encode($value));
	}

	public function getEncodeTests()
	{
		return [
			[
				'i22e',
				22
			],
			[
				'i22e',
				(double) 22
			],
			[
				'i1e',
				true
			],
			[
				'i0e',
				false
			],
			[
				'i-1e',
				-1
			],
			[
				'le',
				[]
			],
			[
				'de',
				new stdClass
			],
			[
				'd3:fooi1ee',
				['foo' => 1]
			],
			[
				'd3:fooi1ee',
				(object) ['foo' => 1]
			],
			[
				'd3:bari2e3:fooi1ee',
				['foo' => 1, 'bar' => 2]
			],
			[
				'd3:fool1:a1:b1:cee',
				['foo' => ['a', 'b', 'c']]
			],
			[
				'd0:l1:a1:b1:cee',
				['' => ['a', 'b', 'c']]
			],
			[
				'd3:food3:bari1ee1:xd1:yi1eee',
				new ArrayObject([
					'foo' => new ArrayObject(['bar' => 1]),
					'x'   => new ArrayObject(['y' => 1])
				])
			],
		];
	}

	/**
	* @dataProvider getDecodeTests
	*/
	public function testDecode($bencoded, $value)
	{
		$this->assertEquals($value, Bencode::decode($bencoded));
	}

	public function getDecodeTests()
	{
		return [
			[
				'i1234567890e',
				1234567890
			],
			[
				'i-1e',
				-1
			],
			[
				'i0e',
				0
			],
			[
				'le',
				[]
			],
			[
				'de',
				new ArrayObject
			],
			[
				'd3:fooi1ee',
				new ArrayObject(['foo' => 1])
			],
			[
				'd3:bari2e3:fooi1ee',
				new ArrayObject(['foo' => 1, 'bar' => 2])
			],
			[
				'd3:fool1:a1:b1:cee',
				new ArrayObject(['foo' => ['a', 'b', 'c']])
			],
			[
				'd3:food3:bari1ee1:xd1:yi1eee',
				new ArrayObject([
					'foo' => new ArrayObject(['bar' => 1]),
					'x'   => new ArrayObject(['y' => 1])
				])
			],
			[
				'3:abc',
				'abc'
			],
			[
				'd0:l1:a1:b1:cee',
				new ArrayObject(['' => ['a', 'b', 'c']])
			],
			[
				'0:',
				''
			],
			[
				'1:i',
				'i'
			],
			[
				'2:i-',
				'i-'
			],
			[
				'3:i-1',
				'i-1'
			],
			[
				'4:i-1e',
				'i-1e'
			],
		];
	}

	/**
	* @dataProvider getDecodeInvalidTests
	*/
	public function testDecodeInvalid($input, $expected)
	{
		$this->expectException(get_class($expected));
		$this->expectExceptionMessage($expected->getMessage());
		$this->assertNull(Bencode::decode($input));
	}

	public function getDecodeInvalidTests()
	{
		return [
			[
				null,
				new TypeError('Argument 1 passed to ' . Bencode::class . '::decode() must be of the type string')
			],
			[
				'',
				new InvalidArgumentException
			],
			[
				'lxe',
				new RuntimeException('Illegal character found at offset 1')
			],
			[
				'l',
				new RuntimeException('Premature end of data')
			],
			[
				'lee',
				new RuntimeException('Superfluous content found at offset 2')
			],
			[
				'ddee',
				new RuntimeException('Invalid dictionary key type "d"')
			],
			[
				'd1:xe',
				new RuntimeException('Premature end of dictionary at offset 4')
			],
			[
				'ie',
				new RuntimeException('Invalid integer found at offset 0')
			],
			[
				'i1x',
				new RuntimeException('Invalid integer found at offset 0')
			],
			[
				'lxe',
				new RuntimeException('Illegal character found at offset 1')
			],
			[
				'li',
				new RuntimeException('Premature end of data')
			],
			[
				'i-1-e',
				new RuntimeException('Invalid integer found at offset 0')
			],
			[
				'i',
				new RuntimeException('Premature end of data')
			],
			[
				'i-',
				new RuntimeException('Premature end of data')
			],
			[
				'i1',
				new RuntimeException('Premature end of data')
			],
			[
				'i-1',
				new RuntimeException('Premature end of data')
			],
			[
				'lli123',
				new RuntimeException('Premature end of data')
			],
			[
				'3 abc',
				new RuntimeException('Illegal character found at offset 1')
			],
			[
				'3a3:abc',
				new RuntimeException('Illegal character found at offset 1')
			],
			[
				'3a',
				new RuntimeException('Illegal character found at offset 1')
			],
			[
				':a',
				new RuntimeException('Illegal character found at offset 0')
			],
			[
				'3:abc3:abc',
				new RuntimeException('Superfluous content found at offset 5')
			],
			[
				'3:abci',
				new RuntimeException('Premature end of data')
//				new RuntimeException('Superfluous content found at offset 5')
			],
			[
				'3:',
				new RuntimeException('Premature end of data')
			],
			[
				'3:a',
				new RuntimeException('Premature end of data')
			],
			[
				'l11:ae',
				new RuntimeException('Premature end of data')
			],
			[
				'11:a',
				new RuntimeException('Premature end of data')
			],
			[
				'i0123e',
				new RuntimeException('Illegal character found at offset 1')
			],
			[
				'01:a',
				new RuntimeException('Illegal character found at offset 0')
			],
			[
				'1',
				new RuntimeException('Premature end of data')
			],
			[
				'1:',
				new RuntimeException('Premature end of data')
			],
		];
	}

	public function testDecodeDictionaryAccess()
	{
		$dict = Bencode::decode('d3:bar4:spam3:fooi42ee');

		$this->assertSame('spam', $dict->bar);
		$this->assertSame(42,     $dict['foo']);

		$actual = [];
		foreach ($dict as $k => $v)
		{
			$actual[$k] = $v;
		}
		$this->assertSame(['bar' => 'spam', 'foo' => 42], $actual);
	}
}