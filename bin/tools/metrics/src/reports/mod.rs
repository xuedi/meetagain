pub mod daily;
pub mod deploy;
pub mod issues;
pub mod performance;
pub mod probes;

use crate::client::by_label;
use crate::time;
use crate::{Context, Thresholds};
use std::collections::BTreeMap;

pub const MATCHED: &str = "route!~\"_none|_blocked\"";

pub struct Window {
    pub start: i64,
    pub end: i64,
}

impl Window {
    pub fn since(ctx: &Context, since: &str) -> Result<Self, String> {
        let start = time::parse_point(since, ctx.now)?;
        let end = ctx.end();
        if start >= end {
            return Err(format!("'{}' leaves an empty window", since));
        }
        Ok(Self { start, end })
    }

    pub fn span(&self) -> i64 {
        self.end - self.start
    }
}

pub fn finish(ctx: &Context, title: &str, window: &Window, body: String) -> String {
    format!(
        "{}  ({} queries)\n{}",
        ctx.header(title, window.start, window.end),
        ctx.client.query_count(),
        body
    )
}

#[derive(Debug, Clone, Default, PartialEq)]
pub struct Stats {
    pub key: String,
    pub count: f64,
    pub total_ms: f64,
    pub p50: Option<f64>,
    pub p95: Option<f64>,
    pub p99: Option<f64>,
    pub max: Option<f64>,
    pub db_total_ms: f64,
    pub queries_p95: Option<f64>,
    pub memory_p95: Option<f64>,
}

impl Stats {
    pub fn db_share(&self) -> Option<f64> {
        (self.total_ms > 0.0).then(|| (self.db_total_ms / self.total_ms).min(1.0))
    }
}

fn quantile(metric: &str, q: f64, by: &str, matcher: &str) -> String {
    format!(
        "histogram_quantile({}, sum by ({}, vmrange) (histogram_over_time({}{{$S,{}}}[$W])))",
        q, by, metric, matcher
    )
}

pub fn group_stats(
    ctx: &Context,
    at: i64,
    window: i64,
    matcher: &str,
    by: &str,
) -> Result<Vec<Stats>, String> {
    let d = "http_request_duration_ms";
    let queries: Vec<(&str, String)> = vec![
        (
            "count",
            format!(
                "sum by ({}) (count_over_time({}{{$S,{}}}[$W]))",
                by, d, matcher
            ),
        ),
        (
            "total",
            format!(
                "sum by ({}) (sum_over_time({}{{$S,{}}}[$W]))",
                by, d, matcher
            ),
        ),
        ("p50", quantile(d, 0.5, by, matcher)),
        ("p95", quantile(d, 0.95, by, matcher)),
        ("p99", quantile(d, 0.99, by, matcher)),
        (
            "max",
            format!(
                "max by ({}) (max_over_time({}{{$S,{}}}[$W]))",
                by, d, matcher
            ),
        ),
        (
            "db",
            format!(
                "sum by ({}) (sum_over_time(http_request_db_ms{{$S,{}}}[$W]))",
                by, matcher
            ),
        ),
        (
            "queries",
            quantile("http_request_db_queries", 0.95, by, matcher),
        ),
        (
            "memory",
            quantile("http_request_memory_peak_mb", 0.95, by, matcher),
        ),
    ];
    let queries: Vec<(&str, String)> = queries
        .into_iter()
        .map(|(k, q)| (k, ctx.q(&q, window)))
        .collect();
    let results = ctx.client.instant_many(&queries, at)?;
    let get = |key: &str| by_label(&results[key], by);

    let (count, total, p50, p95, p99, max, db, q95, mem) = (
        get("count"),
        get("total"),
        get("p50"),
        get("p95"),
        get("p99"),
        get("max"),
        get("db"),
        get("queries"),
        get("memory"),
    );
    let mut stats: Vec<Stats> = count
        .iter()
        .filter(|(_, c)| **c > 0.0)
        .map(|(key, c)| {
            let max = max.get(key).copied();
            let capped = |q: Option<f64>| match (q, max) {
                (Some(q), Some(max)) => Some(q.min(max)),
                (q, _) => q,
            };
            Stats {
                key: key.clone(),
                count: *c,
                total_ms: total.get(key).copied().unwrap_or(0.0),
                p50: capped(p50.get(key).copied()),
                p95: capped(p95.get(key).copied()),
                p99: capped(p99.get(key).copied()),
                max,
                db_total_ms: db.get(key).copied().unwrap_or(0.0),
                queries_p95: q95.get(key).copied(),
                memory_p95: mem.get(key).copied(),
            }
        })
        .collect();
    stats.sort_by(|a, b| b.total_ms.total_cmp(&a.total_ms));
    Ok(stats)
}

