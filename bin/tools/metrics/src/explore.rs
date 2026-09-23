use crate::client::{Labels, Series};
use crate::table::{num, Table};
use crate::time;
use crate::Context;
use std::collections::{BTreeMap, BTreeSet};
use std::time::Instant;

const MEASUREMENTS: [&str; 13] = [
    "http_request",
    "php_runtime",
    "cron_run",
    "cron_task",
    "deploy",
    "security_block",
    "email_queue",
    "log_files",
    "container",
    "database_table",
    "database",
    "valkey",
    "push_queue",
];
const MAX_ROWS: usize = 200;
const MAX_POINTS_PER_SERIES: i64 = 25_000;

pub fn measurement_of(name: &str) -> String {
    MEASUREMENTS
        .iter()
        .filter(|m| name.starts_with(&format!("{}_", m)))
        .max_by_key(|m| m.len())
        .map(|m| m.to_string())
        .unwrap_or_else(|| name.split('_').next().unwrap_or(name).to_string())
}

pub fn health(ctx: &Context) -> Result<String, String> {
    let started = Instant::now();
    ctx.client.instant("vector(1)", ctx.now)?;
    let latency = started.elapsed().as_millis();

    let tsdb = ctx.client.get("status/tsdb", &[])?;
    let total_series = tsdb["data"]["totalSeries"]
        .as_i64()
        .or(tsdb["data"]["headStats"]["numSeries"].as_i64());
    let dropped = ctx.client.instant(
        "sum(increase(vm_hourly_series_limit_rows_dropped_total[24h]))",
        ctx.now,
    )?;
    let newest = ctx.client.instant(
        &ctx.q(
            "max by (__name__) (tlast_over_time({$S}[7d]) keep_metric_names)",
            0,
        ),
        ctx.now,
    )?;

    let mut out = format!(
        "health  {} UTC  app={} env={}  backend answered in {} ms\n",
        time::format_minute(ctx.now),
        ctx.app,
        ctx.env,
        latency
    );
    out.push_str(&format!(
        "series: {} in the store, {} dropped by the cardinality limit in the last 24h\n",
        total_series
            .map(|n| n.to_string())
            .unwrap_or_else(|| "?".into()),
        crate::client::single(&dropped)
            .map(num)
            .unwrap_or_else(|| "0".into())
    ));

    if newest.is_empty() {
        out.push_str("no samples for this app/env in the last 7 days - check METRICS_DSN and the collector\n");
        return Ok(out);
    }
    let mut groups: BTreeMap<String, (f64, usize)> = BTreeMap::new();
    for sample in &newest {
        let name = sample.labels.get("__name__").cloned().unwrap_or_default();
        let entry = groups.entry(measurement_of(&name)).or_insert((0.0, 0));
        entry.0 = entry.0.max(sample.value);
        entry.1 += 1;
    }
    out.push_str("newest sample per measurement:\n");
    let mut table = Table::new(&["measurement", "metrics", "age"]);
    for (measurement, (last, count)) in groups {
        table.row(vec![
            measurement,
            count.to_string(),
            time::format_age(ctx.now - last as i64),
        ]);
    }
    out.push_str(&table.render(2));
    Ok(out)
}

pub fn names(ctx: &Context, filter: Option<&str>, all: bool) -> Result<String, String> {
    let mut params = vec![];
    if !all {
        params.push(("match[]", format!("{{{}}}", ctx.selector())));
        params.push(("start", (ctx.now - 7 * 86_400).to_string()));
    }
    let json = ctx.client.get("label/__name__/values", &params)?;
    let mut groups: BTreeMap<String, Vec<String>> = BTreeMap::new();
    for name in json["data"]
        .as_array()
        .into_iter()
        .flatten()
        .filter_map(|n| n.as_str())
    {
        if filter.is_some_and(|f| !name.contains(f)) {
            continue;
        }
        let measurement = measurement_of(name);
        let field = name
            .strip_prefix(&format!("{}_", measurement))
            .unwrap_or(name)
            .to_string();
        groups.entry(measurement).or_default().push(field);
    }
    if groups.is_empty() {
        return Ok("no metric names match\n".into());
    }
    Ok(groups
        .into_iter()
        .map(|(m, fields)| format!("{}: {}\n", m, fields.join(" ")))
        .collect())
}

