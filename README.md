# PhpWeasyPrint

PhpWeasyPrint is a PHP library allowing PDF generation from a URL or an HTML page.
It's a wrapper for [WeasyPrint](https://weasyprint.org/), a smart solution helping web developers to create PDF documents, available everywhere Python runs.

You will have to download and install WeasyPrint to use PhpWeasyPrint (version 56 or greater is recommended).

This library is massively inspired by [KnpLabs/snappy](https://github.com/KnpLabs/snappy), of which it aims to be a one-to-one substitute (`GeneratorInterface` is the same).
See "[Differences with Snappy](#differences-with-snappy)" section to see how the two differs

## Installation using [Composer](https://getcomposer.org/)

```bash
$ composer require pontedilana/php-weasyprint
```

## Usage

### Initialization

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Pontedilana\PhpWeasyPrint\Pdf;

$pdf = new Pdf('/usr/local/bin/weasyprint');

// or you can do it in two steps
$pdf = new Pdf();
$pdf->setBinary('/usr/local/bin/weasyprint');
```

### Display the pdf in the browser

```php
$pdf = new Pdf('/usr/local/bin/weasyprint');
header('Content-Type: application/pdf');
echo $pdf->getOutput('https://www.github.com');
```

### Download the pdf from the browser

```php
$pdf = new Pdf('/usr/local/bin/weasyprint');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="file.pdf"');
echo $pdf->getOutput('https://www.github.com');
```

### Generate local pdf file

```php
$pdf = new Pdf('/usr/local/bin/weasyprint');
$pdf->generateFromHtml('<h1>Bill</h1><p>You owe me money, dude.</p>', '/tmp/bill-123.pdf');
```

### Pass options to PhpWeasyPrint

```php
// Type weasyprint -h to see the list of options
$pdf = new Pdf('/usr/local/bin/weasyprint');
$pdf->setOption('encoding', 'utf8');
$pdf->setOption('media-type', 'screen');
$pdf->setOption('presentational-hints');
$pdf->setOption('optimize-size', 'all');
$pdf->setOption('stylesheet', ['/path/to/first-style.css', '/path/to/second-style.css']);
$pdf->setOption('attachment', ['/path/to/image.png', '/path/to/logo.jpg']);
```

### Reset options
Options can be reset to their initial values with `resetOptions()` method.

```php
$pdf = new Pdf('/usr/local/bin/weasyprint');
// Set some options
$pdf->setOption('media-type', 'screen');
// ..
// Reset options
$pdf->resetOptions();
```

## Differences with Snappy

Although PhpWeasyPrint and Snappy are interchangeable, there are a couple of differences between the two, due to WeasyPrint cli API:

* WeasyPrint doesn't support multiple sources to be merged in one single output pdf, so only one input source (string or URL) is accepted in PhpWeasyPrint;
* WeasyPrint version >= 53 doesn't generate images, so image generation from HTML string or URL is possible only with WeasyPrint lower versions and an unsupported PhpWeasyPrint version (`Pontedilana\PhpWeasyPrint\Image` has been successfully tested with Weasyprint 52.5 on PhpWeasyPrint 0.13.0).

## Bugs & Support

If you found a bug please fill a [detailed issue](https://github.com/pontedilana/php-weasyprint/issues) with all the following points.
If you need some help, please at least provide a complete reproducer, so we could help you based on facts rather than assumptions.

* OS and its version
* WeasyPrint, its version and how you installed it
* A complete reproducer with relevant PHP and html/css/js code

If your reproducer is big, please try to shrink it. It will help everyone to narrow the bug.

## Credits

PhpWeasyPrint has been originally developed by the [Pontedilana](https://www.pontedilana.it) dev team.  
Snappy has been originally developed by the [KnpLabs](https://knplabs.com) team.

