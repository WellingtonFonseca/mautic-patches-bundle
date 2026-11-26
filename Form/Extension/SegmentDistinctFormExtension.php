<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Extension;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Form\Type\ListType;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentDistinctConfig;
use MauticPlugin\MauticPatchesBundle\Service\IndexedLeadFields;
use MauticPlugin\MauticPatchesBundle\Service\SegmentDistinctStore;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "DISTINCT" setting of the segment: three fields that are not columns of the segment (enabled, remove empty, the
 * contact fields to compare). The segment form and the segments API both use ListType, so one extension covers both.
 * Core's template does not list them: form_end() renders them at the end of the form and SegmentDistinctTabSubscriber's
 * script moves them into a "DISTINCT" tab.
 *
 * The setting is read from, and (after the segment is saved) written to, the plugin's own table by SegmentDistinctStore.
 * The fields offered are those with a real index (IndexedLeadFields); a field already chosen that lost its index stays
 * in the list, marked.
 */
class SegmentDistinctFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private IndexedLeadFields $indexedFields,
        private SegmentDistinctStore $store
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ListType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $segment = $options['data'] ?? null;
        $current = ($segment instanceof LeadList ? $this->store->get($segment->getId()) : null) ?? new SegmentDistinctConfig();

        $choices = array_flip($this->indexedFields->all());
        foreach ($current->fields as $field) {
            if (!in_array($field, $choices, true)) {
                $choices[$field.' '.$this->translator->trans('mautic.patches.segment.distinct.no_index')] = $field;
            }
        }

        $builder->add('distinct_enabled', YesNoButtonGroupType::class, [
            'mapped'   => false,
            'data'     => $current->enabled,
            'label'    => 'mautic.patches.segment.distinct.enabled',
            'required' => false,
            'attr'     => ['tooltip' => 'mautic.patches.segment.distinct.enabled.help'],
        ]);
        $builder->add('distinct_remove_empty', YesNoButtonGroupType::class, [
            'mapped'   => false,
            'data'     => $current->removeEmpty,
            'label'    => 'mautic.patches.segment.distinct.remove_empty',
            'required' => false,
            'attr'     => ['tooltip' => 'mautic.patches.segment.distinct.remove_empty.help'],
        ]);
        $builder->add('distinct_fields', ChoiceType::class, [
            'mapped'     => false,
            'data'       => $current->fields,
            'choices'    => $choices,
            'multiple'   => true,
            'label'      => 'mautic.patches.segment.distinct.fields',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => ['class' => 'form-control', 'data-placeholder' => $this->translator->trans('mautic.patches.segment.distinct.fields.placeholder')],
            'required'   => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($current): void {
            $form    = $event->getForm();
            $segment = $form->getData();
            if (!$segment instanceof LeadList) {
                return;
            }

            // A partial submit (PATCH of the API) keeps what it did not send.
            $enabled = $form->get('distinct_enabled');
            $remove  = $form->get('distinct_remove_empty');
            $fields  = $form->get('distinct_fields');
            if (!$enabled->isSubmitted() && !$remove->isSubmitted() && !$fields->isSubmitted()) {
                return;
            }

            $chosen = $fields->isSubmitted() ? array_values(array_unique(array_map('strval', (array) $fields->getData()))) : $current->fields;
            sort($chosen);
            $config = new SegmentDistinctConfig(
                $enabled->isSubmitted() ? (bool) $enabled->getData() : $current->enabled,
                $remove->isSubmitted() ? (bool) $remove->getData() : $current->removeEmpty,
                $chosen
            );

            if ($config->enabled && [] === $config->fields) {
                $fields->addError(new FormError($this->translator->trans('mautic.patches.segment.distinct.fields.required')));

                return;
            }
            $this->store->remember($segment, $config);
        });
    }
}
