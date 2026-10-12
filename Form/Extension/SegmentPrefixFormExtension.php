<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Extension;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Form\Type\ListType;
use MauticPlugin\MauticPatchesBundle\Service\SegmentAlias;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixes;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The required "Prefixo" field of the segment: a select of the prefixes registered in Configurações. The segment form
 * and the segments API (POST/PATCH/PUT /api/segments, key "prefix") both use ListType, so one field covers both. Core's
 * template does not list it, but form_end() renders it at the end of the form; SegmentPrefixFieldSubscriber's script
 * moves it before the name. Core is not touched.
 *
 * It also gives the segment an alias made of prefix and name, letters and numbers only, when none was typed (SegmentAlias).
 *
 * The value is not a column of the segment: it is read from, and (after the segment is saved) written to, the plugin's
 * own table by SegmentPrefixStore. A prefix the segment already has but that is no longer registered stays valid for
 * that segment only.
 */
class SegmentPrefixFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private SegmentPrefixes $prefixes,
        private SegmentPrefixStore $store
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ListType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $segment = $options['data'] ?? null;
        $current = $segment instanceof LeadList ? $this->store->get($segment->getId()) : null;
        $list    = $this->prefixes->all();
        if (null !== $current && !in_array($current, $list, true)) {
            $list[] = $current;
        }

        $builder->add('prefix', ChoiceType::class, [
            'mapped'      => false,
            'data'        => $current,
            'choices'     => array_combine($list, $list),
            'placeholder' => $this->translator->trans('mautic.patches.segment.prefix.placeholder'),
            'label'       => 'mautic.patches.segment.prefix',
            'label_attr'  => ['class' => 'control-label'],
            'attr'        => ['class' => 'form-control'],
            'required'    => true,
            // The text itself, here and in NotBlank: these messages are not keys of the validators domain.
            'invalid_message' => $this->translator->trans('mautic.patches.segment.prefix.not_registered', ['%list%' => implode(', ', $this->prefixes->all())]),
            'constraints'     => [
                new NotBlank(['message' => $this->translator->trans([] === $list
                    ? 'mautic.patches.segment.prefix.none_registered'
                    : 'mautic.patches.segment.prefix.required')]),
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form    = $event->getForm();
            $segment = $form->getData();
            $field   = $form->get('prefix');

            if ($segment instanceof LeadList && $field->isSubmitted() && is_string($field->getData()) && '' !== $field->getData()) {
                $this->store->remember($segment, $field->getData());
            }

            // No alias typed: prefix and name, letters and numbers only. An alias that was typed is left as it is.
            if ($segment instanceof LeadList && '' === trim((string) $segment->getAlias())) {
                $prefix = is_string($field->getData()) && '' !== $field->getData() ? $field->getData() : $this->store->get($segment->getId());
                $alias  = SegmentAlias::generate((string) $prefix, (string) $segment->getName());
                if (null !== $prefix && '' !== $alias) {
                    $segment->setAlias($alias);
                }
            }
        });
    }
}
