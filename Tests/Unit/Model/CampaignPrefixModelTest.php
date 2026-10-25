<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Model;

use Mautic\CampaignBundle\Model\CampaignModel;
use MauticPlugin\MauticPatchesBundle\Model\CampaignPrefixModel;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\TestCase;

class CampaignPrefixModelTest extends TestCase
{
    public function testItIsCoresCampaignModel(): void
    {
        $this->assertTrue(is_subclass_of(CampaignPrefixModel::class, CampaignModel::class));
    }

    /**
     * @param array<int|string, mixed> $parentResult
     *
     * @return array<int|string, mixed>
     */
    private function sources(?string $type, array $parentResult): array
    {
        $store = $this->createStub(SegmentPrefixStore::class);
        $store->method('all')->willReturn([5 => 'Compras']);

        return CampaignPrefixModel::prefixSources($type, $parentResult, $store);
    }

    public function testTheListsOfASingleTypeGetThePrefix(): void
    {
        $this->assertSame([5 => 'Compras - Onboarding'], $this->sources('lists', [5 => 'Onboarding']));
    }

    public function testTheListsKeyOfTheFullAnswerGetsThePrefixAndFormsAreUntouched(): void
    {
        $this->assertSame(
            ['lists' => [5 => 'Compras - Onboarding'], 'forms' => [9 => 'Contato']],
            $this->sources(null, ['lists' => [5 => 'Onboarding'], 'forms' => [9 => 'Contato']])
        );
    }

    public function testFormsAreNotTouched(): void
    {
        $this->assertSame([9 => 'Contato'], $this->sources('forms', [9 => 'Contato']));
    }
}
