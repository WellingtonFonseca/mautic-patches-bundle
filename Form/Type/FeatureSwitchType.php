<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A Yes / No switch of the Features tab of the plugin settings modal; its `alert` option (a translation key; not `help`, core's widget draws that under the switch) is drawn as an info alert at its right
 * (Resources/views/FormTheme/feature_switch.html.twig).
 */
class FeatureSwitchType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('alert', '');
        $resolver->setAllowedTypes('alert', 'string');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['alert'] = $options['alert'];
    }

    public function getParent(): string
    {
        return YesNoButtonGroupType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'patches_feature_switch';
    }
}
