<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentDistinctConfig;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentDistinctValue;

/**
 * Reads and writes the DISTINCT setting of each segment (table segment_distinct).
 *
 * Like SegmentPrefixStore: a new segment has no id when the form is submitted, so the setting is held here (remember)
 * until the segment is saved (SegmentDistinctSaveSubscriber).
 */
class SegmentDistinctStore
{
    /** @var array<int, SegmentDistinctConfig>|null segment id => setting, read once per request */
    private ?array $all = null;

    /** @var \WeakMap<LeadList, SegmentDistinctConfig> */
    private \WeakMap $pending;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->pending = new \WeakMap();
    }

    /**
     * @return array<int, SegmentDistinctConfig>
     */
    public function all(): array
    {
        if (null === $this->all) {
            $this->all = [];
            $rows      = $this->em->getConnection()->fetchAllAssociative(
                'SELECT segment_id, enabled, remove_empty, fields FROM '.$this->table()
            );
            foreach ($rows as $row) {
                $fields = json_decode((string) $row['fields'], true);
                $this->all[(int) $row['segment_id']] = new SegmentDistinctConfig(
                    (bool) $row['enabled'],
                    (bool) $row['remove_empty'],
                    is_array($fields) ? array_values(array_filter($fields, 'is_string')) : []
                );
            }
        }

        return $this->all;
    }

    public function get(?int $segmentId): ?SegmentDistinctConfig
    {
        return null === $segmentId ? null : ($this->all()[$segmentId] ?? null);
    }

    public function save(int $segmentId, SegmentDistinctConfig $config): void
    {
        $this->em->getConnection()->executeStatement(
            'INSERT INTO '.$this->table().' (segment_id, enabled, remove_empty, fields) VALUES (?, ?, ?, ?)'
            .' ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), remove_empty = VALUES(remove_empty), fields = VALUES(fields)',
            [$segmentId, (int) $config->enabled, (int) $config->removeEmpty, json_encode(array_values($config->fields))]
        );
        $this->all = null;
    }

    public function remove(int $segmentId): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM '.$this->table().' WHERE segment_id = ?', [$segmentId]);
        $this->all = null;
    }

    /** The table name with Mautic's table prefix, as the mapping has it. */
    private function table(): string
    {
        return $this->em->getClassMetadata(SegmentDistinctValue::class)->getTableName();
    }

    public function remember(LeadList $segment, SegmentDistinctConfig $config): void
    {
        $this->pending[$segment] = $config;
    }

    public function pending(LeadList $segment): ?SegmentDistinctConfig
    {
        return $this->pending[$segment] ?? null;
    }
}
