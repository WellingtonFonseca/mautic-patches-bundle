<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Type;

use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The settings of the campaign wait "Wait" (patches.wait_relative): which date it counts from (a date field of the contact or
 * of a Custom Object), how much, and before or after that date. See RelativeWaitDateResolver.
 */
class RelativeWaitType extends AbstractType
{
    public function __construct(private RelativeWaitFieldCatalog $catalog)
    {
    }

    /**
     * @param mixed[] $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('dateField', ChoiceType::class, [
            'label'       => 'mautic.patches.wait_relative.date_field',
            'choices'     => $this->catalog->choices(),
            'placeholder' => '',
            'constraints' => [new Assert\NotBlank(['message' => 'mautic.core.value.required'])],
            'attr'        => ['class' => 'form-control', 'tooltip' => 'mautic.patches.wait_relative.date_field.help'],
        ]);

        $builder->add('amount', IntegerType::class, [
            'label'       => 'mautic.patches.wait_relative.amount',
            'data'        => $options['data']['amount'] ?? 1,
            'constraints' => [new Assert\NotBlank(['message' => 'mautic.core.value.required']), new Assert\PositiveOrZero()],
            'attr'        => ['class' => 'form-control'],
        ]);

        $builder->add('unit', ChoiceType::class, [
            'label'    => 'mautic.patches.wait_relative.unit',
            'choices'  => [
                'mautic.campaign.event.intervalunit.choice.d'  => 'd',
                'mautic.patches.wait_relative.unit.w'          => 'w',
                'mautic.campaign.event.intervalunit.choice.m'  => 'm',
                'mautic.campaign.event.intervalunit.choice.y'  => 'y',
                'mautic.campaign.event.intervalunit.choice.h'  => 'h',
                'mautic.campaign.event.intervalunit.choice.i'  => 'i',
            ],
            'data'     => $options['data']['unit'] ?? 'd',
            'attr'     => ['class' => 'form-control'],
        ]);

        $builder->add('direction', ChoiceType::class, [
            'label'    => 'mautic.patches.wait_relative.direction',
            'choices'  => [
                'mautic.patches.wait_relative.direction.after'  => 'after',
                'mautic.patches.wait_relative.direction.before' => 'before',
            ],
            'data'     => $options['data']['direction'] ?? 'after',
            'attr'     => ['class' => 'form-control'],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'campaignevent_wait_relative';
    }
}
