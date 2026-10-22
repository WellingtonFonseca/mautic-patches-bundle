<?php

declare(strict_types=1);

return [
    'name'        => 'Mautic Patches',
    'description' => 'Small, targeted fixes for Mautic core UI bugs, applied without editing core files.',
    'version'     => '0.5.0',
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
            // The details modal of the segment list: the tabs of the segment page.
            'mautic_patches_segment_details' => [
                'path'         => '/segment-details/{id}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\SegmentDetailsController::detailsAction',
                'method'       => 'GET',
                'requirements' => ['id' => '\\d+'],
            ],
            // The modal of the hourglass of a campaign node: the contacts waiting at that event.
            'mautic_patches_campaign_event_pending' => [
                'path'         => '/campaign-pending/{eventId}',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\CampaignEventPendingController::pendingAction',
                'method'       => 'GET',
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
            // Settings > Performance: where the time of the API goes, and the live diagnostics.
            'mautic_patches_performance' => [
                'path'       => '/performance',
                'controller' => 'MauticPlugin\\MauticPatchesBundle\\Controller\\PerformanceController::indexAction',
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
    'parameters'  => [
        // The Prefixes a segment name can start with (Configurações > Prefixos), one string per prefix.
        'segment_prefixes'  => [],
        // The same for campaigns (Configurações > Configurações de campanhas > Prefixos).
        'campaign_prefixes' => [],
    ],
    'author'      => 'Wellington Fonseca',
];
