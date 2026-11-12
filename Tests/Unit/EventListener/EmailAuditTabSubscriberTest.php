<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\EmailBundle\Entity\Email;
use MauticPlugin\MauticPatchesBundle\EventListener\EmailAuditTabSubscriber;
use PHPUnit\Framework\TestCase;

class EmailAuditTabSubscriberTest extends TestCase
{
    private function email(?int $id): Email
    {
        $email = new Email();
        (new \ReflectionProperty($email, 'id'))->setValue($email, $id);

        return $email;
    }

    private function event(string $view, string $context, array $vars): CustomContentEvent
    {
        return new CustomContentEvent($view, $context, $vars);
    }

    public function testSubscribesToCustomContentEvent(): void
    {
        $this->assertSame(['injectTab', -100], EmailAuditTabSubscriber::getSubscribedEvents()[CoreEvents::VIEW_INJECT_CUSTOM_CONTENT]);
    }

    public function testAddsTheTabWithTheRowsOfTheEmail(): void
    {
        $model = $this->createMock(AuditLogModel::class);
        $model->expects($this->once())->method('getLogForObject')->with('email', 7, null, 200, 'email')
            ->willReturn([['action' => 'update', 'userName' => 'Ana', 'dateAdded' => null, 'details' => ['name' => ['A', 'B']]]]);
        $event = $this->event('@MauticEmail/Email/details.html.twig', 'details.stats.graph.below', ['email' => $this->email(7)]);

        (new EmailAuditTabSubscriber($model))->injectTab($event);

        $templates = $event->getTemplates();
        $this->assertCount(1, $templates);
        $this->assertSame('@MauticPatches/Email/audit_tab.html.twig', $templates[0]['template']);
        $this->assertSame([['field' => 'name', 'old' => 'A', 'new' => 'B']], $templates[0]['vars']['rows'][0]['changes']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function ignoredProvider(): array
    {
        return [
            'another view'    => ['@MauticEmail/Email/form.html.twig', 'details.stats.graph.below', []],
            'another context' => ['@MauticEmail/Email/details.html.twig', 'email.tabs', []],
            'the form, another context' => ['@MauticEmail/Email/form.html.twig', 'email.something', []],
        ];
    }

    /**
     * @dataProvider ignoredProvider
     *
     * @param array<string, mixed> $vars
     */
    public function testIgnoresOtherViewsAndContexts(string $view, string $context, array $vars): void
    {
        $model = $this->createMock(AuditLogModel::class);
        $model->expects($this->never())->method('getLogForObject');
        $event = $this->event($view, $context, $vars + ['email' => $this->email(7)]);

        (new EmailAuditTabSubscriber($model))->injectTab($event);

        $this->assertSame([], $event->getTemplates());
    }

    public function testTheEditPageGetsTheTabLinkWithoutReadingTheLog(): void
    {
        $model = $this->createMock(AuditLogModel::class);
        $model->expects($this->never())->method('getLogForObject');
        $event = $this->event('@MauticEmail/Email/form.html.twig', 'email.tabs', ['email' => $this->email(7)]);

        (new EmailAuditTabSubscriber($model))->injectTab($event);

        $this->assertSame('@MauticPatches/Email/audit_form_link.html.twig', $event->getTemplates()[0]['template']);
    }

    public function testTheEditPageGetsThePaneWithTheRowsOfTheEmail(): void
    {
        $model = $this->createMock(AuditLogModel::class);
        $model->expects($this->once())->method('getLogForObject')->with('email', 7, null, 200, 'email')
            ->willReturn([['action' => 'create', 'userName' => 'Ana', 'dateAdded' => null, 'details' => ['name' => [null, 'A']]]]);
        $event = $this->event('@MauticEmail/Email/form.html.twig', 'email.tabs.content', ['email' => $this->email(7)]);

        (new EmailAuditTabSubscriber($model))->injectTab($event);

        $this->assertSame('@MauticPatches/Email/audit_form_pane.html.twig', $event->getTemplates()[0]['template']);
        $this->assertSame('create', $event->getTemplates()[0]['vars']['rows'][0]['action']);
    }

    public function testNothingIsAddedForAnEmailThatIsNotSavedYet(): void
    {
        $model = $this->createMock(AuditLogModel::class);
        $model->expects($this->never())->method('getLogForObject');
        foreach (['email.tabs', 'email.tabs.content'] as $context) {
            $form = $this->event('@MauticEmail/Email/form.html.twig', $context, ['email' => $this->email(null)]);
            (new EmailAuditTabSubscriber($model))->injectTab($form);
            $this->assertSame([], $form->getTemplates());
        }

        $event = $this->event('@MauticEmail/Email/details.html.twig', 'details.stats.graph.below', ['email' => $this->email(null)]);

        (new EmailAuditTabSubscriber($model))->injectTab($event);

        $this->assertSame([], $event->getTemplates());
    }
}
