<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RelativeWait;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The name of a Wait node ("Esperar 2 dias"), always built from its amount and unit: the node has no name field of its own.
 */
class RelativeWaitNameFormatter
{
    private const UNITS = ['d', 'w', 'm', 'y', 'h', 'i'];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function format(int $amount, string $unit): string
    {
        $key = in_array($unit, self::UNITS, true) ? 'mautic.patches.wait_relative.name.'.$unit : 'mautic.patches.wait_relative.name.unknown';

        return $this->translator->trans($key, ['%count%' => max(0, $amount)]);
    }

    /**
     * @param array<string, mixed>|null $properties the "properties" of a Wait event
     */
    public function fromProperties(?array $properties): string
    {
        return $this->format((int) ($properties['amount'] ?? 1), (string) ($properties['unit'] ?? 'd'));
    }
}
