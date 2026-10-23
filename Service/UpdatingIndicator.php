<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The look of "<spinner> Updating..." that the segment and campaign lists show while an update runs, and that the "Recalculate dates" of
 * the Wait node modal shows as "<spinner> Recalculating...": one place for the style and the spinner, used by the subscriber of the lists
 * (SegmentUpdateLockSubscriber) and by the Twig of the modal (pending_modal.html.twig).
 */
final class UpdatingIndicator
{
    // The "Updating..." text: bold, in the theme's link color (--link-primary, readable in light and dark), breathing softly. It is a fade (opacity), not movement, so it also runs for people who ask the system to reduce motion (on Windows, "Show animations" off): behind that setting it never showed.
    public const STYLE = '@keyframes mauticPatchesPulse{0%,100%{opacity:1}50%{opacity:.35}}'
        .'.mautic-patches-updating{color:var(--link-primary);font-weight:600}'
        .'.mautic-patches-updating-text{display:inline-block;animation:mauticPatchesPulse 1.2s ease-in-out infinite}';

    // Remix Icon "loader-3-line" (Apache-2.0), as drawn by the library: two rings open on the sides, symmetric around (12, 12).
    public const SPINNER_SVG = '<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true" style="display:inline-block;vertical-align:-2px;margin-right:4px;animation:ri-spin 1s linear infinite;"><path d="M3.05469 13H5.07065C5.55588 16.3923 8.47329 19 11.9998 19C15.5262 19 18.4436 16.3923 18.9289 13H20.9448C20.4474 17.5 16.6323 21 11.9998 21C7.36721 21 3.55213 17.5 3.05469 13ZM3.05469 11C3.55213 6.50005 7.36721 3 11.9998 3C16.6323 3 20.4474 6.50005 20.9448 11H18.9289C18.4436 7.60771 15.5262 5 11.9998 5C8.47329 5 5.55588 7.60771 5.07065 11H3.05469Z"/></svg>';
}
