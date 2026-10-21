<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The same as SegmentStatusSubscriber, for the campaign list: the active / inactive toggle next to the name (an icon that is easy to hit
 * by mistake, with the status told only by its color) becomes
 *  - a label at the start of the name ("Ativo" green, "Inativo" red, informative, not a link, all with the width of "Inativo");
 *  - "Desativar" / "Ativar" as the FIRST item of the three-dots menu (Delete is the last), red / green on hover like the status it
 *    leads to; only for who may edit the campaign.
 * Core's flow is kept: the menu item clicks core's icon (hidden), so deactivating asks for the confirmation of core
 * (Mautic.confirmationCampaignPublishStatus) and activating does not. The label follows core's icon. See
 * Resources/js/segment-status-toggle.js (installCampaign).
 *
 * The label goes in the 'campaign.name' hook, which core prints INSIDE the name link: the script moves it out, to the start of the
 * block. Injected like SegmentStatusSubscriber.
 */
class CampaignStatusSubscriber implements EventSubscriberInterface
{
    public const STYLE = 'tr i[class*="campaign-publish-icon"]{display:none!important}'
        .'a[data-campaign-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-campaign-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-campaign-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-campaign-status-action]:hover *{color:inherit!important}'
        // the label's own style (width, margin, hidden until placed) is SegmentStatusSubscriber::STYLE, on every page
        ;

    public function __construct(
        private TranslatorInterface $translator,
        private CorePermissions $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        $context = $customContentEvent->getContext();

        if ('page.header.left' === $context) {
            $config = [
                'active'     => $this->translator->trans('mautic.core.form.active'),
                'inactive'   => $this->translator->trans('mautic.core.form.inactive'),
                'activate'   => $this->translator->trans('mautic.patches.segment.activate'),
                'deactivate' => $this->translator->trans('mautic.patches.segment.deactivate'),
                'style'      => self::STYLE,
            ];
            $customContentEvent->addContent(
                '<script>'.file_get_contents(__DIR__.'/../Resources/js/segment-status-toggle.js')
                .';mauticPatchesSegmentStatusApi.installCampaign(mQuery,Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>'
            );

            return;
        }

        $campaign = $customContentEvent->getVars()['item'] ?? null;
        if ('campaign.name' !== $context || !$campaign instanceof Campaign) {
            return;
        }

        $published = $campaign->isPublished();
        $customContentEvent->addContent(sprintf(
            '<span class="label %s segment-status-label" data-campaign-id="%d" data-status="%s">%s</span>',
            $published ? 'label-success' : 'label-danger',
            $campaign->getId(),
            $published ? 'published' : 'unpublished',
            htmlspecialchars($this->translator->trans($published ? 'mautic.core.form.active' : 'mautic.core.form.inactive'), ENT_QUOTES)
        ));
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        $campaign = $event->getItem();

        if (!$campaign instanceof Campaign
            || ButtonHelper::LOCATION_LIST_ACTIONS !== $event->getLocation()
            || 'mautic_campaign_index' !== $event->getRoute()
            || !$this->security->hasEntityAccess('campaign:campaigns:editown', 'campaign:campaigns:editother', $campaign->getCreatedBy())) {
            return;
        }

        $published = $campaign->isPublished();
        $event->addButton(
            [
                'attr' => [
                    // not 'ajax': core would add that and navigate; the script clicks core's icon
                    'data-toggle'                 => 'campaign-status',
                    'data-campaign-id'            => $campaign->getId(),
                    'data-campaign-status-action' => $published ? 'deactivate' : 'activate',
                    'href'                        => 'javascript:void(0);',
                ],
                'btnText'   => $this->translator->trans($published ? 'mautic.patches.segment.deactivate' : 'mautic.patches.segment.activate'),
                'iconClass' => $published ? 'ri-close-circle-line' : 'ri-checkbox-circle-line',
                'priority'  => 300, // first in the row menu: above Edit and, far below, Delete
            ],
            ButtonHelper::LOCATION_LIST_ACTIONS
        );
    }
}
