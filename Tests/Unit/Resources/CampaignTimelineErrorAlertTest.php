<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Resources;

use MauticPlugin\MauticPatchesBundle\DependencyInjection\Compiler\CoreTemplateOverridePass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class CampaignTimelineErrorAlertTest extends TestCase
{
    private const CORE_TAG = '<p class="text-danger mt-0 mb-10"><i class="ri-alert-line"></i> ';

    private string $coreDir;

    protected function setUp(): void
    {
        $this->coreDir = sys_get_temp_dir().'/patches-core-'.bin2hex(random_bytes(4));
        mkdir($this->coreDir.'/SubscribedEvents/Timeline', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->coreDir.'/SubscribedEvents/Timeline/index.html.twig');
        @rmdir($this->coreDir.'/SubscribedEvents/Timeline');
        @rmdir($this->coreDir.'/SubscribedEvents');
        @rmdir($this->coreDir);
    }

    private function render(string $coreTemplate): string
    {
        file_put_contents($this->coreDir.'/SubscribedEvents/Timeline/index.html.twig', $coreTemplate);
        $loader = new FilesystemLoader();
        $loader->addPath($this->coreDir, 'MauticCampaign');
        $loader->addPath($this->coreDir, '!MauticCampaign');
        $loader->prependPath(CoreTemplateOverridePass::FOLDER, 'MauticCampaign');

        return (new Environment($loader, ['autoescape' => 'html']))->render('@MauticCampaign/SubscribedEvents/Timeline/index.html.twig');
    }

    public function testTheErrorParagraphBecomesAnAlertDanger(): void
    {
        $html = $this->render(self::CORE_TAG.'Last error:<br/>boom</p>');

        $this->assertStringContainsString('<p class="alert alert-danger mt-0 mb-10">Last error:<br/>boom</p>', $html);
        $this->assertStringNotContainsString('text-danger', $html);
    }

    public function testARowWithoutTheTagPassesThroughUnchanged(): void
    {
        $html = $this->render('<p class="mt-10">scheduled <b>x</b></p>');

        $this->assertSame('<p class="mt-10">scheduled <b>x</b></p>', trim($html));
    }
}
