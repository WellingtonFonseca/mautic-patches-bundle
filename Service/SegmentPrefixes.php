<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;

/**
 * The prefixes a segment can have: the "Prefixos" box in Configurações (parameter
 * `segment_prefixes`, one prefix per line).
 */
class SegmentPrefixes
{
    public const PARAMETER = 'segment_prefixes';

    public function __construct(private CoreParametersHelper $coreParametersHelper)
    {
    }

    /**
     * @return string[] trimmed, no empty line, no repeated prefix, in the order they were typed
     */
    public function all(): array
    {
        $value = $this->coreParametersHelper->get(self::PARAMETER, []);

        return self::normalize(is_array($value) ? $value : []);
    }

    public function has(string $prefix): bool
    {
        return in_array($prefix, $this->all(), true);
    }

    /**
     * @param array<mixed> $lines
     *
     * @return string[]
     */
    public static function normalize(array $lines): array
    {
        $prefixes = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ('' !== $line && !in_array($line, $prefixes, true)) {
                $prefixes[] = $line;
            }
        }

        return $prefixes;
    }

    /**
     * The first problem with the lines typed in Configurações, or null when all are fine: a prefix has no space and no
     * "|" (it would not fit "PREFIXO | Nome"), and is not repeated.
     *
     * @param array<mixed> $lines
     */
    public static function problem(array $lines): ?string
    {
        $seen = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ('' === $line) {
                continue;
            }
            if (1 !== preg_match('/^[^\s|]+$/u', $line)) {
                return 'invalid';
            }
            if (isset($seen[$line])) {
                return 'duplicate';
            }
            $seen[$line] = true;
        }

        return null;
    }
}
