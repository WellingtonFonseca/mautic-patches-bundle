<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller\Api;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Mautic\CoreBundle\Helper\AppVersion;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\MauticPatchesBundle\Controller\Api\PluginsInstalledApiController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('area:plugin-settings')]
#[Group('risk:INFRA')]
#[Group('risk:FLUXO')]
class PluginsInstalledApiControllerTest extends TestCase
{
    private EntityRepository&MockObject $repository;

    private CorePermissions&MockObject $security;

    private PluginsInstalledApiController $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $em               = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Plugin::class)->willReturn($this->repository);
        $this->security = $this->createMock(CorePermissions::class);
        $appVersion     = $this->createMock(AppVersion::class);
        $appVersion->method('getVersion')->willReturn('5.2.1');

        $this->controller = new PluginsInstalledApiController($em, $this->security, $appVersion);
    }

    /**
     * @param bool[] $integrationsPublished
     */
    private function plugin(string $name, string $bundle, ?string $version, bool $missing, array $integrationsPublished): Plugin
    {
        $integrations = [];
        foreach ($integrationsPublished as $published) {
            $integration = $this->createMock(Integration::class);
            $integration->method('getIsPublished')->willReturn($published);
            $integrations[] = $integration;
        }
        $plugin = $this->createMock(Plugin::class);
        $plugin->method('getName')->willReturn($name);
        $plugin->method('getBundle')->willReturn($bundle);
        $plugin->method('getVersion')->willReturn($version);
        $plugin->method('getIsMissing')->willReturn($missing);
        $plugin->method('getIntegrations')->willReturn(new ArrayCollection($integrations));

        return $plugin;
    }

    public function testWithoutThePluginsManagePermissionItIs403AndTheDatabaseIsNotRead(): void
    {
        $this->security->expects($this->once())->method('isGranted')->with('plugin:plugins:manage')->willReturn(false);
        $this->repository->expects($this->never())->method('findBy');

        $response = $this->controller->listAction();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            ['errors' => [['code' => 403, 'message' => 'Access denied.']]],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testItListsThePluginsWithTheMauticVersionInTheDocumentedShape(): void
    {
        $this->security->method('isGranted')->with('plugin:plugins:manage')->willReturn(true);
        $this->repository->method('findBy')->with([], ['name' => 'ASC'])->willReturn([
            $this->plugin('Mautic Patches', 'MauticPatchesBundle', '0.7.1', false, [true, false]),
            $this->plugin('Gone', 'GoneBundle', null, true, [false]),
            $this->plugin('NoIntegration', 'NoIntegrationBundle', '1.0', false, []),
        ]);

        $response = $this->controller->listAction();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                'mauticVersion' => '5.2.1',
                'plugins'       => [
                    ['name' => 'Mautic Patches', 'bundle' => 'MauticPatchesBundle', 'version' => '0.7.1', 'isMissing' => false, 'published' => true],
                    ['name' => 'Gone', 'bundle' => 'GoneBundle', 'version' => null, 'isMissing' => true, 'published' => false],
                    ['name' => 'NoIntegration', 'bundle' => 'NoIntegrationBundle', 'version' => '1.0', 'isMissing' => false, 'published' => false],
                ],
            ],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testNoPluginsGivesAnEmptyListNotAnError(): void
    {
        $this->security->method('isGranted')->willReturn(true);
        $this->repository->method('findBy')->willReturn([]);

        $this->assertSame(
            ['mauticVersion' => '5.2.1', 'plugins' => []],
            json_decode((string) $this->controller->listAction()->getContent(), true)
        );
    }
}
