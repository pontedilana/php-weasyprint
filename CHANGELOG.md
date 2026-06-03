# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog(https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning(https://semver.org/spec/v2.0.0.html).

## Unreleased

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
