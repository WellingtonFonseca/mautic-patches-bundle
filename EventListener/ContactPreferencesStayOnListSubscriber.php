<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Keeps the user on the contact list after the preferences modal is saved: core answers with the contact PAGE, so for
 * a save made from the list (Referer /s/contacts[/page]) the answer's content is swapped for the list itself, read
 * with a sub request of the same URL. Why and what is left alone: wiki/patches-contact-preferences.md.
 */
class ContactPreferencesStayOnListSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', 0]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request  = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest() || !$this->isPreferencesSave($request) || !$response instanceof JsonResponse) {
            return;
        }

        $saved = json_decode((string) $response->getContent(), true);
        if (!\is_array($saved) || empty($saved['closeModal'])) {
            return;
        }

        $listUrl = $this->listUrl($request);
        if (null === $listUrl) {
            return;
        }

        $list = $this->readList($event, $request, $listUrl);
        if (null === $list) {
            return;
        }

        $response->setData(['closeModal' => 1, 'updateMainContent' => true] + $list);
    }

    private function isPreferencesSave(Request $request): bool
    {
        return $request->isMethod(Request::METHOD_POST)
            && $request->hasSession()
            && 'mautic_contact_action' === $request->attributes->get('_route')
            && 'contactFrequency' === $request->attributes->get('objectAction');
    }

    private function listUrl(Request $request): ?string
    {
        $referer = parse_url((string) $request->headers->get('Referer'));
        $path    = $referer['path'] ?? '';
        if (!preg_match('#^'.preg_quote($this->router->generate('mautic_contact_index'), '#').'(/\d+)?$#', $path)) {
            return null;
        }

        return $request->getSchemeAndHttpHost().$path.(isset($referer['query']) ? '?'.$referer['query'] : '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readList(ResponseEvent $event, Request $request, string $listUrl): ?array
    {
        $sub = Request::create($listUrl, Request::METHOD_GET, [], $request->cookies->all(), [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $sub->setSession($request->getSession());
        $subResponse = $event->getKernel()->handle($sub, HttpKernelInterface::SUB_REQUEST);
        $list        = json_decode((string) $subResponse->getContent(), true);

        if ($subResponse->isSuccessful() && \is_array($list) && isset($list['newContent'])) {
            return $list;
        }

        $this->logger->warning('Contact preferences saved, but the contact list could not be read to stay on it.', ['url' => $listUrl, 'status' => $subResponse->getStatusCode()]);

        return null;
    }
}
