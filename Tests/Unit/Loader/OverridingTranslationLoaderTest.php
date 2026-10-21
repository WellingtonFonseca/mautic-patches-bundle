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
        $this->assertSame('Voltar ao %target%', $overrides['messages']['mautic.core.close_back']);
        $this->assertStringNotContainsString('%alvo%', json_encode($overrides));
    }

    public function testTheOverridesAreByDomainAndKeepTheEnglishPlaceholders(): void
    {
        $overrides = OverridingTranslationLoader::overrides('pt_BR');

        $this->assertEqualsCanonicalizing(['flashes', 'messages', 'validators'], array_keys($overrides));
        $this->assertArrayHasKey('mautic.core.notice.used.field', $overrides['flashes']);
        $this->assertArrayHasKey('mautic.integration.sync.missing_mautic_field_mappings', $overrides['validators']);
        // the placeholders of the English texts, named as core fills them (the text of the pack had %contato%, %ccount%, %mensagem%...)
        $this->assertStringContainsString('%contact%', $overrides['messages']['mautic.email.contact_has_unsubscribed_from_category']);
        $this->assertStringContainsString('%count%', $overrides['messages']['mautic.campaign.trigger.events_executed']);
        $this->assertStringContainsString('%message%', $overrides['messages']['mautic.core.command.transifex_error_pulling_data']);
        $this->assertStringContainsString('%segments%', $overrides['flashes']['mautic.core.notice.used.field']);
        // no placeholder that is not closed, spaced or in another language in any of them
        foreach ($overrides as $messages) {
            foreach ($messages as $key => $text) {
                $this->assertSame(0, substr_count(preg_replace('/%[A-Za-z_]+%/', '', $text), '%'), $key.': '.$text);
            }
        }
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
        // another domain of the overrides lands in its own domain
        $this->assertSame('%name% foi desbloqueado e agora está disponível para edição.', $catalogue->get('mautic.core.action.entity.unlocked', 'flashes'));
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
