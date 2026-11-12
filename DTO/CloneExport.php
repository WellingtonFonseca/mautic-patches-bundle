<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\DTO;

/**
 * The result of a roles / users export: the encrypted package and the key that opens it (shown once).
 */
class CloneExport
{
    public function __construct(private string $package, private string $key)
    {
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
