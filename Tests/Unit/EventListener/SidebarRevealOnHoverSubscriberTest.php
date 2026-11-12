<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\SidebarRevealOnHoverSubscriber;
use PHPUnit\Framework\TestCase;

class SidebarRevealOnHoverSubscriberTest extends TestCase
{
    private function content(string $context = 'page.header.left'): string
    {
        $event = new CustomContentEvent('some_view', $context);
        (new SidebarRevealOnHoverSubscriber())->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SidebarRevealOnHoverSubscriber::getSubscribedEvents());
    }

    public function testInjectsTheStyleOnPageHeaderLeftOnly(): void
    {
        $this->assertSame('<style>'.SidebarRevealOnHoverSubscriber::style().'</style>', $this->content());
        $this->assertSame('', $this->content('something.else'));
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
