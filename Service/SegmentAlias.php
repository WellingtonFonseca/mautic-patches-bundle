<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

/**
 * The alias a segment gets when none is typed: prefix and name, joined, in lower case letters and numbers only (no spaces,
 * dashes, underscores, symbols or accents): prefix "PUCPR" and name "Nome - do meu segmento" give "pucprnomedomeusegmento".
 *
 * Core would build it from the name alone and join the words with dashes ("nome---do-meu-segmento").
 */
final class SegmentAlias
{
    public static function generate(string $prefix, string $name): string
    {
        $text = $prefix.' '.$name;

        $ascii = function_exists('transliterator_transliterate')
            ? transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text)
            : iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return (string) preg_replace('/[^a-z0-9]/', '', strtolower((string) $ascii));
    }
}
