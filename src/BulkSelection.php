<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle;

use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class BulkSelection
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    /**
     * @template T of object
     * @param BatchActionDto<T> $batch
     * @param class-string $expectedEntity
     * @return list<int|string>
     */
    public function fromBatchAction(BatchActionDto $batch, string $expectedEntity): array
    {
        if ($batch->getEntityFqcn() !== $expectedEntity) {
            throw new BulkEditException('Unexpected entity class.');
        }
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ea-batch-action-'.$batch->getName().'-'.$expectedEntity, $batch->getCsrfToken()))) {
            throw new BulkEditException('Invalid EasyAdmin batch action CSRF token.');
        }
        $ids = $batch->getEntityIds();
        if ($ids === [] || count($ids) > 100) {
            throw new BulkEditException('Select between 1 and 100 entities.');
        }
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || (string) $id === '') {
                throw new BulkEditException('Invalid entity identifier.');
            }
        }
        /** @var list<int|string> $ids */
        return $ids;
    }
}
