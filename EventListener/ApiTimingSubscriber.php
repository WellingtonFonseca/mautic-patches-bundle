<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Doctrine\DBAL\Connection;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceReport;
use MauticPlugin\MauticPatchesBundle\Service\Performance\QueryTimer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Measures every /api/ request: total time, time spent in SQL, number of
 * queries. The numbers go back to the caller in the standard Server-Timing
 * header (db;dur=..., app;dur=..., queries;desc=...) and, after the response
 * is sent, into the day's log (PerformanceLog) that the Performance screen
 * and the mautic:performance:report command read. The slowest queries are kept
 * only for requests over the slow limit.
 *
 * Switch off with the environment variable MAUTIC_PERF_DISABLED=1; change the
 * slow limit (ms) with MAUTIC_PERF_SLOW_MS.
 */
class ApiTimingSubscriber implements EventSubscriberInterface
{
    private const ATTR = '_patches_perf';

    public function __construct(private Connection $connection, private PerformanceLog $log)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST   => ['onRequest', 512],
            KernelEvents::RESPONSE  => ['onResponse', -512],
            KernelEvents::TERMINATE => ['onTerminate', -512],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !self::isApi($request->getPathInfo()) || $this->disabled()) {
            return;
        }

        $config = $this->connection->getConfiguration();
        $timer  = new QueryTimer($config->getSQLLogger());
        $config->setSQLLogger($timer);

        $request->attributes->set(self::ATTR, ['timer' => $timer, 'start' => $request->server->get('REQUEST_TIME_FLOAT') ?: microtime(true)]);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $measure = $this->measure($event->getRequest()->attributes->get(self::ATTR));

        if (null === $measure || !$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('Server-Timing', sprintf(
            'db;dur=%.1f, app;dur=%.1f, total;dur=%.1f, queries;desc="%d"',
            $measure['db'],
            max(0, $measure['ms'] - $measure['db']),
            $measure['ms'],
            $measure['q']
        ));
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $measure = $this->measure($request->attributes->get(self::ATTR));

        if (null === $measure) {
            return;
        }

        $entry = [
            't'   => time(),
            'm'   => $request->getMethod(),
            'r'   => (string) $request->attributes->get('_route', ''),
            'p'   => self::normalizePath($request->getPathInfo()),
            's'   => $event->getResponse()->getStatusCode(),
            'ms'  => round($measure['ms'], 1),
            'db'  => round($measure['db'], 1),
            'q'   => $measure['q'],
            'mem' => round(memory_get_peak_usage(true) / 1048576, 1),
            // Which filters the caller sent (names only: the values can be personal data) and how big the answer was.
            'qs'  => array_slice(array_keys($request->query->all()), 0, 10),
            'kb'  => round(strlen((string) $event->getResponse()->getContent()) / 1024, 1),
        ];

        if ($measure['ms'] >= PerformanceReport::slowLimit()) {
            $entry['slow'] = $measure['slow'];
        }

        $this->log->write($entry);
    }

    public static function isApi(string $path): bool
    {
        return str_starts_with($path, '/api/');
    }

    /** Numeric ids become :id so /api/contacts/5 and /api/contacts/9 count as one route. */
    public static function normalizePath(string $path): string
    {
        return preg_replace('#/\d+(?=/|$)#', '/:id', $path) ?? $path;
    }

    /**
     * @return array{ms: float, db: float, q: int, slow: list<array{ms: float, sql: string}>}|null
     */
    private function measure(mixed $state): ?array
    {
        if (!is_array($state) || !($state['timer'] ?? null) instanceof QueryTimer) {
            return null;
        }

        /** @var QueryTimer $timer */
        $timer = $state['timer'];

        return [
            'ms'   => (microtime(true) - (float) $state['start']) * 1000,
            'db'   => $timer->totalMs(),
            'q'    => $timer->count(),
            'slow' => $timer->slowest(),
        ];
    }

    private function disabled(): bool
    {
        return '1' === (string) getenv('MAUTIC_PERF_DISABLED');
    }
}
