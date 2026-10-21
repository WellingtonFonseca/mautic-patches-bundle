<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fixes the active / inactive toggle of the lists (segments, campaigns, emails... every list that uses core's
 * publishstatus_icon). Core's Mautic.togglePublishStatus (10.entity.js) shows that the request is running by adding
 * `ri-spin ri-loader-3-line` to the icon, but it only removes the old FontAwesome classes (`fa-toggle-on` /
 * `fa-toggle-off`), not `ri-toggle-fill` / `ri-toggle-line`: the icon keeps both, the toggle glyph wins and the toggle
 * itself spins, with its green / red color. While the request runs the glyph is the loader's (the spinning
 * `ri-loader-3-line`, like the "Atualizando" of the segment and campaign pages), in the neutral text color.
 *
 * Only CSS: the glyph of a `.ri-spin.ri-loader-3-line` icon that still has a toggle class is the loader's. The classes are
 * not touched, so core's own code (the answer replaces the icon; an error puts the toggle class back) works as before.
 * `:root` makes the rules more specific than the icon font's, so the order of the stylesheets does not matter.
 *
 * Injected the same way as ModalFormButtonsSubscriber (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class PublishToggleLoadingSubscriber implements EventSubscriberInterface
{
    // "\eec4" is the code of ri-loader-3-line in the icon font
    public const STYLE = ':root i.ri-spin.ri-loader-3-line.ri-toggle-fill:before,:root i.ri-spin.ri-loader-3-line.ri-toggle-line:before{content:"\eec4"}'
        .':root i.ri-spin.ri-loader-3-line.toggle-publish-status{color:var(--text-secondary)!important}';

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

        $customContentEvent->addContent('<style>'.self::STYLE.'</style>');
    }
}
