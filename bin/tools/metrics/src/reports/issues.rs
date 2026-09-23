use super::{finish, probes, Window, MATCHED};
use crate::client::{by_label, single};
use crate::table::{ms, num, pct};
use crate::time;
use crate::{Context, Thresholds};
use std::collections::BTreeMap;

#[derive(Debug, PartialEq, PartialOrd, Eq, Ord, Clone, Copy)]
pub enum Severity {
    Error,
    Warn,
}

#[derive(Debug, PartialEq)]
pub struct Issue {
    pub severity: Severity,
    pub text: String,
}

#[derive(Default)]
pub struct Signals {
    pub route_p95: BTreeMap<String, f64>,
    pub route_count: BTreeMap<String, f64>,
    pub errors: BTreeMap<String, f64>,
    pub cron_not_ok: Vec<(String, String, f64)>,
    pub email_failed: Option<f64>,
    pub email_oldest: Option<f64>,
    pub opcache_restarts: Option<f64>,
    pub opcache_hit_min: Option<f64>,
    pub opcache_wasted_ratio: Option<f64>,
    pub valkey_evicted: Option<f64>,
    pub valkey_hit: Option<f64>,
    pub slow_queries: Option<f64>,
    pub buffer_pool: Option<f64>,
    pub memory_ratio: Option<f64>,
    pub dropped_series: Option<f64>,
    pub cron_age: Option<i64>,
    pub http_age: Option<i64>,
    pub unstopped_spikes: Vec<(i64, f64)>,
}

const OLDEST_EMAIL_SECONDS: f64 = 900.0;

pub fn evaluate(s: &Signals, t: &Thresholds) -> Vec<Issue> {
    let mut issues = Vec::new();
    let mut add = |severity, text: String| issues.push(Issue { severity, text });

    match s.cron_age {
        None => add(
            Severity::Error,
            "no cron_run sample in 7 days - cron or the metrics path is down".into(),
        ),
        Some(age) if age > t.stale_cron_minutes * 60 => add(
            Severity::Error,
            format!(
                "last cron_run sample is {} old - cron or the metrics path is down",
                time::format_age(age)
            ),
        ),
        _ => {}
    }
    if let Some(age) = s.http_age.filter(|a| *a > t.stale_http_minutes * 60) {
        add(
            Severity::Warn,
            format!("last http_request sample is {} old", time::format_age(age)),
        );
    }
    if let Some(dropped) = s.dropped_series.filter(|d| *d > 0.0) {
        add(
            Severity::Error,
            format!(
                "{} series samples dropped by the cardinality limit - a label is leaking ids",
                num(dropped)
            ),
        );
    }
    for (route, count) in &s.errors {
        add(
            Severity::Error,
            format!("{} server errors (5xx) on {}", num(*count), route),
        );
    }
    for (task, status, count) in &s.cron_not_ok {
        let severity = if status == "warning" {
            Severity::Warn
        } else {
            Severity::Error
        };
        add(
            severity,
            format!("cron task {} ended {} {}x", task, status, num(*count)),
        );
    }
    if let Some(failed) = s.email_failed.filter(|f| *f > 0.0) {
        add(
            Severity::Error,
            format!("{} emails failed in the last 24h", num(failed)),
        );
    }
    if let Some(oldest) = s.email_oldest.filter(|o| *o > OLDEST_EMAIL_SECONDS) {
        add(
            Severity::Warn,
            format!(
                "an email waited {} in the queue",
                time::format_age(oldest as i64)
            ),
        );
    }

    let mut slow: Vec<(&String, &f64)> = s
        .route_p95
        .iter()
        .filter(|(route, p95)| {
            **p95 > t.slow_route_ms
                && s.route_count.get(*route).copied().unwrap_or(0.0) >= t.slow_route_min_hits
        })
        .collect();
    slow.sort_by(|a, b| b.1.total_cmp(a.1));
    for (route, p95) in slow {
        add(
            Severity::Warn,
            format!(
                "slow route {}: p95 {} ms over {} hits",
                route,
                ms(*p95),
                num(s.route_count[route])
            ),
        );
    }

    let bursts = bursts(&s.unstopped_spikes);
    if let Some(largest) = bursts.iter().max_by(|a, b| a.2.total_cmp(&b.2)) {
        let hours: i64 = bursts
            .iter()
            .map(|(from, to, _)| (to - from) / 3600 + 1)
            .sum();
        let unmatched: f64 = bursts.iter().map(|b| b.2).sum();
        add(
            Severity::Warn,
            format!(
                "unmatched-request spikes no block stopped: {} bursts over {} hours, {} requests; largest {} - {} with {}",
                bursts.len(),
                hours,
                num(unmatched),
                time::format_short(largest.0),
                time::format_short(largest.1 + 3600)[6..].to_string(),
                num(largest.2)
            ),
        );
    }
    if let Some(restarts) = s.opcache_restarts.filter(|r| *r > 0.0) {
        add(
            Severity::Warn,
            format!(
                "OPcache restarted {} times - memory or key limit too small",
                num(restarts)
            ),
        );
    }
    if let Some(hit) = s.opcache_hit_min.filter(|h| *h < 95.0) {
        add(
            Severity::Warn,
            format!("OPcache hit rate fell to {}%", num(hit)),
        );
    }
    if let Some(wasted) = s.opcache_wasted_ratio.filter(|w| *w > 0.1) {
        add(
            Severity::Warn,
            format!("OPcache wasted memory reached {}", pct(wasted)),
        );
    }
    if let Some(evicted) = s.valkey_evicted.filter(|e| *e > 0.0) {
        add(
            Severity::Warn,
            format!("Valkey evicted {} keys - maxmemory reached", num(evicted)),
        );
    }
    if let Some(hit) = s.valkey_hit.filter(|h| *h < 0.8) {
        add(Severity::Warn, format!("Valkey hit ratio {}", pct(hit)));
    }
    if let Some(slow) = s.slow_queries.filter(|q| *q >= 1.0) {
        add(
            Severity::Warn,
            format!("{} slow database queries", num(slow)),
        );
    }
    if let Some(ratio) = s.buffer_pool.filter(|r| *r < 0.99) {
        add(
            Severity::Warn,
            format!(
                "InnoDB buffer pool hit ratio {:.2}% - the working set outgrew it",
                ratio * 100.0
            ),
        );
    }
    if let Some(ratio) = s.memory_ratio.filter(|r| *r > 0.85) {
        add(
            Severity::Warn,
            format!("container memory peaked at {} of its limit", pct(ratio)),
        );
    }

    issues.sort_by_key(|i| i.severity);
    issues
}

