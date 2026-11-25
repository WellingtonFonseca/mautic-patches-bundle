<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * One look for every alert (.alert-info / -success / -warning / -danger and the toasts that use the same variables) in
 * every theme. Core's themes set --notification-background-* by theme and they do not agree: the plain Dark theme paints
 * all four with the same gray (#262626) while the light ones have a pale yellow, blue, green and red.
 *
 * Light family (light, solarized-light, freire): a light yellow, blue, green and red, one step darker than the pale ones of core's light theme.
 * Dark family (dark, solarized-dark): a dark yellow, blue, green and red. Only the four variables change, so the bar, the
 * icon and the text of core keep working. The :root[theme=...] prefix keeps the order of the stylesheets out of the question.
 */
class AlertThemeSubscriber implements EventSubscriberInterface
{
    private const LIGHT_THEMES = ['light', 'solarized-light', 'freire'];
    private const DARK_THEMES  = ['dark', 'solarized-dark'];

    private const LIGHT = ['warning' => '#fbe9a6', 'info' => '#d0e2ff', 'success' => '#a7f0ba', 'error' => '#ffd7d9'];

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

        $css = $this->rules(self::LIGHT_THEMES, self::LIGHT).$this->rules(self::DARK_THEMES, self::DARK);

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
