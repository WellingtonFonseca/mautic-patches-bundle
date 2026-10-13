<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Offers "including" / "excluding" (in / !in) on the text fields of a contact or a company in the segment filters, so
 * several values fit in ONE criterion (A|B|C) instead of one criterion per value joined by OR. Core offers them only
 * on lists (select, country...). The query already handles `in` on a text column. The Custom Objects' fields get the
 * same from the Custom Objects plugin.
 */
class SegmentTextInOperatorSubscriber implements EventSubscriberInterface
{
    private const TEXT_TYPES = ['text', 'email', 'tel', 'url', 'textarea', 'phone'];

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents(): array
    {
        return [LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => ['onGenerateSegmentFilters', -100]];
    }

    public function onGenerateSegmentFilters(LeadListFiltersChoicesEvent $event): void
    {
        $translator = $event->getTranslator();

        foreach (['lead', 'company'] as $object) {
            foreach ($event->getChoices()[$object] ?? [] as $alias => $config) {
                $operators = $config['operators'] ?? [];

                if (!in_array($config['properties']['type'] ?? null, self::TEXT_TYPES, true) || in_array('in', $operators, true)) {
                    continue;
                }

                $withIn = [];
                foreach ($operators as $label => $operator) {
                    $withIn[$label] = $operator;
                    if ('!like' === $operator) {
                        $withIn[$translator->trans('mautic.lead.list.form.operator.in')]    = 'in';
                        $withIn[$translator->trans('mautic.lead.list.form.operator.notin')] = '!in';
                    }
                }

                $config['operators'] = $withIn;
                $event->setChoice($object, (string) $alias, $config);
            }
        }
    }
}
