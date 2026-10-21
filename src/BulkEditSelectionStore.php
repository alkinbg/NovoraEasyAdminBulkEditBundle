<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps a batch selection server-side, bound to its CRUD class and action.
 * Consuming a selection invalidates it to prevent accidental double submission.
 */
final readonly class BulkEditSelectionStore
{
    private const KEY = '_novora_bulk_edit_selections';

    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * @param class-string $entityClass
     * @param list<int|string> $ids
     */
    public function create(string $entityClass, array $ids): string
    {
        if ($ids === [] || count($ids) > 100 || count($ids) !== count(array_unique(array_map('strval', $ids)))) {
            throw new BulkEditException('Invalid selection.');
        }

        $token = bin2hex(random_bytes(32));
        $session = $this->requestStack->getSession();
        $data = $session->get(self::KEY, []);
        if (!is_array($data)) {
            $data = [];
        }
        $now = time();
        foreach ($data as $key => $entry) {
            if (!is_array($entry) || !isset($entry['created']) || !is_int($entry['created']) || $entry['created'] < $now - 900) {
                unset($data[$key]);
            }
        }
        $data[$token] = ['entity' => $entityClass, 'ids' => $ids, 'created' => $now];
        $session->set(self::KEY, $data);
        return $token;
    }

    /**
     * @param class-string $expectedEntityClass
     * @return list<int|string>
     */
    public function consume(string $token, string $expectedEntityClass): array
    {
        $ids = $this->inspect($token, $expectedEntityClass);
        $session = $this->requestStack->getSession();
        $data = $session->get(self::KEY, []);
        unset($data[$token]);
        $session->set(self::KEY, $data);

        return $ids;
    }

    /**
     * Inspect a selection without consuming it; use for GET requests and invalid forms.
     * @param class-string $expectedEntityClass
     * @return list<int|string>
     */
    public function inspect(string $token, string $expectedEntityClass): array
    {
        $session = $this->requestStack->getSession();
        $data = $session->get(self::KEY, []);
        if (!is_array($data)) {
            throw new BulkEditException('No valid selection was found.');
        }
        $entry = $data[$token] ?? null;
        if (!is_array($entry) || ($entry['entity'] ?? null) !== $expectedEntityClass || !is_int($entry['created'] ?? null) || $entry['created'] < time() - 900 || !is_array($entry['ids'] ?? null)) {
            throw new BulkEditException('Selection expired or is invalid.');
        }
        /** @var list<int|string> $ids */
        $ids = $entry['ids'];
        return $ids;
    }
    /**
     * Save only prevalidated scalar changes in the server-side session.
     * @param class-string $expectedEntityClass
     * @param array<string, mixed> $changes
     */
    public function saveReview(string $token, string $expectedEntityClass, array $changes): void
    {
        $this->inspect($token, $expectedEntityClass);
        if ($changes === []) {
            throw new BulkEditException('No changes to review.');
        }
        foreach ($changes as $field => $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new BulkEditException('Only scalar values are supported in review.');
            }
        }
        $session = $this->requestStack->getSession();
        $data = $session->get(self::KEY, []);
        $data[$token]['changes'] = $changes;
        $session->set(self::KEY, $data);
    }

    /**
     * @param class-string $expectedEntityClass
     * @return array<string, int|float|string|bool|null>
     */
    public function review(string $token, string $expectedEntityClass): array
    {
        $this->inspect($token, $expectedEntityClass);
        $data = $this->requestStack->getSession()->get(self::KEY, []);
        $changes = $data[$token]['changes'] ?? null;
        if (!is_array($changes) || $changes === []) {
            throw new BulkEditException('Review must be completed first.');
        }
        /** @var array<string, int|float|string|bool|null> $changes */
        return $changes;
    }

}
