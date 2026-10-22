<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

/** A batch action whose own workflow includes a final confirmation step. */
final class BulkEditAction
{
    public static function new(string $name = 'bulkEdit', string $label = 'Bulk edit', string $crudAction = 'bulkEdit'): Action
    {
        return Action::new($name, $label)
            ->linkToCrudAction($crudAction)
            ->setHtmlAttributes(['data-action-batch-no-confirm' => 'true']);
    }
}
