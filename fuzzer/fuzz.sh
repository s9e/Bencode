#!/bin/bash

cd "$(dirname $0)"

../vendor/bin/php-fuzzer fuzz --dict dict.txt --max-runs 10000000 target.php