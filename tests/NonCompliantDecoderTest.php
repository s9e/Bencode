<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use s9e\Bencode\NonCompliantDecoder;

/**
* @covers s9e\Bencode\NonCompliantDecoder
*/
class NonCompliantDecoderTest extends TestCase
{
	use NonCompliantTestProvider;

	/**
	* @dataProvider getDecodeNonCompliantTests
	*/
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
}