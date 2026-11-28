<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Integration;

use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\MauticPatchesBundle\Service\Changelog;

/**
 * Gives the plugin its tile on Settings > Plugins and a settings modal with no keys and no features: the modal exists to show the "Sobre" tab
 * and the change log (CHANGELOG.md) in tabs of their own (EventListener/PluginSettingsTabsSubscriber.php). The "Ativo" switch of the modal switches nothing:
 * the fixes of the plugin work whenever the plugin is installed, so a new integration is born on, and its tile is colored.
 * The tile's icon is Assets/img/mauticpatches.png (core looks for <integration name in lower case>.png, not for icon.png).
 */
class MauticPatchesIntegration extends AbstractIntegration
{
    public const NAME = 'MauticPatches';

    public const ICON = 'plugins/MauticPatchesBundle/Assets/img/mauticpatches.png';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return 'Mautic Patches';
    }

    /**
     * Core builds the row of a new integration (no id yet) and hands it here before saving it, unpublished. It is published here.
     */
    public function setIntegrationSettings(Integration $settings): void
    {
        if (null === $settings->getId()) {
            $settings->setIsPublished(true);
        }

        parent::setIntegrationSettings($settings);
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    /**
     * The About block and the change log under the fields of the first tab (core draws the 'custom' note there).
     * Core asks for the other sections too, and wants its own [text, type] pair for them.
     *
     * @return array<int|string, mixed>
     */
    public function getFormNotes($section)
    {
        if ('custom' !== $section) {
            return parent::getFormNotes($section);
        }

        return [
            'custom'     => true,
            'template'   => '@MauticPatches/Integration/_settings_tabs.html.twig',
            'parameters' => ['days' => Changelog::days(), 'icon' => self::ICON, 'version' => self::version()],
        ];
    }

    /**
     * The installed version, read from Config/config.php so it is never repeated here.
     */
    private static function version(): string
    {
        $config = require __DIR__.'/../Config/config.php';

        return (string) ($config['version'] ?? '');
    }
}
