<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Integration;

use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\MauticPatchesBundle\Form\Type\FeatureSwitchType;
use MauticPlugin\MauticPatchesBundle\Service\SegmentRebuild\KeysetRebuildSetting;
use Symfony\Component\Form\FormBuilderInterface;
use MauticPlugin\MauticPatchesBundle\Integration\MauticPatchesIntegration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:plugin-settings')]
#[Group('risk:INFRA')]
class MauticPatchesIntegrationTest extends TestCase
{
    private function integration(): MauticPatchesIntegration
    {
        // the constructor takes a fixed list of core services, not needed here
        return (new \ReflectionClass(MauticPatchesIntegration::class))->newInstanceWithoutConstructor();
    }

    public function testItIsTheTileOfThePlugin(): void
    {
        $integration = $this->integration();

        $this->assertSame('MauticPatches', $integration->getName());
        $this->assertSame('Mautic Patches', $integration->getDisplayName());
        $this->assertSame('none', $integration->getAuthenticationType());
        $this->assertSame([], $integration->getRequiredKeyFields(), 'no keys: the modal only shows the About tab and the change log');
    }

    public function testTheAboutBlockAndTheChangeLogAreTheCustomNoteOfTheFirstTab(): void
    {
        $note = $this->integration()->getFormNotes('custom');

        $this->assertSame('@MauticPatches/Integration/_settings_tabs.html.twig', $note['template']);
        $this->assertSame(MauticPatchesIntegration::ICON, $note['parameters']['icon']);
        $this->assertTrue($note['custom'], 'core only draws the note when custom is set');
        $this->assertNotEmpty($note['parameters']['days']);
    }

    public function testTheTileIconIsTheFileCoreLooksForAndMatchesThePluginIcon(): void
    {
        $this->assertSame('plugins/MauticPatchesBundle/Assets/img/'.strtolower(MauticPatchesIntegration::NAME).'.png', MauticPatchesIntegration::ICON);
        $this->assertFileEquals(__DIR__.'/../../../Assets/img/icon.png', __DIR__.'/../../../Assets/img/mauticpatches.png');
    }

    public function testTheTabsTemplateDrawsTheAboutBlockBeforeTheChangeLog(): void
    {
        $template = file_get_contents(__DIR__.'/../../../Resources/views/Integration/_settings_tabs.html.twig');

        $this->assertLessThan(strpos($template, '_changelog.html.twig'), strpos($template, '_about.html.twig'));
    }

    public function testTheOtherSectionsKeepCoresNotes(): void
    {
        $this->assertSame(['', 'info'], $this->integration()->getFormNotes('authorization'));
    }

    /** The integration without core's services: reading its keys (what core does next) is cut out. */
    private function integrationWithoutKeys(): MauticPatchesIntegration
    {
        return new class() extends MauticPatchesIntegration {
            public function __construct()
            {
            }

            public function getDecryptedApiKeys($entity = false)
            {
                return [];
            }
        };
    }

    public function testANewIntegrationIsBornPublishedButASavedOneKeepsItsState(): void
    {
        $new = new Integration();
        $this->integrationWithoutKeys()->setIntegrationSettings($new);
        $this->assertTrue($new->getIsPublished(), 'a new row is turned on');

        $saved = new class() extends Integration {
            public function getId(): int
            {
                return 25;
            }
        };
        $saved->setIsPublished(false);
        $this->integrationWithoutKeys()->setIntegrationSettings($saved);
        $this->assertFalse($saved->getIsPublished(), 'a saved row is left as it is');
    }

    public function testItIsRegisteredWithCoresServicesInTheConfig(): void
    {
        $config = require __DIR__.'/../../../Config/config.php';
        $entry  = $config['services']['integrations']['mautic.integration.mauticpatches'];

        $this->assertSame(MauticPatchesIntegration::class, $entry['class']);
        $this->assertCount(16, $entry['arguments'], 'AbstractIntegration takes 16 services');
    }

    /** @return array<string, array{mixed, bool}> */
    public static function savedSwitch(): array
    {
        return ['not saved yet = on' => [null, true], 'saved off' => [0, false], 'saved on' => [1, true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('savedSwitch')]
    public function testTheFeaturesTabHasTheKeysetSwitchWithTheSavedValue(mixed $saved, bool $expected): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->once())->method('add')->with(
            KeysetRebuildSetting::FIELD,
            FeatureSwitchType::class,
            $this->callback(fn (array $o) => $o['data'] === $expected
                && 'mautic.patches.settings.keyset_rebuild' === $o['label']
                && 'mautic.patches.settings.keyset_rebuild.note' === $o['alert'])
        );
        $data = null === $saved ? [] : [KeysetRebuildSetting::FIELD => $saved];

        $this->integration()->appendToForm($builder, $data, 'features');
    }

    /** @return array<string, array{string}> */
    public static function otherAreas(): array
    {
        return ['keys' => ['keys'], 'authorization' => ['authorization'], 'custom' => ['custom']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('otherAreas')]
    public function testOtherTabsGetNoField(string $area): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->never())->method('add');

        $this->integration()->appendToForm($builder, [], $area);
    }

    public function testTheSwitchTextsExistInBothLanguages(): void
    {
        foreach (['en_US', 'pt_BR'] as $lang) {
            $ini = (string) file_get_contents(__DIR__.'/../../../Translations/'.$lang.'/messages.ini');
            $this->assertMatchesRegularExpression('/^mautic\.patches\.settings\.keyset_rebuild="[^"]+"/m', $ini, $lang);
            $this->assertMatchesRegularExpression('/^mautic\.patches\.settings\.keyset_rebuild\.note="[^"]+"/m', $ini, $lang);
        }
    }
}
