<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The script of the status badge of the Mautic lists (Resources/js/list-status-toggle.js) and the call that installs one list on it,
 * for the status subscribers (segment, campaign, user, email). The file is served as a static asset with a filemtime() version, so the
 * browser keeps it: inlining it in every page, once per subscriber, cost about 57 KB per page load. The call is skipped when the file
 * did not load (the page keeps working, without the badge). Each install is guarded in the script, so the repeated `<script src>` of
 * the four subscribers runs the file again but does the work once.
 */
final class StatusScript
{
    private const PATH = 'Resources/js/list-status-toggle.js';

    /**
     * @param string $installFunction the function of mauticPatchesListStatusApi that installs the list (install, installCampaign, ...)
     * @param string $style           the CSS the list needs, added to the page by the script
     */
    public static function html(TranslatorInterface $translator, string $style, string $installFunction): string
    {
        $config = [
            'active'     => $translator->trans('mautic.core.form.active'),
            'inactive'   => $translator->trans('mautic.core.form.inactive'),
            'activate'   => $translator->trans('mautic.patches.segment.activate'),
            'deactivate' => $translator->trans('mautic.patches.segment.deactivate'),
            'column'     => $translator->trans('mautic.patches.status.column'),
            'restart'    => $translator->trans('mautic.patches.campaign.restart'),
            'style'      => $style,
        ];
        $mtime = @filemtime(dirname(__DIR__).'/'.self::PATH);

        return '<script src="/plugins/MauticPatchesBundle/'.self::PATH.'?v='.(false !== $mtime ? $mtime : time()).'"></script>'
            .'<script>window.mauticPatchesListStatusApi&&mauticPatchesListStatusApi.'.$installFunction.'(mQuery,Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>';
    }
}
