<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\UserBundle\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The same as CampaignStatusSubscriber, for the user list: the active / inactive toggle next to the name (an icon that is easy to hit
 * by mistake, with the status told only by its color) becomes
 *  - a label at the start of the name ("Ativo" green, "Inativo" red, informative, not a link, all with the width of "Inativo");
 *  - "Desativar" / "Ativar" as the FIRST item of the three-dots menu (Delete is the last), red / green on hover like the status it
 *    leads to; only for who may edit users.
 * Core's flow is kept: the menu item clicks core's icon (hidden), so the server checks the permission as it always did. Core prints no
 * icon on the row of the logged user (nobody turns oneself off), so that row gets a label "Ativo" and no menu item.
 *
 * The user list has no 'user.name' hook: the script (Resources/js/list-status-toggle.js, installUser) makes the labels from the
 * state of core's icons. Injected like CampaignStatusSubscriber.
 */
class UserStatusSubscriber extends AbstractListStatusSubscriber
{
    // the badge's own style (the pill, its width, hidden until placed) is AbstractListStatusSubscriber::BADGE_STYLE and SegmentStatusSubscriber::STYLE, on every page
    public const STYLE = '#userTable i[class*="user-publish-icon"]{display:none!important}'
        .'a[data-user-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-user-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-user-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-user-status-action]:hover *{color:inherit!important}';

    public function __construct(
        TranslatorInterface $translator,
        CorePermissions $security,
        private UserHelper $userHelper
    ) {
        parent::__construct($translator, $security);
    }

    protected function key(): string
    {
        return 'user';
    }

    protected function installFunction(): string
    {
        return 'installUser';
    }

    protected function style(): string
    {
        return self::STYLE;
    }

    protected function route(): string
    {
        return 'mautic_user_index';
    }

    protected function supports(mixed $item): bool
    {
        return $item instanceof User;
    }

    protected function mayEdit(object $item): bool
    {
        return $item->getId() !== $this->userHelper->getUser()->getId() && $this->security->isGranted('user:users:edit');
    }
}
