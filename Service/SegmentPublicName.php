<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The public name of a segment: always "PREFIXO - NOME DO SEGMENTO", whatever was typed in the public name field.
 */
final class SegmentPublicName
{
    public static function generate(string $prefix, string $name): string
    {
        return trim($prefix).' - '.trim($name);
    }
}
