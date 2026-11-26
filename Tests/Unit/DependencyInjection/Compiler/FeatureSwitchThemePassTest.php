<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\DependencyInjection\Compiler;

use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\FeatureSwitchThemePass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Group('area:plugin-settings')]
#[Group('risk:INFRA')]
#[Group('risk:UI')]
class FeatureSwitchThemePassTest extends TestCase
{
    private const THEME = '@MauticPatches/FormTheme/feature_switch.html.twig';

    public function testTheThemeIsAddedAfterTheOnesAlreadyThere(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('twig.form.resources', ['form_div_layout.html.twig', '@MauticCore/FormTheme/mautic_form_layout.html.twig']);

        (new FeatureSwitchThemePass())->process($container);

        $this->assertSame(['form_div_layout.html.twig', '@MauticCore/FormTheme/mautic_form_layout.html.twig', self::THEME], $container->getParameter('twig.form.resources'));
    }

    public function testTheThemeIsNotAddedTwice(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('twig.form.resources', [self::THEME]);

        (new FeatureSwitchThemePass())->process($container);

        $this->assertSame([self::THEME], $container->getParameter('twig.form.resources'));
    }

    public function testWithoutTwigThemesTheListIsJustTheTheme(): void
    {
        $container = new ContainerBuilder();

        (new FeatureSwitchThemePass())->process($container);

        $this->assertSame([self::THEME], $container->getParameter('twig.form.resources'));
    }

    public function testTheThemeFileDefinesTheRowBlockOfTheType(): void
    {
        $twig = (string) file_get_contents(__DIR__.'/../../../../Resources/views/FormTheme/feature_switch.html.twig');

        $this->assertStringContainsString('{% block patches_feature_switch_row %}', $twig);
        $this->assertStringContainsString('alert', $twig);
    }
}
