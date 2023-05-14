<?php declare(strict_types=1);

/**
* @package   s9e\Bencode
* @copyright Copyright (c) The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Bencode\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use s9e\Bencode\Exceptions\EncodingException;

class EncodingExceptionTest extends TestCase
{
	public function testMessage()
	{
		$exception = new EncodingException('Foo bar', 23);

		$this->assertEquals('Foo bar',$exception->getMessage());
	}

	public function testValue()
	{
		$exception = new EncodingException('Foo bar', 23);

		$this->assertSame(23, $exception->getValue());
	}
}