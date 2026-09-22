use super::{finish, group_stats, Stats, Window, MATCHED};
use crate::client::single;
use crate::explore::fetch_markers;
use crate::table::{change, mb, ms, num, opt, pct, Table};
use crate::time;
use crate::{Context, Thresholds};
use std::collections::BTreeMap;

const SEARCH_SPAN: i64 = 30 * 86_400;
const MIN_AFTER: i64 = 300;
const MIN_HITS: f64 = 5.0;
const ROUTES: usize = 10;

#[derive(Debug, PartialEq)]
pub struct Comparison {
    pub route: String,
    pub before: Option<Stats>,
    pub after: Option<Stats>,
    pub flags: Vec<&'static str>,
}

pub fn compare(before: &[Stats], after: &[Stats], t: &Thresholds) -> Vec<Comparison> {
    let before: BTreeMap<&str, &Stats> = before.iter().map(|s| (s.key.as_str(), s)).collect();
    let after: BTreeMap<&str, &Stats> = after.iter().map(|s| (s.key.as_str(), s)).collect();
    let mut routes: Vec<&str> = before.keys().chain(after.keys()).copied().collect();
    routes.sort();
    routes.dedup();
    let hits = |r: &str| {
        before.get(r).map(|s| s.count).unwrap_or(0.0) + after.get(r).map(|s| s.count).unwrap_or(0.0)
    };
    routes.sort_by(|a, b| hits(b).total_cmp(&hits(a)));

    routes
        .into_iter()
        .take(ROUTES)
        .map(|route| {
            let (b, a) = (before.get(route).copied(), after.get(route).copied());
            let mut flags = Vec::new();
            if let (Some(b), Some(a)) = (b, a) {
                if b.count >= MIN_HITS && a.count >= MIN_HITS {
                    let grew = |x: Option<f64>, y: Option<f64>| match (x, y) {
                        (Some(x), Some(y)) if x > 0.0 => (y - x) / x * 100.0 > t.regression_pct,
                        _ => false,
                    };
                    if grew(b.p95, a.p95) {
                        flags.push("slower");
                    }
                    if grew(b.queries_p95, a.queries_p95) {
                        flags.push("more queries");
                    }
                    if grew(b.memory_p95, a.memory_p95) {
                        flags.push("more memory");
                    }
                }
            }
            Comparison {
                route: route.to_string(),
                before: b.cloned(),
                after: a.cloned(),
                flags,
            }
        })
        .collect()
}

