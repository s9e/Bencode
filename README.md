s9e\Bencode is a simple [Bencode](http://en.wikipedia.org/wiki/Bencode) encoder/decoder. It's probably the fastest and most efficient way to read bencoded strings on PHP 5.4 to PHP 5.6.

### Installation

Via composer:
```yaml
{
    "require": {
        "s9e/bencode": "*"
    }
}
```

### Usage

#### Decode a bencoded string
```php
use s9e\Bencode\Bencode;

print_r(Bencode::decode('d3:bar4:spam3:fooi42ee'));
```
```
stdClass Object
(
    [bar] => spam
    [foo] => 42
)
```

#### Decode a bencoded string, using arrays for dictionaries
```php
use s9e\Bencode\Bencode;

print_r(Bencode::decode('d3:bar4:spam3:fooi42ee', true));
```
```
Array
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