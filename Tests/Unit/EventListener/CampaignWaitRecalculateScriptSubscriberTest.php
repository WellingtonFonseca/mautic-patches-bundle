<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\CampaignWaitRecalculateScriptSubscriber;
use PHPUnit\Framework\TestCase;

/**
 * The behavior of the script itself is in Tests/js/campaign-wait-recalculate.test.js.
 */
class CampaignWaitRecalculateScriptSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('', $context);
        (new CampaignWaitRecalculateScriptSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertSame('injectViewCustomContent', CampaignWaitRecalculateScriptSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_CONTENT]);
    }

    public function testInjectsTheScriptOnPageHeaderLeftOnly(): void
    {
        $this->assertSame('<script>'.CampaignWaitRecalculateScriptSubscriber::script().'</script>', $this->content());
        $this->assertSame('', $this->content('something.else'));
    }
}
