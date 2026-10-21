<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests\Fixtures;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Novora\EasyAdminBulkEditBundle\BulkEditField;
use Novora\EasyAdminBulkEditBundle\Workflow\BulkEditWorkflow;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class IntegrationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkflowItem::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->addBatchAction(Action::new('bulkEdit', 'Bulk edit')->linkToCrudAction('bulkEdit'));
    }

    #[AdminRoute('/bulk-edit', name: 'bulk_edit', options: ['methods' => ['POST']])]
    public function bulkEdit(AdminContext $context, BatchActionDto $batchActionDto, BulkEditWorkflow $workflow, AdminUrlGenerator $urls): Response
    {
        return $workflow->start($batchActionDto, $context->getEntity()->getFqcn(), $urls->setController(self::class)->setAction('bulkEditConfirm')->generateUrl());
    }

    #[AdminRoute('/bulk-edit-confirm', name: 'bulk_edit_confirm', options: ['methods' => ['GET', 'POST']])]
    public function bulkEditConfirm(Request $request, AdminContext $context, BulkEditWorkflow $workflow, AdminUrlGenerator $urls): Response
    {
        return $workflow->confirm(
            $request,
            $context->getEntity()->getFqcn(),
            ['status' => ['type' => TextType::class]],
            ['status' => new BulkEditField('status', static function (WorkflowItem $item, string $status): void { $item->status = $status; })],
            static fn (WorkflowItem $item): bool => true,
            $urls->setController(self::class)->setAction(Action::INDEX)->unset('bulk_selection')->generateUrl(),
        );
    }
}
