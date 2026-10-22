<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignEventAccessTrait;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignEventPendingController;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignPendingContactsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two controllers of the campaign node modals (the body and its table) answer to the same rule about the event: 404 when
 * the event or its campaign is missing, 403 without the campaign's view permission. It lives in one trait, so they cannot drift.
 */
class CampaignEventAccessTest extends TestCase
{
    /**
     * @dataProvider controllers
     */
    public function testBothControllersUseTheSharedRule(string $controller): void
    {
        $this->assertContains(CampaignEventAccessTrait::class, class_uses($controller));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function controllers(): iterable
    {
        yield 'the modal body' => [CampaignEventPendingController::class];
        yield 'the modal table' => [CampaignPendingContactsController::class];
    }

    public function testTheRuleAnswersNotFoundAndAccessDeniedAndOtherwiseGivesTheEvent(): void
    {
        $campaign = new Campaign();
        $event    = new Event();
        $event->setCampaign($campaign);

        $notFound = new Response('', 404);
        $denied   = new Response('', 403);

        $subject = new class($event, $notFound, $denied) {
            use CampaignEventAccessTrait;

            public $security;

            public function __construct(private ?Event $event, private Response $notFound, private Response $denied)
            {
                $this->security = new class() {
                    public bool $allowed = true;

                    public function hasEntityAccess(): bool
                    {
                        return $this->allowed;
                    }
                };
            }

            public function setEvent(?Event $event): void
            {
                $this->event = $event;
            }

            // what the trait asks of its controller
            protected function findEvent(int $eventId): ?Event
            {
                return $this->event;
            }

            protected function notFound(): Response
            {
                return $this->notFound;
            }

            protected function accessDenied(): Response
            {
                return $this->denied;
            }

            public function run(int $id)
            {
                return $this->eventOrDeny($id);
            }
        };

        $this->assertSame($event, $subject->run(1));

        $subject->security->allowed = false;
        $this->assertSame($denied, $subject->run(1));

        $subject->security->allowed = true;
        $subject->setEvent(new Event());
        $this->assertSame($notFound, $subject->run(1), 'an event without a campaign');

        $subject->setEvent(null);
        $this->assertSame($notFound, $subject->run(1), 'an unknown event');
    }
}
