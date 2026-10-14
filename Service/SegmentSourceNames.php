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
            $names[$id] = self::label($name, $prefixes[(int) $id] ?? '');
        }
        asort($names);

        return $names;
    }

    /**
     * One name: "PREFIX - Name", or the name itself when the segment has no prefix.
     */
    public static function label(string $name, string $prefix): string
    {
        return '' === $prefix ? $name : $prefix.' - '.$name;
    }
}
