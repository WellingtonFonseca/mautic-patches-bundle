<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\EmailBundle\Entity\Email;
use MauticPlugin\MauticPatchesBundle\Service\AuditLog\AuditTrail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The "Histórico" tab of the email page and of the edit page: core writes the audit_log of an email but no page shows its detail.
 * The edit page uses core's tab hooks; the view page has none for that block, so the tab is moved in by a script (the reasons,
 * and the priority that keeps it last, are in wiki/patches-email-page.md).
 */
class EmailAuditTabSubscriber implements EventSubscriberInterface
{
    private const VIEW      = '@MauticEmail/Email/details.html.twig';
    private const CONTEXT   = 'details.stats.graph.below';
    private const FORM_VIEW = '@MauticEmail/Email/form.html.twig';
    private const LIMIT     = 200;

    public function __construct(private AuditLogModel $auditLog)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Late, so the tab is the last of the edit page, after the ones other plugins add (Variáveis N8N, Campanhas).
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectTab', -100],
        ];
    }

    public function injectTab(CustomContentEvent $customContentEvent): void
    {
        $onViewPage  = $customContentEvent->checkContext(self::VIEW, self::CONTEXT);
        $isFormLink  = $customContentEvent->checkContext(self::FORM_VIEW, 'email.tabs');
        $isFormPane  = $customContentEvent->checkContext(self::FORM_VIEW, 'email.tabs.content');

        if (!$onViewPage && !$isFormLink && !$isFormPane) {
            return;
        }

        $email = $customContentEvent->getVars()['email'] ?? null;

        // A new email has no id and so no history yet.
        if (!$email instanceof Email || null === $email->getId()) {
            return;
        }

        if ($isFormLink) {
            $customContentEvent->addTemplate('@MauticPatches/Email/audit_form_link.html.twig');

            return;
        }

        $rows = AuditTrail::rows($this->auditLog->getLogForObject('email', $email->getId(), null, self::LIMIT, 'email'));

        $customContentEvent->addTemplate($isFormPane ? '@MauticPatches/Email/audit_form_pane.html.twig' : '@MauticPatches/Email/audit_tab.html.twig', ['rows' => $rows]);
    }
}
