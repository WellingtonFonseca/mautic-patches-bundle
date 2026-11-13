<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignStepContactsController;
use MauticPlugin\MauticPatchesBundle\Controller\ContactsTableTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The table of the second "Ações" tab on Mautic 7: core's CampaignController with the shared table trait, whose column dictionary
 * comes through a #[Required] setter (7 removed the factory from the controller constructor).
 */
class CampaignStepContactsControllerTest extends TestCase
{
    public function testItIsACampaignControllerUsingTheSharedTableTrait(): void
    {
        $this->assertTrue(is_subclass_of(CampaignStepContactsController::class, CampaignController::class));
        $this->assertContains(ContactsTableTrait::class, class_uses(CampaignStepContactsController::class));
        $this->assertCount(1, (new \ReflectionMethod(CampaignStepContactsController::class, 'setColumnsDictionary'))->getAttributes(Required::class));
    }

    public function testOneRoutePerStatePointsAtItsAction(): void
    {
        $routes = (require __DIR__.'/../../../Config/config.php')['routes']['main'];

        foreach (['failed', 'waiting', 'none'] as $state) {
            $this->assertSame(CampaignStepContactsController::class.'::'.$state.'Action', $routes['mautic_patches_campaign_step_'.$state.'_contacts']['controller']);
        }
    }

    public function testTheNodeCountsOfTheCampaignPreviewOpenNoList(): void
    {
        // the contacts of a step are listed on the second "Ações" tab only: the counts of a node open nothing
        $routes = (require __DIR__.'/../../../Config/config.php')['routes']['main'];
        foreach (['mautic_patches_campaign_event_pending', 'mautic_patches_campaign_event_pending_contacts'] as $name) {
            $this->assertArrayNotHasKey($name, $routes);
        }
    }
}
