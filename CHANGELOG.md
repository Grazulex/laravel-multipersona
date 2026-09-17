# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.1.0] - 2026-09-17

### Added

- Laravel 13 support (`illuminate/support` `^12.0|^13.0`).

### Changed

- PHP 8.3 is now the minimum supported version.
- Development dependencies updated: `orchestra/testbench` `^10.0|^11.0`, `pestphp/pest` `^3.8|^4.0`, `pestphp/pest-plugin-laravel` `^3.2|^4.0`.
- CI matrix now runs PHP 8.3 / 8.4 against Laravel 12 and 13 (`prefer-lowest` and `prefer-stable`); the release workflow targets Laravel 13.
- README and installation guide updated to reflect the supported Laravel versions.

### Removed

- Laravel 11 support (end of life).

### Fixed

- `rector.php` no longer passes the removed `strictBooleans` option to `withPreparedSets()`, which broke `composer rector` with Rector 2.x.

## [v1.0.0] - 2025-08-05

### Added

- Initial release.

[v1.1.0]: https://github.com/Grazulex/laravel-multipersona/compare/v1.0.0...v1.1.0
[v1.0.0]: https://github.com/Grazulex/laravel-multipersona/releases/tag/v1.0.0
