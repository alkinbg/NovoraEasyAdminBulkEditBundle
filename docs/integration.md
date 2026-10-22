# Integrating into an EasyAdmin 5 CRUD controller

The library intentionally does **not** decide which entity fields or users may be modified. The application supplies both a field allowlist and an authorization callback. The example below assumes you already have an application `Product` Doctrine entity, an `EDIT` voter for that entity, and a registered EasyAdmin dashboard. Adapt class names and permitted values to your project. This example is illustrative; it is not an end-to-end tested application fixture.

```php
<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Novora\EasyAdminBulkEditBundle\BulkEditAction;
use Novora\EasyAdminBulkEditBundle\BulkEditField;
use Novora\EasyAdminBulkEditBundle\Workflow\BulkEditWorkflow;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->addBatchAction(
            BulkEditAction::new('bulkEdit', 'Bulk edit')
        );
    }

    #[AdminRoute('/bulk-edit', name: 'bulk_edit', options: ['methods' => ['POST']])]
    public function bulkEdit(
        AdminContext $context,
        BatchActionDto $batchActionDto,
        BulkEditWorkflow $workflow,
        AdminUrlGenerator $urls,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PRODUCT_EDITOR');

        return $workflow->start(
            $batchActionDto,
            $context->getEntity()->getFqcn(),
            $urls->setController(self::class)->setAction('bulkEditConfirm')->generateUrl(),
        );
    }

    #[AdminRoute('/bulk-edit-confirm', name: 'bulk_edit_confirm', options: ['methods' => ['GET', 'POST']])]
    public function bulkEditConfirm(
        Request $request,
        AdminContext $context,
        BulkEditWorkflow $workflow,
        AdminUrlGenerator $urls,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PRODUCT_EDITOR');

        return $workflow->confirm(
            $request,
            $context->getEntity()->getFqcn(),
            ['status' => [
                'type' => ChoiceType::class,
                'options' => [
                    'choices' => ['Published' => 'published', 'Draft' => 'draft'],
                ],
            ]],
            ['status' => new BulkEditField('status',
                static function (Product $product, string $status): void {
                    $product->setStatus($status);
                }
            )],
            fn (Product $product): bool => $this->isGranted('EDIT', $product),
            $urls->setController(self::class)->setAction(Action::INDEX)->unset('bulk_selection')->generateUrl(),
        );
    }
}
```

## Security requirements

1. Do not trust an entity class, field name, selected IDs, or redirect destination from request parameters. Pass the entity class from `AdminContext` and use server-defined form configuration.
2. Protect both controller methods with your authorization policy. `BulkSelection` additionally verifies the exact batch action name (`bulkEdit` by default). The action role in this example is only a placeholder. `BulkEditExecutor` also requires explicit **per-entity** checks. If your EasyAdmin application uses separate action permissions or entity access policies, enforce them in these callbacks.
3. Symfony Form's CSRF integration must be enabled in your application. The provided form factory does not disable CSRF.
4. The selection handoff uses a server-side Symfony session scoped to the current browser session with a 15-minute lifetime. The token is consumed on confirmation, including when a database operation fails; retrying then requires a new selection. Concurrent requests are not guaranteed to be exactly-once without additional application-level safeguards.
5. The executor uses the default Doctrine entity manager. If your entity belongs to another manager, adapt the executor integration before using this package.
6. This version intentionally supports simple scalar fields with explicit setters. Do not pass arbitrary client-provided field names or construct setter names dynamically.
7. Configure field-specific validators in the form's `options`, and apply domain invariants in your entities or handlers.
8. Database transaction rollback does not undo external side effects from setter code, listeners, mailers, or remote services. Keep these effects out of the transaction or use an outbox-style design.

The workflow has two steps: a form that selects fields and values, followed by a read-only review and separately CSRF-protected confirmation. The review is **not** an entity-by-entity diff or an audit trail. A bundled Symfony/EasyAdmin HTTP integration fixture tests both steps, but your own authorization policies, validators, database, and infrastructure require application-specific tests.

## Authorization and concurrent confirmation

Re-check permissions on **both** CRUD routes, including the final confirmation request; a role may be revoked after preview. The `canEdit` callback is executed again for every entity when the database transaction runs. Do not cache an earlier authorization result or assume the review page grants permission to commit.

Session-backed token consumption is intentionally **not** advertised as an atomic distributed exactly-once primitive. Default PHP session locking can serialize requests for the same session in some deployments, but it is not universal (custom Redis handlers, multi-node setups and configuration can change the behavior). For applications with financial, irreversible or externally visible effects, add a shared atomic lock or idempotency key backed by a database/lock service, and test your actual deployment. The bundle itself does not guarantee global exactly-once delivery.

Entity-level changes may also race with other requests. Apply optimistic version checks or row locks where lost updates are unacceptable. ORM transactions alone are not sufficient protection from lost updates.

## EasyAdmin's initial batch confirmation

EasyAdmin 5 displays its own confirmation modal for ordinary batch actions by default, **before** the bundle's review and final confirmation screens. Use `BulkEditAction::new()` to skip this redundant first modal **only for the bulk-edit action**, preserving default confirmations on other actions. To skip that generic modal, EasyAdmin exposes `$crud->askConfirmationOnBatchActions(false)` in `configureCrud()`. This setting applies to **all** batch actions in that CRUD controller, including potentially destructive ones. Only disable it when every batch action has its own appropriate validation/confirmation step. The bundle deliberately does not alter that setting automatically.

The bundle includes English and Bulgarian interface translations under the `NovoraEasyAdminBulkEdit` domain. Field labels come from the application Symfony form options; pass a human-readable `label` for every editable field. Configure the application locale as usual.
