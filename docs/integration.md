# Integrating into an EasyAdmin 5 CRUD controller

The library intentionally does **not** decide which entity fields or users may be modified. The application supplies both a field allowlist and an authorization callback. The example below assumes you already have an application `Product` Doctrine entity, an `EDIT` voter for that entity, and a registered EasyAdmin dashboard. Adapt class names and permitted values to your project. This example is illustrative; it is not an end-to-end tested application fixture.

```php
<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
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
            Action::new('bulkEdit', 'Bulk edit')
                ->linkToCrudAction('bulkEdit')
        );
    }

    public function bulkEdit(
        AdminContext $context,
        BatchActionDto $batchActionDto,
        BulkEditWorkflow $workflow,
        AdminUrlGeneratorInterface $urls,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_PRODUCT_EDITOR');

        return $workflow->start(
            $batchActionDto,
            $context->getEntity()->getFqcn(),
            $urls->setController(self::class)->setAction('bulkEditConfirm')->generateUrl(),
        );
    }

    public function bulkEditConfirm(
        Request $request,
        AdminContext $context,
        BulkEditWorkflow $workflow,
        AdminUrlGeneratorInterface $urls,
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
            $urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }
}
```

## Security requirements

1. Do not trust an entity class, field name, selected IDs, or redirect destination from request parameters. Pass the entity class from `AdminContext` and use server-defined form configuration.
2. Protect both controller methods with your authorization policy. The action role in this example is only a placeholder. `BulkEditExecutor` also requires explicit **per-entity** checks. If your EasyAdmin application uses separate action permissions or entity access policies, enforce them in these callbacks.
3. Symfony Form's CSRF integration must be enabled in your application. The provided form factory does not disable CSRF.
4. The selection handoff uses an authenticated server-side Symfony session with a 15-minute lifetime. The token is one-time-use, including on a failed database transaction.
5. The executor uses the default Doctrine entity manager. If your entity belongs to another manager, adapt the executor integration before using this package.
6. This version intentionally supports simple scalar fields with explicit setters. Do not pass arbitrary client-provided field names or construct setter names dynamically.
7. Configure field-specific validators in the form's `options`, and apply domain invariants in your entities or handlers.
8. Database transaction rollback does not undo external side effects from setter code, listeners, mailers, or remote services. Keep these effects out of the transaction or use an outbox-style design.

The confirmation page displays the fields and a count of selected records. It is **not** an entity-by-entity diff or an audit trail. The project does not currently claim full end-to-end validation against a separate Symfony application.
