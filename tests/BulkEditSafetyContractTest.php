<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Novora\EasyAdminBulkEditBundle\BulkEditException;
use Novora\EasyAdminBulkEditBundle\BulkEditExecutor;
use Novora\EasyAdminBulkEditBundle\BulkEditField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BulkEditSafetyContractTest extends TestCase
{
    private function manager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
        $configuration->enableNativeLazyObjects(true);
        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(Item::class)]);
        foreach ([1, 2] as $_) {
            $manager->persist(new Item());
        }
        $manager->flush();
        $manager->clear();
        return $manager;
    }

    public function testMissingRowNeverAppliesChanges(): void
    {
        $manager = $this->manager();
        $calls = 0;
        try {
            (new BulkEditExecutor($manager))->execute(Item::class, [1, 999], [
                'status' => new BulkEditField('status', static function (Item $item, string $value) use (&$calls): void {
                    ++$calls;
                    $item->status = $value;
                }),
            ], ['status' => 'published'], static fn (Item $item): bool => true);
            self::fail('Missing entity must abort');
        } catch (BulkEditException) {
            self::assertSame(0, $calls);
            self::assertSame('draft', $manager->find(Item::class, 1)?->status);
        }
    }

    public function testUnauthorizedRowPreventsAllSetters(): void
    {
        $manager = $this->manager();
        $calls = 0;
        try {
            (new BulkEditExecutor($manager))->execute(Item::class, [1, 2], [
                'status' => new BulkEditField('status', static function (Item $item, string $value) use (&$calls): void {
                    ++$calls;
                    $item->status = $value;
                }),
            ], ['status' => 'published'], static fn (Item $item): bool => $item->id === 1);
            self::fail('Permission failure must abort');
        } catch (AccessDeniedException) {
            self::assertSame(0, $calls);
            self::assertSame('draft', $manager->find(Item::class, 1)?->status);
        }
    }
}
