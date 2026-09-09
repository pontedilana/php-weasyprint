# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog(https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning(https://semver.org/spec/v2.0.0.html).

## Unreleased
### Added
- Backed enums `Enum\PdfVariant`, `Enum\MediaType` and `Enum\PdfVersion` for the most common option values; `setOption()`/`setOptions()` and per-call options now accept a `BackedEnum` and convert it to its scalar value. `WeasyPrintOptionValues` derives its allow-list from `PdfVariant` (`PdfVersion` is a convenience only — WeasyPrint does not constrain `pdf-version`)
- New `buildCommandArray()` and `checkBinary()` protected methods on `AbstractGenerator`

### Removed
- **[BC break]** Drop support for PHP 7.4, 8.0, 8.1 and 8.2; the minimum required version is now PHP 8.3
- **[BC break]** Drop support for Symfony 5.4 (end of life); supported `symfony/process` versions are now 6.4, 7.4 and 8.0
- **[BC break]** Remove the deprecated WeasyPrint options `format` and `resolution`, which are no longer supported by WeasyPrint 60+ (PNG output and these options were removed upstream). Setting them now throws an `InvalidArgumentException`

### Changed
- **[BC break]** Validate `pdf-variant` values against `WeasyPrintOptionValues`; unsupported values now throw `InvalidArgumentException` when supplied to the constructor, `setOption()`, `setOptions()` or per-call options, before WeasyPrint is executed.
- Retain the deprecated `optimize-size` option for compatibility with WeasyPrint 60; it was deprecated in WeasyPrint 59 and removed in WeasyPrint 61
- **[BC break]** The WeasyPrint process is now executed from an argument array (`new Process([...])`) instead of a shell command string (`Process::fromShellCommandline()`). Execution no longer goes through a shell, removing shell-command injection as a class of vulnerability. The escaped string form (`getCommand()` / `buildCommand()`) is retained for logging and exception messages only.
- **[BC break]** Overrides of `getCommand()` or `buildCommand()` no longer customize the executed command; subclasses must override `buildCommandArray()` to change execution arguments. `generate()` no longer calls `getCommand()`.
- **[BC break]** `executeCommand()` signature changed from `executeCommand(string $command)` to `executeCommand(array $command)`. Subclasses overriding it must be updated.
- **[BC break]** The binary executability check moved out of `getEscapedBinary()` into the new `checkBinary()` method. Subclasses overriding `getEscapedBinary()` to bypass the check (e.g. test doubles) must override `checkBinary()` instead.
- Upgrade development tooling to PHPUnit 12.5; this does not add a PHPUnit dependency to applications installing the library.

### Fixed
- Preserve existing output files when overwriting fails; generate to a sibling temporary file and replace the destination only after process and output checks succeed.
- Preserve per-call `null`, `false` and empty-array option overrides without restoring instance defaults or creating empty attachments and stylesheets.
- Reject every non-zero process exit code with `RuntimeException`, including failures with empty stderr; a non-empty partial output file is no longer returned as a successful result.
- Throw `CouldNotReadFileContentException` when an attachment URL cannot be read, instead of continuing with an empty attachment when PHP warnings are not converted to exceptions.

## 2.7.0 - 2026-06-03
### Added
- Support WeasyPrint 69.0 new `--output-intent` option

## 2.6.0 - 2026-05-25
### Security
- Fix potential SSRF and local file disclosure: option URLs are fetched server-side only when their scheme is allowed (`http`, `https` by default, configurable via the new `$allowedSchemes` constructor argument)
- Fix potential arbitrary file deletion at shutdown: `removeTemporaryFiles()` now only deletes files located inside the temporary folder
- Fix PHAR deserialization via the output filename (CVE-2023-28115 case-insensitive bypass): the output path is now validated against a scheme allow-list instead of a case-sensitive `phar://` check

## 2.5.1 - 2026-05-25
### Security
- Fix potential shell-command injection through the WeasyPrint binary path: `buildCommand()` now verifies the binary is executable on the unescaped path and shell-escapes it before use
- Update `symfony/process` minimal version to mitigate [CVE-2026-24739](https://github.com/advisories/GHSA-r39x-jcww-82v6)

## 2.5.0 - 2026-04-03
### Added
- Support WeasyPrint options (`--info`, `--quiet`, `--verbose`, `--debug`, `--version`, `--no-http-redirects`, `--fail-on-http-errors`)

## 2.4.0 - 2026-01-20
### Added
- Support WeasyPrint 68.0 new `--attachment-relationship` and `--xmp-metadata` options

## 2.3.0 - 2025-12-02
### Added
- Support WeasyPrint 67.0 new `--allowed-protocols` option
- Support Symfony 8.0
- More tests

## 2.2.0 - 2025-11-19
### Added
- Support PHP 8.5
- More tests

## 2.1.0 - 2025-07-24
### Added
- Support WeasyPrint 66.0 new `--pdf-tags` option

## 2.0.0 - 2025-04-10
### Added
- Add `--timeout` option to the WeasyPrint command-line call by default. This improves consistency with the internal process timeout already applied by Symfony Process. If you're running WeasyPrint inside a worker, queue, or other timeout-managed environment, you can disable it using `$pdf->disableTimeout()` or `$pdf->setTimeout(null)`. (#15)
- Add `disableTimeout()` method to easily disable the new CLI timeout behavior

### Security
- Update `symfony/process` minimal version to mitigate [CVE-2024-51736](https://github.com/advisories/GHSA-qq5c-677p-737q)

## 1.5.0 - 2024-11-04
### Added
- Support WeasyPrint 63.0 new `--srgb` option
- Add support for PHP 8.4

## 1.4.0 - 2023-11-20
### Changed
- Add support for Symfony 7.0 and PHP 8.3

## 1.3.0 - 2023-10-07
### Added
- Support WeasyPrint 60.0 new `--timeout` option

## 1.2.0 - 2023-05-11
### Added
- Support WeasyPrint 59.0b1 new options

## 1.1.1 - 2023-04-27
### Security
- Implement countermeasures for CVE-2023-28115

## 1.1.0 - 2023-04-03
### Added
- Support WeasyPrint 58 new option (`--pdf-forms`)
### Changed
- Always pass through timeout when creating a process (#7)

## 1.0.1 - 2023-01-17
### Fixed
- Fix logging of errors

## 1.0.0 - 2023-01-16
### Fixed
- Fix handling of repeatable options (attachment and stylesheet)

### Changed
- Bump symfony/process up to ^6.2

### Removed
- Remove Image class
- Remove Version class
- Remove support for Symfony 4.4

## 0.13.0 - 2023-01-16
### Added
- Support WeasyPrint 56 new options

### Deprecated
- Deprecate image generator
- Deprecate Version class

## 0.12.0 - 2022-12-09
### Changed
- Add support for PHP 8.2

## 0.11.0 - 2022-02-28
### Changed
- Bump symfony/process up to ^6.0 and psr/log up to ^3.0

## 0.10.1 - 2021-12-29
### Fixed
- Refactor tests to use `onlyMethods()`

## 0.10.0 - 2021-12-29
### Changed
- Unset `--format` option in `Pdf` class which is deprecated in WeasyPrint 53 and removed in WeasyPrint 54

## 0.9.0 - 2021-07-16
### Added
- First public release
