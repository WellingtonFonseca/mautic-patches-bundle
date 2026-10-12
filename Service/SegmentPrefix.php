<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The IES prefix of a segment name: "PUCPR_POS | Onboarding" is the prefix "PUCPR_POS" and the name "Onboarding".
 *
 * The prefix is not a column of its own (a segment has no custom fields): it is the start of the name, so lists,
 * campaign pickers, the API and the dispatch plugin all show it with no change.
 */
final class SegmentPrefix
{
    public const SEPARATOR = ' | ';

    /**
     * @return array{prefix: string, name: string}
     */
    public static function split(string $fullName): array
    {
        if (1 === preg_match('/^([^\s|]+) \| (\S.*)$/su', $fullName, $m)) {
            return ['prefix' => $m[1], 'name' => $m[2]];
        }

        return ['prefix' => '', 'name' => $fullName];
    }

    public static function join(string $prefix, string $name): string
    {
        return trim($prefix).self::SEPARATOR.trim($name);
    }

    /**
     * A prefix with no spaces or "|", a " | ", then a name that does not start with a space.
     */
    public static function isValid(?string $fullName): bool
    {
        return null !== $fullName && 1 === preg_match('/^[^\s|]+ \| \S.*$/su', $fullName);
    }
}
