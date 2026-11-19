<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RelativeWait;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The name of a Wait node ("Esperar / Esperar 2 dias / depois de / Data de nascimento"), always built from its properties: the node has no name field of its own.
 */
class RelativeWaitNameFormatter
{
    private const UNITS = ['d', 'w', 'm', 'y', 'h', 'i'];

    public function __construct(private TranslatorInterface $translator, private RelativeWaitFieldCatalog $catalog)
    {
    }

    /**
     * Four lines: "Esperar" / "Esperar 2 dias" / "depois de" / "Data de nascimento". A direction other than after/before drops the third line.
     */
    public function format(int $amount, string $unit, string $direction, ?string $fieldLabel): string
    {
        $key   = in_array($unit, self::UNITS, true) ? 'mautic.patches.wait_relative.name.'.$unit : 'mautic.patches.wait_relative.name.unknown';
        $lines = [
            $this->translator->trans('mautic.patches.wait_relative.name.title'),
            $this->translator->trans($key, ['%count%' => max(0, $amount)]),
        ];
        if (in_array($direction, ['after', 'before'], true)) {
            $lines[] = $this->translator->trans('mautic.patches.wait_relative.name.direction.'.$direction);
        }
        $lines[] = null !== $fieldLabel && '' !== $fieldLabel ? $fieldLabel : $this->translator->trans('mautic.patches.wait_relative.name.field_missing');

        return mb_substr(implode("\n", $lines), 0, 191);
    }

    /**
     * @param array<string, mixed>|null $properties the "properties" of a Wait event
     */
    public function fromProperties(?array $properties): string
    {
        $dateField = (string) ($properties['dateField'] ?? '');

        return $this->format(
            (int) ($properties['amount'] ?? 1),
            (string) ($properties['unit'] ?? 'd'),
            (string) ($properties['direction'] ?? ''),
            '' === $dateField ? null : $this->catalog->labelOf($dateField)
        );
    }
}
