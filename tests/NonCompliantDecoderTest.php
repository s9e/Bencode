<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use s9e\Bencode\Exceptions\DecodingException;
use s9e\Bencode\NonCompliantDecoder;

/**
* @covers s9e\Bencode\Decoder
* @covers s9e\Bencode\NonCompliantDecoder
*/
class NonCompliantDecoderTest extends TestCase
{
	use NonCompliantTestProvider;

	#[DataProvider('getDecodeNonCompliantTests')]
	public function testDecodeRelaxed($input, $nonCompliantValue, $exception)
	{
		$actual       = NonCompliantDecoder::decode($input);
		$assertMethod = (is_object($nonCompliantValue)) ? 'assertEquals' : 'assertSame';

		$this->$assertMethod($nonCompliantValue, $actual);

		if ($nonCompliantValue instanceof ArrayObject)
		{
			$this->assertSame(
				array_keys($nonCompliantValue->getArrayCopy()),
				array_keys($actual->getArrayCopy())
			);
		}
	}

	public function testDecodeCompliantDictionary()
	{
		$this->assertEquals(
			new ArrayObject(['a' => 1, 'b' => 2]),
			NonCompliantDecoder::decode('d1:ai1e1:bi2ee')
		);
	}

	#[DataProvider('getDecodeInvalidTests')]
	public function testDecodeInvalid($input, $expected)
	{
		$this->expectException(get_class($expected));
		$this->expectExceptionMessage($expected->getMessage());

		try
		{
			$this->assertNull(NonCompliantDecoder::decode($input));
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
				'i001x',
				new DecodingException('Illegal character', 4)
			],
		];
	}
}