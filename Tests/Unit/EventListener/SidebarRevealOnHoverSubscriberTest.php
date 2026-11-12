<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SidebarRevealOnHoverSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SidebarRevealOnHoverSubscriberTest extends TestCase
{
    private function subscriberAt(?string $path): SidebarRevealOnHoverSubscriber
    {
        $stack = new RequestStack();
        if (null !== $path) {
            $stack->push(Request::create($path));
        }

        return new SidebarRevealOnHoverSubscriber($stack);
    }

    public function testTheStyleGoesInTheHeadOfTheAdmin(): void
    {
        $this->assertSame('injectAssets', SidebarRevealOnHoverSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_ASSETS]);
        $this->assertArrayNotHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SidebarRevealOnHoverSubscriber::getSubscribedEvents());

        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->once())->method('addStyleDeclaration')->with(SidebarRevealOnHoverSubscriber::style());

        $this->subscriberAt('/s/contacts')->injectAssets($event);
    }

    /**
     * @return iterable<string, array{0: ?string}>
     */
    public static function notAdminProvider(): iterable
    {
        yield 'api' => ['/api/contacts'];
        yield 'tracking pixel' => ['/mtracking.gif'];
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

    public function testTheMenuHidesBehindA15pxStripWithThePlayArrowAndOpensOnHover(): void
    {
        $menu = ':root .app-sidebar.sidebar-left';
        $this->assertSame(
            '@media (min-width:768px){'
            .$menu.'{transform:translateX(-215px);transition:transform .15s ease}'
            .$menu.':hover,'.$menu.':has(:focus-visible){transform:none}'
            .$menu.'::after{content:"\f00a";font-family:remixicon;position:absolute;top:0;right:0;bottom:0;width:15px;'
            .'display:flex;align-items:center;justify-content:center;font-size:16px;z-index:10;background:var(--layer);color:var(--text-secondary);'
            .'box-shadow:inset -1px 0 0 0 rgba(0,0,0,.05)}'
            .$menu.':hover::after,'.$menu.':has(:focus-visible)::after{display:none}'
            .$menu.'~#app-content,'.$menu.'~#app-footer{margin-left:15px}'
            .'}',
            SidebarRevealOnHoverSubscriber::style()
        );
    }
}