pub fn labels(
    ctx: &Context,
    metric: &str,
    label: Option<&str>,
    since: &str,
) -> Result<String, String> {
    let start = time::parse_point(since, ctx.now)?;
    let json = ctx.client.get(
        "series",
        &[
            ("match[]", format!("{}{{{}}}", metric, ctx.selector())),
            ("start", start.to_string()),
            ("end", ctx.now.to_string()),
        ],
    )?;
    let series: Vec<Labels> = json["data"]
        .as_array()
        .into_iter()
        .flatten()
        .filter_map(|s| s.as_object())
        .map(|o| {
            o.iter()
                .map(|(k, v)| (k.clone(), v.as_str().unwrap_or("").to_string()))
                .collect()
        })
        .collect();
    if series.is_empty() {
        return Ok(format!(
            "no series for {} since {}\n",
            metric,
            time::format_minute(start)
        ));
    }

    let mut out = format!(
        "{}: {} series since {}\n",
        metric,
        series.len(),
        time::format_minute(start)
    );
    match label {
        Some(label) => {
            let mut counts: BTreeMap<String, usize> = BTreeMap::new();
            for labels in &series {
                *counts
                    .entry(
                        labels
                            .get(label)
                            .cloned()
                            .unwrap_or_else(|| "(unset)".into()),
                    )
                    .or_default() += 1;
            }
            let mut rows: Vec<(String, usize)> = counts.into_iter().collect();
            rows.sort_by(|a, b| b.1.cmp(&a.1).then(a.0.cmp(&b.0)));
            let mut table = Table::new(&[label, "series"]);
            for (value, count) in rows {
                table.row(vec![value, count.to_string()]);
            }
            out.push_str(&table.render(2));
        }
        None => {
            let mut values: BTreeMap<String, BTreeSet<String>> = BTreeMap::new();
            for labels in &series {
                for (key, value) in labels {
                    if key != "__name__" {
                        values.entry(key.clone()).or_default().insert(value.clone());
                    }
                }
            }
            let mut table = Table::new(&["label", "values", "examples"]);
            for (key, set) in values {
                let examples: Vec<&str> = set.iter().take(4).map(String::as_str).collect();
                table.row(vec![key, set.len().to_string(), examples.join(", ")]);
            }
            out.push_str(&table.render(2));
        }
    }
    Ok(out)
}

pub fn query(ctx: &Context, promql: &str, at: Option<&str>) -> Result<String, String> {
    let at = match at {
        Some(at) => time::parse_point(at, ctx.now)?,
        None => ctx.now,
    };
    let mut samples = ctx.client.instant(promql, at)?;
    if samples.is_empty() {
        return Ok(format!("empty result at {} UTC\n", time::format_minute(at)));
    }
    samples.sort_by(|a, b| b.value.total_cmp(&a.value));
    let keys = varying_keys(samples.iter().map(|s| &s.labels));
    let mut headers: Vec<&str> = keys.iter().map(String::as_str).collect();
    headers.push("value");
    let mut table = Table::new(&headers);
    for sample in samples.iter().take(MAX_ROWS) {
        let mut row: Vec<String> = keys
            .iter()
            .map(|k| sample.labels.get(k).cloned().unwrap_or_default())
            .collect();
        row.push(num(sample.value));
        table.row(row);
    }
    let mut out = format!(
        "{} series at {} UTC\n",
        samples.len(),
        time::format_minute(at)
    );
    out.push_str(&table.render(0));
    if samples.len() > MAX_ROWS {
        out.push_str(&format!("... {} more rows\n", samples.len() - MAX_ROWS));
    }
    Ok(out)
}

