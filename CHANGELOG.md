# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog(https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning(https://semver.org/spec/v2.0.0.html).

## Unreleased
### Added
- Add `--timeout` option to the WeasyPrint command-line call by default. This improves consistency with the internal process timeout already applied by Symfony Process. If you're running WeasyPrint inside a worker, queue, or other timeout-managed environment, you can disable it using `$pdf->disableTimeout()` or `$pdf->setTimeout(null)`. (#15)
- Add `disableTimeout()` method to easily disable the new CLI timeout behavior

### Security
- Update `symfony/process` minimal version to mitigate [CVE-2024-51736](https://github.com/advisories/GHSA-qq5c-677p-737q)

## 1.5.0 - 2024-11-04
### Added
- Support WeasyPrint 63.0 new `srgb` option
- Add support for PHP 8.4

## 1.4.0 - 2023-11-20
### Changed
- Add support for Symfony 7.0 and PHP 8.3

## 1.3.0 - 2023-10-07
### Added
- Support WeasyPrint 60.0 new `timeout` option

## 1.2.0 - 2023-05-11
### Added
- Support WeasyPrint 59.0b1 new options

## 1.1.1 - 2023-04-27
### Security
- Implement countermeasures for CVE-2023-28115

## 1.1.0 - 2023-04-03
### Added
- Support WeasyPrint 58 new option (--pdf-forms)
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
