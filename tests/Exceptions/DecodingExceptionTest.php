<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode\Tests\Exceptions;


use PHPUnit\Framework\TestCase;
use TypeError;
use s9e\Bencode\Exceptions\DecodingException;

class DecodingExceptionTest extends TestCase
{
	public function testMessage()
	{
		$exception = new DecodingException('Foo bar', 23);

		$this->assertEquals(
			'Foo bar at offset 23',
			$exception->getMessage()
		);
	}

	public function testOffset()
	{
		$exception = new DecodingException('Foo bar', 23);

		$this->assertSame(23, $exception->getOffset());
	}
}