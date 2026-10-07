<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\Performance;

use Doctrine\DBAL\Connection;

/**
 * Timed probes that tell database, server and Mautic apart. Each check is
 * {key, status: ok|warn|fail|info, value: text, hint: translation key or
 * null}. A probe that cannot run (no permission, missing table) reports
 * 'info' instead of failing the whole run.
 */
class Diagnostics
{
    public const OK   = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';
    public const INFO = 'info';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{key: string, status: string, value: string, hint: ?string}>
     */
    public function run(): array
    {
        return array_merge(
            $this->databasePing(),
            $this->serverLoad(),
            $this->mysqlSettings(),
            $this->customObjectQueries(),
            $this->biggestTables(),
            [$this->check('php', self::INFO, sprintf('PHP %s, memory_limit %s, opcache %s', PHP_VERSION, ini_get('memory_limit'), ini_get('opcache.enable') ? 'on' : 'off'), null)]
        );
    }

    /**
     * A trivial query, five times: the round trip to the database. On the same
     * network it should be about 1 ms; tens of ms mean a network or an
     * overloaded database server, whatever the query.
     *
     * @return list<array{key: string, status: string, value: string, hint: ?string}>
     */
    private function databasePing(): array
    {
        $times = [];
        try {
            for ($i = 0; $i < 5; ++$i) {
                $start = microtime(true);
                $this->connection->executeQuery('SELECT 1')->fetchOne();
                $times[] = (microtime(true) - $start) * 1000;
            }
        } catch (\Throwable $e) {
            return [$this->check('db_ping', self::FAIL, $e->getMessage(), 'mautic.patches.perf.hint.db_ping')];
        }

        $avg    = array_sum($times) / count($times);
        $status = $avg > 50 ? self::FAIL : ($avg > 5 ? self::WARN : self::OK);

        return [$this->check('db_ping', $status, sprintf('%.2f ms', $avg), self::OK === $status ? null : 'mautic.patches.perf.hint.db_ping')];
    }

    /**
     * @return list<array{key: string, status: string, value: string, hint: ?string}>
     */
    private function serverLoad(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        if (false === $load) {
            return [];
        }

        $cpus   = max(1, (int) preg_match_all('/^processor/m', (string) @file_get_contents('/proc/cpuinfo')));
        $ratio  = $load[0] / $cpus;
        $status = $ratio > 1.5 ? self::FAIL : ($ratio > 0.8 ? self::WARN : self::OK);

        return [$this->check('load', $status, sprintf('%.2f / %.2f / %.2f (%d CPU)', $load[0], $load[1], $load[2], $cpus), self::OK === $status ? null : 'mautic.patches.perf.hint.load')];
    }

    /**
     * @return list<array{key: string, status: string, value: string, hint: ?string}>
     */
    private function mysqlSettings(): array
    {
        try {
            $vars   = $this->pairs("SHOW GLOBAL VARIABLES WHERE Variable_name IN ('max_connections','innodb_buffer_pool_size','slow_query_log','long_query_time')");
            $status = $this->pairs("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Slow_queries')");
        } catch (\Throwable) {
            return [$this->check('mysql', self::INFO, '-', 'mautic.patches.perf.hint.mysql_denied')];
        }

        $max       = (int) ($vars['max_connections'] ?? 0);
        $connected = (int) ($status['Threads_connected'] ?? 0);
        $out       = [];

        $out[] = $this->check(
            'connections',
            $max > 0 && $connected / $max > 0.8 ? self::WARN : self::OK,
            sprintf('%d / %d (running: %d)', $connected, $max, (int) ($status['Threads_running'] ?? 0)),
            $max > 0 && $connected / $max > 0.8 ? 'mautic.patches.perf.hint.connections' : null
        );

        $pool = (int) ($vars['innodb_buffer_pool_size'] ?? 0);
        $data = $this->dataSize();
        $out[] = $this->check(
            'buffer_pool',
            null !== $data && $pool > 0 && $pool < $data ? self::WARN : self::INFO,
            sprintf('buffer pool %s, data+indexes %s', $this->bytes($pool), null === $data ? '-' : $this->bytes($data)),
            null !== $data && $pool > 0 && $pool < $data ? 'mautic.patches.perf.hint.buffer_pool' : null
        );

        $slowLog = in_array(strtoupper((string) ($vars['slow_query_log'] ?? '')), ['ON', '1'], true);
        $out[]   = $this->check(
            'slow_log',
            $slowLog ? self::OK : self::INFO,
            sprintf('slow_query_log %s (long_query_time %ss), %d slow queries since start', $slowLog ? 'ON' : 'OFF', $vars['long_query_time'] ?? '?', (int) ($status['Slow_queries'] ?? 0)),
            $slowLog ? null : 'mautic.patches.perf.hint.slow_log'
        );

        return $out;
    }

