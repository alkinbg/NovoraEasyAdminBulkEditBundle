<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use Novora\EasyAdminBulkEditBundle\BulkEditException;
use Novora\EasyAdminBulkEditBundle\BulkEditSelectionStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class BulkEditSelectionStoreTest extends TestCase
{
    private function store(): BulkEditSelectionStore
    {
        $stack = new RequestStack();
        $request = Request::create('/admin');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack->push($request);
        return new BulkEditSelectionStore($stack);
    }

    public function testTokenIsSingleUse(): void
    {
        $store = $this->store();
        $token = $store->create(\stdClass::class, [1, 2]);
        self::assertSame([1, 2], $store->consume($token, \stdClass::class));
        $this->expectException(BulkEditException::class);
        $store->consume($token, \stdClass::class);
    }

    public function testInspectDoesNotConsumeToken(): void
    {
        $store = $this->store();
        $token = $store->create(\stdClass::class, [1]);
        self::assertSame([1], $store->inspect($token, \stdClass::class));
        self::assertSame([1], $store->inspect($token, \stdClass::class));
        self::assertSame([1], $store->consume($token, \stdClass::class));
    }

    public function testTokenCannotBeUsedForAnotherEntity(): void
    {
        $store = $this->store();
        $token = $store->create(\stdClass::class, [1]);
        $this->expectException(BulkEditException::class);
        $store->consume($token, \DateTime::class);
    }

    public function testDuplicateSelectionIsRejected(): void
    {
        $this->expectException(BulkEditException::class);
        $this->store()->create(\stdClass::class, [1, '1']);
    }
    public function testReviewIsStoredUntilSelectionIsConsumed(): void
    {
        $store = $this->store();
        $token = $store->create(\stdClass::class, [1]);
        $this->expectException(BulkEditException::class);
        $store->review($token, \stdClass::class);
    }

    public function testSavedReviewIsAccessibleAndExpiresWithToken(): void
    {
        $store = $this->store();
        $token = $store->create(\stdClass::class, [1]);
        $store->saveReview($token, \stdClass::class, ['status' => 'published', 'price' => 10, 'enabled' => false]);
        self::assertSame(['status' => 'published', 'price' => 10, 'enabled' => false], $store->review($token, \stdClass::class));
        $store->consume($token, \stdClass::class);
        $this->expectException(BulkEditException::class);
        $store->review($token, \stdClass::class);
    }

    public function testObjectValuedReviewIsRejected(): void
    {
        $store = $this->store();
        $token = $store->create(\stdClass::class, [1]);
        $this->expectException(BulkEditException::class);
        $store->saveReview($token, \stdClass::class, ['status' => new \stdClass()]);
    }

}
