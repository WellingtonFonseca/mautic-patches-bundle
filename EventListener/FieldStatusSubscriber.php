<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\LeadBundle\Entity\LeadField;
use MauticPlugin\MauticPatchesBundle\Controller\FieldDefinitionController;

/**
 * The same as EmailStatusSubscriber, for the Contact and Company field list (one list, route mautic_contactfield_index): the toggle icon
 * next to the name becomes an icon badge in the Status column, and "Desativar" / "Ativar" is the FIRST item of the three-dots menu.
 * Core's flow is kept: the menu item clicks core's icon (hidden). A field whose status core does not let change (`disablePublishChange`:
 * the email field, a field whose column a background job is still making) gets a gray badge and no menu item. Core lets the admin
 * deactivate every other field, also a fixed one and a unique identifier, so this list does too.
 *
 * The list has no hook for the label: the script (Resources/js/list-status-toggle.js, installField) makes it from core's icon.
 */
class FieldStatusSubscriber extends AbstractListStatusSubscriber
{
    public const STYLE = '#leadFieldTable i[class*="lead-field-publish-icon"]{display:none!important}'
        .'a[data-field-status-action]:hover{color:var(--text-inverse)!important}'
        .'a[data-field-status-action="deactivate"]:hover{background-color:var(--support-error)!important}'
        .'a[data-field-status-action="activate"]:hover{background-color:var(--support-success)!important}'
        .'a[data-field-status-action]:hover *{color:inherit!important}';

    protected function key(): string
    {
        return 'field';
    }

    protected function installFunction(): string
    {
        return 'installField';
    }

    protected function style(): string
    {
        return self::STYLE;
    }

    protected function route(): string
    {
        return 'mautic_contactfield_index';
    }

    protected function supports(mixed $item): bool
    {
        return $item instanceof LeadField;
    }

    protected function mayEdit(object $item): bool
    {
        return $item instanceof LeadField && !$item->disablePublishChange() && $this->security->isGranted(FieldDefinitionController::PERMISSION);
    }
}
