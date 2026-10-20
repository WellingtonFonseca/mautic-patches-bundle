<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\ContactFields;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Segment\ContactSegmentFilterFactory;
use Mautic\LeadBundle\Segment\Query\ContactSegmentQueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tells whether ONE contact satisfies every criterion (field, operator, value) of the condition "Contact fields".
 *
 * The criteria are evaluated by the segment engine, as the filters of a segment that is never saved: so including /
 * excluding (A|B|C), relative dates ("-35 days"), empty / not empty and the company fields behave exactly as they do in a
 * segment, and a change of the engine reaches the condition too.
 */
class ContactFieldsMatcher
{
    private const LIST_OPERATORS = ['in', '!in'];

    /** Types whose values are chosen from a list even without options of their own. */
    private const LIST_TYPES = ['country', 'region', 'timezone', 'locale'];

    /** @var array<string, array{object: string, type: string, list: bool}>|null */
    private ?array $fields = null;

    public function __construct(
        private FieldModel $fieldModel,
        #[Autowire(service: 'mautic.lead.model.lead_segment_filter_factory')]
        private ContactSegmentFilterFactory $filterFactory,
        #[Autowire(service: 'mautic.lead.repository.lead_segment_query_builder')]
        private ContactSegmentQueryBuilder $queryBuilder
    ) {
    }

    /**
     * @param array<int, array{field: string, operator: string, value: mixed}> $criteria
     */
    public function matches(int $contactId, array $criteria): bool
    {
        $filters = $this->filters($criteria);
        if (null === $filters) {
            return false;
        }

        $segment = new LeadList();
        $segment->setFilters($filters);

        $qb    = $this->queryBuilder->assembleContactsSegmentQueryBuilder(0, $this->filterFactory->getSegmentFilters($segment));
        $alias = $qb->getTableAlias(MAUTIC_TABLE_PREFIX.'leads') ?: Lead::DEFAULT_ALIAS;
        $qb->andWhere($alias.'.id = :patches_contact_id')->setParameter('patches_contact_id', $contactId);

        return (bool) $qb->executeQuery()->fetchOne();
    }

    /**
     * @param array<int, array{field: string, operator: string, value: mixed}> $criteria
     *
     * @return array<int, array<string, mixed>>|null null when a criterion names a field that does not exist (the condition is then false)
     */
    private function filters(array $criteria): ?array
    {
        $fields  = $this->fields();
        $filters = [];

        foreach ($criteria as $criterion) {
            $meta = $fields[$criterion['field']] ?? null;
            if (null === $meta) {
                return null;
            }

            $operator = (string) $criterion['operator'];
            $value    = $criterion['value'] ?? '';
            // The value of "including" / "excluding" is A|B|C. On a list field the segment wants the list itself; on a text
            // field it reads the string.
            if (in_array($operator, self::LIST_OPERATORS, true) && $meta['list'] && is_string($value)) {
                $value = array_values(array_filter(array_map('trim', explode('|', $value)), static fn (string $v): bool => '' !== $v));
            }

            $filters[] = [
                'glue'       => 'and',
                'field'      => $criterion['field'],
                'object'     => $meta['object'],
                'type'       => $meta['type'],
                'operator'   => $operator,
                'properties' => ['filter' => $value],
                'filter'     => $value,
                'display'    => null,
            ];
        }

        return [] === $filters ? null : $filters;
    }

    /**
     * What the matcher needs of each field, read from the fields themselves: the campaign runs on the command line, where the
     * list of choices of the segment screen (ContactFieldsCatalog, which the form uses) cannot be built.
     *
     * @return array<string, array{object: string, type: string, list: bool}> by alias
     */
    private function fields(): array
    {
        if (null !== $this->fields) {
            return $this->fields;
        }

        $fields = [];
        foreach (ContactFieldsCatalog::OBJECTS as $object) {
            foreach ($this->fieldModel->getFieldListWithProperties($object) as $alias => $field) {
                $fields[(string) $alias] = [
                    'object' => $object,
                    'type'   => (string) $field['type'],
                    'list'   => !empty($field['properties']['list']) || in_array($field['type'], self::LIST_TYPES, true),
                ];
            }
        }

        return $this->fields = $fields;
    }
}
