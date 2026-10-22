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
            $contact[(string) $field['label']] = 'contact:'.$field['alias'];
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
            $groups[(string) $row['object']][(string) $row['label']] = 'custom_object:'.$row['id'];
        }

        return $groups;
    }
}
