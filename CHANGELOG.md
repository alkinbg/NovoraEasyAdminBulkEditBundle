# Changelog

## 1.0.0 — Initial stable release

- EasyAdmin 5 batch-action handoff with exact action, entity-class and CSRF validation.
- Two-step form → read-only review → separately CSRF-protected confirmation workflow.
- Per-field opt-in edits using Symfony Forms and explicit field handlers.
- Server-side session review and one-use, expiring selection tokens.
- Explicit per-entity authorization and transactional Doctrine ORM updates with rollback.
- Safe error handling for invalid selections and refused updates.
- Integration tests with the real EasyAdmin router, Symfony container, Twig, Form CSRF and SQLite.
- CI for supported PHP versions and minimal/latest dependency constraints.

See the README for documented boundaries, concurrency considerations and supported field types.
