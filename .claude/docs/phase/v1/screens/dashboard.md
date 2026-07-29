# Screen — Dashboard

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/` · **Nav** primary · **Phase** 5 · **Module**
`resources/admin/modules/dashboard/`

---

## Purpose

The landing screen. Basic analytics, nothing more.

Built last, because every number it shows depends on calendars, events and RSVPs already
existing.

## Reference

`fluent-affiliate/resources/admin/Modules/Dashboard/Dashboard.vue` — the primary design
reference for the whole SPA, and the closest match for this screen.

Backend: `fluent-affiliate/app/Services/Reports/ReportService.php`.

## Layout

An `el-row` split **16 / 8** — stat cards and the growth chart in the main column, recent
RSVPs in the right rail. Tiles are an `el-row` of `<el-col :sm="8" :xs="12" :md="6">`.

Ship a `DashboardSkeleton.vue` alongside, as affiliate does. This is the first paint after
opening the plugin; a blank screen reads as broken.

## Content

| Block | Content |
|---|---|
| Stat tiles | Total calendars · Total events · Total guests |
| Growth overview | Time series — calendars, events and guests created over a date range |
| Recent RSVPs | Latest responses: event, name, going / not going |

No revenue, no upcoming-event widget, no activity log.

## Two patterns worth copying exactly

**Tiles are server-shaped.** Affiliate's component iterates a `stats` object whose entries
carry `{icon, title, amount, is_number}`. Adding or reordering a tile becomes a backend
change with no Vue edit. Drop its `is_currency` flag — v1 has no money.

**The chart takes an endpoint as a prop** —
`<AffiliateGrowth :section-title="…" :end-point="'reports/dashboard-chart-stats'" />`.
Ours becomes an `EventGrowth` equivalent pointed at our own route, so the chart component
stays reusable instead of hardwired to this screen.

## Endpoints — three, not one

Both siblings split these, and the reason is the date picker: changing the range should
refetch the chart alone, not the tiles and the list too.

```php
$router->prefix('reports')->withPolicy('ReportPolicy')->group(function ($router) {
    $router->get('/', 'ReportController@getReports');                   // tiles
    $router->get('/graph-reports', 'ReportController@getGraphReports'); // series
    $router->get('/recent-rsvps', 'ReportController@getRecentRsvps');   // list
});
```

## Series generation is already solved upstream

`ReportService::getMultiChartStatistics(array $seriesConfigs, $startDate, $endDate,
$field = 'created_at')` buckets any model by a date column. Our growth overview is that,
called over `Calendar`, `Event` and `Guest`. Read it before writing a series generator from
scratch.

## Counting rules to fix before building

- **"Total guests" must define itself.** RSVP has two answers, so a tile counting every
  `guests` row counts the people who said *no*. Either the tile means "going" and filters
  on it, or it is relabelled "Total RSVPs" and shows both. A number nobody can interpret is
  worse than no tile.
- Tiles are `COUNT(*)`, cheap at v1 scale — no caching layer yet, but they must not become
  a per-row N+1 behind a model accessor.
- The chart needs an explicit default range (last 30 days) and a granularity. Daily buckets
  over a year is 365 points and an unreadable axis.
- **Draft and deleted events must be excluded consistently** across tile, chart and list,
  and must agree with what [events.md](events.md) shows. Three different numbers for the
  same thing reads as a bug.