pub fn range(
    ctx: &Context,
    promql: &str,
    since: &str,
    step: Option<&str>,
    limit: usize,
) -> Result<String, String> {
    let start = time::parse_point(since, ctx.now)?;
    let end = ctx.end();
    let step = match step {
        Some(step) => {
            time::parse_duration(step).ok_or_else(|| format!("cannot read step '{}'", step))?
        }
        None => time::auto_step(end - start, 48),
    };
    let mut series = ctx
        .client
        .range(promql, time::anchored_start(start, end, step), end, step)?;
    if series.is_empty() {
        return Ok("empty result\n".into());
    }
    series.sort_by(|a, b| peak(b).total_cmp(&peak(a)));
    let omitted = series.len().saturating_sub(limit);
    series.truncate(limit);

    let keys = varying_keys(series.iter().map(|s| &s.labels));
    let names: Vec<String> = series
        .iter()
        .map(|s| series_name(&s.labels, &keys))
        .collect();
    let mut headers = vec!["time"];
    headers.extend(names.iter().map(String::as_str));
    let mut table = Table::new(&headers);
    let values: Vec<BTreeMap<i64, f64>> = series
        .iter()
        .map(|s| s.points.iter().copied().collect())
        .collect();
    let stamps: BTreeSet<i64> = values.iter().flat_map(|v| v.keys().copied()).collect();
    for at in stamps {
        let mut row = vec![time::format_short(at)];
        row.extend(
            values
                .iter()
                .map(|v| v.get(&at).map(|x| num(*x)).unwrap_or_else(|| "-".into())),
        );
        table.row(row);
    }
    let mut out = format!(
        "{} - {} UTC, step {}, {} series{}\n",
        time::format_minute(start),
        time::format_minute(end),
        time::format_span(step),
        series.len(),
        if omitted > 0 {
            format!(" ({} more omitted, raise --limit)", omitted)
        } else {
            String::new()
        }
    );
    out.push_str(&table.render(0));
    Ok(out)
}

pub struct Marker {
    pub at: i64,
    pub kind: &'static str,
    pub text: String,
    pub count: f64,
}

pub fn fetch_markers(
    ctx: &Context,
    start: i64,
    end: i64,
    kind: Option<&str>,
) -> Result<Vec<Marker>, String> {
    let step = (((end - start) / MAX_POINTS_PER_SERIES) / 60 + 1) * 60;
    let mut markers = Vec::new();
    if kind.is_none_or(|k| k == "deploy") {
        let query = ctx.q(
            &format!(
                "sum by (rev) (count_over_time(deploy_value{{$S}}[{}s]))",
                step
            ),
            0,
        );
        for series in ctx.client.range(&query, start, end, step)? {
            for (at, count) in series.points.iter().filter(|(_, v)| *v > 0.0) {
                markers.push(Marker {
                    at: *at,
                    kind: "deploy",
                    text: series.labels.get("rev").cloned().unwrap_or_default(),
                    count: *count,
                });
            }
        }
    }
    if kind.is_none_or(|k| k == "block") {
        let query = ctx.q(
            &format!(
                "sum by (provider, scope) (count_over_time(security_block_value{{$S}}[{}s]))",
                step
            ),
            0,
        );
        for series in ctx.client.range(&query, start, end, step)? {
            let name = format!(
                "{}/{}",
                series
                    .labels
                    .get("provider")
                    .map(String::as_str)
                    .unwrap_or("?"),
                series
                    .labels
                    .get("scope")
                    .map(String::as_str)
                    .unwrap_or("?")
            );
            for (at, count) in series.points.iter().filter(|(_, v)| *v > 0.0) {
                markers.push(Marker {
                    at: *at,
                    kind: "block",
                    text: name.clone(),
                    count: *count,
                });
            }
        }
    }
    markers.sort_by_key(|m| m.at);
    Ok(markers)
}

