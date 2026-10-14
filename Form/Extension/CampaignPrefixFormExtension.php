<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Extension;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Form\Type\CampaignType;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixes;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The required "Prefixo" field of the campaign: a select of the prefixes registered in Configurações. The campaign form
 * and the campaigns API (POST/PATCH/PUT /api/campaigns, key "prefix") both use CampaignType, so one field covers both. Core's
 * template does not list it, but form_end() renders it at the end of the form; CampaignPrefixFieldSubscriber's script
 * moves it before the name. Core is not touched.
 *
 * The value is not a column of the campaign: it is read from, and (after the campaign is saved) written to, the plugin's
 * own table by CampaignPrefixStore. A prefix the campaign already has but that is no longer registered stays valid for
 * that campaign only.
 */
class CampaignPrefixFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private CampaignPrefixes $prefixes,
        private CampaignPrefixStore $store
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CampaignType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $campaign = $options['data'] ?? null;
        $current = $campaign instanceof Campaign ? $this->store->get($campaign->getId()) : null;
        $list    = $this->prefixes->all();
        if (null !== $current && !in_array($current, $list, true)) {
            $list[] = $current;
        }

        $builder->add('prefix', ChoiceType::class, [
            'mapped'      => false,
            'data'        => $current,
            'choices'     => array_combine($list, $list),
            'placeholder' => $this->translator->trans('mautic.patches.campaign.prefix.placeholder'),
            'label'       => 'mautic.patches.campaign.prefix',
            'label_attr'  => ['class' => 'control-label'],
            'attr'        => ['class' => 'form-control'],
            'required'    => true,
            // The text itself, here and in NotBlank: these messages are not keys of the validators domain.
            'invalid_message' => $this->translator->trans('mautic.patches.campaign.prefix.not_registered', ['%list%' => implode(', ', $this->prefixes->all())]),
            'constraints'     => [
                new NotBlank(['message' => $this->translator->trans([] === $list
                    ? 'mautic.patches.campaign.prefix.none_registered'
                    : 'mautic.patches.campaign.prefix.required')]),
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form    = $event->getForm();
            $campaign = $form->getData();
            $field   = $form->get('prefix');

            if ($campaign instanceof Campaign && $field->isSubmitted() && is_string($field->getData()) && '' !== $field->getData()) {
                $this->store->remember($campaign, $field->getData());
            }
        });
    }
}
