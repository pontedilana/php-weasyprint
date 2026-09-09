# Upgrading

This document describes the backward-incompatible changes between major versions
and how to adapt your code.

## From 2.x to 3.0

### Requirements

- **PHP 8.3+** is now required. Support for PHP 7.4, 8.0, 8.1 and 8.2 has been dropped.
- **`symfony/process` 6.4, 7.4 or 8.0** is required. Support for Symfony 5.4 (end of life) has been dropped.

If you run on an older PHP or Symfony version, stay on the `2.x` branch until you can upgrade.

### Option value validation

`pdf-variant` is now validated against the values returned by
`WeasyPrintOptionValues::getAllowedValues('pdf-variant')`. Unsupported values throw
`InvalidArgumentException` in the constructor, `setOption()`, `setOptions()` and
per-call options, before WeasyPrint is executed. Arrays are checked element by element.

Use a supported value or a `PdfVariant` enum case. If your application previously
handled these failures only as process errors, update it to handle
`InvalidArgumentException` during option configuration as well. Free-form options
such as `media-type` and `pdf-version` are not restricted to enum cases.

### Backed enums for option values (optional)

`Enum\PdfVariant`, `Enum\MediaType` and `Enum\PdfVersion` are now available and accepted by `setOption()`,
`setOptions()` and per-call options. This is **additive** — plain strings keep working —
but it is the recommended way to set those options:

```php
use Pontedilana\PhpWeasyPrint\Enum\PdfVariant;

$pdf->setOption('pdf-variant', PdfVariant::PdfA3b); // instead of 'pdf/a-3b'
```

### Removed WeasyPrint options

The deprecated options `format` and `resolution` have been removed: they
are no longer part of the WeasyPrint 60+ CLI (PNG output and these options were dropped
upstream). Passing any of them to `setOption()` / `setOptions()` or as a per-call option now
throws an `InvalidArgumentException`.

- `format` / `resolution`: WeasyPrint only produces PDF since version 53; there is no
  replacement. Remove them from your option arrays.

`optimize-size` remains accepted for compatibility with WeasyPrint 60. It was
deprecated in WeasyPrint 59 and removed in WeasyPrint 61. For newer versions,
use `optimize-images` for image optimization.

### Shell-free command execution

The WeasyPrint binary is now executed from an argument array (`new Process([...])`)
instead of a shell command string (`Process::fromShellCommandline()`). Execution no
longer goes through a shell, which removes shell-command injection as a class of
vulnerability.

**If you only use the public API** (`generate()`, `generateFromHtml()`, `getOutput()`,
`getOutputFromHtml()`), the method signatures in `GeneratorInterface` are unchanged.
Option validation, removed options and error handling still change as described in
this guide.

**If you extend `AbstractGenerator` or `Pdf`**, the execution hooks changed as follows:

#### `executeCommand()` now receives an array

The signature changed from a shell string to an argument list:

```php
// Before (2.x)
protected function executeCommand(string $command): array
{
    // ...
}

// After (3.0)
protected function executeCommand(array $command): array
{
    // $command is now a list<string>, e.g. ['/usr/bin/weasyprint', '--encoding', 'utf-8', 'in.html', 'out.pdf']
    // ...
}
```

If you override it to add behavior around execution, delegate to the parent to
preserve the configured environment and timeout:

```php
protected function executeCommand(array $command): array
{
    // Add custom behavior here.
    return parent::executeCommand($command);
}
```

If you execute the process yourself, pass the argument array to `new Process()`
and supply your own environment and timeout. The parent's `$env` and `$timeout`
properties are private.

#### Command customization moves to `buildCommandArray()`

Overrides of `getCommand()` or `buildCommand()` no longer alter the arguments passed
to WeasyPrint. `generate()` no longer calls `getCommand()`; it calls
`buildCommandArray()` for execution and `buildCommand()` for the log representation.

Move any execution customization to the new protected method:

```php
protected function buildCommandArray(string $binary, string $input, string $output, array $options = []): array
{
    // Apply custom options or adjust the input/output paths here.
    return parent::buildCommandArray($binary, $input, $output, $options);
}
```

Do not shell-escape the array elements. If you change the execution arguments,
keep any custom log representation consistent with them.

#### Binary check moved to `checkBinary()`

The executability check was extracted out of `getEscapedBinary()` into a new
`checkBinary()` method, which is called by both the array (execution) and string
(logging) code paths.

If you overrode `getEscapedBinary()` only to skip the executable check (e.g. a test
double using a stub binary), override `checkBinary()` instead:

```php
// Before (2.x)
protected function getEscapedBinary(string $binary): string
{
    return \escapeshellarg($binary); // skip is_executable() check
}

// After (3.0)
protected function checkBinary(string $binary): void
{
    // skip the is_executable() check
}
```

### Command string representation

`getCommand()` and `buildCommand()` still return the command as a shell-escaped
string, but that string is now used **only** for logging and exception messages — it is
no longer what gets executed. Existing display and logging calls remain available;
execution customizations must use `buildCommandArray()` as described above.

### Replacing existing output files

With `overwrite=true`, an existing output file remains in place until generation
succeeds. The process writes to a temporary file in the destination directory;
only a successful process with a non-empty output replaces the original file.
A failed generation removes the temporary output and preserves the original.

The protected `prepareOutput()` method now validates the destination without
deleting it. Subclasses overriding this method must also preserve existing files.
During replacement, command hooks and logs refer to the temporary output path;
`generate()` still publishes the result at the requested destination.

### Error handling corrections

Every non-zero process exit code now throws `RuntimeException`, even when stderr is
empty. Previously, a failed process could be treated as successful if it left a
non-empty output file. Callers must handle the exception instead of consuming a
partial result.

If an attachment URL cannot be read, the library now throws
`Pontedilana\PhpWeasyPrint\Exception\CouldNotReadFileContentException` (a
`RuntimeException`). Previously, if the application did not convert PHP warnings
into exceptions, the failed read could produce an empty attachment. Handle this
exception or correct the attachment URL before retrying. PHP stream warnings may
still be emitted by the failed read.

### Development tooling

The repository's unit and integration suites now use PHPUnit 12.5, with PHP
attributes for metadata and static data providers. This only affects contributors
and projects reusing these tests: Composer does not install this library's
`require-dev` dependencies in consuming applications.

Run `composer unit-tests` for unit tests and set `WEASYPRINT_BINARY` when running
`composer integration-tests`. Both suites fail on warnings, deprecations and notices.
