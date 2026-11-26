<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;

/**
 * A Yes / No switch of the Features tab of the plugin settings modal; its `help` is drawn as an info alert at its right
 * (Resources/views/FormTheme/feature_switch.html.twig).
 */
class FeatureSwitchType extends AbstractType
{
    public function getParent(): string
    {
        return YesNoButtonGroupType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'patches_feature_switch';
    }
}
