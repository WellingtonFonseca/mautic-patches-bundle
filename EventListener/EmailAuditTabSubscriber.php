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
 * Adds a "Histórico" tab to the email page (Channels > Emails > open one), next to "Contagem de cliques" and "Contatos": who
 * changed the email, when, and which fields went from what to what. Core writes this to its audit_log table when an email is
 * saved but only prints a line per change ("Atividade recente", with no detail), so the detail was never visible.
 *
 * The same tab is on the edit page (Edit), through core's own tab hooks 'email.tabs' (the link) and 'email.tabs.content' (the pane).
 *
 * Core's view page has no hook for a tab of that block. Its hook 'details.stats.graph.below' sits just before the tabs, so the tab
 * (a tab link and a pane) is rendered there as plain markup and a script moves it into the tabs once the page is complete. It
 * runs on a full load and on an ajax load (mQuery(fn) runs at once when the page is already ready).
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
            $customContentEvent->addTemplate('@MauticPatches/Email/audit_form_tab.html.twig', ['rows' => null]);

            return;
        }

        $rows = AuditTrail::rows($this->auditLog->getLogForObject('email', $email->getId(), null, self::LIMIT, 'email'));

        $customContentEvent->addTemplate($isFormPane ? '@MauticPatches/Email/audit_form_tab.html.twig' : '@MauticPatches/Email/audit_tab.html.twig', ['rows' => $rows]);
    }
}
