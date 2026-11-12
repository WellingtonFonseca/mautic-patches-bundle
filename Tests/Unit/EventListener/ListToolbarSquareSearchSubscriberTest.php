<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use MauticPlugin\MauticPatchesBundle\EventListener\ListToolbarSquareSearchSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ListToolbarSquareSearchSubscriberTest extends TestCase
{
    private function subscriberAt(?string $path): ListToolbarSquareSearchSubscriber
    {
        $stack = new RequestStack();
        if (null !== $path) {
            $stack->push(Request::create($path));
        }

        return new ListToolbarSquareSearchSubscriber($stack);
    }

    public function testTheStyleGoesInTheHeadOfTheAdmin(): void
    {
        $this->assertSame('injectAssets', ListToolbarSquareSearchSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_ASSETS]);

        $event = $this->createMock(CustomAssetsEvent::class);
        $event->expects($this->once())->method('addStyleDeclaration')->with(ListToolbarSquareSearchSubscriber::style());

        $this->subscriberAt('/s/contacts/fields')->injectAssets($event);
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

    public function testTheRuleUsesCoresSelectorAndSquaresTheInput(): void
    {
        $this->assertStringContainsString('.list-toolbar > .btn:only-of-type + .input-group > input{border-radius:0}', ListToolbarSquareSearchSubscriber::style());
    }

    public function testTheGearMenuOpensToTheLeft(): void
    {
        $this->assertStringContainsString('.list-toolbar .dropdown > .dropdown-menu{left:auto;right:0}', ListToolbarSquareSearchSubscriber::style());
    }
}
