<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle;

use Doctrine\ORM\EntityManagerInterface;

/** Transactional executor. Never uses DQL UPDATE: entity lifecycle callbacks remain active. */
final readonly class BulkEditExecutor
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param list<int|string> $identifiers
     * @param array<string, BulkEditField<T>> $fields
     * @param array<string, mixed> $changes Only explicitly selected fields.
     * @param callable(T):bool $canEdit Mandatory per-entity authorization.
     * @return int Number of changed entities.
     */
    public function execute(string $entityClass, array $identifiers, array $fields, array $changes, callable $canEdit): int
    {
        if ($identifiers === [] || $changes === []) {
            throw new BulkEditException('No selected entities or changes.');
        }
        if (count($identifiers) !== count(array_unique(array_map('strval', $identifiers)))) {
            throw new BulkEditException('Duplicate identifiers are not allowed.');
        }
        foreach ($changes as $name => $_) {
            if (!isset($fields[$name]) || $fields[$name]->name !== $name) {
                throw new BulkEditException(sprintf('Field "%s" is not allowed.', $name));
            }
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $repository = $this->entityManager->getRepository($entityClass);
            $entities = [];
            foreach ($identifiers as $id) {
                $entity = $repository->find($id);
                if ($entity === null) {
                    throw new BulkEditException(sprintf('Entity "%s" with ID "%s" no longer exists.', $entityClass, $id));
                }
                if (!$canEdit($entity)) {
                    throw new BulkEditException('Not authorized to edit one or more selected entities.');
                }
                $entities[] = $entity;
            }
            foreach ($entities as $entity) {
                foreach ($changes as $name => $value) {
                    ($fields[$name]->apply)($entity, $value);
                }
            }
            $this->entityManager->flush();
            $connection->commit();
            return count($entities);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
