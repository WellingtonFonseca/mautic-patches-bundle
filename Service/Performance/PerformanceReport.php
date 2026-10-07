<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\Performance;

/**
 * Turns the log lines into what the screen and the command show: totals, one
 * row per route, the slowest requests and a verdict on WHERE the time goes
 * (database, many queries, or the application/server). Pure: no I/O.
 */
class PerformanceReport
{
    /** A request over this many ms is "slow" (also the limit above which its slowest queries are logged). */
    public const SLOW_MS = 500;

    public const HEALTHY         = 'healthy';
    public const NO_DATA         = 'no_data';
    public const DB_SLOW_QUERIES = 'db_slow_queries';
    public const DB_MANY_QUERIES = 'db_many_queries';
    public const APP             = 'app';
    public const MIXED           = 'mixed';

    private const MANY_QUERIES = 50;

    /** The slow limit in ms: MAUTIC_PERF_SLOW_MS when set, else SLOW_MS. */
    public static function slowLimit(): int
    {
        $value = (int) getenv('MAUTIC_PERF_SLOW_MS');

        return $value > 0 ? $value : self::SLOW_MS;
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return array{total: array<string, mixed>, routes: list<array<string, mixed>>, slowest: list<array<string, mixed>>, verdict: string}
     */
    public function build(array $entries, int $slowMs = self::SLOW_MS): array
    {
        $total  = $this->stats($entries, $slowMs);
        $groups = [];

        foreach ($entries as $entry) {
            $groups[($entry['m'] ?? '?').' '.($entry['p'] ?? '?')][] = $entry;
        }

        $routes = [];
        foreach ($groups as $name => $group) {
            $routes[] = ['name' => $name] + $this->stats($group, $slowMs);
        }
        usort($routes, static fn (array $a, array $b): int => $b['p95'] <=> $a['p95']);

        $slowest = $entries;
        usort($slowest, static fn (array $a, array $b): int => ($b['ms'] ?? 0) <=> ($a['ms'] ?? 0));

        return [
            'total'   => $total,
            'routes'  => $routes,
            'slowest' => array_slice($slowest, 0, 20),
            'verdict' => $this->verdict($total, $slowMs),
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return array<string, mixed>
     */
    public function stats(array $entries, int $slowMs = self::SLOW_MS): array
    {
        $count = count($entries);

        if (0 === $count) {
            return ['count' => 0, 'avg' => 0.0, 'p95' => 0.0, 'max' => 0.0, 'avgDb' => 0.0, 'avgQueries' => 0.0, 'dbShare' => 0.0, 'slow' => 0, 'errors' => 0];
        }

        $times = array_map(static fn (array $e): float => (float) ($e['ms'] ?? 0), $entries);
        sort($times);
        $sumMs = array_sum($times);
        $sumDb = array_sum(array_map(static fn (array $e): float => (float) ($e['db'] ?? 0), $entries));
        $sumQ  = array_sum(array_map(static fn (array $e): int => (int) ($e['q'] ?? 0), $entries));

        return [
            'count'      => $count,
            'avg'        => round($sumMs / $count, 1),
            'p95'        => round($times[(int) max(0, ceil(0.95 * $count) - 1)], 1),
            'max'        => round(end($times), 1),
            'avgDb'      => round($sumDb / $count, 1),
            'avgQueries' => round($sumQ / $count, 1),
            'dbShare'    => $sumMs > 0 ? round($sumDb / $sumMs, 2) : 0.0,
            'slow'       => count(array_filter($times, static fn (float $t): bool => $t >= $slowMs)),
            'errors'     => count(array_filter($entries, static fn (array $e): bool => (int) ($e['s'] ?? 200) >= 500)),
        ];
    }

    /**
     * @param array<string, mixed> $total as stats() returns it
     */
    public function verdict(array $total, int $slowMs = self::SLOW_MS): string
    {
        if (0 === $total['count']) {
            return self::NO_DATA;
        }

        if ($total['p95'] < $slowMs) {
            return self::HEALTHY;
        }

        if ($total['dbShare'] >= 0.6) {
            return $total['avgQueries'] >= self::MANY_QUERIES ? self::DB_MANY_QUERIES : self::DB_SLOW_QUERIES;
        }

        return $total['dbShare'] <= 0.3 ? self::APP : self::MIXED;
    }
}
