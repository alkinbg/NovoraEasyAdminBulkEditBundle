<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Novora\EasyAdminBulkEditBundle\BulkEditAction;
use PHPUnit\Framework\TestCase;

final class BulkEditActionTest extends TestCase
{
    public function testCreatesBatchCompatibleActionWithoutGenericConfirmation(): void
    {
        $dto = BulkEditAction::new()->getAsDto();
        self::assertSame('bulkEdit', $dto->getName());
        self::assertSame('bulkEdit', $dto->getCrudActionName());
        self::assertSame('true', $dto->getHtmlAttributes()['data-action-batch-no-confirm']);
    }
}
