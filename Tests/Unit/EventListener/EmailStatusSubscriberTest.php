<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\EmailBundle\Entity\Email;
use MauticPlugin\MauticPatchesBundle\EventListener\EmailStatusSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailStatusSubscriberTest extends TestCase
{
    private function subscriber(bool $canEdit = true): EmailStatusSubscriber
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createStub(CorePermissions::class);
        $security->method('hasEntityAccess')->willReturn($canEdit);

        return new EmailStatusSubscriber($translator, $security);
    }

    private function email(bool $published, int $id = 9): Email
    {
        $email = new Email();
        $email->setIsPublished($published);
        (new \ReflectionProperty($email, 'id'))->setValue($email, $id);

        return $email;
    }

    private function buttons(EmailStatusSubscriber $subscriber, object $item, string $route = 'mautic_email_index', string $location = ButtonHelper::LOCATION_LIST_ACTIONS): array
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $event = new CustomButtonEvent($location, $request, [], $item);
        $subscriber->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    public function testSubscribesToTheContentAndTheButtons(): void
    {
        $events = EmailStatusSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, $events);
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, $events);
    }

    public function testTheScriptIsOnPageHeaderLeftOnly(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertStringContainsString('mauticPatchesListStatusApi.installEmail(mQuery,Mautic,', implode('', $event->getContent()));

        $other = new CustomContentEvent('some_view', 'email.name');
        $this->subscriber()->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testTheCoreIconIsHiddenInTheEmailTableAndTheHoverHasTheColorOfTheNewStatus(): void
    {
        $css = EmailStatusSubscriber::STYLE;
        $this->assertStringContainsString('table.email-list i[class*="email-publish-icon"]{display:none!important}', $css);
        $this->assertStringContainsString('[data-email-status-action="deactivate"]:hover{background-color:var(--support-error)', $css);
        $this->assertStringContainsString('[data-email-status-action="activate"]:hover{background-color:var(--support-success)', $css);
    }

    public function testTheMenuOffersToDeactivateAnActiveTemplateAndToActivateAnInactiveOne(): void
    {
        $active = $this->buttons($this->subscriber(), $this->email(true))[0];
        $this->assertSame('deactivate', $active['attr']['data-email-status-action']);
        $this->assertSame('mautic.patches.segment.deactivate', $active['btnText']);

        $inactive = $this->buttons($this->subscriber(), $this->email(false))[0];
        $this->assertSame('activate', $inactive['attr']['data-email-status-action']);
        $this->assertSame('mautic.patches.segment.activate', $inactive['btnText']);
    }

    public function testTheItemIsFirstInTheMenuAndCoreDoesNotMakeItANavigationLink(): void
    {
        $button = $this->buttons($this->subscriber(), $this->email(true))[0];

        $this->assertGreaterThan(250, $button['priority']);
        $this->assertSame('email-status', $button['attr']['data-toggle']);
        $this->assertSame(9, $button['attr']['data-email-id']);
    }

    public function testNoItemWithoutTheEditPermissionOrForOtherItemsRoutesOrLocations(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(false), $this->email(true)));
        $this->assertSame([], $this->buttons($this->subscriber(), new \stdClass()));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->email(true), 'mautic_segment_index'));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->email(true), 'mautic_email_index', ButtonHelper::LOCATION_PAGE_ACTIONS));
    }

    public function testThePermissionAskedIsTheEmailsEditOwnOrOther(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->expects($this->once())->method('hasEntityAccess')->with('email:emails:editown', 'email:emails:editother', $this->anything())->willReturn(true);
        $request = new Request();
        $request->attributes->set('_route', 'mautic_email_index');
        $event = new CustomButtonEvent(ButtonHelper::LOCATION_LIST_ACTIONS, $request, [], $this->email(true));

        (new EmailStatusSubscriber($translator, $security))->injectViewButtons($event);

        $this->assertCount(1, $event->getButtons());
    }

    public function testAnItemThatIsNotAnEmailGetsNoMenuItem(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(), new \Mautic\CampaignBundle\Entity\Campaign()));
    }

    public function testTheHeaderScriptCarriesTheStyleOfTheList(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);

        $this->assertStringContainsString('email-publish-icon', implode('', $event->getContent()));
    }
}
