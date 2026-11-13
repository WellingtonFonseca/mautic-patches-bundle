<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Ativo / Inativo badge of the segment and campaign lists (and the reentry badge of the campaign), copied to the details page: circles left of the
 * Editar button, in the look of the contact page (ContactDetailStatusSubscriber::STYLE with its class renamed, so the size is written once). Core keeps its own
 * status label under the title. The state travels in a hidden marker printed here, in the 'page.header.left' hook, whose variables hold the
 * entity of the page ('campaign' on the campaign page, 'list' on the segment page); the other pages that use that hook have neither, and print nothing.
 * The script is Resources/js/segment-campaign-detail-badges.js.
 */
class SegmentCampaignDetailStatusSubscriber implements EventSubscriberInterface
{
    private const SCRIPT = 'Resources/js/segment-campaign-detail-badges.js';

    public function __construct(private TranslatorInterface $translator, private CoreParametersHelper $coreParametersHelper)
    {
    }

    public static function style(): string
    {
        return str_replace('.patches-contact-badges', '.patches-details-badges', ContactDetailStatusSubscriber::STYLE);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        if ('page.header.left' !== $customContentEvent->getContext()) {
            return;
        }

        $vars   = $customContentEvent->getVars();
        $entity = $vars['campaign'] ?? $vars['list'] ?? null;
        if (!$entity instanceof Campaign && !$entity instanceof LeadList) {
            return;
        }

        $customContentEvent->addContent(
            sprintf(
                '<span class="patches-detail-status" hidden data-status="%s"%s></span>',
                $entity->isPublished() ? 'published' : 'unpublished',
                $entity instanceof Campaign ? ($entity->allowRestart() ? ' data-restart="1"' : '') : $this->segmentAttributes($entity)
            )
            .StatusScript::html($this->translator, self::style(), 'installSegmentCampaignDetail', self::SCRIPT)
        );
    }

    /**
     * The flags the segment list shows after the status (the keys of SEGMENT_FLAGS in list-status-toggle.js), with the same conditions as
     * core's List/_list_row.html.twig: hours since the last build and the build time, each against its warning threshold.
     */
    private function segmentAttributes(LeadList $list): string
    {
        $flags = [];
        if ($list->getIsGlobal()) {
            $flags[] = 'global';
        }
        if (!empty($list->getFilters())) {
            $flags[] = 'filters';
        }

        $attributes = '';
        $builtAt    = $list->getLastBuiltDate();
        $hours      = null !== $builtAt ? (time() - $builtAt->getTimestamp()) / 3600 : null;
        if (null !== $hours && $hours >= $this->coreParametersHelper->get('segment_rebuild_time_warning')) {
            $flags[]    = 'rebuild';
            $attributes .= sprintf(' data-rebuild-hours="%d"', round($hours));
        }
        if ($list->getLastBuiltTime() >= $this->coreParametersHelper->get('segment_build_time_warning')) {
            $flags[]    = 'slow';
            $attributes .= sprintf(' data-build-seconds="%d"', round($list->getLastBuiltTime()));
        }

        return $flags ? sprintf(' data-flags="%s"', implode(',', $flags)).$attributes : '';
    }
}
