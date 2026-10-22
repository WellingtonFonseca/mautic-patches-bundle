<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignPendingContactsController;
use MauticPlugin\MauticPatchesBundle\Controller\ContactsTableTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The table of the campaign node modals (hourglass and check) is the contacts tab's table: building the controller is heavy
 * (core controller), so this pins the contract; the rendered table is checked by the browser spec campaign-pending-modal.
 */
class CampaignPendingContactsControllerTest extends TestCase
{
    public function testItIsACampaignControllerUsingTheSharedTableTrait(): void
    {
        $this->assertTrue(is_subclass_of(CampaignPendingContactsController::class, CampaignController::class));
        $this->assertContains(ContactsTableTrait::class, class_uses(CampaignPendingContactsController::class));
        $this->assertCount(1, (new \ReflectionMethod(CampaignPendingContactsController::class, 'setColumnsDictionary'))->getAttributes(Required::class));
    }

    public function testTheActionsHaveNamesOfTheirOwn(): void
    {
        // a `contactsAction` would have to keep core's signature (fatal error otherwise)
        $this->assertFalse(method_exists(CampaignController::class, 'pendingContactsAction'));
        $this->assertFalse(method_exists(CampaignController::class, 'doneContactsAction'));
    }

    public function testTheRoutesPointAtThePendingAndDoneActions(): void
    {
        $routes = (require __DIR__.'/../../../Config/config.php')['routes']['main'];

        $this->assertSame(CampaignPendingContactsController::class.'::pendingContactsAction', $routes['mautic_patches_campaign_event_pending_contacts']['controller']);
        $this->assertSame(CampaignPendingContactsController::class.'::doneContactsAction', $routes['mautic_patches_campaign_event_done_contacts']['controller']);
        $this->assertStringContainsString('{objectId}', $routes['mautic_patches_campaign_event_pending_contacts']['path'], 'the trait builds its urls with objectId');
        $this->assertStringContainsString('{objectId}', $routes['mautic_patches_campaign_event_done_contacts']['path']);
    }
}
