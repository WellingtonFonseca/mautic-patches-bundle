# Patches: performance monitor

The Server-Timing header, the Performance screen and the `mautic:performance:report` / `mautic:performance:diagnose` commands.

## Performance monitor (API timing, Performance screen, diagnose)

Answers "is it the database, Mautic, or the server?" without console access. It exists because a Custom Object GET once took 2 s for 15 rows in prod.

```json
{
  "measures": "every /api/ request (ApiTimingSubscriber): total ms, ms inside SQL (QueryTimer, plugged into the DBAL connection; the old logger keeps working), number of queries, peak memory. Only the SQL text of the 3 slowest queries is kept, and only for slow requests; query parameters are never stored (no contact data).",
  "header": "every API answer carries Server-Timing: db;dur=..., app;dur=..., total;dur=..., queries;desc=\"N\". db = SQL, app = total minus SQL. Visible in the browser dev tools (Network > Timing) or with curl -D -.",
  "log": "var/logs/performance/api-YYYY-MM-DD.log, one JSON line per request, 7 days kept (older files deleted when a new day starts). A plain file on purpose: no table, and it still works when the database is the slow part.",
  "screen": "Admin menu (gear) > Performance (admin users only). Period filter (1h/6h/24h/7d), a verdict, totals, one row per route (slowest first), the 20 slowest requests with their heaviest SQL, and a 'Run diagnostics' button (a plain GET of the same page with ?diagnose=1, rendered on the server: a JavaScript + POST version shows nothing, because Mautic's ajax POSTs need the CSRF token).",
  "commands": {
    "mautic:performance:report [--hours=24]": "same numbers as the screen, in the terminal",
    "mautic:performance:diagnose": "timed probes; exit code 1 if one fails. Also what the screen button runs."
  },
  "env": {"MAUTIC_PERF_SLOW_MS": "slow limit in ms (default 500)", "MAUTIC_PERF_DISABLED": "1 turns the measuring off"}
}
```

**Verdict rules** (`PerformanceReport::verdict`): p95 under the slow limit = healthy. Otherwise, SQL share >= 60% of the time: 50+ queries per request = "too many queries" (one query per row, gets worse as data grows), else "slow queries" (missing index, full scan, small DB). SQL share <= 30% = "application or server" (CPU, PHP workers, opcache). In between = mixed.

**Diagnostics** (`Service/Performance/Diagnostics`): DB round trip (SELECT 1 x5; over 5 ms warn, 50 fail), server load vs CPUs, MySQL connections / buffer pool vs data size / slow log (info when the DB user cannot read them), the Custom Object listing queries timed and EXPLAINed (full scan of a big table = warn), the 5 biggest tables, PHP info. Read-only.

**Tests**: `Tests/Unit/Service/Performance/*`, `Tests/Unit/EventListener/ApiTimingSubscriberTest`, `Tests/Unit/Config/PerformanceConfigTest` (menu, routes, same keys in en_US and pt_BR).

**Baseline**: even a 404 takes about 410 ms in `app` on the dev box (Symfony boot), so compare prod against its own baseline, not against dev numbers.

**Request shape in the log**: each line also keeps `qs` (the NAMES of the query parameters, never the values: they can be personal data like a student id) and `kb` (answer size). The slowest-requests table shows them, so a call that returns thousands of items shows up as a big answer. Example: a prod `GET .../items` doing 7174 queries per call (fixed, see [../mc-cs-plugin-custom-objects-5/wiki/custom-objects-plugin.md](../../mc-cs-plugin-custom-objects-5/wiki/custom-objects-plugin.md)).
