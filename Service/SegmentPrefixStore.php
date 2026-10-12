<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\Entity\SegmentPrefixValue;

/**
 * Reads and writes the prefix of each segment (table segment_prefix).
 *
 * The segment form does not know the segment's id yet when a new one is submitted, so the prefix typed is held here
 * (remember) until the segment is saved (SegmentPrefixSaveSubscriber), then written with the id.
 */
class SegmentPrefixStore
{
    /** @var array<int, string>|null segment id => prefix, read once per request */
    private ?array $all = null;

    /** @var \WeakMap<LeadList, string> */
    private \WeakMap $pending;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->pending = new \WeakMap();
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        if (null === $this->all) {
            $this->all = [];
            foreach ($this->em->createQuery('SELECT v.segmentId, v.prefix FROM '.SegmentPrefixValue::class.' v')->getArrayResult() as $row) {
                $this->all[(int) $row['segmentId']] = (string) $row['prefix'];
            }
        }

        return $this->all;
    }

    public function get(?int $segmentId): ?string
    {
        return null === $segmentId ? null : ($this->all()[$segmentId] ?? null);
    }

    public function save(int $segmentId, string $prefix): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO '.$this->table().' (segment_id, prefix) VALUES (?, ?) ON DUPLICATE KEY UPDATE prefix = VALUES(prefix)',
            [$segmentId, $prefix]
        );
        $this->all = null;
    }

    public function remove(int $segmentId): void
    {
        $this->connection()->executeStatement('DELETE FROM '.$this->table().' WHERE segment_id = ?', [$segmentId]);
        $this->all = null;
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return $this->em->getConnection();
    }

    /** The table name with Mautic's table prefix, as the mapping has it. */
    private function table(): string
    {
        return $this->em->getClassMetadata(SegmentPrefixValue::class)->getTableName();
    }

    public function remember(LeadList $segment, string $prefix): void
    {
        $this->pending[$segment] = $prefix;
    }

    public function pending(LeadList $segment): ?string
    {
        return $this->pending[$segment] ?? null;
    }
}
