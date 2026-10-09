<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\LeadBundle\Model\LeadModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /s/contact-details/{id}: the body of the "Ver" modal of the contact list, just the Details table (the
 * fields of each group, as on the contact page), none of the page's other tabs. Same access rule as the contact
 * page itself (view own / view other).
 */
class ContactDetailsController extends CommonController
{
    public function detailsAction(LeadModel $leadModel, int $id): Response
    {
        $contact = $leadModel->getEntity($id);

        if (null === $contact) {
            return $this->notFound();
        }

        if (!$this->security->hasEntityAccess('lead:leads:viewown', 'lead:leads:viewother', $contact->getPermissionUser())) {
            return $this->accessDenied();
        }

        return $this->delegateView([
            'viewParameters'  => ['lead' => $contact, 'fields' => $contact->getFields()],
            'contentTemplate' => '@MauticPatches/Contact/details.html.twig',
            'passthroughVars' => ['route' => false],
        ]);
    }
}
