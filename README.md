s9e\Bencode is a clean and efficient [Bencode](http://en.wikipedia.org/wiki/Bencode) encoder/decoder. It is designed to handle malformed and malicious input gracefully.

[![Build Status](https://scrutinizer-ci.com/g/s9e/Bencode/badges/build.png?b=master)](https://scrutinizer-ci.com/g/s9e/Bencode/build-status/master)
[![Code Coverage](https://scrutinizer-ci.com/g/s9e/Bencode/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/s9e/Bencode/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/s9e/Bencode/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/s9e/Bencode/?branch=master)


### Installation

```bash
composer require s9e/bencode
```


### Usage

#### Decode a bencoded string

```php
use s9e\Bencode\Bencode;
print_r(Bencode::decode('d3:bar4:spam3:fooi42ee'));
```
```
ArrayObject Object
(
    [storage:ArrayObject:private] => Array
        (
            [bar] => spam
            [foo] => 42
        )

)
```

#### Encode a PHP value

```php
use s9e\Bencode\Bencode;
print_r(Bencode::encode(['foo' => 42, 'bar' => 'spam']));
```
```
d3:bar4:spam3:fooi42ee
```

Supported types are as follow:

 - `array`, `int`, and `string` values are encoded natively.
 - `float` values that can be losslessly converted to integers are coerced to `int`.
 - `bool` values are coerced to `int`.
 - An object that implements `s9e\Bencode\BencodeSerializable` is encoded as the value returned by its `bencodeSerialize()` method.
 - The properties of an `stdClass` object are encoded in a dictionary.
 - An instance of `ArrayObject` is treated as an array.

```php
use s9e\Bencode\Bencode;
use s9e\Bencode\BencodeSerializable;

$bencodable = new class implements BencodeSerializable
{
	public function bencodeSerialize(): array|int|string
	{
		return 42;
	}
};

print_r(Bencode::encode($bencodable));
```
```
i42e
```

#### Handle exceptions

```php
try
{
	s9e\Bencode\Bencode::decode('i123x');
}
catch (s9e\Bencode\Exceptions\DecodingException $e)
{
	var_dump($e->getMessage(), $e->getOffset());
}
```
```
string(29) "Illegal character at offset 4"
int(4)
```

```php
try
{
	s9e\Bencode\Bencode::encode(2.5);
}
catch (s9e\Bencode\Exceptions\EncodingException $e)
{
	var_dump($e->getMessage(), $e->getValue());
}
```
```
string(17) "Unsupported value"
float(2.5)
```

#### Salvage non-compliant input

By default, the decoder rejects non-compliant input with a `ComplianceError` exception, which is a subtype of `DecodingException`. If you have to handle input produced by a non-compliant encoder, the `decodeNonCompliant` method may be able to salvage it by replacing illegal values as follow:

 - Unordered dictionaries are automatically sorted.
 - Duplicate entries in dictionaries overwrite prior entries.
 - Integers used as dictionary keys are converted to strings.
 - Leading `0`s are removed from integers.
 - Negative zero is converted to `0`.
 - Trailing junk at the end of the input is ignored.

In the following example, we try to load an invalid dictionary normally and upon failure, we retry using the non-compliant decoder.

```php
use s9e\Bencode\Bencode;

$input = 'd3:fooi42e3:bar4:spame';
try
{
	$value = Bencode::decode($input);
}
catch (s9e\Bencode\Exceptions\ComplianceError $e)
{
	echo 'Failed: ', $e->getMessage(), "\nRetry with non-compliant decoder:\n";

	$value = Bencode::decodeNonCompliant($input);
	print_r($value);
}
```
```
Failed: Out of order dictionary entry 'bar' at offset 10
Retry with non-compliant decoder:
ArrayObject Object
(
    [storage:ArrayObject:private] => Array
        (
            [bar] => spam
            [foo] => 42
        )

)
```


### Implementation details

 - Rejects invalid bencoded data with meaningful exception messages.
 - Uses [ArrayObject](https://www.php.net/manual/en/class.arrayobject.php) instances to represent dictionaries. Dictionaries can be created and read using either the array notation or the object notation.
 - Integers are limited in range from `PHP_INT_MIN` to `PHP_INT_MAX`.
 - The encoder accepts booleans but converts them to integers.
 - The encoder accepts floats that are equal to their integer value.
