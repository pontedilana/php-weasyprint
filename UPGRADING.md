# Upgrading

This document describes the backward-incompatible changes between major versions
and how to adapt your code.

## From 2.x to 3.0

### Requirements

- **PHP 8.3+** is now required. Support for PHP 7.4, 8.0, 8.1 and 8.2 has been dropped.
- **`symfony/process` 6.4, 7.4 or 8.0** is required. Support for Symfony 5.4 (end of life) has been dropped.

If you run on an older PHP or Symfony version, stay on the `2.x` branch until you can upgrade.

### Backed enums for option values (optional)

`Enum\PdfVariant` and `Enum\MediaType` are now available and accepted by `setOption()`,
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
`getOutputFromHtml()`), nothing changes — the Snappy-compatible `GeneratorInterface` is
unchanged.

**If you extend `AbstractGenerator` or `Pdf`**, the following protected methods changed:

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

If you override it to run the process yourself, build the process from the array:

```php
$process = new \Symfony\Component\Process\Process($command, null, $this->env, null, $this->timeout);
```

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
no longer what gets executed. If you relied on these methods for display or logging,
their output is unchanged.
