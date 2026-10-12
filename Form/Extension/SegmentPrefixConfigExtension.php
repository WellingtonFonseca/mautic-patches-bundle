<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Extension;

use Mautic\CoreBundle\Form\DataTransformer\ArrayLinebreakTransformer;
use Mautic\LeadBundle\Form\Type\SegmentConfigType;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixes;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The "Prefixos" box in Configurações > Configurações de segmentos: one prefix per line, stored as a list in
 * `segment_prefixes`. Core's template of that tab shows every field of the form, so the new field appears on its own.
 * SegmentPrefixConfigSubscriber adds the parameter to the tab's form, so it is loaded and saved.
 */
class SegmentPrefixConfigExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [SegmentConfigType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            $builder->create(
                SegmentPrefixes::PARAMETER,
                TextareaType::class,
                [
                    'label'      => 'mautic.patches.segment.prefixes.label',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'       => 'form-control',
                        'tooltip'     => 'mautic.patches.segment.prefixes.tooltip',
                        'rows'        => 8,
                        'placeholder' => "Compras\nVendas\nEntregas",
                    ],
                    'required'    => false,
                    'constraints' => [
                        new Callback(static function ($lines, ExecutionContextInterface $context): void {
                            $problem = SegmentPrefixes::problem((array) $lines);
                            if (null !== $problem) {
                                $context->addViolation('mautic.patches.segment.prefixes.'.$problem);
                            }
                        }),
                    ],
                ]
            )->addViewTransformer(new ArrayLinebreakTransformer())
        );
    }
}
