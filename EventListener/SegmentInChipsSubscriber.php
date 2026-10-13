<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Puts the chips component (Resources/js/segment-in-chips.js) on the page: on the segment form, the value of an
 * "including" / "excluding" filter on a text field becomes chips, with suggestions and free typing.
 *
 * The suggestions of a contact or company field come from this bundle's route; those of a Custom Object field from the
 * Custom Objects plugin's route. Where that plugin is not installed its route does not exist: the URL is null and the
 * chips work without suggestions for those fields (there are none).
 *
 * Injected the same way as the other page scripts (CoreEvents::VIEW_INJECT_CUSTOM_CONTENT on 'page.header.left').
 */
class SegmentInChipsSubscriber implements EventSubscriberInterface
{
    public function __construct(private RouterInterface $router)
    {
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

        $config = [
            'valuesUrl' => $this->router->generate('mautic_patches_segment_filter_values'),
            'customUrl' => $this->customUrl(),
        ];

        $customContentEvent->addContent(
            '<script>'.file_get_contents(__DIR__.'/../Resources/js/segment-in-chips.js')
            .';mauticPatchesInChipsApi.install(mQuery,Mautic,'.json_encode($config, JSON_UNESCAPED_SLASHES).');</script>'
        );
    }

    private function customUrl(): ?string
    {
        try {
            return $this->router->generate('mautic_custom_segment_filter_values');
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
