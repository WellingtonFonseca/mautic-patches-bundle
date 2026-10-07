<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\Performance;

/**
 * One line of JSON per API request, one file per day, in var/logs/performance/.
 * Files older than RETENTION_DAYS are deleted when the first line of a day is
 * written. A plain file on purpose: no table to install, and it keeps working
 * when the database is the thing that is slow.
 *
 * A line: {t: unix time, m: method, r: route name, p: path with ids as :id,
 * s: HTTP status, ms: total ms, db: ms spent in SQL, q: number of queries,
 * mem: peak MB, slow: [{ms, sql}] (only for requests over the slow limit)}.
 */
class PerformanceLog
{
    public const RETENTION_DAYS = 7;

    public function __construct(private string $dir)
    {
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function write(array $entry): void
    {
        $file = $this->fileFor((int) ($entry['t'] ?? time()));

        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }

        $firstOfDay = !is_file($file);
        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);

        if ($firstOfDay) {
            $this->purge();
        }
    }

    /**
     * Every entry from $since (unix time) on, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function read(int $since): array
    {
        $entries = [];

        for ($day = strtotime('midnight', $since); $day <= time(); $day += 86400) {
            $file = $this->fileFor($day);

            if (!is_file($file)) {
                continue;
            }

            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $entry = json_decode($line, true);

                if (is_array($entry) && ($entry['t'] ?? 0) >= $since) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    private function fileFor(int $time): string
    {
        return $this->dir.'/api-'.date('Y-m-d', $time).'.log';
    }

    private function purge(): void
    {
        $limit = strtotime('-'.self::RETENTION_DAYS.' days midnight');

        foreach (glob($this->dir.'/api-*.log') ?: [] as $file) {
            if (filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }
}
