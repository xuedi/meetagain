use super::{duration, finish, group_stats, hints, list, Stats, Window, MATCHED};
use crate::client::{by_label, single};
use crate::table::{mb, ms, num, opt, pct, Table};
use crate::time;
use crate::Context;

pub fn run(
    ctx: &Context,
    since: &str,
    limit: usize,
    route: Option<&str>,
) -> Result<String, String> {
    let window = Window::since(ctx, since)?;
    let mut body = match route {
        Some(route) => route_detail(ctx, &window, route)?,
        None => routes(ctx, &window, limit)?,
    };
    body.push_str(&cron(ctx, &window)?);
    body.push_str(&platform(ctx, &window)?);
    Ok(finish(ctx, "performance", &window, body))
}

fn stats_table(stats: &[Stats], all_ms: f64, ctx: &Context, first: &str) -> Table {
    let mut table = Table::new(&[
        first, "hits", "total", "share", "p50", "p95", "p99", "max", "db%", "q95", "mem95", "hint",
    ]);
    for s in stats {
        table.row(vec![
            s.key.clone(),
            num(s.count),
            duration(s.total_ms),
            if all_ms > 0.0 {
                pct(s.total_ms / all_ms)
            } else {
                "-".into()
            },
            opt(s.p50, ms),
            opt(s.p95, ms),
            opt(s.p99, ms),
            opt(s.max, ms),
            opt(s.db_share(), |r| format!("{:.0}", r * 100.0)),
            opt(s.queries_p95, num),
            opt(s.memory_p95, mb),
            hints(s, &ctx.thresholds).join(" "),
        ]);
    }
    table
}

fn totals(ctx: &Context, window: &Window) -> Result<(f64, f64, f64, f64), String> {
    let queries = [
        (
            "all_ms",
            ctx.q(
                "sum(sum_over_time(http_request_duration_ms{$S}[$W]))",
                window.span(),
            ),
        ),
        (
            "all",
            ctx.q(
                "sum(count_over_time(http_request_duration_ms{$S}[$W]))",
                window.span(),
            ),
        ),
        (
            "none",
            ctx.q(
                "sum(count_over_time(http_request_duration_ms{$S,route=\"_none\"}[$W]))",
                window.span(),
            ),
        ),
        (
            "blocked",
            ctx.q(
                "sum(count_over_time(http_request_duration_ms{$S,route=\"_blocked\"}[$W]))",
                window.span(),
            ),
        ),
    ];
    let queries: Vec<(&str, String)> = queries.into_iter().collect();
    let r = ctx.client.instant_many(&queries, window.end)?;
    let v = |k: &str| single(&r[k]).unwrap_or(0.0);
    Ok((v("all_ms"), v("all"), v("none"), v("blocked")))
}

fn routes(ctx: &Context, window: &Window, limit: usize) -> Result<String, String> {
    let (all_ms, all, none, blocked) = totals(ctx, window)?;
    let stats = group_stats(ctx, window.end, window.span(), MATCHED, "route")?;
    let mut out = format!(
        "requests: {} using {} of server time; unmatched {}, blocked {}\n",
        num(all),
        duration(all_ms),
        num(none),
        num(blocked)
    );
    if stats.is_empty() {
        out.push_str("no matched requests in this window\n");
        return Ok(out);
    }
    out.push_str(&format!(
        "\nroutes by total server time (top {} of {}; ms; db% = share of time in the database):\n",
        limit.min(stats.len()),
        stats.len()
    ));
    out.push_str(&stats_table(&stats[..limit.min(stats.len())], all_ms, ctx, "route").render(0));
    Ok(out)
}

