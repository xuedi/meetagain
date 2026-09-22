use super::{finish, list, Window, MATCHED};
use crate::client::{by_label, single};
use crate::explore::fetch_markers;
use crate::table::{mb, ms, num, opt, Table};
use crate::time;
use crate::Context;
use std::collections::BTreeMap;

const SLOWEST: usize = 10;

pub fn run(ctx: &Context, since: &str) -> Result<String, String> {
    let window = Window::since(ctx, since)?;
    let matched = format!("{{$S,{}}}", MATCHED);
    let queries: Vec<(&str, String)> = vec![
        ("status", format!("sum by (status) (count_over_time(http_request_duration_ms{}[$W]))", matched)),
        ("none", "sum(count_over_time(http_request_duration_ms{$S,route=\"_none\"}[$W]))".into()),
        ("blocked", "sum(count_over_time(http_request_duration_ms{$S,route=\"_blocked\"}[$W]))".into()),
        ("hosts", "topk(12, sum by (host) (count_over_time(http_request_duration_ms{$S}[$W])))".into()),
        ("p50", format!("histogram_quantile(0.5, sum by (vmrange) (histogram_over_time(http_request_duration_ms{}[$W])))", matched)),
        ("p95", format!("histogram_quantile(0.95, sum by (vmrange) (histogram_over_time(http_request_duration_ms{}[$W])))", matched)),
        ("p99", format!("histogram_quantile(0.99, sum by (vmrange) (histogram_over_time(http_request_duration_ms{}[$W])))", matched)),
        ("route_count", format!("sum by (route) (count_over_time(http_request_duration_ms{}[$W]))", matched)),
        ("route_p95", format!("histogram_quantile(0.95, sum by (route, vmrange) (histogram_over_time(http_request_duration_ms{}[$W])))", matched)),
        ("errors", "sum by (route) (count_over_time(http_request_duration_ms{$S,status=\"5xx\"}[$W]))".into()),
        ("cron_runs", "sum(count_over_time(cron_run_duration_ms{$S}[$W]))".into()),
        ("cron_failed", "sum by (task, status) (count_over_time(cron_task_duration_ms{$S,status!=\"ok\"}[$W]))".into()),
        ("cron_slowest", "topk(3, histogram_quantile(0.95, sum by (task, vmrange) (histogram_over_time(cron_task_duration_ms{$S}[$W]))))".into()),
        ("email_pending", "max(max_over_time(email_queue_pending{$S}[$W]))".into()),
        ("email_failed", "max(last_over_time(email_queue_failed_24h{$S}[$W]))".into()),
        ("email_oldest", "max(max_over_time(email_queue_oldest_pending_age_s{$S}[$W]))".into()),
        ("push_pending", "max(max_over_time(push_queue_pending{$S}[$W]))".into()),
        ("memory", "max(max_over_time(container_memory_used_mb{$S}[$W]))".into()),
        ("memory_limit", "max(max_over_time(container_memory_limit_mb{$S}[$W]))".into()),
        ("load1", "max(max_over_time(container_load1{$S}[$W]))".into()),
        ("valkey_memory", "max(max_over_time(valkey_used_memory_mb{$S}[$W]))".into()),
        ("valkey_evicted", "sum(increase(valkey_evicted_keys{$S}[$W]))".into()),
        ("threads", "max(max_over_time(database_threads_running{$S}[$W]))".into()),
        ("slow", "sum(increase(database_slow_queries{$S}[$W]))".into()),
    ];
    let queries: Vec<(&str, String)> = queries
        .into_iter()
        .map(|(k, q)| (k, ctx.q(&q, window.span())))
        .collect();
    let r = ctx.client.instant_many(&queries, window.end)?;
    let v = |k: &str| single(&r[k]);
    let markers = fetch_markers(ctx, window.start, window.end, None)?;

    let status = by_label(&r["status"], "status");
    let matched_total: f64 = status.values().sum();
    let mut out = format!(
        "traffic: {} requests - matched {} ({}), unmatched {}, blocked {}\n",
        num(matched_total + v("none").unwrap_or(0.0) + v("blocked").unwrap_or(0.0)),
        num(matched_total),
        list(&status, 5),
        opt(v("none"), num),
        num(v("blocked").unwrap_or(0.0))
    );
    out.push_str(&format!(
        "hosts: {}\n",
        list(&by_label(&r["hosts"], "host"), 12)
    ));
    out.push_str(&format!(
        "speed (matched routes): p50 {} ms, p95 {} ms, p99 {} ms\n",
        opt(v("p50"), ms),
        opt(v("p95"), ms),
        opt(v("p99"), ms)
    ));

    let counts = by_label(&r["route_count"], "route");
    let mut slowest: Vec<(String, f64)> = by_label(&r["route_p95"], "route")
        .into_iter()
        .filter(|(route, _)| {
            counts.get(route).copied().unwrap_or(0.0) >= ctx.thresholds.slow_route_min_hits
        })
        .collect();
    slowest.sort_by(|a, b| b.1.total_cmp(&a.1));
    if !slowest.is_empty() {
        out.push_str(&format!(
            "\nslowest routes by p95 (min {} hits):\n",
            num(ctx.thresholds.slow_route_min_hits)
        ));
        let mut table = Table::new(&["route", "hits", "p95 ms"]);
        for (route, p95) in slowest.iter().take(SLOWEST) {
            table.row(vec![route.clone(), num(counts[route]), ms(*p95)]);
        }
        out.push_str(&table.render(2));
    }

    let errors = by_label(&r["errors"], "route");
    out.push_str(&format!(
        "\nserver errors: {}\n",
        if errors.is_empty() {
            "none".into()
        } else {
            list(&errors, 10)
        }
    ));

    let deploys: Vec<String> = markers
        .iter()
        .filter(|m| m.kind == "deploy")
        .map(|m| format!("{} at {}", m.text, time::format_short(m.at)))
        .collect();
    let mut blocks: BTreeMap<String, f64> = BTreeMap::new();
    for marker in markers.iter().filter(|m| m.kind == "block") {
        *blocks.entry(marker.text.clone()).or_default() += marker.count;
    }
    out.push_str(&format!(
        "deploys: {}\n",
        if deploys.is_empty() {
            "none".into()
        } else {
            deploys.join(", ")
        }
    ));
    out.push_str(&format!(
        "blocks: {}\n",
        if blocks.is_empty() {
            "none".into()
        } else {
            format!("{} ({})", num(blocks.values().sum()), list(&blocks, 10))
        }
    ));

    let failed: Vec<String> = r["cron_failed"]
        .iter()
        .map(|s| {
            format!(
                "{} {} x{}",
                s.labels.get("task").map(String::as_str).unwrap_or("?"),
                s.labels.get("status").map(String::as_str).unwrap_or("?"),
                num(s.value)
            )
        })
        .collect();
    out.push_str(&format!(
        "\ncron: {} runs; not ok: {}; slowest tasks p95 (ms): {}\n",
        opt(v("cron_runs"), num),
        if failed.is_empty() {
            "none".into()
        } else {
            failed.join(", ")
        },
        list(&by_label(&r["cron_slowest"], "task"), 3)
    ));
    out.push_str(&format!(
        "queues: email pending max {}, failed (24h) {}, oldest pending max {}{}\n",
        opt(v("email_pending"), num),
        opt(v("email_failed"), num),
        opt(v("email_oldest"), |s| time::format_age(s as i64)),
        v("push_pending")
            .map(|p| format!("; push pending max {}", num(p)))
            .unwrap_or_default()
    ));
    out.push_str(&format!(
        "peaks: memory {} MB{}, load1 {}, valkey {} MB with {} evictions, db threads running {}, slow queries +{}\n",
        opt(v("memory"), mb),
        v("memory_limit").map(|l| format!(" of {}", mb(l))).unwrap_or_default(),
        opt(v("load1"), num),
        opt(v("valkey_memory"), mb),
        opt(v("valkey_evicted"), num),
        opt(v("threads"), num),
        opt(v("slow"), num)
    ));
    Ok(finish(ctx, "daily", &window, out))
}