pub fn hints(stats: &Stats, t: &Thresholds) -> Vec<&'static str> {
    let mut hints = Vec::new();
    let db_share = stats.db_share().unwrap_or(0.0);
    let p95 = stats.p95.unwrap_or(0.0);
    if db_share > 0.5 {
        hints.push("db-bound");
    }
    if stats.queries_p95.is_some_and(|q| q > t.n_plus_one_queries) {
        hints.push("n+1?");
    }
    if stats.memory_p95.is_some_and(|m| m > t.high_memory_mb) {
        hints.push("memory");
    }
    if p95 >= t.php_bound_ms && db_share < 0.2 {
        hints.push("php-bound");
    }
    if let (Some(p50), Some(p99)) = (stats.p50, stats.p99) {
        if stats.count >= 20.0 && p50 > 0.0 && p99 > 5.0 * p50 {
            hints.push("tail");
        }
    }
    hints
}

pub fn duration(ms: f64) -> String {
    let seconds = ms / 1000.0;
    match seconds {
        s if s < 1.0 => format!("{:.0}ms", ms),
        s if s < 120.0 => format!("{:.1}s", s),
        s if s < 3600.0 => {
            let whole = s.round() as i64;
            format!("{}m{:02}s", whole / 60, whole % 60)
        }
        s => {
            let minutes = (s / 60.0).floor() as i64;
            format!("{}h{:02}m", minutes / 60, minutes % 60)
        }
    }
}

pub fn list(map: &BTreeMap<String, f64>, limit: usize) -> String {
    let mut items: Vec<(&String, &f64)> = map.iter().filter(|(_, v)| **v > 0.0).collect();
    items.sort_by(|a, b| b.1.total_cmp(a.1));
    let shown: Vec<String> = items
        .iter()
        .take(limit)
        .map(|(k, v)| format!("{} {}", k, crate::table::num(**v)))
        .collect();
    let mut text = shown.join(", ");
    if items.len() > limit {
        text.push_str(&format!(", +{} more", items.len() - limit));
    }
    text
}

#[cfg(test)]
mod tests {
    use super::*;

    fn stats(
        count: f64,
        total: f64,
        db: f64,
        p50: f64,
        p95: f64,
        p99: f64,
        queries: f64,
        memory: f64,
    ) -> Stats {
        Stats {
            key: "r".into(),
            count,
            total_ms: total,
            p50: Some(p50),
            p95: Some(p95),
            p99: Some(p99),
            max: Some(p99),
            db_total_ms: db,
            queries_p95: Some(queries),
            memory_p95: Some(memory),
        }
    }

    #[test]
    fn db_heavy_route_is_db_bound() {
        let s = stats(100.0, 10_000.0, 7_000.0, 90.0, 120.0, 150.0, 10.0, 20.0);
        assert_eq!(hints(&s, &Thresholds::default()), vec!["db-bound"]);
    }

    #[test]
    fn many_queries_and_memory_are_flagged() {
        let s = stats(10.0, 1000.0, 300.0, 90.0, 120.0, 150.0, 400.0, 128.0);
        assert_eq!(hints(&s, &Thresholds::default()), vec!["n+1?", "memory"]);
    }

    #[test]
    fn slow_route_without_db_time_is_php_bound_and_a_wide_spread_is_a_tail() {
        let s = stats(50.0, 200_000.0, 1_000.0, 500.0, 6_800.0, 7_000.0, 5.0, 30.0);
        assert_eq!(hints(&s, &Thresholds::default()), vec!["php-bound", "tail"]);
    }

    #[test]
    fn a_tail_needs_enough_requests() {
        let s = stats(3.0, 2_000.0, 100.0, 10.0, 90.0, 200.0, 5.0, 30.0);
        assert!(hints(&s, &Thresholds::default()).is_empty());
    }

    #[test]
    fn durations_read_naturally() {
        assert_eq!(duration(850.0), "850ms");
        assert_eq!(duration(12_300.0), "12.3s");
        assert_eq!(duration(754_000.0), "12m34s");
        assert_eq!(duration(539_700.0), "9m00s");
        assert_eq!(duration(5_000_000.0), "1h23m");
    }

    #[test]
    fn list_orders_by_value_and_caps() {
        let map: BTreeMap<String, f64> = [
            ("a".into(), 1.0),
            ("b".into(), 5.0),
            ("c".into(), 0.0),
            ("d".into(), 3.0),
        ]
        .into();
        assert_eq!(list(&map, 2), "b 5, d 3, +1 more");
    }
}
