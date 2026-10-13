<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * The values already stored in a contact or company text field, distinct and matching what the user typed: the
 * suggestions of the including / excluding chips of the segment form. Only the plugin's registered text fields are
 * read (the alias is looked up in lead_fields before it is used as a column). The Custom Objects' own fields are
 * answered by the Custom Objects plugin.
 */
class SegmentFilterValues
{
    public const MAX = 50;

    private const TABLES = ['lead' => 'leads', 'company' => 'companies'];

    private const TEXT_TYPES = ['text', 'email', 'tel', 'url', 'textarea', 'phone'];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return string[]
     *
     * @throws \OutOfBoundsException when the object or the field is not a contact / company text field
     */
    public function find(string $object, string $alias, string $search, int $limit = 20): array
    {
        if (!isset(self::TABLES[$object]) || 1 !== preg_match('/^[a-z0-9_]+$/', $alias)) {
            throw new \OutOfBoundsException("{$object}.{$alias} is not a contact or company field");
        }

        $prefix = defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : '';
        $type   = $this->connection->fetchOne(
            'SELECT type FROM '.$prefix.'lead_fields WHERE object = :object AND alias = :alias AND is_published = 1',
            ['object' => $object, 'alias' => $alias]
        );
        if (!is_string($type) || !in_array($type, self::TEXT_TYPES, true)) {
            throw new \OutOfBoundsException("{$object}.{$alias} is not a text field");
        }

        $limit = max(1, min(self::MAX, $limit));

        return array_map('strval', $this->connection->fetchFirstColumn(
            'SELECT DISTINCT `'.$alias.'` FROM '.$prefix.self::TABLES[$object].' WHERE `'.$alias.'` LIKE :search AND `'.$alias.'` <> \'\' ORDER BY `'.$alias.'` LIMIT '.$limit,
            ['search' => '%'.addcslashes($search, '%_\\').'%']
        ));
    }
}
