<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentDistinctConfig;
use MauticPlugin\MauticPatchesBundle\Service\IndexedLeadFields;
use MauticPlugin\MauticPatchesBundle\Service\SegmentDistinctStore;
use MauticPlugin\MauticPatchesBundle\Service\SegmentSettingsColumn;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The read-only right column of the segment page: built from the SAVED data (segment + SegmentDistinctStore), toggles as Sim/Não.
 */
#[Group('area:segment')]
#[Group('risk:FLUXO')]
class SegmentSettingsColumnTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $vars = [];

    private function column(?SegmentDistinctConfig $config): SegmentSettingsColumn
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $vars): string {
            $this->vars = $vars + ['template' => $template];

            return 'HTML';
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id) => 'T:'.$id);
        $store = $this->createMock(SegmentDistinctStore::class);
        $store->method('get')->willReturn($config);
        $fields = $this->createMock(IndexedLeadFields::class);
        $fields->method('all')->willReturn(['city' => 'Cidade']);

        return new SegmentSettingsColumn($twig, $translator, $store, $fields);
    }

    public function testASegmentWithoutSavedDistinctConfigShowsEverythingOffAndNoFields(): void
    {
        $segment = new LeadList();

        $this->assertSame('HTML', $this->column(null)->html($segment));

        $this->assertSame('@MauticPatches/Segment/settings_column.html.twig', $this->vars['template']);
        $this->assertSame(['T:mautic.core.no', 'T:mautic.core.no'], array_column($this->vars['distinct'], 'value'));
        $this->assertSame([], $this->vars['fields']);
        $this->assertNull($this->vars['category']);
    }

    public function testTogglesShowTheSavedValuesAsYesOrNo(): void
    {
        $segment = new LeadList();
        $segment->setIsGlobal(true);
        $segment->setIsPublished(false);
        $segment->setIsPreferenceCenter(true);

        $this->column(new SegmentDistinctConfig(true, false, ['city']))->html($segment);

        $this->assertSame(['T:mautic.core.yes', 'T:mautic.core.yes', 'T:mautic.core.no'], array_column($this->vars['toggles'], 'value'));
        $this->assertSame(['T:mautic.core.yes', 'T:mautic.core.no'], array_column($this->vars['distinct'], 'value'));
    }

    public function testFieldsAreListedWithTheirLabelAndUnknownAliasesFallBackToTheAlias(): void
    {
        $this->column(new SegmentDistinctConfig(true, true, ['city', 'gone']))->html(new LeadList());

        $this->assertSame(['Cidade (city)', 'gone'], $this->vars['fields']);
    }
}
