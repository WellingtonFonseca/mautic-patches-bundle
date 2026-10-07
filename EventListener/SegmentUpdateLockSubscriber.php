<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
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

    // The "Updating..." text: bold, in the theme's link color (--link-primary, readable in light and dark), breathing softly. It is a fade (opacity), not movement, so it also runs for people who ask the system to reduce motion (on Windows, "Show animations" off): behind that setting it never showed.
    private const STYLE = '@keyframes mauticPatchesPulse{0%,100%{opacity:1}50%{opacity:.35}}'
        .'.mautic-patches-updating{color:var(--link-primary);font-weight:600}'
        .'.mautic-patches-updating-text{display:inline-block;animation:mauticPatchesPulse 1.2s ease-in-out infinite}';

    // Remix Icon "loader-3-line" (Apache-2.0), as drawn by the library: two rings open on the sides, symmetric around (12, 12).
    private const SPINNER_SVG = '<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true" style="display:inline-block;vertical-align:-2px;margin-right:4px;animation:ri-spin 1s linear infinite;"><path d="M3.05469 13H5.07065C5.55588 16.3923 8.47329 19 11.9998 19C15.5262 19 18.4436 16.3923 18.9289 13H20.9448C20.4474 17.5 16.6323 21 11.9998 21C7.36721 21 3.55213 17.5 3.05469 13ZM3.05469 11C3.55213 6.50005 7.36721 3 11.9998 3C16.6323 3 20.4474 6.50005 20.9448 11H18.9289C18.4436 7.60771 15.5262 5 11.9998 5C8.47329 5 5.55588 7.60771 5.07065 11H3.05469Z"/></svg>';

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

    public static function script(int $pollMs, int $maxWaitMs): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentUpdateLock){return;}'
            .'window.mauticPatchesSegmentUpdateLock=true;'
            .'var locked={};'
            .'var re=/\/s\/segment-rebuild\/(\d+)/;'
            .'function idOf(a){var m=re.exec(a.getAttribute("href")||"");return m?m[1]:null;}'
            .'function decorate(){'
            // The button: only disabled, its icon stays as it is.
            .'mQuery("a[href*=\"/s/segment-rebuild/\"]").each(function(){'
            .'var id=idOf(this);if(!id||!locked[id]){return;}'
            .'mQuery(this).addClass("disabled").attr("aria-disabled","true").css("pointer-events","none");'
            .'});'
            // The "Updated on ..." line of that segment becomes "<spinner> Updating...". The spinner is core's own loader icon (Remix Icon loader-3-line, same shape as ri-loader-3-line) drawn as an inline SVG instead of through the icon font: the font glyph sat off-center in the box core gives it and wobbled when turned. An SVG in a square 24x24 viewBox turns on its center; core's own 'ri-spin' keyframes do the turning.
            .'mQuery(".segment-last-built").each(function(){'
            .'var $line=mQuery(this),id=String($line.data("segment-id"));'
            .'if(!locked[id]||$line.data("mauticPatchesUpdating")){return;}'
            .'$line.data("mauticPatchesUpdating",true);'
            .'var $spinner=mQuery(\''.self::SPINNER_SVG.'\');'
            .'$line.empty().append(mQuery("<small class=\\"mautic-patches-updating\\"></small>").append($spinner).append(mQuery("<span class=\\"mautic-patches-updating-text\\"></span>").text($line.data("updating-text"))));'
            .'});'
            .'}'
            // Capture phase: runs before core's ajax link handler, so a click on a locked link never reaches it.
            .'document.addEventListener("click",function(event){'
            .'var a=event.target.closest?event.target.closest("a[href*=\"/s/segment-rebuild/\"]"):null;'
            .'if(!a){return;}'
            .'var id=idOf(a);if(!id){return;}'
            .'if(locked[id]){event.preventDefault();event.stopImmediatePropagation();return;}'
            .'locked[id]=true;'
            .'var where=window.location.pathname+window.location.search;'
            .'var statusUrl=(a.getAttribute("href")||"").split("?")[0]+"/status";'
            .'var started=Date.now(),baseline;'
            // Done: free the button and, if the user is still on the same page, reload it (new "N contacts" and "Updated on").
            .'function finish(){'
            .'delete locked[id];'
            .'if(window.location.pathname+window.location.search===where){Mautic.loadContent(where);}'
            .'}'
            .'function next(){if(Date.now()-started>='.$maxWaitMs.'){finish();return;}setTimeout(check,'.$pollMs.');}'
            // The rebuild writes the segment's last built date when it ENDS: the first answer (taken at the click, long before the
            // command can end) is the baseline, a different date after it means the rebuild is done.
            .'function check(){'
            .'mQuery.ajax({url:statusUrl,dataType:"json",global:false}).done(function(r){'
            .'if(baseline===undefined){baseline=r.lastBuilt;next();return;}'
            .'if(r.lastBuilt!==baseline){finish();return;}'
            .'next();'
            .'}).fail(next);'
            .'}'
            .'setTimeout(decorate,0);'
            .'check();'
            .'},true);'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);decorate();return result;};'
            .'})();';
    }
}
