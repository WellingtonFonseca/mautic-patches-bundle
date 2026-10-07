<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

class PerformanceConfigTest extends TestCase
{
    public function testTheScreenIsAnAdminMenuEntryWithItsRoutes(): void
    {
        $config = require __DIR__.'/../../../Config/config.php';

        $this->assertSame('admin', $config['menu']['admin']['mautic.patches.perf.menu']['access']);
        $this->assertSame('/performance', $config['routes']['main']['mautic_patches_performance']['path']);
        $this->assertSame('POST', $config['routes']['main']['mautic_patches_performance_diagnose']['method']);
    }

    public function testEveryPerformanceKeyExistsInBothLanguages(): void
    {
        $keys = static function (string $locale): array {
            preg_match_all('/^(mautic\.patches\.perf\.[^=]+)=/m', (string) file_get_contents(__DIR__.'/../../../Translations/'.$locale.'/messages.ini'), $m);

            return $m[1];
        };

        $this->assertNotEmpty($keys('en_US'));
        $this->assertEqualsCanonicalizing($keys('en_US'), $keys('pt_BR'));
    }
}
