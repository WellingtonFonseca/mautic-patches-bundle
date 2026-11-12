<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

class RoutesTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return require __DIR__.'/../../../Config/config.php';
    }

    public function testTheStatusRouteIsAGetOnTheSessionFirewallUnderTheRebuildPath(): void
    {
        $route = $this->config()['routes']['main']['mautic_patches_segment_rebuild_status'];

        $this->assertSame('/segment-rebuild/{id}/status', $route['path']);
        $this->assertSame('GET', $route['method']);
        $this->assertSame('MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentRebuildController::statusAction', $route['controller']);
        $this->assertSame(['id' => '\\d+'], $route['requirements']);
    }

    public function testTheStatusPathIsTheRebuildPathPlusStatusWhichIsWhatTheScriptBuilds(): void
    {
        $routes = $this->config()['routes']['main'];

        $this->assertSame(
            $routes['mautic_patches_segment_rebuild']['path'].'/status',
            $routes['mautic_patches_segment_rebuild_status']['path']
        );
    }

    public function testTheCampaignRoutesMirrorTheSegmentOnes(): void
    {
        $routes = $this->config()['routes']['main'];

        $this->assertSame('POST', $routes['mautic_patches_campaign_rebuild']['method']);
        $this->assertSame('/campaign-rebuild/{id}', $routes['mautic_patches_campaign_rebuild']['path']);
        $this->assertSame('GET', $routes['mautic_patches_campaign_rebuild_status']['method']);
        $this->assertSame($routes['mautic_patches_campaign_rebuild']['path'].'/status', $routes['mautic_patches_campaign_rebuild_status']['path']);
        $this->assertSame(['id' => '\\d+'], $routes['mautic_patches_campaign_rebuild_status']['requirements']);
    }

    public function testTheClickRouteStaysPostOnly(): void
    {
        $this->assertSame('POST', $this->config()['routes']['main']['mautic_patches_segment_rebuild']['method']);
    }

    public function testTheApiRouteStaysPostOnly(): void
    {
        $this->assertSame('POST', $this->config()['routes']['api']['mautic_patches_api_segment_rebuild']['method']);
    }

    public function testEveryRouteOnlyTakesANumericId(): void
    {
        foreach (['main', 'api'] as $firewall) {
            foreach ($this->config()['routes'][$firewall] as $name => $route) {
                if (!str_contains($route['path'], '{id}')) {
                    continue; // e.g. the Performance screen: no id at all
                }

                $this->assertSame(['id' => '\\d+'], $route['requirements'], $name);
            }
        }
    }

    public function testTheFieldDefinitionPathsDoNotMatchCoresImportRoute(): void
    {
        $routes = $this->config()['routes']['main'];

        // core's /s/{object}/import would take /s/field-definition/import first (the list of Importações)
        $this->assertSame('/field-definition/clone-export', $routes['mautic_patches_field_definition_export']['path']);
        $this->assertSame('GET', $routes['mautic_patches_field_definition_import']['method']);
        $this->assertSame('/field-definition/clone-import', $routes['mautic_patches_field_definition_import']['path']);
        $this->assertSame('POST', $routes['mautic_patches_field_definition_import_save']['method']);
        $this->assertSame($routes['mautic_patches_field_definition_import']['path'], $routes['mautic_patches_field_definition_import_save']['path']);
    }

    public function testTheRoleUserCloneRoutesTakeOnlyRolesOrUsersAndTheExportChangesNothing(): void
    {
        $routes     = $this->config()['routes']['main'];
        $controller = 'MauticPlugin\\MauticPatchesBundle\\Controller\\RoleUserCloneController::';
        $expected   = [
            'mautic_patches_role_user_clone_export'      => ['/role-user-clone/{kind}/export', 'GET', 'exportAction'],
            'mautic_patches_role_user_clone_import'      => ['/role-user-clone/{kind}/import', 'GET', 'importFormAction'],
            'mautic_patches_role_user_clone_import_save' => ['/role-user-clone/{kind}/import', 'POST', 'importSaveAction'],
        ];

        foreach ($expected as $name => [$path, $method, $action]) {
            $this->assertSame($path, $routes[$name]['path'], $name);
            $this->assertSame($method, $routes[$name]['method'], $name);
            $this->assertSame($controller.$action, $routes[$name]['controller'], $name);
            $this->assertSame(['kind' => 'roles|users'], $routes[$name]['requirements'], $name);
        }
    }
}
