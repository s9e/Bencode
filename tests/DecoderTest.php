<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;
use s9e\Bencode\Decoder;
use s9e\Bencode\Exceptions\ComplianceError;
use s9e\Bencode\Exceptions\DecodingException;

/**
* @covers s9e\Bencode\Decoder
*/
class DecoderTest extends TestCase
{
	use NonCompliantTestProvider;

	public static function setUpBeforeClass(): void
	{
		// Preload the library so the memory-related tests don't count it as overhead
		Decoder::decode('i1e');
	}

	/**
	* @group memory
	*/
	public function testMemoryList()
	{
		if (is_callable('memory_reset_peak_usage'))
		{
			memory_reset_peak_usage();
		}

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

		$decoded = Decoder::decode($str);
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
		if (is_callable('memory_reset_peak_usage'))
		{
			memory_reset_peak_usage();
		}
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

		$decoded  = Decoder::decode($str);
		$after    = memory_get_peak_usage();
		$delta    = $after - $before;
		$overhead = $delta - $len;

		// Test that the overhead was less than 4 KB
		$this->assertLessThan(4096, $overhead);
		$this->assertEquals($len, strlen($decoded));
	}

	#[DataProvider('getDecodeTests')]
	public function testDecode($bencoded, $value)
	{
		$this->assertEquals($value, Decoder::decode($bencoded));
	}

	public static function getDecodeTests()
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
				'i1e',
				1
			],
			[
				'i' . PHP_INT_MAX . 'e',
				PHP_INT_MAX
			],
			[
				'i' . PHP_INT_MIN . 'e',
				PHP_INT_MIN
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
			[
				'd6:Lengthi1e4:Pathi2e6:lengthi3e4:pathi4ee',
				new ArrayObject(['Length' => 1, 'Path' => 2, 'length' => 3, 'path' => 4])
			],
			[
				'd8:announcei1e8:xxxxxxxx8:xxxxxxxxe',
				new ArrayObject(['announce' => 1, 'xxxxxxxx' => 'xxxxxxxx'])
			],
		];
	}

	#[DataProvider('getDecodeInvalidTests')]
	public function testDecodeInvalid($input, $expected)
	{
		$this->expectException(get_class($expected));
		$this->expectExceptionMessage($expected->getMessage());

		try
		{
			$this->assertNull(Decoder::decode($input));
		}
		catch (DecodingException $e)
		{
			$this->assertEquals($expected->getOffset(), $e->getOffset());
			throw $e;
		}
	}

	public static function getDecodeInvalidTests()
	{
		return [
			[
				null,
				new TypeError(Decoder::class . '::decode(): Argument #1 ($bencoded) must be of type string, null given')
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
				'i0x',
				new DecodingException('Illegal character', 2)
			],
			[
				'lxe',
				new DecodingException('Illegal character', 1)
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
				'l0l',
				new DecodingException('Illegal character', 2)
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
				'i999999999999999999999999e',
				new DecodingException('Integer overflow', 1)
			],
			[
				'i-999999999999999999999999e',
				new DecodingException('Integer overflow', 1)
			],
			[
				'l' . PHP_INT_MAX . ':xe',
				new DecodingException('String length overflow', strlen('l' . PHP_INT_MAX))
			],
			[
				'li111',
				new DecodingException('Premature end of data', 4)
			],
			[
				'd4:path',
				new DecodingException('Premature end of data', 6)
			],
			[
				'd4:pathd',
				new DecodingException('Premature end of data', 7)
			],
			[
				'ld1',
				new DecodingException('Premature end of data', 2)
			],
		];
	}

	#[DataProvider('getDecodeNonCompliantTests')]
	public function testDecodeNonCompliant($input, $nonCompliantValue, $exception)
	{
		$this->expectException(get_class($exception));
		$this->expectExceptionMessage($exception->getMessage());
		Decoder::decode($input);
	}

	public function testDecodeDictionaryAccess()
	{
		$dict = Decoder::decode('d3:bar4:spam3:fooi42ee');

		$this->assertSame('spam', $dict->bar);
		$this->assertSame(42,     $dict['foo']);

		$actual = [];
		foreach ($dict as $k => $v)
		{
			$actual[$k] = $v;
		}
		$this->assertSame(['bar' => 'spam', 'foo' => 42], $actual);
	}

	public function testFaultyStringDecoderDecoder()
	{
		$this->expectException('TypeError');
		FaultyStringDecoder::decode('1:x');
	}

	public function testFaultyDictionaryDecoderDecoder()
	{
		$this->expectException('TypeError');
		FaultyDictionaryDecoder::decode('d1:xi1ee');
	}
}

class FaultyStringDecoder extends Decoder
{
	protected function decodeString(): string
	{
		$this->offset = 1.2;

		return '?';
	}
}

class FaultyDictionaryDecoder extends Decoder
{
	protected function decodeDictionary(): ArrayObject
	{
		$this->offset = PHP_INT_MAX;

		return parent::decodeDictionary();
	}
}