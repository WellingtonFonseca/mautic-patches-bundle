<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RelativeWait;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The date fields the wait "Wait" can count from, as the groups of a select: the date and datetime fields of the contact, and
 * the date and datetime fields of each Custom Object. The value of an option is what RelativeWaitDateResolver reads:
 * `contact:<alias>` or `custom_object:<custom field id>`. Without the Custom Objects plugin there are only the contact fields.
 */
class RelativeWaitFieldCatalog
{
    public function __construct(private Connection $connection, private TranslatorInterface $translator)
    {
    }

    /**
     * @return array<string, array<string, string>> group label => (field label => value)
     */
    public function choices(): array
    {
        $prefix = defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
        $groups = [];

        $contact = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT alias, label FROM '.$prefix."lead_fields WHERE object = 'lead' AND is_published = 1 AND type IN ('date', 'datetime') ORDER BY label"
        ) as $field) {
            $contact[$this->label($contact, (string) $field['label'], (string) $field['alias'])] = 'contact:'.$field['alias'];
        }
        if ([] !== $contact) {
            $groups[$this->translator->trans('mautic.patches.condition.contact_fields.group.lead')] = $contact;
        }

        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT o.name_singular AS object, f.id, f.label FROM '.$prefix.'custom_field f JOIN '.$prefix.'custom_object o ON o.id = f.custom_object_id'
                ." WHERE f.is_published = 1 AND o.is_published = 1 AND f.type IN ('date', 'datetime') ORDER BY o.name_singular, f.field_order, f.label"
            );
        } catch (\Throwable) {
            $rows = []; // the Custom Objects plugin is not installed
        }
        foreach ($rows as $row) {
            $object = (string) $row['object'];
            $groups[$object][$this->label($groups[$object] ?? [], (string) $row['label'], '#'.$row['id'])] = 'custom_object:'.$row['id'];
        }

        return $groups;
    }

    /**
     * The label of the field a Wait event counts from ("Data de nascimento"; "Consulta: Data" for a Custom Object field), or null when
     * the value is not `contact:<alias>` / `custom_object:<id>` or the field is gone. Published or not: the name keeps the field it points to.
     */
    public function labelOf(string $dateField): ?string
    {
        $prefix = defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
        [$kind, $key] = array_pad(explode(':', $dateField, 2), 2, '');
        if ('' === $key) {
            return null;
        }

        try {
            if ('contact' === $kind) {
                $label = $this->connection->fetchOne('SELECT label FROM '.$prefix."lead_fields WHERE object = 'lead' AND alias = ?", [$key]);

                return is_string($label) && '' !== $label ? $label : null;
            }
            if ('custom_object' === $kind && ctype_digit($key)) {
                $row = $this->connection->fetchAssociative(
                    'SELECT o.name_singular AS object, f.label FROM '.$prefix.'custom_field f JOIN '.$prefix.'custom_object o ON o.id = f.custom_object_id WHERE f.id = ?',
                    [(int) $key]
                );

                return is_array($row) ? $row['object'].': '.$row['label'] : null;
            }
        } catch (\Throwable) {
            return null; // e.g. the Custom Objects plugin is not installed
        }

        return null;
    }

    /**
     * The choices are keyed by label: a second field with the same label in the same group gets its alias (or id) after it, so both can be chosen.
     *
     * @param array<string, string> $group
     */
    private function label(array $group, string $label, string $suffix): string
    {
        return isset($group[$label]) ? $label.' ('.$suffix.')' : $label;
    }
}
