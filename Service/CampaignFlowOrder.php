<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * Puts the events of a campaign in the order of its flow: from the first node down, each node followed by the whole path under it
 * (the yes path of a condition before its no path). Core's page groups the events by type (decision, action, condition) and
 * loses this order, which the Steps tab needs to show actions and conditions in one list.
 */
class CampaignFlowOrder
{
    /**
     * @param list<array<string, mixed>> $events core's event arrays of the page (id, parent_id, decisionPath)
     *
     * @return list<array<string, mixed>> the same events, in flow order
     */
    public function sort(array $events): array
    {
        $ids      = array_column($events, 'id');
        $children = [];
        foreach ($events as $event) {
            // a node whose parent is not in the list (or has none) starts a flow
            $parent              = in_array($event['parent_id'] ?? null, $ids, false) ? (int) $event['parent_id'] : 0;
            $children[$parent][] = $event;
        }

        $sorted = [];
        $this->walk($children, 0, $sorted);

        return $sorted;
    }

    /**
     * @param array<int, list<array<string, mixed>>> $children events by parent id
     * @param list<array<string, mixed>>             $sorted
     */
    private function walk(array $children, int $parent, array &$sorted): void
    {
        $siblings = $children[$parent] ?? [];
        usort($siblings, static fn (array $a, array $b): int => [self::pathRank($a), (int) $a['id']] <=> [self::pathRank($b), (int) $b['id']]);

        foreach ($siblings as $event) {
            $sorted[] = $event;
            $this->walk($children, (int) $event['id'], $sorted);
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private static function pathRank(array $event): int
    {
        return 'no' === ($event['decisionPath'] ?? null) ? 1 : 0;
    }
}
