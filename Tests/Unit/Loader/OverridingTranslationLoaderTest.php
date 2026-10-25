<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Loader;

use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\OverridingTranslationLoaderPass;
use MauticPlugin\MauticPatchesBundle\Loader\OverridingTranslationLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

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
        $this->assertArrayHasKey('mautic.core.notice.campaign.unpublished.email', $overrides['flashes']);
        $this->assertArrayHasKey('mautic.integration.sync.missing_mautic_field_mappings', $overrides['validators']);
        // the placeholders of the English texts, named as core fills them (the text of the pack had %contato%, %ccount%, %mensagem%...)
        $this->assertStringContainsString('%contact%', $overrides['messages']['mautic.email.contact_has_unsubscribed_from_category']);
        $this->assertStringContainsString('%count%', $overrides['messages']['mautic.campaign.trigger.events_executed']);
        $this->assertStringContainsString('%message%', $overrides['messages']['mautic.core.command.transifex_error_pulling_data']);
        $this->assertStringContainsString('%project%', $overrides['flashes']['mautic.project.notice.entities_added']);
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
        // core's loader is final on this version: its answer is made by a stand-in
        $core = $this->createStub(LoaderInterface::class);
        $core->method('load')->willReturn(new MessageCatalogue('pt_BR', ['messages' => ['mautic.core.close_back' => 'Voltar ao %alvo%', 'other' => 'Outro']]));

        $catalogue = (new OverridingTranslationLoader($core))->load('mautic', 'pt_BR', 'messages');
        // the texts the overrides do not name are core's
        $this->assertSame('Outro', $catalogue->get('other', 'messages'));

        $this->assertSame('Voltar ao %target%', $catalogue->get('mautic.core.close_back', 'messages'));
        // another domain of the overrides lands in its own domain
        $this->assertSame('%name% foi desbloqueado e agora está disponível para edição.', $catalogue->get('mautic.core.action.entity.unlocked', 'flashes'));
    }

    public function testThePassPutsTheWrapperUnderCoresServiceIdOverACopyOfCoresDefinition(): void
    {
        $container  = new ContainerBuilder();
        $definition = new Definition('Mautic\CoreBundle\Loader\TranslationLoader', ['mautic.helper.bundle', 'mautic.helper.paths']);
        $definition->addTag('translation.loader', ['alias' => 'mautic'])->setPublic(true);
        $container->setDefinition('mautic.translation.loader', $definition);

        (new OverridingTranslationLoaderPass())->process($container);

        // the service id keeps its tag and alias, and is the wrapper
        $wrapper = $container->getDefinition('mautic.translation.loader');
        $this->assertSame(OverridingTranslationLoader::class, $wrapper->getClass());
        $this->assertEquals([new Reference(OverridingTranslationLoaderPass::CORE_COPY)], $wrapper->getArguments());
        $this->assertSame(['alias' => 'mautic'], $wrapper->getTag('translation.loader')[0]);
        // core's own loader is a private copy with core's arguments and no tag
        $copy = $container->getDefinition(OverridingTranslationLoaderPass::CORE_COPY);
        $this->assertSame('Mautic\CoreBundle\Loader\TranslationLoader', $copy->getClass());
        $this->assertSame(['mautic.helper.bundle', 'mautic.helper.paths'], $copy->getArguments());
        $this->assertSame([], $copy->getTags());
        $this->assertFalse($copy->isPublic());

        // a second pass does not wrap the wrapper
        (new OverridingTranslationLoaderPass())->process($container);
        $this->assertEquals([new Reference(OverridingTranslationLoaderPass::CORE_COPY)], $container->getDefinition('mautic.translation.loader')->getArguments());
    }

    public function testThePassDoesNothingWithoutCoresLoader(): void
    {
        $container = new ContainerBuilder();

        (new OverridingTranslationLoaderPass())->process($container);

        $this->assertFalse($container->hasDefinition('mautic.translation.loader'));
    }
}
