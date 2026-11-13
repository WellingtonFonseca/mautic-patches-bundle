<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactTimelineFailedBadgeSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ContactTimelineFailedBadgeSubscriberTest extends TestCase
{
    private const ICON = '#contact-timeline .timeline-name i.ri-alert-line.text-danger';

    private function subscriberAt(?string $path): ContactTimelineFailedBadgeSubscriber
    {
        $stack = new RequestStack();
        if (null !== $path) {
            $stack->push(Request::create($path));
        }

        return new ContactTimelineFailedBadgeSubscriber($stack);
    }

    public function testTheStyleGoesInTheHeadOfTheAdmin(): void
    {
        $this->assertSame('injectAssets', ContactTimelineFailedBadgeSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_ASSETS]);

        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->once())->method('addStyleDeclaration')->with(ContactTimelineFailedBadgeSubscriber::style());

        $this->subscriberAt('/s/contacts/view/1')->injectAssets($event);
    }

    /**
     * @return iterable<string, array{0: ?string}>
     */
    public static function notAdminProvider(): iterable
    {
        yield 'api' => ['/api/contacts'];
        yield 'landing page' => ['/my-landing-page'];
        yield 'no request (command)' => [null];
    }

    #[DataProvider('notAdminProvider')]
    public function testNothingOutsideTheAdmin(?string $path): void
    {
        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->never())->method('addStyleDeclaration');

        $this->subscriberAt($path)->injectAssets($event);
    }

    public function testOnlyTheIconOfTheContactTimelineIsStyled(): void
    {
        $style = ContactTimelineFailedBadgeSubscriber::style();

        $this->assertStringContainsString(self::ICON.'{display:inline-flex;', $style);
        // the "Upcoming events" icon of core has the same classes: every rule must start with the timeline scope
        $rules = array_filter(explode('}', $style));
        $this->assertNotEmpty($rules);
        foreach ($rules as $rule) {
            $this->assertStringStartsWith(self::ICON, $rule);
        }
    }

    public function testTheWordOpensOnHoverOnlyAndTheIconIsRound(): void
    {
        $style = ContactTimelineFailedBadgeSubscriber::style();

        $this->assertStringContainsString('max-width:22px;border-radius:11px;background-color:var(--support-error)', $style);
        $this->assertStringContainsString(self::ICON.':hover{max-width:120px}', $style);
        $this->assertStringContainsString(self::ICON.'::after{content:"Falhou";', $style);
        $this->assertStringContainsString('opacity:0;', $style);
        $this->assertStringContainsString(self::ICON.':hover::after{opacity:1}', $style);
    }

    public function testTheBadgeHasTheShadowAndTheTransitionOfTheCampaignNodes(): void
    {
        $style = ContactTimelineFailedBadgeSubscriber::style();

        $this->assertStringContainsString('box-shadow:rgba(0,0,0,.2) 0 1px 4px 0;', $style);
        $this->assertStringContainsString('transition:max-width .2s ease}', $style);
    }

    public function testTheWordHasItsOwnFontBecauseTheIconFontIsForced(): void
    {
        $this->assertStringContainsString('font-family:"Source Sans Pro",Helvetica,Arial,sans-serif!important', ContactTimelineFailedBadgeSubscriber::style());
    }

    public function testTheGlyphKeepsItsBoxSoTheIconStaysUnderThePointer(): void
    {
        $this->assertStringContainsString(self::ICON.'::before{flex:0 0 22px;width:22px;height:22px;', ContactTimelineFailedBadgeSubscriber::style());
    }
}
