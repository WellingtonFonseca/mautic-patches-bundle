<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\CampaignBundle\Controller\CampaignController;
use Mautic\LeadBundle\Controller\ListController;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignContactsController;
use MauticPlugin\MauticPatchesBundle\Controller\CampaignPendingContactsController;
use MauticPlugin\MauticPatchesBundle\Controller\ContactsTableTrait;
use MauticPlugin\MauticPatchesBundle\Controller\SegmentContactsController;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The two controllers take over the contacts tab routes of core's segment and campaign pages. Building them is
 * heavy (core controllers), so this pins the contract the tabs rely on; the rendered table is checked live by
 * scripts/test-contacts-tables.sh.
 */
class ContactsTabControllersTest extends TestCase
{
    public function testTheSegmentTabExtendsCoresListController(): void
    {
        $this->assertTrue(is_subclass_of(SegmentContactsController::class, ListController::class));
        $this->assertContains(ContactsTableTrait::class, class_uses(SegmentContactsController::class));
    }

    public function testTheCampaignTabExtendsCoresCampaignController(): void
    {
        $this->assertTrue(is_subclass_of(CampaignContactsController::class, CampaignController::class));
        $this->assertContains(ContactsTableTrait::class, class_uses(CampaignContactsController::class));
    }

    public function testThePendingTableOfTheCampaignNodeUsesTheSameTrait(): void
    {
        $this->assertTrue(is_subclass_of(CampaignPendingContactsController::class, CampaignController::class));
        $this->assertContains(ContactsTableTrait::class, class_uses(CampaignPendingContactsController::class));
        $this->assertCount(1, (new \ReflectionMethod(CampaignPendingContactsController::class, 'setColumnsDictionary'))->getAttributes(Required::class));

        $route = (require __DIR__.'/../../../Config/config.php')['routes']['main']['mautic_patches_campaign_event_pending_contacts'];
        $this->assertSame(CampaignPendingContactsController::class.'::pendingContactsAction', $route['controller']);
        $this->assertStringContainsString('{objectId}', $route['path'], 'the trait builds its urls with objectId');
        $routes = (require __DIR__.'/../../../Config/config.php')['routes']['main'];
        $this->assertArrayNotHasKey('mautic_patches_campaign_event_done_contacts', $routes, 'the check (done count) opens nothing');
        $this->assertArrayNotHasKey('mautic_patches_campaign_event_done', $routes);
    }

    /**
     * @dataProvider controllers
     */
    public function testContactsActionKeepsCoresSignature(string $controller, string $core): void
    {
        $plugin = new \ReflectionMethod($controller, 'contactsAction');
        $parent = new \ReflectionMethod($core, 'contactsAction');

        $this->assertSame(
            array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $parent->getParameters()),
            array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $plugin->getParameters())
        );
    }

    /**
     * @dataProvider controllers
     */
    public function testTheColumnsDictionaryComesInThroughARequiredSetter(string $controller): void
    {
        $setter = new \ReflectionMethod($controller, 'setColumnsDictionary');

        $this->assertCount(1, $setter->getAttributes(Required::class));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function controllers(): iterable
    {
        yield 'segment' => [SegmentContactsController::class, ListController::class];
        yield 'campaign' => [CampaignContactsController::class, CampaignController::class];
    }

    public function testBothRoutesPointAtThePluginControllers(): void
    {
        $routes = (require __DIR__.'/../../../Config/config.php')['routes']['main'];

        $this->assertSame(SegmentContactsController::class.'::contactsAction', $routes['mautic_segment_contacts']['controller']);
        $this->assertSame(CampaignContactsController::class.'::contactsAction', $routes['mautic_campaign_contacts']['controller']);
    }
}
