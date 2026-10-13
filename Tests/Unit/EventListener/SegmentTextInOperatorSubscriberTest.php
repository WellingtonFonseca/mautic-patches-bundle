<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentTextInOperatorSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentTextInOperatorSubscriberTest extends TestCase
{
    private const TEXT = ['equals' => '=', 'not equal' => '!=', 'contém' => 'like', 'not like' => '!like', 'regexp' => 'regexp', 'contains' => 'contains'];

    private function choicesAfter(array $choices): array
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key) => ['mautic.lead.list.form.operator.in' => 'including', 'mautic.lead.list.form.operator.notin' => 'excluding'][$key] ?? $key);
        $event = new LeadListFiltersChoicesEvent($choices, [], $translator);
        (new SegmentTextInOperatorSubscriber())->onGenerateSegmentFilters($event);

        return $event->getChoices();
    }

    /**
     * @dataProvider textTypes
     */
    public function testContactAndCompanyTextFieldsGetIncludingAndExcludingAfterNotLike(string $object, string $type): void
    {
        $choices = $this->choicesAfter([$object => ['f' => ['properties' => ['type' => $type], 'operators' => self::TEXT]]]);

        $this->assertSame(
            ['equals' => '=', 'not equal' => '!=', 'contém' => 'like', 'not like' => '!like', 'including' => 'in', 'excluding' => '!in', 'regexp' => 'regexp', 'contains' => 'contains'],
            $choices[$object]['f']['operators']
        );
    }

    public static function textTypes(): array
    {
        $cases = [];
        foreach (['lead', 'company'] as $object) {
            foreach (['text', 'email', 'tel', 'url', 'textarea', 'phone'] as $type) {
                $cases["$object $type"] = [$object, $type];
            }
        }

        return $cases;
    }

    public function testOtherTypesAndObjectsAreLeftAlone(): void
    {
        $number = ['=' => '=', 'gt' => 'gt'];
        $lookup = self::TEXT;
        $choices = $this->choicesAfter([
            'lead'          => ['points' => ['properties' => ['type' => 'number'], 'operators' => $number], 'owner' => ['properties' => ['type' => 'lookup'], 'operators' => $lookup]],
            'custom_object' => ['cmf_1' => ['properties' => ['type' => 'text'], 'operators' => self::TEXT]],
        ]);

        $this->assertSame($number, $choices['lead']['points']['operators']);
        $this->assertSame($lookup, $choices['lead']['owner']['operators']);
        $this->assertSame(self::TEXT, $choices['custom_object']['cmf_1']['operators'], 'Custom Object fields are the Custom Objects plugin\'s');
    }

    public function testAFieldThatAlreadyHasInIsNotDuplicated(): void
    {
        $ops     = ['equals' => '=', 'including' => 'in', 'excluding' => '!in'];
        $choices = $this->choicesAfter(['lead' => ['f' => ['properties' => ['type' => 'text'], 'operators' => $ops]]]);

        $this->assertSame($ops, $choices['lead']['f']['operators']);
    }

    public function testSubscribesAfterTheOthers(): void
    {
        $this->assertSame([LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => ['onGenerateSegmentFilters', -100]], SegmentTextInOperatorSubscriber::getSubscribedEvents());
    }
}
