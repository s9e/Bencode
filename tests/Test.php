<?php

namespace s9e\Bencode\Tests;

use ArrayObject;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use RuntimeException;
use s9e\Bencode\Bencode;
use stdClass;

class Test extends PHPUnit_Framework_TestCase
{
	/**
	* @expectedException InvalidArgumentException
	*/
	public function testUnsupported()
	{
		Bencode::encode(function(){});
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
				'd3:bari2e3:fooi1ee',
				['foo' => 1, 'bar' => 2]
			],
			[
				'd3:fool1:a1:b1:cee',
				['foo' => ['a', 'b', 'c']]
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
				'i22e',
				22
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
		];
	}

	/**
	* @dataProvider getDecodeInvalidTests
	* @expectedException RuntimeException
	*/
	public function testDecodeInvalid($input, $expected)
	{
		$this->setExpectedException(get_class($expected), $expected->getMessage());
		$this->assertNull(Bencode::decode($input));
	}

	public function getDecodeInvalidTests()
	{
		return [
			[
				null,
				new InvalidArgumentException
			],
			[
				'',
				new InvalidArgumentException
			],
			[
				'lxe',
				new RuntimeException('Invalid character found at offset 1')
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
				new RuntimeException('Invalid character found at offset 1')
			],
			[
				'li',
				new RuntimeException('Invalid integer found at offset 1')
			],
			[
				'i-1-e',
				new RuntimeException('Invalid integer found at offset 0')
			],
			[
				'lli123',
				new RuntimeException('Invalid integer found at offset 2')
			],
			[
				'3 abc',
				new RuntimeException('Invalid character found at offset 1')
			],
			[
				'3:abc3:abc',
				new RuntimeException('Unexpected content ending at offset 10')
			],
		];
	}
}