<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\DependencyInjection;

use Novora\EasyAdminBulkEditBundle\BulkEditExecutor;
use Novora\EasyAdminBulkEditBundle\BulkSelection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

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
    }
}
