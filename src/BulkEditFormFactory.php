<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;

/** Builds a form with an explicit opt-in checkbox for each editable property. */
final readonly class BulkEditFormFactory
{
    public function __construct(private FormFactoryInterface $formFactory)
    {
    }

    /**
     * @param array<string, array{type: class-string<FormTypeInterface>, options?: array<string, mixed>}> $definitions
     * @return FormInterface<mixed>
     */
    public function create(array $definitions): FormInterface
    {
        if ($definitions === []) {
            throw new \InvalidArgumentException('At least one editable field must be configured.');
        }

        $builder = $this->formFactory->createBuilder();
        foreach ($definitions as $name => $definition) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $name)) {
                throw new \InvalidArgumentException(sprintf('Invalid field name "%s".', $name));
            }
            $builder->add('apply_'.$name, CheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'Change '.$name,
            ]);
            $builder->add('value_'.$name, $definition['type'], array_replace([
                'required' => false,
                'mapped' => false,
            ], $definition['options'] ?? []));
        }

        return $builder->getForm();
    }

    /**
     * Extract only opted-in fields from a submitted *valid* form.
     * @param FormInterface<mixed> $form
     * @param list<string> $fieldNames
     * @return array<string, mixed>
     */
    public function selectedChanges(FormInterface $form, array $fieldNames): array
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new BulkEditException('The form must be submitted and valid.');
        }
        $changes = [];
        foreach ($fieldNames as $name) {
            if ($form->get('apply_'.$name)->getData() === true) {
                $changes[$name] = $form->get('value_'.$name)->getData();
            }
        }
        if ($changes === []) {
            throw new BulkEditException('Choose at least one field to update.');
        }

        return $changes;
    }
}
