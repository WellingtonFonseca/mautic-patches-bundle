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
 * others, which can be updated meanwhile) is locked for LOCK_MS: shown
 * disabled with a spinner in place of its icon (and on the row's dropdown
 * toggle, since the link sits in a menu that is closed), and any further click
 * on it is swallowed. When the time is up the list (or the segment's page) is
 * reloaded, which refreshes "N contacts" and "Updated on", and the button is
 * free again.
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
    public const LOCK_MS = 10000;

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

        $customContentEvent->addContent('<script>'.self::script(self::LOCK_MS).'</script>');
    }

    public static function script(int $lockMs): string
    {
        return '(function(){'
            .'if(window.mauticPatchesSegmentUpdateLock){return;}'
            .'window.mauticPatchesSegmentUpdateLock=true;'
            .'var locked={};'
            .'var re=/\/s\/segment-rebuild\/(\d+)/;'
            .'function idOf(a){var m=re.exec(a.getAttribute("href")||"");return m?m[1]:null;}'
            .'function spin($i){if(!$i.data("mauticPatchesIcon")){$i.data("mauticPatchesIcon",$i.attr("class"));}$i.attr("class","ri-loader-3-line ri-spin ri-fw");}'
            .'function decorate(){'
            .'mQuery("a[href*=\"/s/segment-rebuild/\"]").each(function(){'
            .'var id=idOf(this);if(!id||!locked[id]){return;}'
            .'var $a=mQuery(this);'
            .'$a.addClass("disabled").attr("aria-disabled","true").css("pointer-events","none");'
            .'spin($a.find("i").first());'
            .'spin($a.closest(".dropdown-menu").siblings(".dropdown-toggle").find("i").first());'
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
            .'setTimeout(decorate,0);'
            .'setTimeout(function(){'
            .'delete locked[id];'
            .'if(window.location.pathname+window.location.search===where){Mautic.loadContent(where);}'
            .'},'.$lockMs.');'
            .'},true);'
            .'var original=Mautic.onPageLoad;'
            .'Mautic.onPageLoad=function(){var result=original.apply(this,arguments);decorate();return result;};'
            .'})();';
    }
}
