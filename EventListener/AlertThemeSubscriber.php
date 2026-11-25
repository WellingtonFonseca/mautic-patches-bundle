<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The alerts (.alert-info / -success / -warning / -danger and the toasts that use the same variables) of the two dark
 * themes. Core's themes set --notification-background-* by theme and they do not agree: the plain Dark theme paints all
 * four with the same gray (#262626). Both dark themes (dark, solarized-dark) get a dark yellow, blue, green and red.
 * The light themes are left as core has them. Only the four variables change, so the bar, the icon and the text of core
 * keep working. The :root[theme=...] prefix keeps the order of the stylesheets out of the question.
 */
class AlertThemeSubscriber implements EventSubscriberInterface
{
    private const DARK_THEMES  = ['dark', 'solarized-dark'];

    private const DARK  = ['warning' => '#4a3610', 'info' => '#12304f', 'success' => '#00402b', 'error' => '#46181b'];

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

        $css = $this->rules(self::DARK_THEMES, self::DARK);

        $customContentEvent->addContent('<style>'.$css.'</style>');
    }

    /**
     * @param string[]              $themes
     * @param array<string, string> $colors
     */
    private function rules(array $themes, array $colors): string
    {
        $selector = implode(',', array_map(static fn (string $theme): string => ':root[theme="'.$theme.'"]', $themes));
        $declarations = '';
        foreach ($colors as $name => $color) {
            $declarations .= '--notification-background-'.$name.':'.$color.';';
        }

        return $selector.'{'.$declarations.'}';
    }
}