fn route_detail(ctx: &Context, window: &Window, route: &str) -> Result<String, String> {
    let matcher = format!("route=\"{}\"", route.replace('"', "\\\""));
    let (all_ms, ..) = totals(ctx, window)?;
    let overall = group_stats(ctx, window.end, window.span(), &matcher, "route")?;
    if overall.is_empty() {
        return Ok(format!("no requests for route {} in this window\n", route));
    }
    let mut out = format!("route {} (ms):\n", route);
    out.push_str(&stats_table(&overall, all_ms, ctx, "route").render(0));
    for by in ["host", "method", "status"] {
        let stats = group_stats(ctx, window.end, window.span(), &matcher, by)?;
        if stats.len() > 1 {
            out.push_str(&format!("\nby {}:\n", by));
            out.push_str(&stats_table(&stats, all_ms, ctx, by).render(0));
        }
    }

    let step = time::auto_step(window.span(), 24);
    let step_window = format!("{}s", step);
    let p95 = ctx.client.range(
        &ctx.q(&format!("histogram_quantile(0.95, sum by (vmrange) (histogram_over_time(http_request_duration_ms{{$S,{}}}[{}])))", matcher, step_window), 0),
        time::anchored_start(window.start, window.end, step),
        window.end,
        step,
    )?;
    let hits = ctx.client.range(
        &ctx.q(
            &format!(
                "sum(count_over_time(http_request_duration_ms{{$S,{}}}[{}]))",
                matcher, step_window
            ),
            0,
        ),
        time::anchored_start(window.start, window.end, step),
        window.end,
        step,
    )?;
    let p95: std::collections::BTreeMap<i64, f64> = p95
        .first()
        .map(|s| s.points.iter().copied().collect())
        .unwrap_or_default();
    let mut table = Table::new(&["until", "hits", "p95"]);
    for (at, count) in hits.first().map(|s| s.points.clone()).unwrap_or_default() {
        if count > 0.0 {
            table.row(vec![
                time::format_short(at),
                num(count),
                opt(p95.get(&at).copied(), ms),
            ]);
        }
    }
    if !table.is_empty() {
        out.push_str(&format!("\ncurve per {}:\n", time::format_span(step)));
        out.push_str(&table.render(0));
    }
    Ok(out)
}

fn cron(ctx: &Context, window: &Window) -> Result<String, String> {
    let queries = [
        ("runs", "sum by (task) (count_over_time(cron_task_duration_ms{$S}[$W]))"),
        ("total", "sum by (task) (sum_over_time(cron_task_duration_ms{$S}[$W]))"),
        ("p95", "histogram_quantile(0.95, sum by (task, vmrange) (histogram_over_time(cron_task_duration_ms{$S}[$W])))"),
        ("max", "max by (task) (max_over_time(cron_task_duration_ms{$S}[$W]))"),
        ("failed", "sum by (task) (count_over_time(cron_task_duration_ms{$S,status!=\"ok\"}[$W]))"),
        ("run_count", "sum(count_over_time(cron_run_duration_ms{$S}[$W]))"),
        ("run_p95", "histogram_quantile(0.95, sum by (vmrange) (histogram_over_time(cron_run_duration_ms{$S}[$W])))"),
        ("run_max", "max(max_over_time(cron_run_duration_ms{$S}[$W]))"),
        ("run_memory", "max(max_over_time(cron_run_memory_peak_mb{$S}[$W]))"),
    ];
    let queries: Vec<(&str, String)> = queries
        .iter()
        .map(|(k, q)| (*k, ctx.q(q, window.span())))
        .collect();
    let r = ctx.client.instant_many(&queries, window.end)?;
    let runs = by_label(&r["runs"], "task");
    if runs.is_empty() {
        return Ok("\ncron: no runs in this window\n".into());
    }
    let (total, p95, max, failed) = (
        by_label(&r["total"], "task"),
        by_label(&r["p95"], "task"),
        by_label(&r["max"], "task"),
        by_label(&r["failed"], "task"),
    );

    let mut out = format!(
        "\ncron: {} runs, p95 {} ms, max {} ms, peak memory {} MB\n",
        opt(single(&r["run_count"]), num),
        opt(single(&r["run_p95"]), ms),
        opt(single(&r["run_max"]), ms),
        opt(single(&r["run_memory"]), mb)
    );
    let mut tasks: Vec<&String> = runs.keys().collect();
    tasks.sort_by(|a, b| {
        total
            .get(*b)
            .unwrap_or(&0.0)
            .total_cmp(total.get(*a).unwrap_or(&0.0))
    });
    let mut table = Table::new(&["task", "runs", "total", "p95", "max", "not ok"]);
    for task in tasks {
        table.row(vec![
            task.clone(),
            num(runs[task]),
            duration(total.get(task).copied().unwrap_or(0.0)),
            opt(p95.get(task).copied(), ms),
            opt(max.get(task).copied(), ms),
            num(failed.get(task).copied().unwrap_or(0.0)),
        ]);
    }
    out.push_str(&table.render(0));
    Ok(out)
}