pub fn markers(ctx: &Context, since: &str, kind: Option<&str>) -> Result<String, String> {
    if kind.is_some_and(|k| k != "deploy" && k != "block") {
        return Err("--kind is deploy or block".into());
    }
    let start = time::parse_point(since, ctx.now)?;
    let end = ctx.end();
    let markers = fetch_markers(ctx, start, end, kind)?;

    let mut out = format!(
        "markers  {} - {} UTC  app={} env={}\n",
        time::format_minute(start),
        time::format_minute(end),
        ctx.app,
        ctx.env
    );
    let deploys = markers.iter().filter(|m| m.kind == "deploy").count();
    let mut blocks: BTreeMap<&str, f64> = BTreeMap::new();
    for marker in markers.iter().filter(|m| m.kind == "block") {
        *blocks.entry(marker.text.as_str()).or_default() += marker.count;
    }
    let block_total: f64 = blocks.values().sum();
    out.push_str(&format!(
        "{} deploys, {} blocks{}\n",
        deploys,
        num(block_total),
        if blocks.is_empty() {
            String::new()
        } else {
            format!(
                " ({})",
                blocks
                    .iter()
                    .map(|(k, v)| format!("{} {}", k, num(*v)))
                    .collect::<Vec<_>>()
                    .join(", ")
            )
        }
    ));
    let skipped = markers.len().saturating_sub(MAX_ROWS);
    if skipped > 0 {
        out.push_str(&format!(
            "showing the latest {} of {} entries\n",
            MAX_ROWS,
            markers.len()
        ));
    }
    let mut table = Table::new(&["time", "kind", "detail", "count"]);
    for marker in markers.iter().skip(skipped) {
        table.row(vec![
            time::format_minute(marker.at),
            marker.kind.to_string(),
            marker.text.clone(),
            num(marker.count),
        ]);
    }
    if !table.is_empty() {
        out.push_str(&table.render(0));
    }
    Ok(out)
}

pub fn varying_keys<'a>(labels: impl Iterator<Item = &'a Labels> + Clone) -> Vec<String> {
    let all: Vec<&Labels> = labels.collect();
    let mut keys: BTreeSet<&String> = BTreeSet::new();
    for l in &all {
        keys.extend(l.keys());
    }
    let varying: Vec<String> = keys
        .iter()
        .filter(|key| {
            let values: BTreeSet<Option<&String>> = all.iter().map(|l| l.get(**key)).collect();
            values.len() > 1
        })
        .map(|k| k.to_string())
        .collect();
    if varying.is_empty() && all.len() == 1 {
        return keys
            .into_iter()
            .filter(|k| *k != "app" && *k != "env")
            .cloned()
            .collect();
    }
    varying
}

fn series_name(labels: &Labels, keys: &[String]) -> String {
    if keys.is_empty() {
        return "value".into();
    }
    keys.iter()
        .map(|k| labels.get(k).cloned().unwrap_or_default())
        .collect::<Vec<_>>()
        .join(",")
}

fn peak(series: &Series) -> f64 {
    series.points.iter().map(|p| p.1).fold(f64::MIN, f64::max)
}

#[cfg(test)]
mod tests {
    use super::*;

    fn labels(pairs: &[(&str, &str)]) -> Labels {
        pairs
            .iter()
            .map(|(k, v)| (k.to_string(), v.to_string()))
            .collect()
    }

    #[test]
    fn measurement_prefers_the_longest_known_prefix() {
        assert_eq!(measurement_of("database_table_size_mb"), "database_table");
        assert_eq!(measurement_of("database_threads_running"), "database");
        assert_eq!(measurement_of("http_request_duration_ms"), "http_request");
        assert_eq!(measurement_of("something_else"), "something");
    }

    #[test]
    fn only_labels_that_differ_become_columns() {
        let a = labels(&[("app", "x"), ("route", "a"), ("status", "2xx")]);
        let b = labels(&[("app", "x"), ("route", "b"), ("status", "2xx")]);
        assert_eq!(varying_keys([&a, &b].into_iter()), vec!["route"]);
    }

    #[test]
    fn a_single_series_shows_its_identity() {
        let a = labels(&[("app", "x"), ("route", "a")]);
        assert_eq!(varying_keys([&a].into_iter()), vec!["route"]);
    }
}
