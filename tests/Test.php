<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use TypeError;
use s9e\Bencode\Bencode;
use s9e\Bencode\Exceptions\ComplianceError;
use s9e\Bencode\Exceptions\DecodingException;
use s9e\Bencode\Exceptions\EncodingException;
use stdClass;

class Test extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		// Preload the library so the memory-related tests don't count it as overhead
		Bencode::decode('i1e');
	}

	/**
	* @group memory
	*/
	public function testMemoryList()
	{
		$len = 10000;
		$str = str_repeat('i0e', $len + 2);

		$str[0]  = $str[1]  = $str[2]  = 'l';
		$str[-1] = $str[-2] = $str[-3] = 'e';

		$reference = memory_get_peak_usage();

		// Create a copy of the expected result so we get a feel for how much memory it will use
		$expected = array_fill(0, $len, 0);
		unset($expected);

		$before = memory_get_peak_usage();
		if ($before === $reference)
		{
			$this->markTestSkipped('Cannot measure peak memory because the reference value is too high');
		}

		$decoded = Bencode::decode($str);
		$after   = memory_get_peak_usage();
		$delta   = $after - $before;

		// Test that the delta is less than 4 KB
		$this->assertLessThan(4096, $delta);
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
			$this->markTestSkipped('Cannot measure peak memory because the reference value is too high');
		}

		$decoded  = Bencode::decode($str);
		$after    = memory_get_peak_usage();
		$delta    = $after - $before;
		$overhead = $delta - $len;

		// Test that the overhead was less than 4 KB
		$this->assertLessThan(4096, $overhead);
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
				'de',
				new foo
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
			[
				'd1:0i0e1:1i1ee',
				[1 => 1, 0 => 0]
			],
			[
				'd2:11i11e1:5i5ee',
				[5 => 5, 11 => 11]
			],
			[
				'i1000000000e',
				1000000000
			],
		];
	}

	/**
	* @dataProvider getEncodeInvalidTests
	*/
	public function testEncodeInvalid($input)
	{
		$this->expectException(EncodingException::class);
		$this->expectExceptionMessage('Unsupported value');

		try
		{
			$this->assertNull(Bencode::encode($input));
		}
		catch (EncodingException $e)
		{
			$this->assertSame($input, $e->getValue());

			throw $e;
		}
	}

	public function getEncodeInvalidTests()
	{
		$fp = fopen('php://stdin', 'rb');
		fclose($fp);

		return [
			[function(){}],
			[1.2],
			[$fp],
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

		try
		{
			$this->assertNull(Bencode::decode($input));
		}
		catch (DecodingException $e)
		{
			$this->assertEquals($expected->getOffset(), $e->getOffset());
			throw $e;
		}
	}

	public function getDecodeInvalidTests()
	{
		return [
			[
				null,
				new TypeError(Bencode::class . '::decode(): Argument #1 ($bencoded) must be of type string, null given')
			],
			[
				'',
				new DecodingException('Premature end of data', 0)
			],
			[
				'lxe',
				new DecodingException('Illegal character', 1)
			],
			[
				'l',
				new DecodingException('Premature end of data', 0)
			],
			[
				'lle',
				new DecodingException('Premature end of data', 2)
			],
			[
				'lee',
				new ComplianceError('Superfluous content', 2)
			],
			[
				'le0',
				new ComplianceError('Superfluous content', 2)
			],
			[
				'ddee',
				new DecodingException('Illegal character', 1)
			],
			[
				'd1:xe',
				new DecodingException('Illegal character', 4)
			],
			[
				'd1:xl',
				new DecodingException('Premature end of data', 4)
			],
			[
				'd1:xx',
				new DecodingException('Illegal character', 4)
			],
			[
				'ie',
				new DecodingException('Illegal character', 1)
			],
			[
				'i1x',
				new DecodingException('Illegal character', 2)
			],
			[
				'lxe',
				new DecodingException('Illegal character', 1)
			],
			[
				'3:abcd',
				new ComplianceError('Superfluous content', 5)
			],
			[
				'li',
				new DecodingException('Premature end of data', 1)
			],
			[
				'l3',
				new DecodingException('Premature end of data', 1)
			],
			[
				'i-1-e',
				new DecodingException('Illegal character', 3)
			],
			[
				'i',
				new DecodingException('Premature end of data', 0)
			],
			[
				'i-',
				new DecodingException('Premature end of data', 1)
			],
			[
				'd1:xi-',
				new DecodingException('Premature end of data', 5)
			],
			[
				'i1',
				new DecodingException('Premature end of data', 1)
			],
			[
				'i-1',
				new DecodingException('Premature end of data', 2)
			],
			[
				'li-1',
				new DecodingException('Premature end of data', 3)
			],
			[
				'lli-1',
				new DecodingException('Premature end of data', 4)
			],
			[
				'lli123',
				new DecodingException('Premature end of data', 5)
			],
			[
				'3 abc',
				new DecodingException('Illegal character', 1)
			],
			[
				'3a3:abc',
				new DecodingException('Illegal character', 1)
			],
			[
				'3a',
				new DecodingException('Illegal character', 1)
			],
			[
				':a',
				new DecodingException('Illegal character', 0)
			],
			[
				'3:abc3:abc',
				new ComplianceError('Superfluous content', 5)
			],
			[
				'3:abci',
				new ComplianceError('Superfluous content', 5)
			],
			[
				'3:',
				new DecodingException('Premature end of data', 1)
			],
			[
				'3:a',
				new DecodingException('Premature end of data', 2)
			],
			[
				'2:a',
				new DecodingException('Premature end of data', 2)
			],
			[
				'l11:ae',
				new DecodingException('Premature end of data', 5)
			],
			[
				'i0123e',
				new ComplianceError('Illegal character', 2)
			],
			[
				'i00e',
				new ComplianceError('Illegal character', 2)
			],
			[
				'i-0e',
				new ComplianceError('Illegal character', 2)
			],
			[
				'01:a',
				new ComplianceError('Illegal character', 1)
			],
			[
				'1',
				new DecodingException('Premature end of data', 0)
			],
			[
				'e',
				new DecodingException('Illegal character', 0)
			],
			[
				'-1',
				new DecodingException('Illegal character', 0)
			],
			[
				'd3:fooi0e3:foo3:abce',
				new ComplianceError("Duplicate dictionary entry 'foo'", 9)
			],
			[
				'd4:abcdi0e4:abcdli0eee',
				new ComplianceError("Duplicate dictionary entry 'abcd'", 10)
			],
			[
				'd3:fooi0e3:bar3:abce',
				new ComplianceError("Out of order dictionary entry 'bar'", 9)
			],
			[
				'd1:5i0e2:11i0ee',
				new ComplianceError("Out of order dictionary entry '11'", 7)
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

class foo extends stdClass
{
}