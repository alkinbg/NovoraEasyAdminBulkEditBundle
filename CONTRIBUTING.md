# Contributing

Thank you for helping improve Novora EasyAdmin Bulk Edit Bundle.

## Before opening an issue

Check existing issues and the README for supported features and known limitations. Open a bug report for reproducible defects, or a feature request for a concrete use case. Please **never post security vulnerabilities publicly**; follow [SECURITY.md](SECURITY.md).

## Development

Requirements: PHP 8.4+, Composer 2, the PHP SQLite extension, and a compatible Symfony/EasyAdmin dependency set.

```bash
composer install
composer validate --strict
vendor/bin/phpunit
vendor/bin/phpstan analyse src --level=8 --no-progress
```

Integration tests boot a Symfony kernel, load EasyAdmin routes and render Twig; do not replace them with mocks for changes affecting HTTP workflows.

## Pull requests

1. Open a focused PR against `main`, ideally referencing an issue. Explain the user-facing problem, not just the implementation.
2. Include regression tests for bug fixes. For UI, authorization, CSRF, session or ORM behavior, include an HTTP/integration test where feasible.
3. Preserve backwards compatibility for the 1.x API unless a change is explicitly coordinated as a breaking release.
4. Verify changes under the minimum and latest supported dependencies. CI tests PHP 8.4 (lowest and highest dependencies) and PHP 8.5 (highest).
5. Update README, integration examples and CHANGELOG when behavior or public APIs change.
6. Keep pull requests free of unrelated formatting, generated dependencies, secrets, and unrelated artifacts.

The maintainer reviews contributions for safety, maintainability, tests, security, and fit with the project's focused scope. A PR is not guaranteed to be merged. The MIT license applies to contributions accepted into the repository.

## Coding expectations

Use `declare(strict_types=1);`, explicit types, narrow public APIs, and straightforward Symfony/Doctrine integration. Never trust request-supplied entity classes, IDs or field names. Enforce authorization per entity; avoid DQL bulk UPDATE that bypasses ORM lifecycle behavior. Avoid broad architectural abstractions or features unrelated to safe bulk editing.

Please follow our [Code of Conduct](CODE_OF_CONDUCT.md).
