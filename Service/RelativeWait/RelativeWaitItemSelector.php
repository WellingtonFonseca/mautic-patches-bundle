<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RelativeWait;

use Doctrine\DBAL\Connection;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use MauticPlugin\CustomObjectsBundle\Segment\SegmentItemResolver;

/**
 * The items of a Custom Object that the wait "Wait" counts its date from, when its date field is one of a Custom Object: the items the
 * campaign's source segments select for the contact (a contact in two disciplines waits for the one the segment is about, not for
 * the latest one). The rules of the selection (conditions of the segment on the object, AND / OR groups, negated operators, merged
 * filters) are the Custom Objects plugin's SegmentItemResolver, the same the n8n dispatch variables use.
 *
 * null: nothing narrows the items (a contact field, no condition of the segments on the object, the plugin or the object not
 * there), so any item of the contact counts. A list, empty or not: exactly those items.
 */
class RelativeWaitItemSelector
{
    public function __construct(
        private Connection $connection,
        private ?SegmentItemResolver $itemResolver = null,
        private ?CustomObjectModel $customObjectModel = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config the properties of the wait
     *
     * @return int[]|null
     */
    public function itemIds(Lead $contact, Campaign $campaign, array $config): ?array
    {
        if (null === $this->itemResolver || null === $this->customObjectModel
            || 1 !== preg_match('/^custom_object:([0-9]+)$/', (string) ($config['dateField'] ?? ''), $matches)) {
            return null;
        }

        $prefix   = defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
        $objectId = $this->connection->fetchOne('SELECT custom_object_id FROM '.$prefix.'custom_field WHERE id = :id', ['id' => (int) $matches[1]]);
        if (false === $objectId || null === $objectId) {
            return null;
        }

        try {
            $customObject = $this->customObjectModel->fetchEntity((int) $objectId);
        } catch (NotFoundException) {
            return null;
        }

        return $this->itemResolver->resolveItemIds($contact, $campaign->getLists(), $customObject);
    }
}