pub fn bursts(spikes: &[(i64, f64)]) -> Vec<(i64, i64, f64)> {
    let mut bursts: Vec<(i64, i64, f64)> = Vec::new();
    for (start, unmatched) in spikes {
        match bursts.last_mut() {
            Some(last) if last.1 + 3600 == *start => {
                last.1 = *start;
                last.2 += unmatched;
            }
            _ => bursts.push((*start, *start, *unmatched)),
        }
    }
    bursts
}

pub fn run(ctx: &Context, since: &str) -> Result<String, String> {
    let window = Window::since(ctx, since)?;
    let matched = format!("{{$S,{}}}", MATCHED);
    let queries: Vec<(&str, String)> = vec![
        ("route_p95", format!("histogram_quantile(0.95, sum by (route, vmrange) (histogram_over_time(http_request_duration_ms{}[$W])))", matched)),
        ("route_count", format!("sum by (route) (count_over_time(http_request_duration_ms{}[$W]))", matched)),
        ("errors", "sum by (route) (count_over_time(http_request_duration_ms{$S,status=\"5xx\"}[$W]))".into()),
        ("cron_not_ok", "sum by (task, status) (count_over_time(cron_task_duration_ms{$S,status!=\"ok\"}[$W]))".into()),
        ("email_failed", "max(last_over_time(email_queue_failed_24h{$S}[$W]))".into()),
        ("email_oldest", "max(max_over_time(email_queue_oldest_pending_age_s{$S}[$W]))".into()),
        ("opcache_restarts", "sum(increase(php_runtime_opcache_restarts{$S}[$W]))".into()),
        ("opcache_hit", "min(min_over_time(php_runtime_opcache_hit_rate{$S}[$W]))".into()),
        ("opcache_wasted", "max(max_over_time((php_runtime_opcache_wasted_mb{$S} / (php_runtime_opcache_used_mb{$S} + php_runtime_opcache_free_mb{$S} + php_runtime_opcache_wasted_mb{$S}))[$W:]))".into()),
        ("valkey_evicted", "sum(increase(valkey_evicted_keys{$S}[$W]))".into()),
        ("valkey_hit", "sum(increase(valkey_keyspace_hits{$S}[$W])) / (sum(increase(valkey_keyspace_hits{$S}[$W])) + sum(increase(valkey_keyspace_misses{$S}[$W])))".into()),
        ("slow", "sum(increase(database_slow_queries{$S}[$W]))".into()),
        ("buffer_pool", "1 - sum(increase(database_innodb_buffer_pool_reads{$S}[$W])) / sum(increase(database_innodb_buffer_pool_read_requests{$S}[$W]))".into()),
        ("memory", "max(max_over_time(container_memory_used_mb{$S}[$W]))".into()),
        ("memory_limit", "max(max_over_time(container_memory_limit_mb{$S}[$W]))".into()),
        ("dropped", "sum(increase(vm_hourly_series_limit_rows_dropped_total[$W]))".into()),
        ("cron_last", "max(tlast_over_time(cron_run_duration_ms{$S}[7d]))".into()),
        ("http_last", "max(tlast_over_time(http_request_duration_ms{$S}[7d]))".into()),
    ];
    let queries: Vec<(&str, String)> = queries
        .into_iter()
        .map(|(k, q)| (k, ctx.q(&q, window.span())))
        .collect();
    let r = ctx.client.instant_many(&queries, window.end)?;
    let v = |k: &str| single(&r[k]);
    let probes = probes::fetch(ctx, &window)?;

    let signals = Signals {
        route_p95: by_label(&r["route_p95"], "route"),
        route_count: by_label(&r["route_count"], "route"),
        errors: by_label(&r["errors"], "route"),
        cron_not_ok: r["cron_not_ok"]
            .iter()
            .map(|s| {
                (
                    s.labels.get("task").cloned().unwrap_or_default(),
                    s.labels.get("status").cloned().unwrap_or_default(),
                    s.value,
                )
            })
            .collect(),
        email_failed: v("email_failed"),
        email_oldest: v("email_oldest"),
        opcache_restarts: v("opcache_restarts"),
        opcache_hit_min: v("opcache_hit"),
        opcache_wasted_ratio: v("opcache_wasted"),
        valkey_evicted: v("valkey_evicted"),
        valkey_hit: v("valkey_hit"),
        slow_queries: v("slow"),
        buffer_pool: v("buffer_pool"),
        memory_ratio: match (v("memory"), v("memory_limit")) {
            (Some(used), Some(limit)) if limit > 0.0 => Some(used / limit),
            _ => None,
        },
        dropped_series: v("dropped"),
        cron_age: v("cron_last").map(|t| ctx.now - t as i64),
        http_age: v("http_last").map(|t| ctx.now - t as i64),
        unstopped_spikes: probes
            .spikes
            .iter()
            .filter(|s| !s.stopped)
            .map(|s| (s.start, s.unmatched))
            .collect(),
    };

    let issues = evaluate(&signals, &ctx.thresholds);
    let mut out = String::new();
    if issues.is_empty() {
        out.push_str("no issues\n");
    }
    for issue in &issues {
        let label = match issue.severity {
            Severity::Error => "ERROR",
            Severity::Warn => "warn ",
        };
        out.push_str(&format!("{} {}\n", label, issue.text));
    }
    Ok(finish(ctx, "issues", &window, out))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn healthy() -> Signals {
        Signals {
            cron_age: Some(60),
            http_age: Some(30),
            buffer_pool: Some(0.999),
            valkey_hit: Some(0.95),
            ..Signals::default()
        }
    }

    #[test]
    fn a_healthy_system_has_no_issues() {
        assert!(evaluate(&healthy(), &Thresholds::default()).is_empty());
    }

    #[test]
    fn a_silent_cron_is_an_error() {
        let signals = Signals {
            cron_age: Some(3600),
            ..healthy()
        };
        let issues = evaluate(&signals, &Thresholds::default());
        assert_eq!(issues[0].severity, Severity::Error);
        assert!(issues[0].text.contains("60m old"));
    }

    #[test]
    fn slow_routes_need_enough_hits() {
        let mut signals = healthy();
        signals.route_p95 = [("busy".to_string(), 6852.0), ("rare".to_string(), 9000.0)].into();
        signals.route_count = [("busy".to_string(), 40.0), ("rare".to_string(), 2.0)].into();
        let issues = evaluate(&signals, &Thresholds::default());
        assert_eq!(issues.len(), 1);
        assert_eq!(issues[0].text, "slow route busy: p95 6852 ms over 40 hits");
    }

    #[test]
    fn errors_sort_before_warnings() {
        let mut signals = healthy();
        signals.valkey_evicted = Some(12.0);
        signals.errors = [("app_home".to_string(), 3.0)].into();
        let issues = evaluate(&signals, &Thresholds::default());
        assert_eq!(issues[0].severity, Severity::Error);
        assert_eq!(issues[1].severity, Severity::Warn);
    }

    #[test]
    fn a_cron_warning_is_only_a_warning() {
        let mut signals = healthy();
        signals.cron_not_ok = vec![
            ("cleanup".into(), "warning".into(), 2.0),
            ("mail".into(), "exception".into(), 1.0),
        ];
        let issues = evaluate(&signals, &Thresholds::default());
        assert_eq!(issues[0].text, "cron task mail ended exception 1x");
        assert_eq!(issues[1].severity, Severity::Warn);
    }

    #[test]
    fn consecutive_spike_hours_form_one_burst() {
        let spikes = vec![(0, 100.0), (3600, 200.0), (10_800, 50.0)];
        assert_eq!(
            bursts(&spikes),
            vec![(0, 3600, 300.0), (10_800, 10_800, 50.0)]
        );
    }

    #[test]
    fn unstopped_spikes_are_one_line() {
        let mut signals = healthy();
        signals.unstopped_spikes = vec![(0, 100.0), (3600, 200.0), (10_800, 50.0)];
        let issues = evaluate(&signals, &Thresholds::default());
        assert_eq!(issues.len(), 1);
        assert_eq!(
            issues[0].text,
            "unmatched-request spikes no block stopped: 2 bursts over 3 hours, 350 requests; largest 01-01 00:00 - 02:00 with 300"
        );
    }
}
