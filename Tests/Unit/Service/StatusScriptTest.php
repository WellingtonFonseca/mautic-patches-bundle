<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatusScriptTest extends TestCase
{
    private function html(string $install = 'installUser'): string
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return StatusScript::html($translator, 'a{b:c}', $install);
    }

    public function testTheScriptIsAVersionedStaticFileNotInlined(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression('#<script src="/plugins/MauticPatchesBundle/Resources/js/list-status-toggle\.js\?v=\d+"></script>#', $html);
        $this->assertStringNotContainsString('(function (root)', $html, 'the 14 KB file is not copied into every page');
    }

    public function testTheInstallCallComesAfterTheFileAndCarriesTheConfig(): void
    {
        $html = $this->html('installEmail');

        $this->assertLessThan(strpos($html, 'installEmail('), strpos($html, '<script src='), 'the file loads before the call that needs it');
        $this->assertStringContainsString('mauticPatchesListStatusApi.installEmail(mQuery,Mautic,{', $html);
        $this->assertStringContainsString('"active":"mautic.core.form.active"', $html);
        $this->assertStringContainsString('"deactivate":"mautic.patches.segment.deactivate"', $html);
        $this->assertStringContainsString('"column":"mautic.patches.status.column"', $html);
        $this->assertStringContainsString('"restart":"mautic.patches.campaign.restart"', $html);
        $this->assertStringContainsString('"global":"mautic.patches.segment.global"', $html);
        $this->assertStringContainsString('"filters":"mautic.patches.segment.filters"', $html);
        $this->assertStringContainsString('"rebuild":"mautic.patches.segment.not_rebuilt"', $html);
        $this->assertStringContainsString('"slow":"mautic.patches.segment.slow_build"', $html);
        $this->assertStringContainsString('"style":"a{b:c}"', $html);
        $this->assertStringEndsWith(');</script>', $html, 'the call is closed, or the page script breaks');
    }

    public function testTheVersionOfTheFileIsItsModificationTime(): void
    {
        $file = dirname(__DIR__, 3).'/Resources/js/list-status-toggle.js';

        $this->assertStringContainsString('list-status-toggle.js?v='.filemtime($file).'"', $this->html(), 'the browser reloads the file only when it changed');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function configKeys(): array
    {
        return [
            'active'           => ['active', 'mautic.core.form.active'],
            'inactive'         => ['inactive', 'mautic.core.form.inactive'],
            'activate'         => ['activate', 'mautic.patches.segment.activate'],
            'deactivate'       => ['deactivate', 'mautic.patches.segment.deactivate'],
            'column'           => ['column', 'mautic.patches.status.column'],
            'restart'          => ['restart', 'mautic.patches.campaign.restart'],
            'global'           => ['global', 'mautic.patches.segment.global'],
            'filters'          => ['filters', 'mautic.patches.segment.filters'],
            'rebuild'          => ['rebuild', 'mautic.patches.segment.not_rebuilt'],
            'slow'             => ['slow', 'mautic.patches.segment.slow_build'],
            'contactEmail'     => ['contactEmail', 'mautic.patches.contact.status.email'],
            'contactSms'       => ['contactSms', 'mautic.patches.contact.status.sms'],
            'contactHsm'       => ['contactHsm', 'mautic.patches.contact.status.hsm'],
            'emailCampaign'    => ['emailCampaign', 'mautic.patches.email.type.campaign'],
            'emailBroadcast'   => ['emailBroadcast', 'mautic.patches.email.type.broadcast'],
            'emailAbtest'      => ['emailAbtest', 'mautic.patches.email.abtest'],
            'emailTranslation' => ['emailTranslation', 'mautic.patches.email.translation'],
            'emailDraft'       => ['emailDraft', 'mautic.patches.email.draft'],
        ];
    }

    #[DataProvider('configKeys')]
    public function testEveryTextOfTheScriptIsInItsConfig(string $key, string $translationKey): void
    {
        $this->assertStringContainsString('"'.$key.'":"'.$translationKey.'"', $this->html());
    }
}
