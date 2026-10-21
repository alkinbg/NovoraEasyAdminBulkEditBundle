<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Novora\EasyAdminBulkEditBundle\BulkEditException;
use Novora\EasyAdminBulkEditBundle\BulkEditExecutor;
use Novora\EasyAdminBulkEditBundle\BulkEditField;
use PHPUnit\Framework\TestCase;

#[ORM\Entity]
#[ORM\Table(name: 'bulk_test_items')]
class Item
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string')]
    public string $status = 'draft';
}

final class BulkEditExecutorTest extends TestCase
{
    private function manager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
        $config->enableNativeLazyObjects(true);
        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(Item::class)]);
        foreach ([1, 2] as $_) {
            $manager->persist(new Item());
        }
        $manager->flush();
        $manager->clear();
        return $manager;
    }

    public function testUpdatesTwoRows(): void
    {
        $manager = $this->manager();
        $count = (new BulkEditExecutor($manager))->execute(Item::class, [1, 2], [
            'status' => new BulkEditField('status', static function (Item $item, string $status): void { $item->status = $status; }),
        ], ['status' => 'published'], static fn (Item $item): bool => true);
        self::assertSame(2, $count);
        $manager->clear();
        self::assertSame('published', $manager->find(Item::class, 1)?->status);
        self::assertSame('published', $manager->find(Item::class, 2)?->status);
    }

    public function testDeniedEntityDoesNotModifyDatabase(): void
    {
        $manager = $this->manager();
        try {
            (new BulkEditExecutor($manager))->execute(Item::class, [1, 2], [
                'status' => new BulkEditField('status', static function (Item $item, string $status): void { $item->status = $status; }),
            ], ['status' => 'published'], static fn (Item $item): bool => $item->id !== 2);
            self::fail('Expected authorization failure.');
        } catch (BulkEditException) {
            $manager->clear();
            self::assertSame('draft', $manager->find(Item::class, 1)?->status);
            self::assertSame('draft', $manager->find(Item::class, 2)?->status);
        }
    }

    public function testUnknownFieldIsRejected(): void
    {
        $manager = $this->manager();
        $this->expectException(BulkEditException::class);
        (new BulkEditExecutor($manager))->execute(Item::class, [1], [], ['unknown' => true], static fn (Item $item): bool => true);
    }
    public function testSetterFailureRollsBackDatabase(): void
    {
        $manager = $this->manager();
        $fields = ['status' => new BulkEditField('status', static function (Item $item, string $status): void {
            $item->status = $status;
            if ($item->id === 2) {
                throw new \RuntimeException('Business rule failed.');
            }
        })];
        try {
            (new BulkEditExecutor($manager))->execute(Item::class, [1, 2], $fields, ['status' => 'published'], static fn (Item $item): bool => true);
            self::fail('Expected setter failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Business rule failed.', $exception->getMessage());
            $manager->clear();
            self::assertSame('draft', $manager->find(Item::class, 1)?->status);
            self::assertSame('draft', $manager->find(Item::class, 2)?->status);
        }
    }

    public function testDuplicateIdsAreRejected(): void
    {
        $this->expectException(BulkEditException::class);
        (new BulkEditExecutor($this->manager()))->execute(Item::class, [1, '1'], [], ['status' => 'published'], static fn (Item $item): bool => true);
    }

}