pub fn run(ctx: &Context, rev: &str, window: &str) -> Result<String, String> {
    let span =
        time::parse_duration(window).ok_or_else(|| format!("cannot read --window '{}'", window))?;
    let end = ctx.end();
    let markers = fetch_markers(ctx, end - SEARCH_SPAN, end, Some("deploy"))?;
    let marker = match rev {
        "latest" => markers.last(),
        rev => markers.iter().rev().find(|m| m.text.starts_with(rev)),
    }
    .ok_or_else(|| format!("no deploy marker for '{}' in the last 30 days", rev))?;

    let deployed = marker.at;
    let before_at = deployed - 120;
    let after_end = (deployed + span).min(end);
    let after_span = after_end - deployed;
    let report_window = Window {
        start: before_at - span,
        end: after_end,
    };
    let mut out = format!(
        "deploy {} at {} UTC; before = {} ending {}, after = {} since the deploy\n",
        marker.text,
        time::format_minute(deployed),
        time::format_span(span),
        time::format_minute(before_at),
        time::format_age(after_span)
    );
    if after_span < MIN_AFTER {
        out.push_str("the deploy is too recent to compare - wait a few minutes\n");
        return Ok(finish(ctx, "deploy", &report_window, out));
    }
    if after_span < span {
        out.push_str("note: the after window is shorter than the before window, so its hit counts are lower\n");
    }

    let before = group_stats(ctx, before_at, span, MATCHED, "route")?;
    let after = group_stats(ctx, after_end, after_span, MATCHED, "route")?;
    let rows = compare(&before, &after, &ctx.thresholds);

    let mut table = Table::new(&["route", "hits", "p95", "change", "q95", "mem95", "flag"]);
    for row in &rows {
        let pair = |f: fn(&Stats) -> Option<f64>, format: fn(f64) -> String| {
            format!(
                "{} > {}",
                opt(row.before.as_ref().and_then(f), format),
                opt(row.after.as_ref().and_then(f), format)
            )
        };
        let count = |s: &Option<Stats>| {
            s.as_ref()
                .map(|s| num(s.count))
                .unwrap_or_else(|| "0".into())
        };
        table.row(vec![
            row.route.clone(),
            format!("{} > {}", count(&row.before), count(&row.after)),
            pair(|s| s.p95, ms),
            match (
                row.before.as_ref().and_then(|s| s.p95),
                row.after.as_ref().and_then(|s| s.p95),
            ) {
                (Some(b), Some(a)) => change(b, a),
                _ => "-".into(),
            },
            pair(|s| s.queries_p95, num),
            pair(|s| s.memory_p95, mb),
            row.flags.join(", "),
        ]);
    }
    let regressed = rows.iter().filter(|r| !r.flags.is_empty()).count();
    out.push_str(&format!(
        "\nbusiest {} routes, before > after (ms, MB); flagged when p95 grows more than {}% with {}+ hits on both sides: {} flagged\n",
        rows.len(),
        ctx.thresholds.regression_pct,
        MIN_HITS,
        regressed
    ));
    out.push_str(&table.render(0));

    let side = |at: i64, w: i64| -> Result<(Option<f64>, Option<f64>, Option<f64>), String> {
        let queries: Vec<(&str, String)> = vec![
            ("all", ctx.q("sum(count_over_time(http_request_duration_ms{$S}[$W]))", w)),
            ("errors", ctx.q("sum(count_over_time(http_request_duration_ms{$S,status=\"5xx\"}[$W]))", w)),
            ("cron", ctx.q("histogram_quantile(0.95, sum by (vmrange) (histogram_over_time(cron_run_duration_ms{$S}[$W])))", w)),
        ];
        let r = ctx.client.instant_many(&queries, at)?;
        Ok((single(&r["all"]), single(&r["errors"]), single(&r["cron"])))
    };
    let (b_all, b_err, b_cron) = side(before_at, span)?;
    let (a_all, a_err, a_cron) = side(after_end, after_span)?;
    let rate = |err: Option<f64>, all: Option<f64>| match all {
        Some(all) if all > 0.0 => pct(err.unwrap_or(0.0) / all),
        _ => "-".into(),
    };
    out.push_str(&format!(
        "\n5xx rate {} > {}; cron run p95 {} > {} ms\n",
        rate(b_err, b_all),
        rate(a_err, a_all),
        opt(b_cron, ms),
        opt(a_cron, ms)
    ));
    Ok(finish(ctx, "deploy", &report_window, out))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn stats(route: &str, count: f64, p95: f64, queries: f64) -> Stats {
        Stats {
            key: route.into(),
            count,
            p95: Some(p95),
            queries_p95: Some(queries),
            memory_p95: Some(20.0),
            ..Stats::default()
        }
    }

    #[test]
    fn a_route_that_got_slower_is_flagged() {
        let rows = compare(
            &[stats("a", 50.0, 100.0, 10.0)],
            &[stats("a", 40.0, 180.0, 10.0)],
            &Thresholds::default(),
        );
        assert_eq!(rows[0].flags, vec!["slower"]);
    }

    #[test]
    fn small_samples_are_not_flagged() {
        let rows = compare(
            &[stats("a", 3.0, 100.0, 10.0)],
            &[stats("a", 40.0, 900.0, 90.0)],
            &Thresholds::default(),
        );
        assert!(rows[0].flags.is_empty());
    }

    #[test]
    fn routes_are_ordered_by_hits_on_both_sides() {
        let rows = compare(
            &[
                stats("quiet", 5.0, 10.0, 1.0),
                stats("busy", 100.0, 10.0, 1.0),
            ],
            &[stats("new", 60.0, 10.0, 1.0)],
            &Thresholds::default(),
        );
        let order: Vec<&str> = rows.iter().map(|r| r.route.as_str()).collect();
        assert_eq!(order, vec!["busy", "new", "quiet"]);
        assert!(rows[1].before.is_none());
    }

    #[test]
    fn more_queries_is_its_own_flag() {
        let rows = compare(
            &[stats("a", 50.0, 100.0, 10.0)],
            &[stats("a", 50.0, 100.0, 40.0)],
            &Thresholds::default(),
        );
        assert_eq!(rows[0].flags, vec!["more queries"]);
    }
}
