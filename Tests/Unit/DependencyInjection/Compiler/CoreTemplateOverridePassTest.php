<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\DependencyInjection\Compiler;

use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\CoreTemplateOverridePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class CoreTemplateOverridePassTest extends TestCase
{
    public function testTheOverrideFolderGoesFirstInCoresNamespace(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native_filesystem', new Definition());

        (new CoreTemplateOverridePass())->process($container);

        $calls = $container->getDefinition('twig.loader.native_filesystem')->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('prependPath', $calls[0][0]);
        $this->assertSame([realpath(CoreTemplateOverridePass::FOLDER), 'MauticCampaign'], $calls[0][1]);
        $this->assertDirectoryExists($calls[0][1][0]);
    }

    public function testNothingHappensWithoutTwigsLoader(): void
    {
        $container = new ContainerBuilder();

        (new CoreTemplateOverridePass())->process($container);

        $this->assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
    }
}
