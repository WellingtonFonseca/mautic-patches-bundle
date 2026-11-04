<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The form of the import of a field definition: the JSON of another instance pasted in a text area, checked first and then confirmed.
 * Twin of the form of the Custom Objects import (plugins stay independent, so the code is copied on purpose).
 */
class FieldDefinitionImportType extends AbstractType
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $confirming = $options['confirming'];

        // two steps: the JSON is first CHECKED (nothing is saved), then confirmed with the same text, now read only, so what was checked is what is imported
        $builder->add('definition', TextareaType::class, [
            'label'    => 'mautic.patches.field_definition.import.label',
            'required' => false,
            'attr'     => array_filter([
                'class'    => 'form-control',
                'rows'     => 24,
                'style'    => 'font-family:monospace;white-space:pre',
                'readonly' => $confirming ? 'readonly' : null,
            ]),
        ]);

        $builder->add('confirmed', HiddenType::class, ['data' => $confirming ? '1' : '0', 'mapped' => false]);

        // a check that finds nothing to do keeps the save button hidden (not removed) so that Limpar can bring Conferir back
        $confirm = $confirming && $options['can_confirm'];
        $buttons = [
            'apply_text'     => false,
            'save_text'      => $confirm ? 'mautic.patches.field_definition.import.confirm' : 'mautic.patches.field_definition.import.check',
            'save_icon'      => $confirm ? 'ri-upload-2-line' : 'ri-search-line',
            'save_class'     => 'btn btn-primary btn-save'.($confirming && !$options['can_confirm'] ? ' hide' : ''),
            'cancel_onclick' => $options['cancel_onclick'],
        ];
        if ($confirming) {
            $buttons['pre_extra_buttons'] = [[
                'name'  => 'reset',
                'label' => 'mautic.patches.field_definition.import.reset',
                'attr'  => ['class' => 'btn btn-secondary btn-reset btn-dnd btn-nospin', 'icon' => 'ri-eraser-line', 'onclick' => $this->resetScript()],
            ]];
        }
        $builder->add('buttons', FormButtonsType::class, $buttons);

        $builder->setAction($options['action']);
    }

    /**
     * Core copies the buttons of a form into the page header and the copy clicks the original, so this runs from either. btn-dnd and
     * btn-nospin stop core from disabling the other buttons and spinning the icon, as it does for a submit.
     */
    private function resetScript(): string
    {
        $label = json_encode($this->translator->trans('mautic.patches.field_definition.import.check'), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

        return "var f = mQuery('form[name=field_definition_import]');"
            ." f.find('textarea').val('').prop('readonly', false).removeAttr('readonly').focus();"
            ." f.find('input[name\$=\"[confirmed]\"]').val('0');"
            ." mQuery('.field-definition-check, .field-definition-help, #field-definition-errors').hide();"
            ." mQuery('.btn-save').removeClass('hide').html(".$label." + '<i class=\"ri-search-line\"></i>');"
            ." mQuery('.btn-reset').hide();"
            .' return false;';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['cancel_onclick' => '', 'action' => '', 'confirming' => false, 'can_confirm' => true]);
    }

    public function getBlockPrefix(): string
    {
        return 'field_definition_import';
    }
}
