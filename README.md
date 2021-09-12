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
    [bar] => spam
    [foo] => 42
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

### Implementation details

 - Rejects invalid bencoded data with meaningful exception messages.
 - Uses [ArrayObject](https://www.php.net/manual/en/class.arrayobject.php) instances to represent dictionaries. Dictionaries can be created and read using either the array notation or the object notation.
 - Integers are limited in range from `PHP_INT_MIN` to `PHP_INT_MAX`.
 - The encoder accepts booleans but converts them to integers.
 - The encoder accepts floats that are equal to their integer value.
