<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * The published contact fields whose column is the FIRST column of a real index of the leads table (read from
 * information_schema, not from lead_fields.is_index, which disagrees with the table). DISTINCT groups by these fields,
 * so the grouping can use the index. A column that is only the 2nd column of an index does not count.
 */
class IndexedLeadFields
{
    /** @var array<string, string>|null alias => label, read once per request */
    private ?array $fields = null;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, string> alias => label
     */
    public function all(): array
    {
        if (null === $this->fields) {
            $connection = $this->em->getConnection();
            $leads      = MAUTIC_TABLE_PREFIX.'leads';
            $indexed    = $connection->fetchFirstColumn(
                'SELECT DISTINCT s.COLUMN_NAME FROM information_schema.STATISTICS s'
                .' WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = ? AND s.SEQ_IN_INDEX = 1',
                [$leads]
            );
            $labels = $connection->fetchAllKeyValue(
                'SELECT alias, label FROM '.MAUTIC_TABLE_PREFIX."lead_fields WHERE object = 'lead' AND is_published = 1 ORDER BY field_order, label"
            );

            $this->fields = [];
            foreach ($labels as $alias => $label) {
                if (in_array((string) $alias, $indexed, true)) {
                    $this->fields[(string) $alias] = (string) $label;
                }
            }
        }

        return $this->fields;
    }

    /**
     * Whether the leads table has this column (a field deleted after it was chosen no longer does).
     */
    public function columnExists(string $column): bool
    {
        return (bool) $this->em->getConnection()->fetchOne(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [MAUTIC_TABLE_PREFIX.'leads', $column]
        );
    }

    /**
     * Whether the column holds text (only then is '' a possible "empty" value; numeric/boolean columns cast '' to 0).
     */
    public function isTextColumn(string $column): bool
    {
        return (bool) $this->em->getConnection()->fetchOne(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext')",
            [MAUTIC_TABLE_PREFIX.'leads', $column]
        );
    }
}
