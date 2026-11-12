<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The import form: the key and the encrypted package of another instance, checked first and then confirmed with the same
 * (now read only) text, so what was checked is what is imported.
 */
class RoleUserCloneImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $confirming = $options['confirming'];
        $readonly   = $confirming ? ['readonly' => 'readonly'] : [];

        $builder->add('key', TextType::class, [
            'label'    => 'mautic.patches.role_user_clone.import.key',
            'required' => false,
            'attr'     => ['class' => 'form-control', 'autocomplete' => 'off', 'style' => 'font-family:monospace'] + $readonly,
        ]);
        $builder->add('package', TextareaType::class, [
            'label'    => 'mautic.patches.role_user_clone.import.package',
            'required' => false,
            'attr'     => ['class' => 'form-control', 'rows' => 16, 'style' => 'font-family:monospace'] + $readonly,
        ]);
        $builder->add('confirmed', HiddenType::class, ['data' => $confirming ? '1' : '0', 'mapped' => false]);

        // a check that finds nothing to do keeps the save button hidden: there is nothing to confirm
        $confirm = $confirming && $options['can_confirm'];
        $builder->add('buttons', FormButtonsType::class, [
            'apply_text'     => false,
            'save_text'      => $confirm ? 'mautic.patches.role_user_clone.import.confirm' : 'mautic.patches.role_user_clone.import.check',
            'save_icon'      => $confirm ? 'ri-upload-2-line' : 'ri-search-line',
            'save_class'     => 'btn btn-primary btn-save'.($confirming && !$options['can_confirm'] ? ' hide' : ''),
            'cancel_onclick' => $options['cancel_onclick'],
        ]);
        $builder->setAction($options['action']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['cancel_onclick' => '', 'action' => '', 'confirming' => false, 'can_confirm' => true]);
    }

    public function getBlockPrefix(): string
    {
        return 'role_user_clone_import';
    }
}
