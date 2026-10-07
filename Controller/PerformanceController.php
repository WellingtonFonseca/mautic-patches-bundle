<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticPatchesBundle\Service\Performance\Diagnostics;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceReport;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings > Performance: how fast the API has been (per route, with the
 * time split between SQL and the application), the slowest requests with
 * their heaviest queries, and a button that runs the live diagnostics. Admins
 * only, like the menu entry.
 */
class PerformanceController extends CommonController
{
    /** Periods offered, in hours. */
    public const PERIODS = [1, 6, 24, 168];

    public function indexAction(Request $request, PerformanceLog $log, PerformanceReport $report, Diagnostics $diagnostics): Response
    {
        if (!$this->security->isAdmin()) {
            return $this->accessDenied();
        }

        $hours = (int) $request->query->get('hours', 24);
        $hours = in_array($hours, self::PERIODS, true) ? $hours : 24;
        $slow  = PerformanceReport::slowLimit();

        return $this->delegateView([
            'viewParameters' => [
                'hours'   => $hours,
                'periods' => self::PERIODS,
                'slowMs'  => $slow,
                'report'  => $report->build($log->read(time() - $hours * 3600), $slow),
                // "Run diagnostics" reloads the screen (Mautic's ajax page load) with ?diagnose=1: the numbers above refresh too.
                'checks'  => $request->query->getBoolean('diagnose') ? $diagnostics->run() : null,
            ],
            'contentTemplate' => '@MauticPatches/Performance/index.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_patches_performance',
                'mauticContent' => 'patchesPerformance',
                'route'         => $this->generateUrl('mautic_patches_performance', ['hours' => $hours]),
            ],
        ]);
    }
}
