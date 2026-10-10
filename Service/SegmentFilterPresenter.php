<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns the filters stored in a segment (LeadList::getFilters()) into rows a person can read: the field's label, the
 * operator written out and the value, the way the segment's edit form shows them. The labels come from
 * ListModel::getChoiceFields() (passed in, so this class needs no service): [object][field alias] => [label,
 * operators [translated label => key] (core's order), properties [list => [value => label]]].
 *
 * What the stored filter holds: object, field, operator, glue ('and'/'or'), type and properties.filter (the value:
 * a string, or an array for multi value filters like a select list). `properties.display` is what the edit form
 * shows for lookup fields. A field or operator not found in the choices (a deleted custom field) is shown by its alias.
 */
class SegmentFilterPresenter
{
    /** Operators that take no value. */
    private const NO_VALUE = ['empty', '!empty'];

    /** Relative date values the segment form offers, as stored => translation key (core's RelativeDate). */
    private const RELATIVE_DATES = [
        'today'      => 'mautic.lead.list.today',
        'tomorrow'   => 'mautic.lead.list.tomorrow',
        'yesterday'  => 'mautic.lead.list.yesterday',
        'last month' => 'mautic.lead.list.month_last',
        'this month' => 'mautic.lead.list.month_this',
        'next month' => 'mautic.lead.list.month_next',
        'last week'  => 'mautic.lead.list.week_last',
        'this week'  => 'mautic.lead.list.week_this',
        'next week'  => 'mautic.lead.list.week_next',
        'last year'  => 'mautic.lead.list.year_last',
        'this year'  => 'mautic.lead.list.year_this',
        'next year'  => 'mautic.lead.list.year_next',
        'anniversary' => 'mautic.lead.list.anniversary',
    ];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param array<int, array<string, mixed>>                $filters
     * @param array<string, array<string, array<string, mixed>>> $choices
     *
     * @return list<array{glue: string, field: string, operator: string, value: string}>
     */
    public function present(array $filters, array $choices): array
    {
        $rows = [];
        foreach (array_values($filters) as $filter) {
            $object   = (string) ($filter['object'] ?? 'lead');
            $alias    = (string) ($filter['field'] ?? '');
            $operator = (string) ($filter['operator'] ?? '');
            $choice   = $choices[$object][$alias] ?? [];

            $rows[] = [
                'glue'     => 'or' === ($filter['glue'] ?? 'and') ? 'or' : 'and',
                'field'    => (string) ($choice['label'] ?? $alias),
                'operator' => $this->operatorLabel($operator, $choice['operators'] ?? []),
                'value'    => in_array($operator, self::NO_VALUE, true) ? '' : $this->value($filter, $choice['properties']['list'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, string> $operators translated label => operator key
     */
    private function operatorLabel(string $operator, array $operators): string
    {
        $label = array_search($operator, $operators, true);

        return false === $label ? $operator : (string) $label;
    }

    /**
     * @param array<string, mixed> $filter
     * @param mixed                $list   [value => label] of a select-like field
     */
    private function value(array $filter, $list): string
    {
        $display = $filter['display'] ?? ($filter['properties']['display'] ?? null);
        if (is_string($display) && '' !== $display) {
            return $display;
        }

        $value = $filter['properties']['filter'] ?? ($filter['filter'] ?? '');
        $list  = is_array($list) ? $list : [];

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v): string => $this->label($v, $list), $value));
        }

        return $this->label($value, $list);
    }

    /**
     * @param mixed                $value
     * @param array<string, mixed> $list
     */
    private function label($value, array $list): string
    {
        $key = is_scalar($value) ? (string) $value : '';
        if (isset($list[$key]) && is_scalar($list[$key])) {
            return (string) $list[$key];
        }

        // A list written as [['value' => ..., 'label' => ...], ...]
        foreach ($list as $option) {
            if (is_array($option) && isset($option['value'], $option['label']) && (string) $option['value'] === $key) {
                return (string) $option['label'];
            }
        }

        if (isset(self::RELATIVE_DATES[$key])) {
            return $this->translator->trans(self::RELATIVE_DATES[$key]);
        }

        return $key;
    }
}
