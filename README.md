# PhpWeasyPrint for PHP 5.6

[![CI Status](https://github.com/xmarcos/php-weasyprint/actions/workflows/ci.yaml/badge.svg)](https://github.com/xmarcos/php-weasyprint/actions/workflows/ci.yaml)
[![Latest Stable Version](http://poser.pugx.org/xmarcos/php-weasyprint/v)](https://packagist.org/packages/xmarcos/php-weasyprint)
[![Total Downloads](http://poser.pugx.org/xmarcos/php-weasyprint/downloads)](https://packagist.org/packages/xmarcos/php-weasyprint)
[![License](http://poser.pugx.org/xmarcos/php-weasyprint/license)](https://packagist.org/packages/xmarcos/php-weasyprint)
[![PHP Version Require](http://poser.pugx.org/xmarcos/php-weasyprint/require/php)](https://packagist.org/packages/xmarcos/php-weasyprint)

PhpWeasyPrint is a wrapper for [WeasyPrint](https://weasyprint.org), an alternative to [wkhtmltopdf](https://wkhtmltopdf.org).

:warning: This is a backport of [`pontedilana/php-weasyprint`](https://github.com/pontedilana/php-weasyprint) for legacy applications running PHP 5.6 —which have reached end-of-life and should not be used in production.

This original library is massively inspired by [KnpLabs/snappy](https://github.com/KnpLabs/snappy), of which it aims to be a one-to-one substitute (`GeneratorInterface` is the same). Checkout its [README](https://github.com/pontedilana/php-weasyprint/blob/main/README.md) for more information.

## Installation

```bash
composer require xmarcos/php-weasyprint
```

> You need to have WeasyPrint installed and available in your path as well.

## Usage

### Initialization

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use xmarcos\PhpWeasyPrint\Pdf;

$pdf = new Pdf('/usr/bin/weasyprint');
```

### Display the pdf in the browser

```php
$pdf = new Pdf('/usr/bin/weasyprint');
header('Content-Type: application/pdf');
echo $pdf->getOutput('http://www.github.com');
```

### Download the pdf from the browser

```php
$pdf = new Pdf('/usr/bin/weasyprint');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="file.pdf"');
echo $pdf->getOutput('http://www.github.com');
```

### Generate local pdf file from HTML

```php
$pdf = new Pdf('/usr/bin/weasyprint');
$pdf->generateFromHtml('<h1>Bill</h1><p>You owe me money, dude.</p>', '/tmp/bill-123.pdf');
```

### Generate local pdf file from URL

```php
$pdf = new Pdf('/usr/bin/weasyprint');
$pdf->generate('http://www.github.com', '/local/path-to.pdf');
```

### Pass arguments to `weasyprint`

> See weasyprint [Command-line API](https://doc.courtbouillon.org/weasyprint/stable/api_reference.html#command-line-api) for an explanation of these options.

```php
// Type weasyprint -h to see the list of options
$pdf = new Pdf('/usr/bin/weasyprint');
$pdf->setOption('encoding', 'utf8');
$pdf->setOption('media-type', 'screen');
$pdf->setOption('presentational-hints');
$pdf->setOption('optimize-size', 'all');
$pdf->setOption('stylesheet', ['/path/to/first-style.css', '/path/to/second-style.css']);
$pdf->setOption('attachment', ['/path/to/image.png', '/path/to/logo.jpg']);
```

### Reset arguments

Options/arguments can be reset to their initial values with `resetOptions()` method.

```php
$pdf = new Pdf('/usr/bin/weasyprint');
// Set some options
$pdf->setOption('media-type', 'screen');
// ..
// Reset options
$pdf->resetOptions();
```

## Integration Test

There is a very simple integration test included for convenience. It's using the same docker image and exact same python and weasyprint versions that the project that originated this fork requires. Don't expect that to be a very comprehensive suite, just there to ensure it works. That said, run it like this:

```bash
# build image
docker build -t php56 .

# run the image, which runs the unit and integration tests
docker run -it --rm php56

#  Expected Output:

# Time: 102 ms, Memory: 5.50MB
# OK (41 tests, 64 assertions)
# Running Integration Test with 'WeasyPrint version 52.5'
#     PDF Generated: OK
#     PDF Size: 17251
#     PDF Content: OK


```

## Bugs & Support

If you need support for a version of PHP other than 5.6, please see the original library. The only goal of this fork is to support legacy applications running PHP 5.6 until they can be upgraded or sunset. It should be possible to use this code for even older versions, but I don't have the need nor the time to try them.

## Credits

- PhpWeasyPrint has been originally developed by the [Pontedilana](https://www.pontedilana.it) dev team.
- Snappy has been originally developed by the [KnpLabs](http://knplabs.com) team.
