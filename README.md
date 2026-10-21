# Novora EasyAdmin Bulk Edit Bundle

A Symfony library for explicit, transactional bulk editing of Doctrine entities selected in EasyAdmin 5.

> **Development status:** Core executor and batch-selection validation are implemented. This is not yet a complete EasyAdmin bulk-edit workflow or a stable 1.0 release. Do not publish or deploy this branch as a finished product.

## Requirements

PHP 8.4+, Symfony 7.4/8, EasyAdmin 5, Doctrine ORM 3.

## Core API

`BulkEditExecutor::execute()` receives an entity class, selected IDs, an explicit whitelist of field handlers, values that have already passed Symfony Form validation, and a mandatory per-entity authorization callback. It fetches and authorizes **all selected rows before changing any**, then changes managed entities using the supplied handlers, flushes, and commits in a single database transaction. Exceptions cause a rollback.

```php
use Novora\EasyAdminBulkEditBundle\BulkEditField;

$count = $executor->execute(
    Product::class,
    [15, 16],
    [
        'status' => new BulkEditField('status', static function (Product $product, string $value): void {
            $product->setStatus($value);
        }),
    ],
    ['status' => 'published'], // Prevalidated by your Symfony form.
    fn (Product $product): bool => $authorizationChecker->isGranted('EDIT', $product),
);
```

**Security obligations for integrations:** Check the current CRUD context's entity class, validate EasyAdmin's batch-action CSRF token, authorize the batch action, and authorize every entity. Never derive field handlers or entity classes directly from user input. Setters are application-specific and values must be validated in Symfony Form before executing. The executor does not lock rows against concurrent changes, enforce optimistic concurrency or perform field validation by itself. A bulk-edit UI and complete EasyAdmin controller integration are not included yet.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse src --level=8
```

MIT license. Copyright (c) 2026 Alkin Fehim.

## Two-request selection handoff

`BulkEditSelectionStore` stores selected IDs in the server-side Symfony session and returns a random one-time token. Use it after validating the `BatchActionDto` through `BulkSelection`. On confirmation, call `consume($token, Product::class)` and then validate form values and authorization before invoking the executor. Tokens expire after 15 minutes and are consumed once; this prevents trusting entity IDs submitted in the confirmation form. **Note:** CSRF protection for the confirmation form is still required separately. This is an integration primitive, not a complete ready-made EasyAdmin form.

### Transaction safety and session behavior

The executor clears the Doctrine EntityManager after rolling back a failed transaction. This avoids continued use of dirty managed entities, **but it also detaches unrelated entities managed by that EntityManager**; call it within a dedicated request/operation. It does not automatically retry failed transactions, check concurrent writes, or use row-level locks. For systems requiring concurrency protection, implement application-specific optimistic locking or pessimistic locks and test the chosen database platform.

`BulkEditSelectionStore::inspect()` reads the selection without consuming it. Use this when displaying the form or handling a validation failure. `consume()` is intended for an already validated, CSRF-protected confirmation. The application must handle retry behavior explicitly if persistence fails after consumption. Sessions must be configured and enabled.
