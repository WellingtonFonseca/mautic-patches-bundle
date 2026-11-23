<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactDetailStatusSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\StatusScript;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * GET /s/contact-details/{id}: the body of the "Ver" modal of the contact list, just the Details table (the
 * fields of each group, as on the contact page), none of the page's other tabs. Same access rule as the contact
 * page itself (view own / view other).
 */
class ContactDetailsController extends CommonController
{
    // the badges of the contact page (the list's circles at the height of the buttons), scoped to the modal's header, and its title
    private const STYLE = '.patches-details-badges{display:inline-flex;align-items:center;gap:8px;height:40px;margin-bottom:16px}'
        .'.patches-contact-header .patches-modal-name{margin:0 0 12px;word-break:break-word;float:none}';

    public function detailsAction(LeadModel $leadModel, Environment $twig, TranslatorInterface $translator, int $id): Response
    {
        $contact = $leadModel->getEntity($id);

        if (null === $contact) {
            return $this->notFound();
        }

        if (!$this->security->hasEntityAccess('lead:leads:viewown', 'lead:leads:viewother', $contact->getPermissionUser())) {
            return $this->accessDenied();
        }

        $record  = trim((string) $contact->getFieldValue('enro_academic_record'));
        $name    = trim($contact->getFirstname().' '.$contact->getLastname());
        $blocked = [];
        foreach ($contact->getDoNotContact() as $dnc) {
            $blocked[$dnc->getChannel()] = true;
        }

        // the header component (title + hidden marker) and the script that draws its badges, as the segment and campaign modals do
        $headHtml = $twig->render('@MauticPatches/Contact/header.html.twig', [
            'title'   => '' !== $record && '' !== $name ? $record.' - '.$name : ('' !== $record ? $record : $name),
            'blocked' => implode(',', array_keys($blocked)),
        ]).StatusScript::html($translator, str_replace('.patches-contact-badges', '.patches-details-badges', ContactDetailStatusSubscriber::STYLE).self::STYLE, 'installContactModalHead', 'Resources/js/contact-detail-badges.js');

        return $this->delegateView([
            'viewParameters'  => [
                'headHtml' => $headHtml,
                'lead'    => $contact,
                'fields'  => $contact->getFields(),
                'canEdit' => $this->security->hasEntityAccess('lead:leads:editown', 'lead:leads:editother', $contact->getPermissionUser()),
            ],
            'contentTemplate' => '@MauticPatches/Contact/details.html.twig',
            'passthroughVars' => ['route' => false],
        ]);
    }
}
