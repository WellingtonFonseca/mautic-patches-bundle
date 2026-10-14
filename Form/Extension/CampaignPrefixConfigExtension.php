<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Extension;

use Mautic\CoreBundle\Form\DataTransformer\ArrayLinebreakTransformer;
use Mautic\CampaignBundle\Form\Type\ConfigType;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixes;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The "Prefixos" box in Configurações > Configurações de campanhas: one prefix per line, stored as a list in
 * `campaign_prefixes`. Core's template of that tab shows every field of the form, so the new field appears on its own.
 * CampaignPrefixConfigSubscriber adds the parameter to the tab's form, so it is loaded and saved.
 */
class CampaignPrefixConfigExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ConfigType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            $builder->create(
                CampaignPrefixes::PARAMETER,
                TextareaType::class,
                [
                    'label'      => 'mautic.patches.campaign.prefixes.label',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'       => 'form-control',
                        'tooltip'     => 'mautic.patches.campaign.prefixes.tooltip',
                        'rows'        => 8,
                        'placeholder' => "Compras\nVendas\nEntregas",
                    ],
                    'required'    => false,
                    'constraints' => [
                        new Callback(static function ($lines, ExecutionContextInterface $context): void {
                            $problem = CampaignPrefixes::problem((array) $lines);
                            if (null !== $problem) {
                                $context->addViolation('mautic.patches.campaign.prefixes.'.$problem);
                            }
                        }),
                    ],
                ]
            )->addViewTransformer(new ArrayLinebreakTransformer())
        );
    }
}
