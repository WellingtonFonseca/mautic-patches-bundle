<?php

declare(strict_types=1);

return [
    'name'        => 'Mautic Patches',
    'description' => 'Small, targeted fixes for Mautic core UI bugs, applied without editing core files.',
    'version'     => '0.2.0',
    'routes'      => [
        'api' => [
            'mautic_patches_api_segment_rebuild' => [
                'path'         => '/segments/{id}/rebuild',
                'controller'   => 'MauticPlugin\\MauticPatchesBundle\\Controller\\Api\\SegmentRebuildApiController::rebuildAction',
                'method'       => 'POST',
                'requirements' => ['id' => '\\d+'],
            ],
        ],
    ],
    'author'      => 'Wellington Fonseca',
];
