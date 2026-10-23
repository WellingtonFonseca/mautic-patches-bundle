<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use MauticPlugin\MauticPatchesBundle\EventListener\SmsStatusSubscriber;
use MauticPlugin\N8nDispatchBundle\Entity\SmsTemplate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsStatusSubscriberTest extends TestCase
{
    private function subscriber(bool $canEdit = true): SmsStatusSubscriber
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $security = $this->createMock(CorePermissions::class);
        $security->method('hasEntityAccess')->willReturn($canEdit);

        return new SmsStatusSubscriber($translator, $security);
    }

    private function template(bool $published, int $id = 9): SmsTemplate
    {
        $template = new SmsTemplate();
        $template->setIsPublished($published);
        (new \ReflectionProperty($template, 'id'))->setValue($template, $id);

        return $template;
    }

    private function buttons(SmsStatusSubscriber $subscriber, object $item, string $route = 'mautic_n8ndispatch.smstemplate_index', string $location = ButtonHelper::LOCATION_LIST_ACTIONS): array
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $event = new CustomButtonEvent($location, $request, [], $item);
        $subscriber->injectViewButtons($event);

        return array_values($event->getButtons());
    }

    public function testSubscribesToTheContentAndTheButtons(): void
    {
        $events = SmsStatusSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, $events);
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS, $events);
    }

    public function testTheScriptIsOnPageHeaderLeftOnly(): void
    {
        $event = new CustomContentEvent('some_view', 'page.header.left');
        $this->subscriber()->injectViewCustomContent($event);
        $this->assertStringContainsString('mauticPatchesSegmentStatusApi.installSms(mQuery,Mautic,', implode('', $event->getContent()));

        $other = new CustomContentEvent('some_view', 'sms.name');
        $this->subscriber()->injectViewCustomContent($other);
        $this->assertSame([], $other->getContent());
    }

    public function testTheCoreIconIsHiddenInTheSmsTableAndTheHoverHasTheColorOfTheNewStatus(): void
    {
        $css = SmsStatusSubscriber::STYLE;
        $this->assertStringContainsString('#smsTemplateTable i[class*="n8ndispatch-smstemplate-publish-icon"]{display:none!important}', $css);
        $this->assertStringContainsString('[data-sms-status-action="deactivate"]:hover{background-color:var(--support-error)', $css);
        $this->assertStringContainsString('[data-sms-status-action="activate"]:hover{background-color:var(--support-success)', $css);
    }

    public function testTheMenuOffersToDeactivateAnActiveTemplateAndToActivateAnInactiveOne(): void
    {
        $active = $this->buttons($this->subscriber(), $this->template(true))[0];
        $this->assertSame('deactivate', $active['attr']['data-sms-status-action']);
        $this->assertSame('mautic.patches.segment.deactivate', $active['btnText']);

        $inactive = $this->buttons($this->subscriber(), $this->template(false))[0];
        $this->assertSame('activate', $inactive['attr']['data-sms-status-action']);
        $this->assertSame('mautic.patches.segment.activate', $inactive['btnText']);
    }

    public function testTheItemIsFirstInTheMenuAndCoreDoesNotMakeItANavigationLink(): void
    {
        $button = $this->buttons($this->subscriber(), $this->template(true))[0];

        $this->assertGreaterThan(250, $button['priority']);
        $this->assertSame('sms-status', $button['attr']['data-toggle']);
        $this->assertSame(9, $button['attr']['data-sms-id']);
    }

    public function testNoItemWithoutTheEditPermissionOrForOtherItemsRoutesOrLocations(): void
    {
        $this->assertSame([], $this->buttons($this->subscriber(false), $this->template(true)));
        $this->assertSame([], $this->buttons($this->subscriber(), new \stdClass()));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->template(true), 'mautic_segment_index'));
        $this->assertSame([], $this->buttons($this->subscriber(), $this->template(true), 'mautic_n8ndispatch.smstemplate_index', ButtonHelper::LOCATION_PAGE_ACTIONS));
    }
}
