<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
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
        $this->assertStringContainsString('"style":"a{b:c}"', $html);
    }
}
