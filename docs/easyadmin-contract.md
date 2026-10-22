# EasyAdmin 5 integration contract

This document records the supported integration boundaries of Novora EasyAdmin Bulk Edit.

## EasyAdmin responsibilities

- EasyAdmin registers batch actions via `Actions::addBatchAction()` and injects `BatchActionDto` into the POST action.
- A CRUD controller must define the bulk action and confirmation route using `#[AdminRoute]`.
- Use `Actions::setPermission()` and controller-level authorization for access to the action. Display permission alone is not sufficient for entity-specific authorization.
- Normal EasyAdmin layouts display Symfony `success` flash messages. Applications overriding the layout must preserve flash rendering.

## Bundle responsibilities

- Verify that batch name, entity class and CSRF token match the trusted CRUD context.
- Keep selected IDs server-side, avoid trusting confirmation POST identifiers, and validate the form and final confirmation CSRF tokens.
- Apply only explicitly selected, allowlisted scalar fields.
- Require a per-entity authorization callback and verify all entities before invoking any field handler.
- Apply Doctrine entity setters and flush inside a transaction, rolling back database changes on error.
- Create success feedback only after the transaction commits.

## Application responsibilities

- Authorize entry to **both** CRUD routes and re-check access on final confirmation.
- Provide real form constraints, setters, business invariants, and a per-entity edit policy.
- Select an appropriate EntityManager, and guard against lost updates for versioned/concurrent resources.
- Use durable idempotency/locking mechanisms where exactly-once processing is required. Session-backed one-use tokens alone do not provide that guarantee.
- Avoid externally visible side effects inside entity setters or Doctrine listeners unless their failure semantics are explicitly handled.

## Confirmation behavior

`BulkEditAction::new()` skips the generic EasyAdmin confirmation **only for this action**. Do not switch off `Crud::askConfirmationOnBatchActions()` for the whole CRUD unless every other batch action has equivalent confirmation safeguards.

## Verification scope

The repository's HTTP integration tests exercise the EasyAdmin route, selection, Symfony form, review, final CSRF confirmation, authorization changes, flash, and replay. They do not certify browser-specific rendering of a host application's custom templates or multi-node concurrency behavior. Validate these separately in the consuming application.

References: [EasyAdmin Actions](https://symfony.com/doc/current/EasyAdminBundle/actions.html), [EasyAdmin Security](https://symfony.com/doc/current/EasyAdminBundle/security.html).
