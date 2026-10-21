<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Loader;

use Mautic\CoreBundle\Helper\BundleHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\OverridingTranslationLoaderPass;
use MauticPlugin\MauticPatchesBundle\Loader\OverridingTranslationLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class OverridingTranslationLoaderTest extends TestCase
{
    public function testThePtBrTextOfTheBackButtonNamesTheCorePlaceholder(): void
    {
        $overrides = OverridingTranslationLoader::overrides('pt_BR');

        // core's templates fill %target%; the downloaded pack names it %alvo%
        $this->assertSame('Voltar ao %target%', $overrides['mautic.core.close_back']);
        $this->assertStringNotContainsString('%alvo%', implode('', $overrides));
    }

    public function testALocaleWithoutAnOverridesFileHasNone(): void
    {
        $this->assertSame([], OverridingTranslationLoader::overrides('en_US'));
        $this->assertSame([], OverridingTranslationLoader::overrides('xx_XX'));
    }

    public function testTheOverridesGoOverCoresCatalogue(): void
    {
        $bundleHelper = $this->createMock(BundleHelper::class);
        $bundleHelper->method('getMauticBundles')->willReturn([]);
        $pathsHelper = $this->createMock(PathsHelper::class);
        $pathsHelper->method('getSystemPath')->willReturn('/nonexistent');

        $catalogue = (new OverridingTranslationLoader($bundleHelper, $pathsHelper))->load('mautic', 'pt_BR', 'messages');

        $this->assertSame('Voltar ao %target%', $catalogue->get('mautic.core.close_back', 'messages'));
    }

    public function testThePassReplacesTheClassOfCoresLoaderAndKeepsTheRest(): void
    {
        $container  = new ContainerBuilder();
        $definition = new Definition(\Mautic\CoreBundle\Loader\TranslationLoader::class, ['mautic.helper.bundle', 'mautic.helper.paths']);
        $definition->addTag('translation.loader', ['alias' => 'mautic']);
        $container->setDefinition('mautic.translation.loader', $definition);

        (new OverridingTranslationLoaderPass())->process($container);

        $this->assertSame(OverridingTranslationLoader::class, $container->getDefinition('mautic.translation.loader')->getClass());
        $this->assertSame(['mautic.helper.bundle', 'mautic.helper.paths'], $container->getDefinition('mautic.translation.loader')->getArguments());
        $this->assertSame(['alias' => 'mautic'], $container->getDefinition('mautic.translation.loader')->getTag('translation.loader')[0]);
    }

    public function testThePassDoesNothingWithoutCoresLoader(): void
    {
        $container = new ContainerBuilder();

        (new OverridingTranslationLoaderPass())->process($container);

        $this->assertFalse($container->hasDefinition('mautic.translation.loader'));
    }
}
