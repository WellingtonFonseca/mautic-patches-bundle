<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Extension;

use Mautic\LeadBundle\Form\Type\ListType;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefix;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixes;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Makes the prefix of the segment name required: the name must be "PREFIXO | Nome", and the prefix one of the
 * prefixes registered in Configurações (SegmentPrefixes). The segment form and the
 * segments API (POST/PATCH/PUT /api/segments) both use ListType, so one check covers both. Core is not touched.
 *
 * A blank name is left to core's own NotBlank, so the user does not get two messages.
 */
class SegmentPrefixFormExtension extends AbstractTypeExtension
{
    public const ERROR_CODE = 'mautic-patches-segment-prefix-required';

    public function __construct(private TranslatorInterface $translator, private SegmentPrefixes $prefixes)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ListType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if (!$form->has('name')) {
                return;
            }

            $name = $form->get('name')->getData();
            if (null === $name || '' === trim((string) $name)) {
                return;
            }

            if (!SegmentPrefix::isValid((string) $name)) {
                $message = $this->translator->trans('mautic.patches.segment.prefix.required');
            } elseif (!$this->prefixes->has(SegmentPrefix::split((string) $name)['prefix'])) {
                $list    = $this->prefixes->all();
                $message = [] === $list
                    ? $this->translator->trans('mautic.patches.segment.prefix.none_registered')
                    : $this->translator->trans('mautic.patches.segment.prefix.not_registered', ['%list%' => implode(', ', $list)]);
            } else {
                return;
            }

            // Core's API controller reads the cause of every error (getFormErrorCodes), so it must be a violation.
            $form->get('name')->addError(new FormError(
                $message,
                null,
                [],
                null,
                new ConstraintViolation($message, null, [], $form->getData(), 'name', $name, null, self::ERROR_CODE)
            ));
        });
    }
}
