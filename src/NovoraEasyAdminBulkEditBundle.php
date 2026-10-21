<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NovoraEasyAdminBulkEditBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
