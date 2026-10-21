<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Novora\EasyAdminBulkEditBundle\BulkEditException;
use Novora\EasyAdminBulkEditBundle\BulkSelection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

final class BulkSelectionTest extends TestCase
{
    public function testValidSelection(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->getToken('ea-batch-action-bulkEdit-stdClass')->getValue();
        $batch = new BatchActionDto('bulkEdit', [1, 2], \stdClass::class, $token);
        self::assertSame([1, 2], (new BulkSelection($manager))->fromBatchAction($batch, \stdClass::class));
    }

    public function testRejectsForgedEntity(): void
    {
        $this->expectException(BulkEditException::class);
        (new BulkSelection(new CsrfTokenManager()))->fromBatchAction(new BatchActionDto('bulkEdit', [1], \DateTime::class, 'no'), \stdClass::class);
    }

    public function testRejectsForgedToken(): void
    {
        $this->expectException(BulkEditException::class);
        (new BulkSelection(new CsrfTokenManager()))->fromBatchAction(new BatchActionDto('bulkEdit', [1], \stdClass::class, 'no'), \stdClass::class);
    }
}
