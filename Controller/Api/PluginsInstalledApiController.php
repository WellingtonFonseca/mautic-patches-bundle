<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\AppVersion;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/plugins-installed: the plugins Mautic knows (the plugins table) and
 * the Mautic version. Mautic's core has no REST route for plugins. Read only.
 * "published" is true when at least one integration of the plugin is published.
 *
 *   200 {mauticVersion, plugins: [{name, bundle, version, isMissing, published}]}
 *   403 no plugin:plugins:manage
 */
class PluginsInstalledApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CorePermissions $security,
        private AppVersion $appVersion,
    ) {
    }

    public function listAction(): JsonResponse
    {
        if (!$this->security->isGranted('plugin:plugins:manage')) {
            return new JsonResponse(
                ['errors' => [['code' => Response::HTTP_FORBIDDEN, 'message' => 'Access denied.']]],
                Response::HTTP_FORBIDDEN
            );
        }

        $plugins = [];
        foreach ($this->em->getRepository(Plugin::class)->findBy([], ['name' => 'ASC']) as $plugin) {
            $plugins[] = [
                'name'      => $plugin->getName(),
                'bundle'    => $plugin->getBundle(),
                'version'   => $plugin->getVersion(),
                'isMissing' => (bool) $plugin->getIsMissing(),
                'published' => $plugin->getIntegrations()->exists(
                    static fn (int|string $key, Integration $integration): bool => $integration->getIsPublished()
                ),
            ];
        }

        return new JsonResponse(['mauticVersion' => $this->appVersion->getVersion(), 'plugins' => $plugins]);
    }
}
