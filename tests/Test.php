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
	public static function setUpBeforeClass(): void
	{
		// Preload the library so the memory-related tests don't count it as overhead
		Bencode::decode('i1e');
	}

	public function testUnsupportedClass()
	{
		$this->expectException('InvalidArgumentException');
		Bencode::encode(function(){});
	}

	public function testUnsupportedFloat()
	{
		$this->expectException('InvalidArgumentException');
		Bencode::encode(1.2);
	}

	public function testUnsupportedType()
	{
		$fp = fopen('php://stdin', 'rb');
		fclose($fp);

		$this->expectException('InvalidArgumentException');
		Bencode::encode($fp);
	}

	/**
	* @group memory
	*/
	public function testMemoryList()
	{
		$reference = memory_get_peak_usage();

		$len = 10000;
		$str = str_repeat('i0e', $len + 2);
		for ($i = 0; $i < 3; ++$i)
		{
			$str[$i]      = 'l';
			$str[-3 + $i] = 'e';
		}

		// Create a copy of the expected result so we get a feel for how much memory it will use
		$expected = array_fill(0, $len, 0);
		unset($expected);

		$before = memory_get_peak_usage();
		if ($before === $reference)
		{
			$this->markTestSkipped('Cannot measure peak memory before the reference value is too high');
		}

		$decoded = Bencode::decode($str);
		$after   = memory_get_peak_usage();
		$delta   = $after - $before;

		// Test that the delta is less than ~4 KB
		$this->assertLessThan(4000, $delta);
	}

	/**
	* @group memory
	*/
	public function testMemoryString()
	{
		$reference = memory_get_peak_usage();

		// Create a bencoded value that will be decoded into a string that is 2e6 characters long.
		// The overhead from bencoding is 8 for "2000000:" and we avoid creating copies of the
		// string by modifying it in place
		$len    = 2000000;
		$str    = str_repeat('0', $len + 8);
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
		$overhead = $delta - $len;

		// Test that the overhead was less than ~30 KB
		$this->assertLessThan(30e3, $overhead);
		$this->assertEquals($len, strlen($decoded));
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
				(double) 22.0
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
			[
				'lli0ei1eeli2ei3eee',
				[[0, 1], [2, 3]]
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
				new RuntimeException('Illegal character at offset 1')
			],
			[
				'l',
				new RuntimeException('Premature end of data')
			],
			[
				'lle',
				new RuntimeException('Premature end of data')
			],
			[
				'lee',
				new RuntimeException('Superfluous content at offset 2')
			],
			[
				'le0',
				new RuntimeException('Superfluous content at offset 2')
			],
			[
				'ddee',
				new RuntimeException('Illegal character at offset 1')
			],
			[
				'd1:xe',
				new RuntimeException('Illegal character at offset 4')
			],
			[
				'd1:xl',
				new RuntimeException('Premature end of data')
			],
			[
				'd1:xx',
				new RuntimeException('Illegal character at offset 4')
			],
			[
				'ie',
				new RuntimeException('Illegal character at offset 1')
			],
			[
				'i1x',
				new RuntimeException('Illegal character at offset 2')
			],
			[
				'lxe',
				new RuntimeException('Illegal character at offset 1')
			],
			[
				'3:abcd',
				new RuntimeException('Superfluous content at offset 5')
			],
			[
				'li',
				new RuntimeException('Premature end of data')
			],
			[
				'l3',
				new RuntimeException('Premature end of data')
			],
			[
				'i-1-e',
				new RuntimeException('Illegal character at offset 3')
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
				'd1:xi-',
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
				new RuntimeException('Illegal character at offset 1')
			],
			[
				'3a3:abc',
				new RuntimeException('Illegal character at offset 1')
			],
			[
				'3a',
				new RuntimeException('Illegal character at offset 1')
			],
			[
				':a',
				new RuntimeException('Illegal character at offset 0')
			],
			[
				'3:abc3:abc',
				new RuntimeException('Superfluous content at offset 5')
			],
			[
				'3:abci',
				new RuntimeException('Superfluous content at offset 5')
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
				'2:a',
				new RuntimeException('Premature end of data')
			],
			[
				'l11:ae',
				new RuntimeException('Premature end of data')
			],
			[
				'i0123e',
				new RuntimeException('Illegal character at offset 2')
			],
			[
				'i00e',
				new RuntimeException('Illegal character at offset 2')
			],
			[
				'i-0e',
				new RuntimeException('Illegal character at offset 2')
			],
			[
				'01:a',
				new RuntimeException('Illegal character at offset 1')
			],
			[
				'1',
				new RuntimeException('Premature end of data')
			],
			[
				'e',
				new RuntimeException('Illegal character at offset 0')
			],
			[
				'-1',
				new RuntimeException('Illegal character at offset 0')
			],
			[
				'd3:fooi0e3:foo3:abce',
				new RuntimeException("Duplicate dictionary entry 'foo' at offset 9")
			],
			[
				'd4:abcdi0e4:abcdli0eee',
				new RuntimeException("Duplicate dictionary entry 'abcd' at offset 10")
			],
			[
				'd3:fooi0e3:bar3:abce',
				new RuntimeException("Out of order dictionary entry 'bar' at offset 9")
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