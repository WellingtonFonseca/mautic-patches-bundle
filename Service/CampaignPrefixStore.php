<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Campaign;
use MauticPlugin\MauticPatchesBundle\Entity\CampaignPrefixValue;

/**
 * Reads and writes the prefix of each campaign (table campaign_prefix).
 *
 * The campaign form does not know the campaign's id yet when a new one is submitted, so the prefix typed is held here
 * (remember) until the campaign is saved (CampaignPrefixSaveSubscriber), then written with the id.
 */
class CampaignPrefixStore
{
    /** @var array<int, string>|null campaign id => prefix, read once per request */
    private ?array $all = null;

    /** @var \WeakMap<Campaign, string> */
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
            foreach ($this->em->createQuery('SELECT v.campaignId, v.prefix FROM '.CampaignPrefixValue::class.' v')->getArrayResult() as $row) {
                $this->all[(int) $row['campaignId']] = (string) $row['prefix'];
            }
        }

        return $this->all;
    }

    public function get(?int $campaignId): ?string
    {
        return null === $campaignId ? null : ($this->all()[$campaignId] ?? null);
    }

    public function save(int $campaignId, string $prefix): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO '.$this->table().' (campaign_id, prefix) VALUES (?, ?) ON DUPLICATE KEY UPDATE prefix = VALUES(prefix)',
            [$campaignId, $prefix]
        );
        $this->all = null;
    }

    public function remove(int $campaignId): void
    {
        $this->connection()->executeStatement('DELETE FROM '.$this->table().' WHERE campaign_id = ?', [$campaignId]);
        $this->all = null;
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return $this->em->getConnection();
    }

    /** The table name with Mautic's table prefix, as the mapping has it. */
    private function table(): string
    {
        return $this->em->getClassMetadata(CampaignPrefixValue::class)->getTableName();
    }

    public function remember(Campaign $campaign, string $prefix): void
    {
        $this->pending[$campaign] = $prefix;
    }

    public function pending(Campaign $campaign): ?string
    {
        return $this->pending[$campaign] ?? null;
    }
}
