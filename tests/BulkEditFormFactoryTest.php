<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use Novora\EasyAdminBulkEditBundle\BulkEditException;
use Novora\EasyAdminBulkEditBundle\BulkEditFormFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;

final class BulkEditFormFactoryTest extends TestCase
{
    public function testOnlyCheckedFieldsAreReturned(): void
    {
        $factory = new BulkEditFormFactory(Forms::createFormFactory());
        $form = $factory->create(['status' => ['type' => TextType::class], 'category' => ['type' => TextType::class]]);
        $form->submit(['apply_status' => '1', 'value_status' => 'published', 'value_category' => 'ignored']);
        self::assertTrue($form->isValid());
        self::assertSame(['status' => 'published'], $factory->selectedChanges($form, ['status', 'category']));
    }

    public function testSelectingNothingIsRejected(): void
    {
        $factory = new BulkEditFormFactory(Forms::createFormFactory());
        $form = $factory->create(['status' => ['type' => TextType::class]]);
        $form->submit(['value_status' => 'published']);
        $this->expectException(BulkEditException::class);
        $factory->selectedChanges($form, ['status']);
    }
    public function testReturningFromReviewKeepsCheckedValues(): void
    {
        $factory = new BulkEditFormFactory(Forms::createFormFactory());
        $form = $factory->create(['status' => ['type' => TextType::class]], ['status' => 'published']);
        self::assertTrue($form->get('apply_status')->getData());
        self::assertSame('published', $form->get('value_status')->getData());
    }

}
