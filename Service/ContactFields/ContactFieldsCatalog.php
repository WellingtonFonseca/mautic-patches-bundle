<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\ContactFields;

use Mautic\LeadBundle\Model\ListModel;

/**
 * The fields the condition "Contact fields" offers: the contact and company fields of the segment filter, each with the
 * operators the segment filter offers for it (so the two screens always agree) and, for a list field, its options.
 */
class ContactFieldsCatalog
{
    public const OBJECTS = ['lead', 'company'];

    /** Not a value of the contact: segment membership has its own condition, tags are not a field. */
    private const SKIPPED_TYPES = ['leadlist', 'tags'];

    /** Needs the Custom Objects tables; meaningless for a plain field of the contact. */
    private const SKIPPED_OPERATORS = ['notInCustomObjects'];

    /** @var array<string, array{object: string, label: string, type: string, operators: array<string, string>, options: array<string, string>}>|null */
    private ?array $fields = null;

    public function __construct(private ListModel $listModel)
    {
    }

    /**
     * @return array<string, array{object: string, label: string, type: string, operators: array<string, string>, options: array<string, string>}> by alias
     */
    public function fields(): array
    {
        if (null !== $this->fields) {
            return $this->fields;
        }

        $choices = $this->listModel->getChoiceFields();
        $fields  = [];

        foreach (self::OBJECTS as $object) {
            foreach ($choices[$object] ?? [] as $alias => $config) {
                $type = (string) ($config['properties']['type'] ?? 'text');
                if (in_array($type, self::SKIPPED_TYPES, true)) {
                    continue;
                }

                $operators = [];
                foreach ($config['operators'] ?? [] as $label => $operator) {
                    if (!in_array($operator, self::SKIPPED_OPERATORS, true)) {
                        $operators[$operator] = (string) $label;
                    }
                }

                $fields[(string) $alias] = [
                    'object'    => $object,
                    'label'     => (string) $config['label'],
                    'type'      => $type,
                    'operators' => $operators,
                    'options'   => $this->options($config['properties']['list'] ?? null),
                ];
            }
        }

        return $this->fields = $fields;
    }

    /**
     * @param mixed $list a list of options of a select field: value => label, a plain list of values or groups of them
     *
     * @return array<string, string>
     */
    private function options($list): array
    {
        if (!is_array($list)) {
            return [];
        }

        $options = [];
        foreach ($list as $value => $label) {
            if (is_array($label)) {
                $options += $this->options($label);
            } else {
                $options[(string) (is_int($value) ? $label : $value)] = (string) $label;
            }
        }

        return $options;
    }
}
