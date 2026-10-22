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

## Installation in an existing Symfony application

Use **`composer require` in your Symfony application directory**, not `composer install` in the application to add a new dependency:

```bash
composer require novora/easyadmin-bulk-edit-bundle:^1.0
```

The package is published on [Packagist](https://packagist.org/packages/novora/easyadmin-bulk-edit-bundle), and version `v1.0.1` was independently resolved and downloaded in a clean Composer smoke test. You do **not** need to configure a GitHub VCS repository.

With Symfony Flex, the bundle is automatically registered in `config/bundles.php`. Verify the entry exists:

```php
Novora\EasyAdminBulkEditBundle\NovoraEasyAdminBulkEditBundle::class => ['all' => true],
```

Without Flex, add that entry manually. Ensure your application has a configured EasyAdmin dashboard, Doctrine entity manager, Symfony sessions and CSRF enabled. Installing the Composer dependency **does not automatically add a bulk-edit action to every CRUD controller**: explicitly integrate it using the example below.

### `composer require` vs `composer install`

- `composer require novora/easyadmin-bulk-edit-bundle:^1.0` **adds** the bundle to an existing application's `composer.json` and resolves its dependencies.
- `composer install` **installs existing locked dependencies** of a project. Run it after cloning an application or this bundle's source repository for development; it does not add the package to an unrelated application.
- In the **bundle repository itself**, run `composer install` only to set up the contributor/test environment; it is not how users install the bundle into their projects.

Verification from an existing working Symfony application:

```bash
composer show novora/easyadmin-bulk-edit-bundle
php bin/console about
```

The second command requires your application to have its normal Symfony Runtime, autoload and configuration in place. Installing this bundle alone is not a replacement for creating a Symfony application.

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

## Interface and translations

The form and review screens use native EasyAdmin/Bootstrap layout and include English and Bulgarian translations (`NovoraEasyAdminBulkEdit` domain). The application locale determines the language. Use the `label` option in each allowed field definition for a meaningful human-readable field label. The first checkbox chooses whether a property changes; the value control sets the new value, including a valid `false` for boolean fields.

Use `BulkEditAction::new()` in `configureActions()` to skip the first generic confirmation **only for bulk edit**; the bundle provides its own review and final confirmation. EasyAdmin 5 shows its own generic confirmation modal for other batch actions by default. To disable it, EasyAdmin provides `$crud->askConfirmationOnBatchActions(false)` in `configureCrud()`. **This affects every batch action in that CRUD controller**. Only use it if all configured batch actions implement adequate confirmation themselves. This bundle does not disable other actions' confirmations automatically.

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
