<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Novora\EasyAdminBulkEditBundle\NovoraEasyAdminBulkEditBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class IntegrationKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new EasyAdminBundle();
        yield new NovoraEasyAdminBulkEditBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/novora_easyadmin_bundle_test_cache';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()->alias('test.workflow', \Novora\EasyAdminBulkEditBundle\Workflow\BulkEditWorkflow::class)->public();
        $container->extension('framework', [
            'secret' => 'testing-only-secret',
            'test' => true,
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        $container->extension('security', ['firewalls' => ['main' => ['lazy' => true, 'logout' => ['path' => '/logout']]]]);
        $container->extension('twig', ['default_path' => $this->getProjectDir().'/tests/templates']);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['auto_mapping' => false],
        ]);
    }
}
