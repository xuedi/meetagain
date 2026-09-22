use super::{finish, list, Window};
use crate::client::{by_label, Series};
use crate::table::{num, Table};
use crate::time;
use crate::Context;
use std::collections::BTreeMap;

const HOUR: i64 = 3600;
const MAX_HOURLY_ROWS: usize = 48;

#[derive(Debug, Clone, Default, PartialEq)]
pub struct Hour {
    pub start: i64,
    pub unmatched: f64,
    pub blocked: f64,
    pub blocks: BTreeMap<String, f64>,
}

impl Hour {
    pub fn block_count(&self) -> f64 {
        self.blocks.values().sum()
    }
}

#[derive(Debug, PartialEq)]
pub struct Spike {
    pub start: i64,
    pub unmatched: f64,
    pub blocked: f64,
    pub stopped: bool,
}

pub struct Analysis {
    pub hours: Vec<Hour>,
    pub baseline: f64,
    pub spikes: Vec<Spike>,
}

pub fn fetch(ctx: &Context, window: &Window) -> Result<Analysis, String> {
    let last_end = window.end;
    let first_end = time::anchored_start(window.start + HOUR, last_end, HOUR);
    if last_end < first_end {
        return Ok(Analysis {
            hours: vec![],
            baseline: 0.0,
            spikes: vec![],
        });
    }
    let requests = ctx.client.range(
        &ctx.q("sum by (route) (count_over_time(http_request_duration_ms{$S,route=~\"_none|_blocked\"}[1h]))", 0),
        first_end,
        last_end,
        HOUR,
    )?;
    let blocks = ctx.client.range(
        &ctx.q(
            "sum by (provider) (count_over_time(security_block_value{$S}[1h]))",
            0,
        ),
        first_end,
        last_end,
        HOUR,
    )?;
    let hours = build_hours(first_end, last_end, &requests, &blocks);
    Ok(analyse(
        hours,
        ctx.thresholds.probe_spike_factor,
        ctx.thresholds.probe_spike_min,
    ))
}

pub fn build_hours(
    first_end: i64,
    last_end: i64,
    requests: &[Series],
    blocks: &[Series],
) -> Vec<Hour> {
    let mut hours: BTreeMap<i64, Hour> = BTreeMap::new();
    let mut end = first_end;
    while end <= last_end {
        hours.insert(
            end,
            Hour {
                start: end - HOUR,
                ..Hour::default()
            },
        );
        end += HOUR;
    }
    for series in requests {
        let route = series.labels.get("route").map(String::as_str).unwrap_or("");
        for (at, value) in &series.points {
            if let Some(hour) = hours.get_mut(at) {
                match route {
                    "_none" => hour.unmatched += value,
                    "_blocked" => hour.blocked += value,
                    _ => {}
                }
            }
        }
    }
    for series in blocks {
        let provider = series
            .labels
            .get("provider")
            .cloned()
            .unwrap_or_else(|| "?".into());
        for (at, value) in &series.points {
            if let Some(hour) = hours.get_mut(at) {
                *hour.blocks.entry(provider.clone()).or_default() += value;
            }
        }
    }
    hours.into_values().collect()
}

pub fn analyse(hours: Vec<Hour>, factor: f64, minimum: f64) -> Analysis {
    let first = hours
        .iter()
        .position(|h| h.unmatched > 0.0 || h.blocked > 0.0)
        .unwrap_or(0);
    let mut counts: Vec<f64> = hours[first..].iter().map(|h| h.unmatched).collect();
    counts.sort_by(f64::total_cmp);
    let baseline = match counts.len() {
        0 => 0.0,
        n => counts[(n - 1) / 4],
    };
    let threshold = (baseline.max(1.0) * factor).max(minimum);
    let spikes = hours
        .iter()
        .enumerate()
        .filter(|(_, h)| h.unmatched >= threshold)
        .map(|(i, h)| {
            let next_blocks = hours.get(i + 1).map(Hour::block_count).unwrap_or(0.0);
            Spike {
                start: h.start,
                unmatched: h.unmatched,
                blocked: h.blocked,
                stopped: h.block_count() + next_blocks > 0.0,
            }
        })
        .collect();
    Analysis {
        hours,
        baseline,
        spikes,
    }
}

