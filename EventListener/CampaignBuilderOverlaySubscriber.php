<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes a Mautic core bug in the Campaign Builder: clicking "Locate" on a
 * Jump to Event action shows a full-canvas overlay (#EventJumpOverlay) to
 * spotlight the source and target nodes, but the overlay has no click
 * handler of its own — the only way to dismiss it is clicking the exact
 * same "Locate" link again, so any other click on the canvas is silently
 * swallowed by the overlay in the meantime. This adds a delegated click
 * handler that hides the overlay when the overlay itself is clicked.
 *
 * Injected the same way N8nDispatchBundle's AssetInjectionSubscriber does
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left', a context
 * rendered on every admin page) — harmless elsewhere, since the selector
 * only matches an element that exists on the campaign builder page.
 *
 * Mautic.highlightJumpTarget (campaign.js) stores its toggle state as a
 * jQuery .data('highlighted') flag on the source event's inner
 * .campaign-event-type div (the click target's parent().parent()), and
 * bumps that same element's z-index to 2010 — separately from the jump
 * target, whose outer .list-campaign-event wrapper is what gets bumped
 * instead. Only resetting z-index on click (an earlier version of this
 * fix) left that .data('highlighted') flag stuck at true, so the *next*
 * "Locate" click re-entered the "already highlighted" branch and quietly
 * turned itself off again — needing a second click to actually reopen the
 * overlay. Resetting both elements' z-index and the flag together keeps
 * this click in sync with what core's own toggle-off does.
 */
class CampaignBuilderOverlaySubscriber implements EventSubscriberInterface
{
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

        $customContentEvent->addContent(
            '<script>'
            .'mQuery(document).on("click", "#EventJumpOverlay", function () {'
            .'mQuery(this).hide();'
            .'mQuery(".campaign-event-type").data("highlighted", false).css("z-index", 1010);'
            .'mQuery(".list-campaign-event").css("z-index", 1010);'
            .'});'
            .'</script>'
        );
    }
}
