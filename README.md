# Novora EasyAdmin Bulk Edit Bundle

Explicit, validated bulk editing for EasyAdmin 5 and Doctrine ORM. Administrators select records from the normal EasyAdmin index, choose which allowed fields to change, review the exact proposed values, and explicitly confirm the operation.

## Requirements

- PHP 8.4 or newer (tested on PHP 8.4 and 8.5)
- Symfony 7.4 or 8.x
- EasyAdmin 5.x and Doctrine ORM 3.x
- Configured Symfony session, Form CSRF protection, Doctrine entity manager and EasyAdmin routes

## Features

- Native EasyAdmin batch-action integration without patching vendor code
- **Only checked fields change**; server-defined field allowlist and Symfony Form validation
- Separate review and final confirmation screens; review data remains server-side
- Two separate CSRF checks (EasyAdmin batch action and Symfony confirmation form)
- Time-limited, session-scoped selection tokens (15 minutes; maximum 100 IDs)
- Explicit per-entity authorization callback and matching CRUD entity/action checks
- A single database transaction with ORM lifecycle callbacks; rollback on failure
- Tests for the real Symfony/EasyAdmin HTTP workflow, form validation, invalid tokens and replay attempts

## Installation

After the package is indexed by Packagist:

```bash
composer require novora/easyadmin-bulk-edit-bundle:^1.0
```

Until Packagist registration, add this public GitHub repository as a Composer VCS repository first. Symfony Flex should enable the bundle automatically; in applications without Flex, register `Novora\\EasyAdminBulkEditBundle\\NovoraEasyAdminBulkEditBundle` in `config/bundles.php`. EasyAdmin's own Twig and routing bundle dependencies must also be enabled.

## EasyAdmin integration

See **[the complete CRUD integration example](docs/integration.md)** for the controller action configuration, `#[AdminRoute]` attributes, typed field handlers, authorization checks and a sample `ChoiceType` field.

```php
$fields = [
    'status' => new BulkEditField('status',
        static function (Product $product, string $status): void {
            $product->setStatus($status);
        }
    ),
];
```

The bundle deliberately does not guess setters or permit all entity fields. The caller must specify the entity class from the trusted EasyAdmin context, define the allowed form fields and field handlers, check permissions for the action **and every selected row**, and configure application-specific validation.

## Scope and safety

- Only simple scalar values (`string`, `int`, `float`, `bool`, `null`) are supported by the server-side review screen in this version. Complex Doctrine associations, custom object-valued fields and large asynchronous batches are deliberately outside the scope.
- The executor uses the default Doctrine entity manager. Do not use it for entities mapped to a different entity manager without adapting the integration.
- Doctrine lifecycle callbacks are honored because the executor updates managed entities, not DQL `UPDATE` statements.
- The transaction rolls back on exceptions. A failed operation clears the EntityManager, detaching other managed objects in that request. External side effects from listeners or handlers **cannot** be rolled back by the database.
- The bundle does not automatically lock rows or resolve concurrent changes. Applications that need lost-update protection should use Doctrine versioning or appropriate database locks.
- A consumed selection cannot be retried using the same token after an exception. Session-backed single-use tokens are **not a guarantee of exactly-once execution across concurrent requests or multiple application nodes**. Review your session storage and application idempotency requirements.
- The review screen displays requested field/value changes and the number of selected records; it is not an item-by-item before/after diff, an audit log or a dry-run of business side effects.

## Development

```bash
composer install
composer validate --strict
vendor/bin/phpunit
vendor/bin/phpstan analyse src --level=8 --no-progress
```

CI runs PHPUnit and PHPStan on PHP 8.4 and PHP 8.5. The integration fixture boots a real Symfony container with EasyAdmin, Doctrine and Twig and tests the batch → form → review → confirmation HTTP sequence.

MIT License. Copyright (c) 2026 Alkin Fehim.
