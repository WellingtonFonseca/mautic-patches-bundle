<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The script of the status badge of the Mautic lists (Resources/js/list-status-toggle.js) and the call that installs one list on it,
 * for the status subscribers (segment, campaign, user, email, contact). The file is served as a static asset with a filemtime() version, so the
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
     * @param string $companionScript a file of the plugin that loads after the list script and adds its own install function to its api (the contact page badges)
     */
    // Each call prints the list script again, and every run replaces mauticPatchesListStatusApi with a new object that has lost the install function a
    // companion script added. So the install call must stay right after its own companion tag, in the same output.
    public static function html(TranslatorInterface $translator, string $style, string $installFunction, string $companionScript = ''): string
    {
        $config = [
            'active'     => $translator->trans('mautic.core.form.active'),
            'inactive'   => $translator->trans('mautic.core.form.inactive'),
            'activate'   => $translator->trans('mautic.patches.segment.activate'),
            'deactivate' => $translator->trans('mautic.patches.segment.deactivate'),
            'column'     => $translator->trans('mautic.patches.status.column'),
            'restart'    => $translator->trans('mautic.patches.campaign.restart'),
            'global'     => $translator->trans('mautic.patches.segment.global'),
            'filters'    => $translator->trans('mautic.patches.segment.filters'),
            'rebuild'    => $translator->trans('mautic.patches.segment.not_rebuilt'),
            'slow'       => $translator->trans('mautic.patches.segment.slow_build'),
            'contactEmail'   => $translator->trans('mautic.patches.contact.status.email'),
            'contactSms'     => $translator->trans('mautic.patches.contact.status.sms'),
            'contactHsm'     => $translator->trans('mautic.patches.contact.status.hsm'),
            'emailCampaign'    => $translator->trans('mautic.patches.email.type.campaign'),
            'emailBroadcast'   => $translator->trans('mautic.patches.email.type.broadcast'),
            'emailAbtest'      => $translator->trans('mautic.patches.email.abtest'),
            'emailTranslation' => $translator->trans('mautic.patches.email.translation'),
            'emailDraft'       => $translator->trans('mautic.patches.email.draft'),
            'style'      => $style,
        ];

        return self::tag(self::PATH)
            .('' !== $companionScript ? self::tag($companionScript) : '')
            .'<script>window.mauticPatchesListStatusApi&&mauticPatchesListStatusApi.'.$installFunction.'&&mauticPatchesListStatusApi.'.$installFunction.'(mQuery,Mautic,'.json_encode($config, JSON_UNESCAPED_UNICODE).');</script>';
    }

    private static function tag(string $path): string
    {
        $mtime = @filemtime(dirname(__DIR__).'/'.$path);

        return '<script src="/plugins/MauticPatchesBundle/'.$path.'?v='.(false !== $mtime ? $mtime : time()).'"></script>';
    }
}
