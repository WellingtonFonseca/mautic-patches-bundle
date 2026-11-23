<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticPatchesBundle\Controller\ContactDetailsController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The title and the blocked channels the "Ver" modal of the contact list hands to its header component (Contact/header.html.twig):
 * "academic record - first last name", the record alone, the name alone; and the Do Not Contact channels, which the script turns into
 * the red badges. The twig and the delegated view are doubles: only what the controller decides is checked here.
 */
#[Group('area:contacts')]
#[Group('risk:FLUXO')]
class ContactDetailsControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function titles(): iterable
    {
        yield 'record and name' => ['A-100', 'Ana', 'Souza', 'A-100 - Ana Souza'];
        yield 'record only' => ['A-100', '', '', 'A-100'];
        yield 'name only' => ['', 'Ana', 'Souza', 'Ana Souza'];
        yield 'first name only, no stray space' => ['', 'Ana', '', 'Ana'];
        yield 'record with blanks around' => ['  A-100  ', 'Ana', '', 'A-100 - Ana'];
        yield 'nothing' => ['', '', '', ''];
    }

    #[DataProvider('titles')]
    public function testTheTitleFollowsTheRecordAndTheNameRules(string $record, string $first, string $last, string $expected): void
    {
        $lead = $this->lead($record, $first, $last);

        $this->assertSame($expected, $this->details($lead)['title']);
    }

    public function testTheBlockedChannelsAreTheDoNotContactChannelsWithoutRepeats(): void
    {
        $lead = $this->lead('A-1', 'Ana', 'Souza');
        foreach (['email', 'sms', 'email'] as $channel) {
            $dnc = new DoNotContact();
            $dnc->setChannel($channel);
            $lead->addDoNotContactEntry($dnc);
        }

        $this->assertSame('email,sms', $this->details($lead)['blocked']);
    }

    public function testNothingBlockedGivesAnEmptyMarker(): void
    {
        $this->assertSame('', $this->details($this->lead('A-1', 'Ana', 'Souza'))['blocked']);
    }

    public function testTheHeaderGoesIntoTheViewAndTheScriptIsAppended(): void
    {
        $view = [];
        $this->details($this->lead('A-1', 'Ana', 'Souza'), $view);

        $this->assertStringContainsString('<HEAD>', $view['viewParameters']['headHtml']);
        $this->assertStringContainsString('installContactModalHead', $view['viewParameters']['headHtml']);
        $this->assertStringContainsString('contact-detail-badges.js', $view['viewParameters']['headHtml']);
        $this->assertStringContainsString('.patches-details-badges', $view['viewParameters']['headHtml']);
    }

    /**
     * @param array<string, mixed> $view receives the arguments of delegateView
     *
     * @return array<string, mixed> the variables the header twig got
     */
    private function details(Lead $lead, array &$view = []): array
    {
        $vars = [];
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context) use (&$vars): string {
            $vars = $context;

            return '<HEAD>';
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $model = $this->createMock(LeadModel::class);
        $model->method('getEntity')->willReturn($lead);
        $security = $this->createMock(CorePermissions::class);
        $security->method('hasEntityAccess')->willReturn(true);

        $controller = $this->getMockBuilder(ContactDetailsController::class)->disableOriginalConstructor()->onlyMethods(['delegateView'])->getMock();
        $controller->method('delegateView')->willReturnCallback(static function (array $args) use (&$view): Response {
            $view = $args;

            return new Response();
        });
        $property = new \ReflectionProperty(ContactDetailsController::class, 'security');
        $property->setValue($controller, $security);

        $controller->detailsAction($model, $twig, $translator, 1);

        return $vars;
    }

    private function lead(string $record, string $first, string $last): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($first);
        $lead->setLastname($last);
        $lead->addUpdatedField('enro_academic_record', $record);

        return $lead;
    }
}
