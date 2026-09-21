# Metrics

MeetAgain can push runtime metrics to a [VictoriaMetrics](https://victoriametrics.com/) collector and
show them in Grafana: response times and memory per route, database queries per request, cron task
durations, queue backlogs, and Valkey, MariaDB and OPcache health. Deploys show up as markers, so a
slowdown after a release is visible at a glance.

Metrics are **off by default** and cost nothing while off. They switch on with one environment
variable, `METRICS_DSN`.

## Try it locally

The collector and Grafana are part of the dev compose file, but `just start` does not start them.

1. Start them:

    ```bash
    just dockerMetricsStart
    ```

2. Uncomment the variable from `.env.dist` into your `.env.local`:

    ```bash
    METRICS_DSN=udp://victoriametrics:8089?app=meetagain
    ```

3. Browse the site and run `just app app:cron` once or twice, then open
   [http://localhost:3001](http://localhost:3001). The MeetAgain dashboard opens as the home page.

`just dockerMetricsStop` stops both services and keeps their data. `just stop` stops them too.

VictoriaMetrics hides the most recent 30 seconds from queries, so give new data half a minute before
it appears.

## How it works

- Points are sent as InfluxDB line protocol over UDP after the response has gone to the browser. If
  the collector is down, the points are lost and nothing else happens: no error, no log line, no
  slower page.
- The `app` parameter of the DSN tags every point, so several sites can share one collector.
- Only route names, status classes and numbers are sent. URLs, query strings, IPs and user ids never
  are.
- The dashboard lives in `docker/metrics/grafana/dashboards/meetagain.json`. To change it, edit it in
  Grafana, export the JSON, and commit the file.

## Adding a gauge

Gauges are collected on every cron tick while metrics are enabled. Implement
`App\Metrics\GaugeInterface` and yield `App\Metrics\Point`s. The interface is auto-tagged, so no
configuration is needed, and plugins can add gauges the same way. A gauge that throws is skipped for
that tick and reported in the cron log. `src/Metrics/Gauge/EmailQueueGauge.php` is a short example.

A point's measurement and field names become the metric name in VictoriaMetrics: `new Point('email_queue',
['pending' => 3])` is queried as `email_queue_pending`. Keep tag values few and bounded - a tag that
takes a new value per request or per user creates a new series each time.

## Production

Run VictoriaMetrics wherever suits your setup, with `-influxListenAddr=:8089` so it accepts the UDP
stream. Set `METRICS_DSN` to its address and point Grafana at it as a Prometheus data source. Call
`bin/console app:metrics:deploy-marker --rev=<revision>` at the end of your deploy to get deploy
markers.
