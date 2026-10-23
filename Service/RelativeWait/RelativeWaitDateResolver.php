<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RelativeWait;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * The date a contact leaves the campaign wait "Wait" (patches.wait_relative): a date of the CONTACT or of a Custom Object
 * item linked to it, plus (after) or minus (before) an amount of time.
 *
 * Settings (the properties of the event): `dateField` = `contact:<alias>` (a date / datetime contact field) or
 * `custom_object:<custom field id>` (a date / datetime field of a Custom Object), `amount`, `unit` (i, h, d, w, m, y) and
 * `direction` (after / before).
 *
 * A Custom Object field is read from the item linked to the contact with the LATEST date and, on a tie, the highest item id
 * (so a contact with two items is always answered with the same one). When the campaign's source segments select items of the
 * object (RelativeWaitItemSelector), only those are considered: the date of the item the segment points at, not another one.
 *
 * A `date` field is a calendar day, not an instant: it is read as midnight of the system time zone, so "+12 days" never lands
 * on the day before. A `datetime` field is stored in UTC and is an instant. No date for the contact, or a setting that cannot be
 * read: null (the wait has nothing to wait for).
 */
class RelativeWaitDateResolver
{
    /** The unit => the ISO 8601 duration of an amount of it (i is minutes, m is months). */
    private const UNITS = ['i' => 'PT%dM', 'h' => 'PT%dH', 'd' => 'P%dD', 'w' => 'P%dW', 'm' => 'P%dM', 'y' => 'P%dY'];

    private const DATE_TYPES = ['date', 'datetime'];

    private const CUSTOM_TABLES = ['date' => 'custom_field_value_date', 'datetime' => 'custom_field_value_datetime'];

    /** The type of a field, by the key of the lookup: it is the same for every contact of a batch (null: not a field). */
    private array $fieldTypes = [];

    public function __construct(private Connection $connection, private string $timezone = 'UTC')
    {
    }

    /**
     * @param array<string, mixed> $config
     * @param int[]|null           $itemIds the items of the Custom Object the segments select (null: any item of the contact; [] none)
     */
    public function resolve(int $contactId, array $config, ?array $itemIds = null): ?\DateTimeImmutable
    {
        $amount    = $config['amount'] ?? null;
        $unit      = (string) ($config['unit'] ?? '');
        $direction = (string) ($config['direction'] ?? '');
        if (!is_numeric($amount) || (int) $amount < 0 || !isset(self::UNITS[$unit]) || !in_array($direction, ['after', 'before'], true)) {
            return null;
        }

        $base = $this->baseDate($contactId, (string) ($config['dateField'] ?? ''), $itemIds);
        if (null === $base) {
            return null;
        }

        $interval = new \DateInterval(sprintf(self::UNITS[$unit], (int) $amount));

        return 'before' === $direction ? $base->sub($interval) : $base->add($interval);
    }

    /**
     * @param int[]|null $itemIds
     */
    private function baseDate(int $contactId, string $dateField, ?array $itemIds): ?\DateTimeImmutable
    {
        if (1 !== preg_match('/^(contact|custom_object):([A-Za-z0-9_]+)$/', $dateField, $matches)) {
            return null;
        }

        return 'contact' === $matches[1] ? $this->contactDate($contactId, $matches[2]) : $this->customObjectDate($contactId, $matches[2], $itemIds);
    }

    private function prefix(): string
    {
        return defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
    }

    private function contactDate(int $contactId, string $alias): ?\DateTimeImmutable
    {
        $type = $this->fieldType(
            'lead:'.$alias,
            'SELECT type FROM '.$this->prefix().'lead_fields WHERE object = :object AND alias = :alias AND is_published = 1',
            ['object' => 'lead', 'alias' => $alias]
        );
        if (!is_string($type) || !in_array($type, self::DATE_TYPES, true)) {
            return null;
        }

        $value = $this->connection->fetchOne('SELECT `'.$alias.'` FROM '.$this->prefix().'leads WHERE id = :id', ['id' => $contactId]);

        return $this->parse($value, $type);
    }

    /**
     * @param int[]|null $itemIds
     */
    private function customObjectDate(int $contactId, string $fieldId, ?array $itemIds): ?\DateTimeImmutable
    {
        if (1 !== preg_match('/^[0-9]+$/', $fieldId) || [] === $itemIds) {
            return null;
        }
        $fieldId = (int) $fieldId;

        $type = $this->fieldType('custom_object:'.$fieldId, 'SELECT type FROM '.$this->prefix().'custom_field WHERE id = :id', ['id' => $fieldId]);
        if (!is_string($type) || !isset(self::CUSTOM_TABLES[$type])) {
            return null;
        }

        $prefix = $this->prefix();
        $params = ['contact' => $contactId, 'field' => $fieldId];
        $types  = [];
        if (null !== $itemIds) {
            $params['items'] = $itemIds;
            $types['items']  = ArrayParameterType::INTEGER;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT v.value FROM '.$prefix.'custom_item_xref_contact x'
            .' JOIN '.$prefix.'custom_item i ON i.id = x.custom_item_id'
            .' JOIN '.$prefix.self::CUSTOM_TABLES[$type].' v ON v.custom_item_id = i.id AND v.custom_field_id = :field'
            .' WHERE x.contact_id = :contact AND v.value IS NOT NULL'
            .(null !== $itemIds ? ' AND i.id IN (:items)' : '')
            .' ORDER BY v.value DESC, i.id DESC LIMIT 1',
            $params,
            $types
        );

        return is_array($row) ? $this->parse($row['value'] ?? null, $type) : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fieldType(string $key, string $sql, array $params): ?string
    {
        if (!array_key_exists($key, $this->fieldTypes)) {
            $type                  = $this->connection->fetchOne($sql, $params);
            $this->fieldTypes[$key] = is_string($type) ? $type : null;
        }

        return $this->fieldTypes[$key];
    }

    private function parse(mixed $value, string $type): ?\DateTimeImmutable
    {
        if (!is_string($value) || '' === $value || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            if ('date' === $type) {
                // a calendar day: midnight of the system time zone
                return new \DateTimeImmutable(substr($value, 0, 10).' 00:00:00', new \DateTimeZone($this->timezone));
            }

            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
