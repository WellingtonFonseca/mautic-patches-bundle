<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/appearance-modal: the body of the modal opened by the sun / moon button of the navbar (AppearanceButtonSubscriber):
 * the accent colors and the theme cards of Account > Appearance, drawn with core's own helper templates. It needs no data:
 * the choice lives in the browser (localStorage), so the script marks the checked radios when the modal opens.
 */
class AppearanceModalController extends CommonController
{
    public function modalAction(): Response
    {
        return $this->delegateView([
            'viewParameters'  => [],
            'contentTemplate' => '@MauticPatches/Appearance/modal.html.twig',
            'passthroughVars' => ['route' => false],
        ]);
    }
}
