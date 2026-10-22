<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\Service\UpdatingIndicator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps a user from clicking "Update" (SegmentListButtonSubscriber) again and
 * again on the same segment. The click only starts the rebuild in the
 * background and answers at once, so nothing on screen says it is running.
 * The server already refuses a second run of the same segment (the command's
 * lock), so this is about the screen: no pile of notifications.
 *
 * After a click on a segment's Update, the button of THAT segment (not the
 * others, which can be updated meanwhile) is locked until the rebuild is DONE:
 * shown disabled (its icon unchanged), and any further click on it is
 * swallowed. In the list, the "Updated on ..." line of that segment
 * (SegmentLastBuiltSubscriber) becomes "<spinner> Updating..." meanwhile.
 *
 * "Done" is read from the server: every POLL_MS the script asks
 * SegmentRebuildController::statusAction for the segment's last built date.
 * The command writes that date when it ENDS, so the first answer (taken at the
 * click, long before the command can end) is the baseline and a different date
 * means it finished. Then the list (or the segment's page) is reloaded, which
 * refreshes "N contacts" and "Updated on", and the button is free again. If no
 * end is seen within MAX_WAIT_MS (a very large segment, a failed start) it is
 * freed and reloaded anyway.
 *
 * The click's own answer redirects to the list, which replaces the page, so the
 * lock lives in a script variable and is applied again after every Mautic ajax
 * page load (it wraps Mautic.onPageLoad). The reload only happens if the user
 * is still on the same page. Nothing is stored in the browser: a plain reload
 * of the page frees the button (the server's lock still protects the segment).
 *
 * Injected the same way as CampaignViewOrderSubscriber
 * (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentUpdateLockSubscriber implements EventSubscriberInterface
{
    /** How often the screen asks whether the rebuild is done. */
    public const POLL_MS = 3000;

    /** The longest the button stays locked waiting for it (a rebuild that never reports an end must not lock it forever). */
    public const MAX_WAIT_MS = 120000;

    public const STYLE = UpdatingIndicator::STYLE;

    private const SPINNER_SVG = UpdatingIndicator::SPINNER_SVG;

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

        $customContentEvent->addContent('<script>'.self::script(self::POLL_MS, self::MAX_WAIT_MS).'</script>');
        $customContentEvent->addContent('<style>'.self::STYLE.'</style>');
    }

    /**
     * The page script. $kind picks what it locks: 'segment' (the default; done = the segment's last built date changed) or
     * 'campaign' (CampaignUpdateLockSubscriber; a campaign has no such date, so done = the status says it is no longer running,
     * and the first question waits one poll so the click's request has started the update).
     */
    public static function script(int $pollMs, int $maxWaitMs, string $kind = 'segment'): string
    {
        $campaign = 'campaign' === $kind;
        $link     = 'a[href*=\"/s/'.$kind.'-rebuild/\"]';
        $line     = $campaign ? '.campaign-updating-line' : '.segment-last-built';

        return '(function(){'
            .'if(window.mauticPatches'.ucfirst($kind).'UpdateLock){return;}'
            .'window.mauticPatches'.ucfirst($kind).'UpdateLock=true;'
            .'var locked={};'
            .'var re=/\/s\/'.$kind.'-rebuild\/(\d+)/;'
            .'function idOf(a){var m=re.exec(a.getAttribute("href")||"");return m?m[1]:null;}'
            .'function decorate(){'
            // The button: only disabled, its icon stays as it is.
            .'mQuery("'.$link.'").each(function(){'
            .'var id=idOf(this);if(!id||!locked[id]){return;}'
            .'mQuery(this).addClass("disabled").attr("aria-disabled","true").css("pointer-events","none");'
            .'});'
            // The "Updated on ..." line of that segment becomes "<spinner> Updating...". The spinner is core's own loader icon (Remix Icon loader-3-line, same shape as ri-loader-3-line) drawn as an inline SVG instead of through the icon font: the font glyph sat off-center in the box core gives it and wobbled when turned. An SVG in a square 24x24 viewBox turns on its center; core's own 'ri-spin' keyframes do the turning.
            .'mQuery("'.$line.'").each(function(){'
            .'var $line=mQuery(this),id=String($line.data("'.$kind.'-id"));'
            .'if(!locked[id]||$line.data("mauticPatchesUpdating")){return;}'
            .'$line.data("mauticPatchesUpdating",true);'
            .'var $spinner=mQuery(\''.self::SPINNER_SVG.'\');'
            .'$line.empty().append(mQuery("<small class=\\"mautic-patches-updating\\"></small>").append($spinner).append(mQuery("<span class=\\"mautic-patches-updating-text\\"></span>").text($line.data("updating-text"))));'
            .'});'
            .'}'
            // Capture phase: runs before core's ajax link handler, so a click on a locked link never reaches it.
            .'document.addEventListener("click",function(event){'
            .'var a=event.target.closest?event.target.closest("'.$link.'"):null;'
            .'if(!a){return;}'
            .'var id=idOf(a);if(!id){return;}'
            .'if(locked[id]){event.preventDefault();event.stopImmediatePropagation();return;}'
            .'locked[id]=true;'
            .'var where=window.location.pathname+window.location.search;'
            .'var statusUrl=(a.getAttribute("href")||"").split("?")[0]+"/status";'
            .'var started=Date.now(),baseline,asked=false;'
            // Done: free the button and, if the user is still on the same page, reload it (new "N contacts" and "Updated on").
            .'function finish(){'
            .'delete locked[id];'
            .'if(window.location.pathname+window.location.search===where){Mautic.loadContent(where);}'
            .'}'
            .'function next(){if(Date.now()-started>='.$maxWaitMs.'){finish();return;}setTimeout(check,'.$pollMs.');}'
            // The rebuild writes the segment's last built date when it ENDS: the first answer (taken at the click, long before the
            // command can end) is the baseline, a different date after it means the rebuild is done.
            .'function check(){'
            // The click's own answer sends the page to the list core builds (/s/campaigns/1, the search filter kept in the session), so the page of a campaign is read again at the first question, after the answer.
            .($campaign ? "if(!asked){asked=true;where=window.location.pathname+window.location.search;}" : '')
            .'mQuery.ajax({url:statusUrl,dataType:"json",global:false}).done(function(r){'
            .($campaign
                ? 'if(r.running===false){finish();return;}'
                : 'if(baseline===undefined){baseline=r.lastBuilt;next();return;}if(r.lastBuilt!==baseline){finish();return;}')
            .'next();'
            .'}).fail(next);'
            .'}'
            .'setTimeout(decorate,0);'
            .($campaign ? 'next();' : 'check();')
            .'},true);'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);decorate();return result;};'
            .'})();';
    }
}
