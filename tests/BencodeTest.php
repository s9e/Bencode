<?php declare(strict_types=1);

namespace s9e\Bencode\Tests;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use s9e\Bencode\Bencode;

/**
* @covers s9e\Bencode\Bencode
*/
class BencodeTest extends TestCase
{
	public function testDecode()
	{
		$this->assertEquals(
			new ArrayObject(['bar' => 'spam', 'foo' => 42]),
			Bencode::decode('d3:bar4:spam3:fooi42ee')
		);
	}

	public function testDecodeType()
	{
		$this->expectException('TypeError');
		$this->expectExceptionMessage(Bencode::class . '::decode(): Argument #1 ($bencoded) must be of type string, null given');

		Bencode::decode(null);
	}

	public function testDecodeNonCompliant()
	{
		$this->assertEquals(
			new ArrayObject(['foo' => 42, 'bar' => 'spam']),
			Bencode::decodeNonCompliant('d3:bar4:spam3:fooi42ee')
		);
	}

	public function testEncode()
	{
		$this->assertEquals(
			'd3:bar4:spam3:fooi42ee',
			Bencode::encode(new ArrayObject(['bar' => 'spam', 'foo' => 42]))
		);
	}
}