fn platform(ctx: &Context, window: &Window) -> Result<String, String> {
    let queries = [
        ("opcache_hit", "min(min_over_time(php_runtime_opcache_hit_rate{$S}[$W]))"),
        ("opcache_free", "min(min_over_time(php_runtime_opcache_free_mb{$S}[$W]))"),
        ("opcache_wasted", "max(max_over_time(php_runtime_opcache_wasted_mb{$S}[$W]))"),
        ("opcache_restarts", "sum(increase(php_runtime_opcache_restarts{$S}[$W]))"),
        ("apcu", "max(max_over_time(php_runtime_apcu_used_mb{$S}[$W]))"),
        ("valkey_hit", "sum(increase(valkey_keyspace_hits{$S}[$W])) / (sum(increase(valkey_keyspace_hits{$S}[$W])) + sum(increase(valkey_keyspace_misses{$S}[$W])))"),
        ("valkey_evicted", "sum(increase(valkey_evicted_keys{$S}[$W]))"),
        ("valkey_memory", "max(max_over_time(valkey_used_memory_mb{$S}[$W]))"),
        ("buffer_pool", "1 - sum(increase(database_innodb_buffer_pool_reads{$S}[$W])) / sum(increase(database_innodb_buffer_pool_read_requests{$S}[$W]))"),
        ("qps", "sum(rate(database_questions{$S}[$W]))"),
        ("qps_peak", "max_over_time(sum(rate(database_questions{$S}[5m]))[$W:5m])"),
        ("threads", "max(max_over_time(database_threads_running{$S}[$W]))"),
        ("slow", "sum(increase(database_slow_queries{$S}[$W]))"),
        ("memory", "max(max_over_time(container_memory_used_mb{$S}[$W]))"),
        ("memory_limit", "max(max_over_time(container_memory_limit_mb{$S}[$W]))"),
        ("load1", "max(max_over_time(container_load1{$S}[$W]))"),
        ("load5", "avg(avg_over_time(container_load5{$S}[$W]))"),
        ("tables", "topk(5, max by (table) (last_over_time(database_table_size_mb{$S}[$W])))"),
    ];
    let queries: Vec<(&str, String)> = queries
        .iter()
        .map(|(k, q)| (*k, ctx.q(q, window.span())))
        .collect();
    let r = ctx.client.instant_many(&queries, window.end)?;
    let v = |k: &str| single(&r[k]);

    let mut out = String::from("\nplatform:\n");
    out.push_str(&format!(
        "  php     opcache hit min {}%, free min {} MB, wasted max {} MB, restarts {}; apcu max {} MB\n",
        opt(v("opcache_hit"), num),
        opt(v("opcache_free"), mb),
        opt(v("opcache_wasted"), mb),
        opt(v("opcache_restarts"), num),
        opt(v("apcu"), mb)
    ));
    out.push_str(&format!(
        "  valkey  hit ratio {}, evictions {}, memory max {} MB\n",
        opt(v("valkey_hit"), pct),
        opt(v("valkey_evicted"), num),
        opt(v("valkey_memory"), mb)
    ));
    out.push_str(&format!(
        "  mariadb buffer pool hit {}, {} queries/s avg, {} peak (5m), threads running max {}, slow queries {}\n",
        opt(v("buffer_pool"), |x| format!("{:.2}%", x * 100.0)),
        opt(v("qps"), num),
        opt(v("qps_peak"), num),
        opt(v("threads"), num),
        opt(v("slow"), num)
    ));
    out.push_str(&format!(
        "  host    memory max {} MB{}, load1 max {}, load5 avg {}\n",
        opt(v("memory"), mb),
        v("memory_limit")
            .map(|l| format!(" of {} MB", mb(l)))
            .unwrap_or_default(),
        opt(v("load1"), num),
        opt(v("load5"), num)
    ));
    let tables = by_label(&r["tables"], "table");
    if !tables.is_empty() {
        out.push_str(&format!("  tables  {} (MB)\n", list(&tables, 5)));
    }
    Ok(out)
}
