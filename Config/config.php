<?php

declare(strict_types=1);

return [
    'name'        => 'Mautic Patches',
    'description' => 'Small, targeted fixes for Mautic core UI bugs, applied without editing core files.',
    'version'     => '0.3.0',
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
    'author'      => 'Wellington Fonseca',
];
