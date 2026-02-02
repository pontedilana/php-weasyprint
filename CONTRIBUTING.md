# Contributing

Thanks for taking the time to contribute! This project is a PHP library that wraps WeasyPrint for PDF generation. We welcome bug reports, documentation improvements, and code contributions.

## Code of Conduct
Please read and follow the Code of Conduct in `CODE_OF_CONDUCT.md`.

## How to Contribute
- **Report bugs**: Open an issue with a clear title and a minimal reproducer. Include your OS version, WeasyPrint version, install method, and a small PHP/HTML/CSS sample.
- **Request features**: Explain the use case and why it belongs in the core library.
- **Submit a pull request**: See the checklist below.

## Development Setup
1) Install PHP 7.4+ and Composer.
2) Install dependencies:
```bash
composer install
```
3) (Optional) Install WeasyPrint 60+ if you need to test end-to-end.

## Useful Commands
```bash
# Run tests
composer unit-tests

# Run static analysis
composer static-analysis

# Check coding style (dry-run)
composer check-cs

# Auto-fix coding style
composer fix-cs
```

## Coding Style
- PSR-4 autoloading; Symfony coding standards via PHP-CS-Fixer.
- Short arrays `[]`, spaced concatenation `'a' . 'b'`.
- Fully qualified native calls (e.g., `\strlen()`).
- Types/casts without spaces: `(int)$value`, `function(string $param): void`.

## Testing
- PHPUnit 9.6; tests are under `tests/Unit/`.
- Use `@covers` annotations (coverage is strict).
- Mock the WeasyPrint binary path in tests to avoid external dependencies.

## Pull Request Checklist
- [ ] Clear title and description; link related issues if applicable.
- [ ] Tests added or updated for behavior changes.
- [ ] `composer check-cs`, `composer static-analysis`, and `composer unit-tests` pass.
- [ ] Documentation updated if public behavior changes.

## Security
If you discover a security issue, please do not open a public issue. Contact the maintainers via the repository’s security contact or open a private advisory.
