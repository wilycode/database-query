# Contributing to WilyCode Database Query

Thanks for your interest in contributing.

## Development

- Use PHP 8.4 or newer and follow the existing `declare(strict_types=1)` style.
- Install development dependencies with `composer install`.
- Run `composer validate --strict` and `composer test` before opening a pull request.
- Add or update tests for changes to placeholders, compilation, or SQL generation.
- Keep the public `QueryInterface`, `CompiledQuery`, and binder contracts compatible unless a breaking change is intentional and documented.
- Update `README.md` (in English) and `CHANGELOG.md` when behavior or the public API changes.
- Keep this package independent from any particular database driver.

## Reporting issues

Open an issue at https://github.com/wilycode/database-query/issues with an example query, expected SQL and parameters, actual behavior, and PHP version. Do not include credentials or real production data.

## Pull requests

Prefer focused changes with clear test coverage. Please describe changes and any backwards compatibility implications.
