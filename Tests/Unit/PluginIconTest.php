<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mautic shows a plugin's icon from <plugin>/Assets/img/icon.png and falls back
 * to its own generic one (a node graph that looks like another tool's logo)
 * when that file is missing.
 */
class PluginIconTest extends TestCase
{
    private const ICON = __DIR__.'/../../Assets/img/icon.png';

    public function testThePluginHasItsOwnIcon(): void
    {
        $this->assertFileExists(self::ICON);
    }

    public function testItIsASquarePng(): void
    {
        $info = getimagesize(self::ICON);

        $this->assertNotFalse($info, 'not an image');
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
        $this->assertSame($info[0], $info[1], 'the icon is shown in a square slot');
        $this->assertGreaterThanOrEqual(128, $info[0]);
    }

    public function testItIsNotMauticsGenericIcon(): void
    {
        $generic = '/var/www/html/docroot/app/bundles/PluginBundle/Assets/img/generic.png';

        if (!is_file($generic)) {
            $this->markTestSkipped('Mautic core is not next to the plugin.');
        }

        $this->assertNotSame(md5_file($generic), md5_file(self::ICON));
    }
}
