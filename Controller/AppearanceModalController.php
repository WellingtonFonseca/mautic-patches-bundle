<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\UserBundle\Model\UserModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/appearance-modal: the body of the modal opened by the sun / moon button of the navbar (AppearanceButtonSubscriber):
 * the theme cards of Account > Appearance, drawn with core's own helper template.
 *
 * POST /s/appearance-modal/save: keeps the chosen theme as the user's preference. Core 7 renders `theme` on <html> from that
 * preference on every page (base.html.twig) and ignores the browser's storage, so a choice that stays in the browser is lost at
 * the next page load.
 */
class AppearanceModalController extends CommonController
{
    /** The ids of the cards of core's Account > Appearance tab; the preference is never written with another value. */
    private const THEMES = ['light', 'dark', 'solarized-light', 'solarized-dark', 'freire'];

    public function modalAction(): Response
    {
        return $this->delegateView([
            'viewParameters'  => [],
            'contentTemplate' => '@MauticPatches/Appearance/modal.html.twig',
            'passthroughVars' => ['route' => false],
        ]);
    }

    public function saveAction(Request $request, UserModel $userModel): JsonResponse
    {
        // all(): InputBag::get() throws on an array value (theme[]=x), which must be a plain refusal
        $theme = $request->request->all()['theme'] ?? null;

        if (!is_string($theme) || !in_array($theme, self::THEMES, true)) {
            return new JsonResponse(['success' => false], Response::HTTP_BAD_REQUEST);
        }

        $userModel->setPreference('theme', $theme);

        return new JsonResponse(['success' => true]);
    }
}
