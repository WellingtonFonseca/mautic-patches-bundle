<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\Performance;

use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Times every SQL query of one request: how many, how long in total, and the
 * slowest ones. Plugged into the DBAL connection by ApiTimingSubscriber (the
 * logger that was already there keeps getting the calls). Query parameters are
 * never kept, only the SQL text, so no contact data ends up in the log.
 */
class QueryTimer implements SQLLogger
{
    private const KEEP_SLOWEST = 3;
    private const SQL_MAX_LEN  = 300;

    private int $count   = 0;
    private float $total = 0.0;
    private float $started = 0.0;
    private string $current = '';

    /** @var list<array{ms: float, sql: string}> */
    private array $slowest = [];

    public function __construct(private ?SQLLogger $next = null)
    {
    }

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->next?->startQuery($sql, $params, $types);
        $this->current = (string) $sql;
        $this->started = microtime(true);
    }

    public function stopQuery(): void
    {
        $ms = (microtime(true) - $this->started) * 1000;
        ++$this->count;
        $this->total += $ms;

        $this->slowest[] = ['ms' => round($ms, 1), 'sql' => mb_substr(preg_replace('/\s+/', ' ', $this->current) ?? '', 0, self::SQL_MAX_LEN)];
        usort($this->slowest, static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);
        $this->slowest = array_slice($this->slowest, 0, self::KEEP_SLOWEST);

        $this->next?->stopQuery();
    }

    public function count(): int
    {
        return $this->count;
    }

    public function totalMs(): float
    {
        return $this->total;
    }

    /**
     * @return list<array{ms: float, sql: string}>
     */
    public function slowest(): array
    {
        return $this->slowest;
    }
}
