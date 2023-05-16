<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use TypeError;
use s9e\Bencode\Encoder;
use s9e\Bencode\Exceptions\EncodingException;
use stdClass;

/**
* @covers s9e\Bencode\Encoder
*/
class EncoderTest extends TestCase
{
	/**
	* @dataProvider getEncodeTests
	*/
	public function testEncode($bencoded, $value)
	{
		$this->assertSame($bencoded, Encoder::encode($value));
	}

	public static function getEncodeTests()
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
				'2:22',
				'22'
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
			$this->assertNull(Encoder::encode($input));
		}
		catch (EncodingException $e)
		{
			if (is_float($input) && is_nan($input))
			{
				$this->assertNan($e->getValue());
			}
			else
			{
				$this->assertSame($input, $e->getValue());
			}

			throw $e;
		}
	}

	public static function getEncodeInvalidTests()
	{
		$fp = fopen('php://stdin', 'rb');
		fclose($fp);

		return [
			[function(){}],
			[1.2],
			[$fp],
			[INF],
			[NAN]
		];
	}
}

class foo extends stdClass
{
}