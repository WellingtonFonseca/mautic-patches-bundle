<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A campaign action with an error shows core's red `<i class="ri-alert-line text-danger">` after its name in the contact's History
 * (CampaignBundle LeadSubscriber builds it inside the label string, so no template can replace it). The style turns that icon into the
 * round badge of the campaign nodes: the icon alone, and on hover the word "Falhou" opens to its right while the icon stays under the pointer.
 * The word is a CSS `content`: the label string is core's and cannot carry a translated span. The tooltip of core stays.
 * Twin of `.n8ndispatch-status-badge` (n8n-dispatch plugin): plugins stay independent, keep the two looks in step.
 */
class ContactTimelineFailedBadgeSubscriber implements EventSubscriberInterface
{
    private const ICON = '#contact-timeline .timeline-name i.ri-alert-line.text-danger';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => 'injectAssets'];
    }

    public function injectAssets(CustomAssetsEvent $assetsEvent): void
    {
        if (!str_starts_with((string) $this->requestStack->getMainRequest()?->getPathInfo(), '/s/')) {
            return;
        }

        $assetsEvent->addStyleDeclaration(self::style());
    }

    public static function style(): string
    {
        $icon = self::ICON;

        return $icon.'{display:inline-flex;align-items:center;vertical-align:middle;height:22px;max-width:22px;border-radius:11px;'
            .'background-color:var(--support-error);color:#fff!important;font-size:12px;overflow:hidden;white-space:nowrap;'
            .'box-shadow:rgba(0,0,0,.2) 0 1px 4px 0;transition:max-width .2s ease}'
            .$icon.':hover{max-width:120px}'
            // the glyph keeps its 22px box so the icon stays where the pointer is when the word opens
            .$icon.'::before{flex:0 0 22px;width:22px;height:22px;display:flex;align-items:center;justify-content:center}'
            // the remixicon font is forced on the <i> (`!important`), so the word needs its own font
            .$icon.'::after{content:"Falhou";font-family:"Source Sans Pro",Helvetica,Arial,sans-serif!important;'
            .'padding:0 10px;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.02em;opacity:0;transition:opacity .15s ease .05s}'
            .$icon.':hover::after{opacity:1}';
    }
}
