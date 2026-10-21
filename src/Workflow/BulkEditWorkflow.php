<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Workflow;

use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Novora\EasyAdminBulkEditBundle\BulkEditExecutor;
use Novora\EasyAdminBulkEditBundle\BulkEditException;
use Novora\EasyAdminBulkEditBundle\BulkEditField;
use Novora\EasyAdminBulkEditBundle\BulkEditFormFactory;
use Novora\EasyAdminBulkEditBundle\BulkEditSelectionStore;
use Novora\EasyAdminBulkEditBundle\BulkSelection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Twig\Environment;

/** Request orchestration independent from app-specific CRUD security rules. */
final readonly class BulkEditWorkflow
{
    public function __construct(
        private BulkSelection $selection,
        private BulkEditSelectionStore $store,
        private BulkEditFormFactory $forms,
        private BulkEditExecutor $executor,
        private Environment $twig,
    ) {
    }

    /**
     * Call from the EasyAdmin batch action after checking action authorization.
     * @template T of object
     * @param BatchActionDto<T> $batch
     * @param class-string<T> $entityClass Read from the trusted AdminContext (not POST).
     */
    public function start(BatchActionDto $batch, string $entityClass, string $confirmUrl): RedirectResponse
    {
        $ids = $this->selection->fromBatchAction($batch, $entityClass);
        $token = $this->store->create($entityClass, $ids);
        $separator = str_contains($confirmUrl, '?') ? '&' : '?';
        return new RedirectResponse($confirmUrl.$separator.http_build_query(['bulk_selection' => $token]));
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass Read from the trusted AdminContext.
     * @param array<string, array{type: class-string<\Symfony\Component\Form\FormTypeInterface>, options?: array<string, mixed>}> $definitions
     * @param array<string, BulkEditField<T>> $fields
     * @param callable(T):bool $canEdit Explicit per-row authorization callback.
     */
    public function confirm(
        Request $request,
        string $entityClass,
        array $definitions,
        array $fields,
        callable $canEdit,
        string $successUrl,
    ): Response {
        $token = $request->query->get('bulk_selection');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return new Response('Invalid selection token.', Response::HTTP_BAD_REQUEST);
        }
        try {
            $ids = $this->store->inspect($token, $entityClass);
        } catch (BulkEditException) {
            return new Response('Selection expired or is invalid.', Response::HTTP_BAD_REQUEST);
        }
        $form = $this->forms->create($definitions);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $changes = $this->forms->selectedChanges($form, array_keys($definitions));
            } catch (BulkEditException $exception) {
                $form->addError(new FormError($exception->getMessage()));
                $changes = [];
            }
            if ($changes !== []) {
                // Token is consumed once; subsequent confirmation POSTs fail closed.
                $this->store->consume($token, $entityClass);
                $this->executor->execute($entityClass, $ids, $fields, $changes, $canEdit);
                return new RedirectResponse($successUrl, Response::HTTP_SEE_OTHER);
            }
        }

        return new Response($this->twig->render('@NovoraEasyAdminBulkEdit/bulk_edit.html.twig', [
            'form' => $form->createView(),
            'selected_count' => count($ids),
            'field_names' => array_keys($definitions),
        ]));
    }
}