pub fn run(ctx: &Context, since: &str) -> Result<String, String> {
    let window = Window::since(ctx, since)?;
    let analysis = fetch(ctx, &window)?;
    let hosts = by_label(
        &ctx.client.instant(
            &ctx.q(
                "sum by (host) (count_over_time(http_request_duration_ms{$S,route=\"_none\"}[$W]))",
                window.span(),
            ),
            window.end,
        )?,
        "host",
    );

    let unmatched: f64 = analysis.hours.iter().map(|h| h.unmatched).sum();
    let blocked: f64 = analysis.hours.iter().map(|h| h.blocked).sum();
    let mut providers: BTreeMap<String, f64> = BTreeMap::new();
    for hour in &analysis.hours {
        for (provider, count) in &hour.blocks {
            *providers.entry(provider.clone()).or_default() += count;
        }
    }
    let threshold = (analysis.baseline.max(1.0) * ctx.thresholds.probe_spike_factor)
        .max(ctx.thresholds.probe_spike_min);

    let mut out = format!(
        "{} hours: {} unmatched, {} answered as blocked, {} blocks{}\n",
        analysis.hours.len(),
        num(unmatched),
        num(blocked),
        num(providers.values().sum()),
        if providers.is_empty() {
            String::new()
        } else {
            format!(" ({})", list(&providers, 5))
        }
    );
    out.push_str(&format!(
        "quiet baseline (25th percentile hour): {} unmatched; a spike is an hour with {} or more\n",
        num(analysis.baseline),
        num(threshold)
    ));

    if analysis.spikes.is_empty() {
        out.push_str("\nno spikes\n");
    } else {
        let stopped = analysis.spikes.iter().filter(|s| s.stopped).count();
        out.push_str(&format!(
            "\nspikes: {} ({} stopped by a block, {} not)\n",
            analysis.spikes.len(),
            stopped,
            analysis.spikes.len() - stopped
        ));
        let mut table = Table::new(&["hour", "unmatched", "blocked", "status"]);
        for spike in &analysis.spikes {
            table.row(vec![
                time::format_short(spike.start),
                num(spike.unmatched),
                num(spike.blocked),
                if spike.stopped {
                    "stopped"
                } else {
                    "not stopped"
                }
                .into(),
            ]);
        }
        out.push_str(&table.render(0));
    }

    if !hosts.is_empty() {
        out.push_str(&format!("\nunmatched per host: {}\n", list(&hosts, 12)));
    }

    if analysis.hours.len() <= MAX_HOURLY_ROWS {
        out.push_str("\nhourly:\n");
        let mut table = Table::new(&["hour", "unmatched", "blocked", "blocks"]);
        for hour in &analysis.hours {
            table.row(vec![
                time::format_short(hour.start),
                num(hour.unmatched),
                num(hour.blocked),
                list(&hour.blocks, 5),
            ]);
        }
        out.push_str(&table.render(0));
    } else {
        out.push_str(&format!(
            "\nhourly table omitted above {} hours - narrow --since to see it\n",
            MAX_HOURLY_ROWS
        ));
    }
    Ok(finish(ctx, "probes", &window, out))
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::client::parse_matrix;

    fn hour(start: i64, unmatched: f64, blocks: f64) -> Hour {
        let mut hour = Hour {
            start,
            unmatched,
            ..Hour::default()
        };
        if blocks > 0.0 {
            hour.blocks.insert("not_found".into(), blocks);
        }
        hour
    }

    #[test]
    fn hours_are_filled_and_split_by_route() {
        let data: serde_json::Value =
            serde_json::from_str(include_str!("../../tests/fixtures/matrix_unmatched.json"))
                .unwrap();
        let requests = parse_matrix(&data["data"]).unwrap();
        let hours = build_hours(1_790_000_000, 1_790_010_800, &requests, &[]);
        assert_eq!(hours.len(), 4);
        assert_eq!(hours[1].unmatched, 912.0);
        assert_eq!(hours[1].blocked, 40.0);
        assert_eq!(hours[3].unmatched, 0.0);
        assert_eq!(hours[0].start, 1_790_000_000 - HOUR);
    }

    #[test]
    fn a_spike_is_measured_against_the_quiet_baseline() {
        let hours = vec![
            hour(0, 10.0, 0.0),
            hour(1, 12.0, 0.0),
            hour(2, 400.0, 1.0),
            hour(3, 8.0, 0.0),
            hour(4, 300.0, 0.0),
        ];
        let analysis = analyse(hours, 5.0, 50.0);
        assert_eq!(analysis.baseline, 10.0);
        assert_eq!(analysis.spikes.len(), 2);
        assert!(analysis.spikes[0].stopped);
        assert!(!analysis.spikes[1].stopped);
    }

    #[test]
    fn a_block_in_the_next_hour_still_counts_as_stopped() {
        let hours = vec![hour(0, 5.0, 0.0), hour(1, 500.0, 0.0), hour(2, 20.0, 2.0)];
        let analysis = analyse(hours, 5.0, 50.0);
        assert!(analysis.spikes[0].stopped);
    }

    #[test]
    fn the_minimum_keeps_a_quiet_site_from_reporting_noise() {
        let hours = vec![hour(0, 0.0, 0.0), hour(1, 0.0, 0.0), hour(2, 12.0, 0.0)];
        assert!(analyse(hours, 5.0, 50.0).spikes.is_empty());
    }

    #[test]
    fn empty_hours_before_the_first_data_do_not_lower_the_baseline() {
        let mut hours: Vec<Hour> = (0..10).map(|i| hour(i, 0.0, 0.0)).collect();
        hours.extend([
            hour(10, 100.0, 0.0),
            hour(11, 120.0, 0.0),
            hour(12, 110.0, 0.0),
        ]);
        let analysis = analyse(hours, 5.0, 50.0);
        assert_eq!(analysis.baseline, 100.0);
        assert!(analysis.spikes.is_empty());
    }
}
