<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use MauticPlugin\MauticPatchesBundle\Service\LocalizedDateFormatter;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use MauticPlugin\MauticPatchesBundle\Service\UpdatingIndicator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * The Ativo / Inativo badge of the segment and campaign lists (and the reentry badge of the campaign), copied to the details page: circles left of the
 * Editar button, in the look of the contact page (ContactDetailStatusSubscriber::STYLE with its class renamed, so the size is written once). Core keeps its own
 * status label under the title on the segment page; on the campaign page the script removes it (placePageHeader). The state travels in a hidden marker printed here, in the 'page.header.left' hook, whose variables hold the
 * entity of the page ('campaign' on the campaign page, 'list' on the segment page); the other pages that use that hook have neither, and print nothing.
 * The script is Resources/js/segment-campaign-detail-badges.js.
 */
class SegmentCampaignDetailStatusSubscriber implements EventSubscriberInterface
{
    private const SCRIPT = 'Resources/js/segment-campaign-detail-badges.js';

    // The contacts badge of the segment (the pill of the list's contacts column, as tall as the circles beside it) and the "Updated on ..." line
    // under the toolbar (14px, right-aligned). Colors as .patches-badge--info / --neutral / --warning with --soft of AbstractListStatusSubscriber.
    private const SEGMENT_STYLE = '.patches-details-badges .patches-count-badge{display:inline-flex;align-items:center;justify-content:center;flex:none;box-sizing:border-box;height:40px;margin:0;padding:0 14px;'
        .'border-radius:20px;gap:4px;font-size:14px;font-weight:600;line-height:normal;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap;text-decoration:none}'
        .'.patches-count-badge.patches-badge--info{background-color:rgba(30,136,229,.16);color:#1e88e5;box-shadow:inset 0 0 0 1px rgba(30,136,229,.45)}'
        .'.patches-count-badge.patches-badge--neutral{background-color:rgba(120,120,120,.16);color:var(--text-secondary,#6f6f6f);box-shadow:inset 0 0 0 1px rgba(120,120,120,.4)}'
        .'.patches-count-badge.patches-badge--warning{background-color:rgba(251,140,0,.16);color:#fb8c00;box-shadow:inset 0 0 0 1px rgba(251,140,0,.45)}'
        .'.patches-count-badge>svg{display:block;flex:none;margin:0!important;vertical-align:baseline!important}'
        .'.patches-count-badge>.mautic-patches-updating-text{display:inline;animation:none;line-height:inherit}'
        .'.patches-segment-updated{display:block;margin:4px 0 0;font-size:14px;text-align:right}'
        .'.patches-segment-header .patches-segment-updated{text-align:left;margin:16px 0}'
        .'.patches-campaign-header .patches-details-badges{margin-bottom:16px}'
        .'.patches-segment-header .patches-modal-name{margin:0 0 12px;word-break:break-word;float:none}';

    public function __construct(
        private Translator $translator,
        private CoreParametersHelper $coreParametersHelper,
        private ListModel $listModel,
        private LocalizedDateFormatter $dateFormatter,
        private RouterInterface $router,
        private Environment $twig,
        private SegmentPrefixStore $prefixes,
        private CampaignPrefixStore $campaignPrefixes
    ) {
    }

    public static function style(): string
    {
        return str_replace('.patches-contact-badges', '.patches-details-badges', ContactDetailStatusSubscriber::STYLE).self::SEGMENT_STYLE.UpdatingIndicator::STYLE;
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

        if ($entity instanceof LeadList) {
            // the segment page: the header component (title, badges, Updated line), which the script puts in place of core's heading
            $customContentEvent->addContent($this->headHtml($entity).StatusScript::html($this->translator, self::style(), 'installSegmentCampaignDetail', self::SCRIPT));

            return;
        }

        // the campaign page: the same component (Campaign/header.html.twig), which the script puts in place of core's heading
        $customContentEvent->addContent($this->campaignHeadHtml($entity).StatusScript::html($this->translator, self::style(), 'installSegmentCampaignDetail', self::SCRIPT));
    }

