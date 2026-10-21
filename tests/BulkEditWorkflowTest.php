<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Novora\EasyAdminBulkEditBundle\BulkEditExecutor;
use Novora\EasyAdminBulkEditBundle\BulkEditField;
use Novora\EasyAdminBulkEditBundle\BulkEditFormFactory;
use Novora\EasyAdminBulkEditBundle\BulkEditSelectionStore;
use Novora\EasyAdminBulkEditBundle\BulkSelection;
use Novora\EasyAdminBulkEditBundle\Tests\Fixtures\WorkflowItem;
use Novora\EasyAdminBulkEditBundle\Workflow\BulkEditWorkflow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class BulkEditWorkflowTest extends TestCase
{
    private function setupWorkflow(): array
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/Fixtures'], true);
        $configuration->enableNativeLazyObjects(true);
        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(WorkflowItem::class)]);
        $manager->persist(new WorkflowItem());
        $manager->flush();
        $manager->clear();

        $stack = new RequestStack();
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/admin');
        $request->setSession($session);
        $stack->push($request);
        $csrf = new CsrfTokenManager();
        $twig = new Environment(new FilesystemLoader());
        $twig->getLoader()->addPath(__DIR__.'/templates', 'NovoraEasyAdminBulkEdit');
        $twig->addFunction(new TwigFunction('form_start', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('form_widget', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('form_end', static fn (): string => '', ['is_safe' => ['html']]));
        $workflow = new BulkEditWorkflow(new BulkSelection($csrf), new BulkEditSelectionStore($stack), new BulkEditFormFactory(Forms::createFormFactoryBuilder()->addExtension(new HttpFoundationExtension())->getFormFactory()), new BulkEditExecutor($manager), $twig);
        return [$workflow, $manager, $csrf, $stack, $session];
    }

    public function testFullBulkEditAndReplayProtection(): void
    {
        [$workflow, $manager, $csrf, $stack, $session] = $this->setupWorkflow();
        $batchToken = $csrf->getToken('ea-batch-action-bulkEdit-'.WorkflowItem::class)->getValue();
        $response = $workflow->start(new BatchActionDto('bulkEdit', [1], WorkflowItem::class, $batchToken), WorkflowItem::class, '/admin?crudAction=confirm');
        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
        self::assertArrayHasKey('bulk_selection', $query);
        $token = $query['bulk_selection'];
        $definitions = ['status' => ['type' => TextType::class]];
        $fields = ['status' => new BulkEditField('status', static function (WorkflowItem $item, string $value): void { $item->status = $value; })];
        $authorize = static fn (WorkflowItem $item): bool => true;

        $get = Request::create('/confirm?bulk_selection='.$token);
        $get->setSession($session);
        $stack->push($get);
        $preview = $workflow->confirm($get, WorkflowItem::class, $definitions, $fields, $authorize, '/done');
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('Selected: 1', (string) $preview->getContent());
        $stack->pop();

        $post = Request::create('/confirm?bulk_selection='.$token, 'POST', ['form' => ['apply_status' => '1', 'value_status' => 'published']]);
        $post->setSession($session);
        $stack->push($post);
        $result = $workflow->confirm($post, WorkflowItem::class, $definitions, $fields, $authorize, '/done');
        self::assertSame(303, $result->getStatusCode());
        $manager->clear();
        self::assertSame('draft', $manager->find(WorkflowItem::class, 1)?->status);
        $stack->pop();
        $reviewUrl = $result->getTargetUrl();
        $reviewRequest = Request::create($reviewUrl);
        $reviewRequest->setSession($session);
        $stack->push($reviewRequest);
        $review = $workflow->confirm($reviewRequest, WorkflowItem::class, $definitions, $fields, $authorize, '/done');
        self::assertSame(200, $review->getStatusCode());
        self::assertStringContainsString('Review: 1', (string) $review->getContent());
        $stack->pop();
        $confirmRequest = Request::create($reviewUrl, 'POST', ['bulk_confirm' => []]);
        $confirmRequest->setSession($session);
        $stack->push($confirmRequest);
        $result = $workflow->confirm($confirmRequest, WorkflowItem::class, $definitions, $fields, $authorize, '/done');
        self::assertSame(303, $result->getStatusCode());
        $manager->clear();
        self::assertSame('published', $manager->find(WorkflowItem::class, 1)?->status);
        self::assertSame(400, $workflow->confirm($confirmRequest, WorkflowItem::class, $definitions, $fields, $authorize, '/done')->getStatusCode());
    }
    public function testMisconfiguredFieldHandlersAreRejected(): void
    {
        [$workflow] = $this->setupWorkflow();
        $this->expectException(\LogicException::class);
        $workflow->confirm(
            Request::create('/confirm'),
            WorkflowItem::class,
            ['status' => ['type' => TextType::class]],
            [],
            static fn (WorkflowItem $item): bool => true,
            '/done',
        );
    }

}
