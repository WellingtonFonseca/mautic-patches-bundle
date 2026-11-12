<?php

declare(strict_types=1);

use MauticPlugin\MauticPatchesBundle\Integration\MauticPatchesIntegration;

return [
    'name'        => 'Mautic Patches',
    'description' => 'Small, targeted fixes for Mautic core UI bugs, applied without editing core files.',
    'version'     => '0.6.0',
    'routes'      => [
        // Session-authenticated target of the "Update" button in the segment list. Not under
        // /segments/..., which core's /s/segments/{objectAction}/{objectId} would match first.
        'main' => [
            'mautic_patches_segment_rebuild' => [
                'path'         => '/segment-rebuild/{id}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentRebuildController::rebuildAction',
                'method'       => 'POST',
                'requirements' => ['id' => '\\d+'],
            ],
            // The segment's last built date, polled by the Update button's script to know when the rebuild is done.
            'mautic_patches_segment_rebuild_status' => [
                'path'         => '/segment-rebuild/{id}/status',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentRebuildController::statusAction',
                'method'       => 'GET',
                'requirements' => ['id' => '\\d+'],
            ],
            // Session-authenticated target of the "Update" button of the campaign list and page, and its status poll.
            'mautic_patches_campaign_rebuild' => [
                'path'         => '/campaign-rebuild/{id}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignRebuildController::rebuildAction',
                'method'       => 'POST',
                'requirements' => ['id' => '\\d+'],
            ],
            'mautic_patches_campaign_rebuild_status' => [
                'path'         => '/campaign-rebuild/{id}/status',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignRebuildController::statusAction',
                'method'       => 'GET',
                'requirements' => ['id' => '\\d+'],
            ],
            // The Appearance modal (accent colors and theme) opened by the sun / moon button left of the notification bell.
            'mautic_patches_appearance_modal' => [
                'path'       => '/appearance-modal',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\AppearanceModalController::modalAction',
                'method'     => 'GET',
            ],
            // The details modal of the segment list: the tabs of the segment page.
            'mautic_patches_segment_details' => [
                'path'         => '/segment-details/{id}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentDetailsController::detailsAction',
                'method'       => 'GET',
                'requirements' => ['id' => '\\d+'],
            ],
            // The one table under the second "Ações" tab of the campaign page: the failed / the waiting contacts of one action step.
            'mautic_patches_campaign_action_failed_contacts' => [
                'path'         => '/campaign-action/{objectId}/failed/{page}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignActionContactsController::failedAction',
                'requirements' => ['objectId' => '\\d+', 'page' => '\\d+'],
                'defaults'     => ['page' => 1],
            ],
            'mautic_patches_campaign_action_none_contacts' => [
                'path'         => '/campaign-action/{objectId}/none/{page}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignActionContactsController::noneAction',
                'requirements' => ['objectId' => '\\d+', 'page' => '\\d+'],
                'defaults'     => ['page' => 1],
            ],
            'mautic_patches_campaign_action_waiting_contacts' => [
                'path'         => '/campaign-action/{objectId}/waiting/{page}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignActionContactsController::waitingAction',
                'requirements' => ['objectId' => '\\d+', 'page' => '\\d+'],
                'defaults'     => ['page' => 1],
            ],
            // The "Recalculate dates" box of a Wait step on the second "Ações" tab: the simulation (reads) and the application (writes).
            'mautic_patches_campaign_wait_recalculate_simulate' => [
                'path'         => '/campaign-wait/{eventId}/recalculate/simulate',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignWaitRecalculateController::simulateAction',
                'method'       => 'GET',
                'requirements' => ['eventId' => '\\d+'],
            ],
            'mautic_patches_campaign_wait_recalculate_apply' => [
                'path'         => '/campaign-wait/{eventId}/recalculate/apply',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignWaitRecalculateController::applyAction',
                'method'       => 'POST',
                'requirements' => ['eventId' => '\\d+'],
            ],
            // The "Ver" modal of the contact list: the contact's Details table, nothing else.
            'mautic_patches_contact_details' => [
                'path'         => '/contact-details/{id}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\ContactDetailsController::detailsAction',
                'method'       => 'GET',
                'requirements' => ['id' => '\\d+'],
            ],
            // The contacts tab of a segment as a table. Same name and path as core's route, which it replaces.
            'mautic_segment_contacts' => [
                'path'       => '/segment/view/{objectId}/contact/{page}',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentContactsController::contactsAction',
            ],
            // The contacts tab of a campaign as a table, same idea.
            'mautic_campaign_contacts' => [
                'path'       => '/campaigns/view/{objectId}/contact/{page}',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignContactsController::contactsAction',
            ],
            // The "Filtros" tab of the segment page: the filters the segment applies, read-only.
            'mautic_patches_segment_filters' => [
                'path'         => '/segment-filters/{id}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentFiltersController::filtersAction',
                'method'       => 'GET',
                'requirements' => ['id' => '\\d+'],
            ],
            // The suggestions of the including / excluding chips of the segment form, for a contact or company text field.
            'mautic_patches_segment_filter_values' => [
                'path'       => '/segment-filter-values',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentFilterValuesController::valuesAction',
                'method'     => 'GET',
            ],
            // Clone of the contact and company fields: the definition as JSON (Exportar) and its import (check, then confirm).
            'mautic_patches_field_definition_export' => [
                'path'       => '/field-definition/clone-export',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\FieldDefinitionController::exportAction',
                'method'     => 'GET',
            ],
            'mautic_patches_field_definition_import' => [
                'path'       => '/field-definition/clone-import',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\FieldDefinitionController::importFormAction',
                'method'     => 'GET',
            ],
            'mautic_patches_field_definition_import_save' => [
                'path'       => '/field-definition/clone-import',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\FieldDefinitionController::importSaveAction',
                'method'     => 'POST',
            ],
            // Clone of the roles or of the users ({kind}: roles | users): the encrypted package with its key (the items are chosen in the core list), and the import (check, then confirm).
            'mautic_patches_role_user_clone_export' => [
                'path'         => '/role-user-clone/{kind}/export',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\RoleUserCloneController::exportAction',
                'method'       => 'GET',
                'requirements' => ['kind' => 'roles|users'],
            ],
            'mautic_patches_role_user_clone_import' => [
                'path'         => '/role-user-clone/{kind}/import',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\RoleUserCloneController::importFormAction',
                'method'       => 'GET',
                'requirements' => ['kind' => 'roles|users'],
            ],
            'mautic_patches_role_user_clone_import_save' => [
                'path'         => '/role-user-clone/{kind}/import',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\RoleUserCloneController::importSaveAction',
                'method'       => 'POST',
                'requirements' => ['kind' => 'roles|users'],
            ],
            // Settings > Performance: where the time of the API goes, and the live diagnostics.
            'mautic_patches_performance' => [
                'path'       => '/performance',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\PerformanceController::indexAction',
            ],
            // Contact list gear > Delete contacts in bulk: the page, its table (with the total of the search) and one delete batch per POST.
            'mautic_patches_contact_bulk_delete' => [
                'path'       => '/contacts-bulk-delete',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\ContactBulkDeleteController::indexAction',
                'method'     => 'GET',
            ],
            // The table of the contacts the search finds: the contact list table, as in the segment and campaign pages.
            'mautic_patches_contact_bulk_delete_contacts' => [
                'path'         => '/contacts-bulk-delete/{objectId}/contacts/{page}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\ContactBulkDeleteController::contactsAction',
                'requirements' => ['objectId' => '\\d+', 'page' => '\\d+'],
                'defaults'     => ['objectId' => 0, 'page' => 1],
            ],
            'mautic_patches_contact_bulk_delete_batch' => [
                'path'       => '/contacts-bulk-delete/batch',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\ContactBulkDeleteController::deleteAction',
                'method'     => 'POST',
            ],
        ],
        'api' => [
            'mautic_patches_api_segment_rebuild' => [
                'path'         => '/segments/{id}/rebuild',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\Api\\SegmentRebuildApiController::rebuildAction',
                'method'       => 'POST',
                'requirements' => ['id' => '\\d+'],
            ],
        ],
    ],
    'menu' => [
        'admin' => [
            'mautic.patches.perf.menu' => [
                'route'     => 'mautic_patches_performance',
                'iconClass' => 'ri-speed-up-line',
                'access'    => 'admin',
                'priority'  => 5,
            ],
        ],
    ],
    'services' => [
        'integrations' => [
            'mautic.integration.mauticpatches' => [
                'class'     => MauticPatchesIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                ],
            ],
        ],
    ],
    'parameters'  => [
        // The Prefixes a segment name can start with (Configurações > Prefixos), one string per prefix.
        'segment_prefixes'  => [],
        // The same for campaigns (Configurações > Configurações de campanhas > Prefixos).
        'campaign_prefixes' => [],
    ],
    'author'      => 'Wellington Fonseca',
];