    /**
     * The header component of a campaign (Campaign/header.html.twig): the title "PREFIX - Name", the hidden marker (status, reentry) the script
     * draws the badges from. No "Updated on" line: a campaign has no build date. Reusable by a campaign modal: render it and add the script.
     */
    public function campaignHeadHtml(Campaign $campaign): string
    {
        $name   = (string) $campaign->getName();
        $prefix = $this->campaignPrefixes->get($campaign->getId());

        return $this->twig->render('@MauticPatches/Campaign/header.html.twig', [
            'title'            => null !== $prefix && '' !== $prefix ? $prefix.' - '.$name : $name,
            'markerAttributes' => sprintf('data-status="%s"', $campaign->isPublished() ? 'published' : 'unpublished').($campaign->allowRestart() ? ' data-restart="1"' : ''),
        ]);
    }

    /**
     * The header component of a segment (Segment/header.html.twig), shared by the details modal of the list and the segment page: the title
     * "PREFIX - Name", the hidden marker the script draws the badges from, and the "Updated on" line. Twig is the only place of its markup.
     */
    public function headHtml(LeadList $list): string
    {
        $name   = (string) $list->getName();
        $prefix = $this->prefixes->get($list->getId());

        return $this->twig->render('@MauticPatches/Segment/header.html.twig', [
            'title'            => null !== $prefix && '' !== $prefix ? $prefix.' - '.$name : $name,
            'segmentId'        => $list->getId(),
            'builtText'        => $this->builtText($list),
            'updatingText'     => $this->translator->trans('mautic.patches.segment.updating'),
            'markerAttributes' => sprintf('data-status="%s"', $list->isPublished() ? 'published' : 'unpublished').$this->segmentAttributes($list),
        ]);
    }

    /** The head of the details modal of the segment list: the header component and the script that draws its badges. */
    public function modalHeadHtml(LeadList $list): string
    {
        return $this->headHtml($list).StatusScript::html($this->translator, self::style(), 'installSegmentModalHead', self::SCRIPT);
    }

    /** The head of the details modal of the campaign list: the campaign header component and the script that draws its badges. */
    public function campaignModalHeadHtml(Campaign $campaign): string
    {
        return $this->campaignHeadHtml($campaign).StatusScript::html($this->translator, self::style(), 'installSegmentModalHead', self::SCRIPT);
    }

    private function builtText(LeadList $list): string
    {
        $builtAt = $list->getLastBuiltDate();

        return null === $builtAt
            ? $this->translator->trans('mautic.patches.segment.never_built')
            : $this->translator->trans('mautic.patches.segment.last_built', ['%date%' => $this->dateFormatter->format((new DateTimeHelper($builtAt))->getLocalDateTime(), $this->translator->getLocale())]);
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

        $count      = (int) $this->listModel->getRepository()->getLeadCount($list->getId());
        $builtAt    = $list->getLastBuiltDate();
        $builtText  = $this->builtText($list);
        // The contacts badge and the "Updated on" line (SegmentLastBuiltSubscriber's text): written by the script of the page, which also swaps both while an Update runs.
        $attributes = sprintf(
            ' data-segment-id="%d" data-count="%d" data-count-text="%s" data-count-url="%s" data-built-text="%s" data-updating-text="%s"',
            $list->getId(),
            $count,
            htmlspecialchars($this->translator->trans('mautic.lead.list.viewleads_count', ['%count%' => $count]), ENT_QUOTES),
            htmlspecialchars($this->router->generate('mautic_contact_index', ['search' => $this->translator->trans('mautic.lead.lead.searchcommand.list').':'.$list->getAlias()]), ENT_QUOTES),
            htmlspecialchars($builtText, ENT_QUOTES),
            htmlspecialchars($this->translator->trans('mautic.patches.segment.updating'), ENT_QUOTES)
        );
        $hours      = null !== $builtAt ? (time() - $builtAt->getTimestamp()) / 3600 : null;
        if (null !== $hours && $hours >= $this->coreParametersHelper->get('segment_rebuild_time_warning')) {
            $flags[]    = 'rebuild';
            $attributes .= sprintf(' data-rebuild-hours="%d"', round($hours));
        }
        if ($list->getLastBuiltTime() >= $this->coreParametersHelper->get('segment_build_time_warning')) {
            $flags[]    = 'slow';
            $attributes .= sprintf(' data-build-seconds="%d"', round($list->getLastBuiltTime()));
        }

        return ($flags ? sprintf(' data-flags="%s"', implode(',', $flags)) : '').$attributes;
    }
}
