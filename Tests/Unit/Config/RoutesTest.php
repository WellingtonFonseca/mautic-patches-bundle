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
}
