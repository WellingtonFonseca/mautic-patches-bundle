<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The segment names of the campaign's contact source (the select of the source form and the node on the canvas) as
 * "PREFIX - Name". A segment without prefix keeps its name.
 */
final class SegmentSourceNames
{
    /**
     * @param array<int|string, string> $names    segment id => name
     * @param array<int, string>        $prefixes segment id => prefix
     *
     * @return array<int|string, string> segment id => name shown, sorted by it
     */
    public static function apply(array $names, array $prefixes): array
    {
        foreach ($names as $id => $name) {
            $prefix = $prefixes[(int) $id] ?? '';
            if ('' !== $prefix) {
                $names[$id] = $prefix.' - '.$name;
            }
        }
        asort($names);

        return $names;
    }
}
