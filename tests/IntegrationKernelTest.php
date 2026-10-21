<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use Novora\EasyAdminBulkEditBundle\Tests\Fixtures\IntegrationKernel;
use Novora\EasyAdminBulkEditBundle\Tests\Fixtures\IntegrationCrudController;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Novora\EasyAdminBulkEditBundle\Tests\Fixtures\WorkflowItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class IntegrationKernelTest extends TestCase
{
    public function testKernelBootsWithBundleAndTwigNamespace(): void
    {
        $kernel = new IntegrationKernel('test', false);
        $kernel->boot();
        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertTrue($container->has('test.workflow'));
            self::assertSame('Масово редактиране', $container->get('translator')->trans('page.edit', [], 'NovoraEasyAdminBulkEdit', 'bg'));
            self::assertSame('Bulk edit', $container->get('translator')->trans('page.edit', [], 'NovoraEasyAdminBulkEdit', 'en'));
            self::assertTrue($container->get('twig')->getLoader()->exists('@NovoraEasyAdminBulkEdit/bulk_edit.html.twig'));
            $routes = $container->get('router')->getRouteCollection();
            self::assertNotNull($routes->get('admin_integration_bulk_edit'));
            self::assertNotNull($routes->get('admin_integration_bulk_edit_confirm'));
        } finally {
            IntegrationCrudController::$canAccessAction = true;
            IntegrationCrudController::$canEditEntity = true;
            $kernel->shutdown();
            restore_exception_handler();
        }
    }
    public function testRealEasyAdminBatchGetPostAndReplay(): void
    {
        $kernel = new IntegrationKernel('test', false);
        $kernel->boot();
        try {
            $container = $kernel->getContainer()->get('test.service_container');
            $manager = $container->get('doctrine')->getManager();
            (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(WorkflowItem::class)]);
            $manager->persist(new WorkflowItem());
            $manager->flush();
            $manager->clear();

            $session = new Session(new MockArraySessionStorage());
            $seedRequest = Request::create('/admin');
            $seedRequest->setSession($session);
            $container->get('request_stack')->push($seedRequest);
            $batchToken = $container->get('security.csrf.token_manager')->getToken('ea-batch-action-bulkEdit-'.WorkflowItem::class)->getValue();
            $container->get('request_stack')->pop();

            $batch = Request::create('/admin/integration/bulk-edit', 'POST', [
                EA::BATCH_ACTION_NAME => 'bulkEdit',
                EA::BATCH_ACTION_ENTITY_IDS => [1],
                EA::ENTITY_FQCN => WorkflowItem::class,
                EA::BATCH_ACTION_CSRF_TOKEN => $batchToken,
            ]);
            $batch->setSession($session);
            $redirect = $kernel->handle($batch);
            self::assertSame(302, $redirect->getStatusCode());
            $url = $redirect->headers->get('Location');
            self::assertNotNull($url);
            self::assertStringContainsString('bulk_selection=', $url);

            $previewRequest = Request::create($url);
            $previewRequest->setSession($session);
            $preview = $kernel->handle($previewRequest);
            self::assertSame(200, $preview->getStatusCode());
            $html = (string) $preview->getContent();
            self::assertStringContainsString('1 selected records', $html);
            self::assertSame(1, preg_match('/name="form\[_token\]"[^>]*value="([^"]+)"/', $html, $matches));
            $formToken = html_entity_decode($matches[1], ENT_QUOTES);

            $invalidCsrfPost = Request::create($url, 'POST', ['form' => [
                'apply_status' => '1',
                'value_status' => 'published',
                '_token' => 'forged-token',
            ]]);
            $invalidCsrfPost->setSession($session);
            self::assertSame(200, $kernel->handle($invalidCsrfPost)->getStatusCode());
            $manager->clear();
            self::assertSame('draft', $manager->find(WorkflowItem::class, 1)?->status);

            $noChangesPost = Request::create($url, 'POST', ['form' => [
                'value_status' => 'published',
                '_token' => $formToken,
            ]]);
            $noChangesPost->setSession($session);
            $noChangesResponse = $kernel->handle($noChangesPost);
            self::assertSame(200, $noChangesResponse->getStatusCode());
            self::assertStringContainsString('Choose at least one field to update', (string) $noChangesResponse->getContent());
            $manager->clear();
            self::assertSame('draft', $manager->find(WorkflowItem::class, 1)?->status);

            $post = Request::create($url, 'POST', ['form' => [
                'apply_status' => '1',
                'value_status' => 'published',
                '_token' => $formToken,
            ]]);
            $post->setSession($session);
            $result = $kernel->handle($post);
            self::assertSame(303, $result->getStatusCode());
            $reviewUrl = $result->headers->get('Location');
            self::assertNotNull($reviewUrl);
            self::assertStringContainsString('bulk_review=1', $reviewUrl);
            $manager->clear();
            self::assertSame('draft', $manager->find(WorkflowItem::class, 1)?->status);

            $reviewGet = Request::create($reviewUrl);
            $reviewGet->setSession($session);
            $reviewResponse = $kernel->handle($reviewGet);
            self::assertSame(200, $reviewResponse->getStatusCode());
            $reviewHtml = (string) $reviewResponse->getContent();
            self::assertStringContainsString('These values will be applied', $reviewHtml);
            self::assertSame(1, preg_match('/name="bulk_confirm\[_token\]"[^>]*value="([^"]+)"/', $reviewHtml, $reviewMatches));
            $reviewFormToken = html_entity_decode($reviewMatches[1], ENT_QUOTES);

            $backToEdit = Request::create($url);
            $backToEdit->setSession($session);
            $backResponse = $kernel->handle($backToEdit);
            self::assertSame(200, $backResponse->getStatusCode());
            self::assertStringContainsString('value="published"', (string) $backResponse->getContent());

            $badConfirmation = Request::create($reviewUrl, 'POST', ['bulk_confirm' => ['_token' => 'forged']]);
            $badConfirmation->setSession($session);
            self::assertSame(200, $kernel->handle($badConfirmation)->getStatusCode());
            $manager->clear();
            self::assertSame('draft', $manager->find(WorkflowItem::class, 1)?->status);

            IntegrationCrudController::$canAccessAction = false;
            $blocked = Request::create($reviewUrl, 'POST', ['bulk_confirm' => ['_token' => $reviewFormToken]]);
            $blocked->setSession($session);
            self::assertSame(401, $kernel->handle($blocked)->getStatusCode());
            IntegrationCrudController::$canAccessAction = true;
            $manager->clear();
            self::assertSame('draft', $manager->find(WorkflowItem::class, 1)?->status);

            $confirm = Request::create($reviewUrl, 'POST', ['bulk_confirm' => ['_token' => $reviewFormToken]]);
            $confirm->setSession($session);
            $finalResponse = $kernel->handle($confirm);
            self::assertSame(303, $finalResponse->getStatusCode());
            self::assertStringNotContainsString('bulk_selection=', (string) $finalResponse->headers->get('Location'));
            $manager->clear();
            self::assertSame('published', $manager->find(WorkflowItem::class, 1)?->status);
            self::assertSame(400, $kernel->handle($confirm)->getStatusCode());
            $forgedEntity = Request::create('/admin/integration/bulk-edit', 'POST', [
                EA::BATCH_ACTION_NAME => 'bulkEdit',
                EA::BATCH_ACTION_ENTITY_IDS => [1],
                EA::ENTITY_FQCN => \DateTime::class,
                EA::BATCH_ACTION_CSRF_TOKEN => $batchToken,
            ]);
            $forgedEntity->setSession($session);
            self::assertSame(400, $kernel->handle($forgedEntity)->getStatusCode());

            $forgedBatchToken = Request::create('/admin/integration/bulk-edit', 'POST', [
                EA::BATCH_ACTION_NAME => 'bulkEdit',
                EA::BATCH_ACTION_ENTITY_IDS => [1],
                EA::ENTITY_FQCN => WorkflowItem::class,
                EA::BATCH_ACTION_CSRF_TOKEN => 'forged',
            ]);
            $forgedBatchToken->setSession($session);
            self::assertSame(400, $kernel->handle($forgedBatchToken)->getStatusCode());

        } finally {
            IntegrationCrudController::$canAccessAction = true;
            IntegrationCrudController::$canEditEntity = true;
            $kernel->shutdown();
            restore_exception_handler();
        }
    }

}