    /**
     * The Custom Object listing's own shape (items of one object, 15 per page)
     * timed and EXPLAINed: a full scan of a big table is flagged.
     *
     * @return list<array{key: string, status: string, value: string, hint: ?string}>
     */
    private function customObjectQueries(): array
    {
        $queries = [
            'co_items'  => 'SELECT * FROM custom_item WHERE custom_object_id = (SELECT MIN(id) FROM custom_object) ORDER BY id LIMIT 15',
            'co_values' => 'SELECT * FROM custom_field_value_text WHERE custom_item_id IN (SELECT id FROM (SELECT id FROM custom_item ORDER BY id LIMIT 15) t)',
        ];
        $out = [];

        foreach ($queries as $key => $sql) {
            try {
                $start = microtime(true);
                $this->connection->executeQuery($sql)->fetchAllAssociative();
                $ms = (microtime(true) - $start) * 1000;

                $scan = false;
                foreach ($this->connection->executeQuery('EXPLAIN '.$sql)->fetchAllAssociative() as $row) {
                    if ('ALL' === ($row['type'] ?? '') && (int) ($row['rows'] ?? 0) > 1000) {
                        $scan = true;
                    }
                }
            } catch (\Throwable $e) {
                $out[] = $this->check($key, self::INFO, '-', null);
                continue;
            }

            $status = $scan ? self::WARN : ($ms > 200 ? self::WARN : self::OK);
            $out[]  = $this->check($key, $status, sprintf('%.1f ms%s', $ms, $scan ? ' (full table scan)' : ''), self::OK === $status ? null : 'mautic.patches.perf.hint.scan');
        }

        return $out;
    }

    /**
     * @return list<array{key: string, status: string, value: string, hint: ?string}>
     */
    private function biggestTables(): array
    {
        try {
            $rows = $this->connection->executeQuery(
                'SELECT table_name AS name, table_rows AS num, ROUND((data_length + index_length) / 1048576, 1) AS mb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY (data_length + index_length) DESC LIMIT 5'
            )->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }

        return [$this->check('tables', self::INFO, implode('; ', array_map(static fn (array $r): string => sprintf('%s: %s MB (~%s rows)', $r['name'] ?? $r['NAME'] ?? '?', $r['mb'] ?? $r['MB'] ?? '?', $r['num'] ?? $r['NUM'] ?? '?'), $rows)), null)];
    }

    private function dataSize(): ?int
    {
        try {
            $size = $this->connection->executeQuery('SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchOne();
        } catch (\Throwable) {
            return null;
        }

        return false === $size || null === $size ? null : (int) $size;
    }

    /**
     * @return array<string, string>
     */
    private function pairs(string $sql): array
    {
        $out = [];
        foreach ($this->connection->executeQuery($sql)->fetchAllAssociative() as $row) {
            $out[(string) ($row['Variable_name'] ?? $row['VARIABLE_NAME'])] = (string) ($row['Value'] ?? $row['VALUE']);
        }

        return $out;
    }

    private function bytes(int $bytes): string
    {
        return $bytes >= 1073741824 ? sprintf('%.1f GB', $bytes / 1073741824) : sprintf('%.0f MB', $bytes / 1048576);
    }

    /**
     * @return array{key: string, status: string, value: string, hint: ?string}
     */
    private function check(string $key, string $status, string $value, ?string $hint): array
    {
        return ['key' => $key, 'status' => $status, 'value' => $value, 'hint' => $hint];
    }
}
