<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the user on the contact list after the preferences modal (ContactPreferencesButtonSubscriber) is saved.
 * Core answers the save with the contact PAGE as the new main content. When the request comes from the contact list
 * (its Referer is /s/contacts or /s/contacts/{page}), the page content of that answer is swapped for the list itself,
 * read with a sub request of the same URL (so the search and the page of the user stay), and the modal still closes
 * and the list shows the new Status. A save from the contact page (any other Referer) is not touched, and neither
 * is a form with errors (no closeModal in its answer).
 */
class ContactPreferencesStayOnListSubscriber implements EventSubscriberInterface
{
    private const LIST_PATH = '#^/s/contacts(/\d+)?$#';

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

        $response->setData($list + ['closeModal' => 1, 'updateMainContent' => true]);
    }

    private function isPreferencesSave(Request $request): bool
    {
        return $request->isMethod(Request::METHOD_POST)
            && 'mautic_contact_action' === $request->attributes->get('_route')
            && 'contactFrequency' === $request->attributes->get('objectAction');
    }

    private function listUrl(Request $request): ?string
    {
        $referer = parse_url((string) $request->headers->get('Referer'), PHP_URL_PATH);
        if (!\is_string($referer) || !preg_match(self::LIST_PATH, $referer)) {
            return null;
        }

        $query = parse_url((string) $request->headers->get('Referer'), PHP_URL_QUERY);

        return $request->getSchemeAndHttpHost().$referer.(\is_string($query) ? '?'.$query : '');
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

        return $subResponse->isSuccessful() && \is_array($list) && isset($list['newContent']) ? $list : null;
    }
}
