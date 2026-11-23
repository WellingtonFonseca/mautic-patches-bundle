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
    public function detailsAction(LeadModel $leadModel, Environment $twig, TranslatorInterface $translator, int $id): Response
    {
        $contact = $leadModel->getEntity($id);

        if (null === $contact) {
            return $this->notFound();
        }

        if (!$this->security->hasEntityAccess('lead:leads:viewown', 'lead:leads:viewother', $contact->getPermissionUser())) {
            return $this->accessDenied();
        }

        // the header component (title + hidden marker) and the script that draws its badges, as the segment and campaign modals do
        $headHtml = ContactDetailStatusSubscriber::headHtml($twig, $contact)
            .StatusScript::html($translator, ContactDetailStatusSubscriber::headerStyle(), 'installContactModalHead', 'Resources/js/contact-detail-badges.js');

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
