<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use Novora\EasyAdminBulkEditBundle\Tests\Fixtures\IntegrationKernel;
use PHPUnit\Framework\TestCase;

final class IntegrationKernelTest extends TestCase
{
    public function testKernelBootsWithBundleAndTwigNamespace(): void
    {
        $kernel = new IntegrationKernel('test', true);
        $kernel->boot();
        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertTrue($container->has('test.workflow'));
            self::assertTrue($container->get('twig')->getLoader()->exists('@NovoraEasyAdminBulkEdit/bulk_edit.html.twig'));
        } finally {
            $kernel->shutdown();
        }
    }
}
