<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Entity;

use Mautic\CampaignBundle\Entity\CampaignRepository;

/**
 * Core's campaign repository, plus sorting the campaign list by prefix. The prefix is not a column of the campaign (it
 * lives in the plugin's own table), and the order a list asks for is only a column name, so the prefix is read by a
 * hidden sub-select and sorted on. Everything else is core's own; CampaignRepositoryListener puts this class in place of
 * core's.
 */
class CampaignPrefixRepository extends CampaignRepository
{
    /** The name the "Prefixo" header sorts by (a value of the list's session order, sanitized by core to [a-z_.]). */
    public const SORT_KEY = 'campaign_prefix';

    protected function buildOrderByClause($q, array $args): void
    {
        if (self::SORT_KEY !== ($args['orderBy'] ?? null)) {
            parent::buildOrderByClause($q, $args);

            return;
        }

        $alias = $this->getTableAlias();
        $dir   = 'DESC' === strtoupper((string) ($args['orderByDir'] ?? 'ASC')) ? 'DESC' : 'ASC';

        $q->addSelect('(SELECT sp.prefix FROM '.CampaignPrefixValue::class.' sp WHERE sp.campaignId = '.$alias.'.id) AS HIDDEN '.self::SORT_KEY);
        $q->addOrderBy(self::SORT_KEY, $dir);
        // The same prefix keeps a steady order.
        $q->addOrderBy($alias.'.name', 'ASC');
    }
}
