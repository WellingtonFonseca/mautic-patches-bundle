<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\SegmentRebuild;

use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticPatchesBundle\Integration\MauticPatchesIntegration;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuild\KeysetRebuildSetting;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:segment')]
#[Group('area:plugin-settings')]
#[Group('risk:DADOS')]
class KeysetRebuildSettingTest extends TestCase
{
    private function setting(mixed $integrationObject): KeysetRebuildSetting
    {
        $helper = $this->createMock(IntegrationHelper::class);
        $helper->method('getIntegrationObject')->with(MauticPatchesIntegration::NAME)->willReturn($integrationObject);

        return new KeysetRebuildSetting(static fn () => $helper);
    }

    private function integrationWith(array $featureSettings): MauticPatchesIntegration
    {
        $settings = new Integration();
        $settings->setFeatureSettings($featureSettings);
        $integration = $this->createMock(MauticPatchesIntegration::class);
        $integration->method('getIntegrationSettings')->willReturn($settings);

        return $integration;
    }

    public function testNotSavedYetMeansOn(): void
    {
        $this->assertTrue($this->setting($this->integrationWith([]))->isEnabled());
    }

    public function testNoIntegrationObjectMeansOn(): void
    {
        $this->assertTrue($this->setting(false)->isEnabled());
    }

    /** @return array<string, array{mixed, bool}> */
    public static function savedValues(): array
    {
        return ['no as 0' => [0, false], 'no as "0"' => ['0', false], 'no as false' => [false, false], 'yes as 1' => [1, true], 'yes as "1"' => ['1', true], 'yes as true' => [true, true]];
    }

    #[DataProvider('savedValues')]
    public function testTheSavedValueDecides(mixed $saved, bool $expected): void
    {
        $setting = $this->setting($this->integrationWith([KeysetRebuildSetting::FIELD => $saved]));

        $this->assertSame($expected, $setting->isEnabled());
    }

    public function testTheHelperIsOnlyBuiltWhenAskedAndEveryCallReadsTheCurrentValue(): void
    {
        $calls   = 0;
        $current = $this->integrationWith([KeysetRebuildSetting::FIELD => 1]);
        $helper  = $this->createMock(IntegrationHelper::class);
        $helper->method('getIntegrationObject')->willReturnCallback(function () use (&$current) { return $current; });
        $setting = new KeysetRebuildSetting(function () use ($helper, &$calls) { ++$calls; return $helper; });

        $this->assertSame(0, $calls, 'lazy: the helper reaches the segment service');
        $this->assertTrue($setting->isEnabled());
        $current = $this->integrationWith([KeysetRebuildSetting::FIELD => 0]);
        $this->assertFalse($setting->isEnabled(), 'saving the switch applies to the next call, no restart');
    }
}
