<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\DependencyInjection;

use Novora\EasyAdminBulkEditBundle\BulkEditExecutor;
use Novora\EasyAdminBulkEditBundle\BulkEditFormFactory;
use Novora\EasyAdminBulkEditBundle\BulkEditSelectionStore;
use Novora\EasyAdminBulkEditBundle\BulkSelection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Novora\EasyAdminBulkEditBundle\Workflow\BulkEditWorkflow;

final class NovoraEasyAdminBulkEditExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(BulkEditExecutor::class, BulkEditExecutor::class)
            ->setPublic(false)
            ->setArgument(0, new Reference('doctrine.orm.entity_manager'));

        $container->register(BulkSelection::class, BulkSelection::class)
            ->setPublic(false)
            ->setArgument(0, new Reference('security.csrf.token_manager'));
        $container->register(BulkEditSelectionStore::class, BulkEditSelectionStore::class)
            ->setPublic(false)
            ->setArgument(0, new Reference('request_stack'));
        $container->register(BulkEditFormFactory::class, BulkEditFormFactory::class)
            ->setPublic(false)
            ->setArgument(0, new Reference('form.factory'));
        $container->register(BulkEditWorkflow::class, BulkEditWorkflow::class)
            ->setPublic(false)
            ->setArguments([
                new Reference(BulkSelection::class),
                new Reference(BulkEditSelectionStore::class),
                new Reference(BulkEditFormFactory::class),
                new Reference(BulkEditExecutor::class),
                new Reference('twig'),
            ]);
    }
}
