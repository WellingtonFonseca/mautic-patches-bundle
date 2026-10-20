<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * The form of the campaign condition "Contact fields": the first criterion in its own field / operator / value (what the
 * other conditions store) plus `additionalCriteria`, more of them. The contact must satisfy all.
 */
class ContactFieldsConditionType extends AbstractType
{
    public function __construct(private ContactFieldsCriterionType $criterionType)
    {
    }

    /**
     * @param mixed[] $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->criterionType->addCriterionFields($builder, $options['data']['field'] ?? null);

        // The first value is required; the additional rows may leave an empty / not empty operator valueless.
        $builder->add('value', TextType::class, [
            'required' => true,
            'label'    => 'mautic.patches.condition.contact_fields.value',
            'attr'     => ['class' => 'form-control cf-criterion-value'],
        ]);

        $builder->add('additionalCriteria', CollectionType::class, [
            'entry_type'   => ContactFieldsCriterionType::class,
            'allow_add'    => true,
            'allow_delete' => true,
            'prototype'    => true,
            'required'     => false,
            'label'        => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'campaignevent_contact_fields';
    }
}
