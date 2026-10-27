<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\DependencyInjection\Compiler;

use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\SortableListThemePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SortableListThemePassTest extends TestCase
{
    public function testTheThemeIsAddedAfterCoresSoItsBlockWins(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('twig.form.resources', ['form_div_layout.html.twig', '@MauticCore/FormTheme/mautic_form_layout.html.twig']);

        (new SortableListThemePass())->process($container);

        $this->assertSame(
            ['form_div_layout.html.twig', '@MauticCore/FormTheme/mautic_form_layout.html.twig', SortableListThemePass::THEME],
            $container->getParameter('twig.form.resources')
        );
    }

    public function testTheThemeIsNotAddedTwice(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('twig.form.resources', [SortableListThemePass::THEME]);

        (new SortableListThemePass())->process($container);

        $this->assertSame([SortableListThemePass::THEME], $container->getParameter('twig.form.resources'));
    }

    public function testNothingHappensWithoutTwigsFormThemes(): void
    {
        $container = new ContainerBuilder();

        (new SortableListThemePass())->process($container);

        $this->assertFalse($container->hasParameter('twig.form.resources'));
    }

    public function testTheThemeFileDefinesTheBlockAndHasTheButtonAfterTheList(): void
    {
        $twig = (string) file_get_contents(__DIR__.'/../../../../Resources/views/FormTheme/sortablelist.html.twig');

        $this->assertStringContainsString('{% block sortablelist_row %}', $twig);
        $this->assertGreaterThan(strpos($twig, 'class="list-sortable"'), strpos($twig, 'id="{{ form.vars.id }}_additem"'));
    }
}
