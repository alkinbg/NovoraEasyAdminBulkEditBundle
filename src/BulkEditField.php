<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle;

/** @template T of object */
final readonly class BulkEditField
{
    /**
     * @param string $name
     * @param \Closure(T, mixed): void $apply
     */
    public function __construct(public string $name, public \Closure $apply)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Field name must not be empty.');
        }
    }
}
