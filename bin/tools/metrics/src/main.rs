mod client;
mod explore;
mod reports;
mod table;
mod time;

use clap::{Parser, Subcommand};
use client::Client;
use std::process::exit;

#[derive(Parser)]
#[command(
    name = "metrics",
    about = "Read-only client for the metrics store: exploration and canned reports"
)]
struct Cli {
    /// Override the APP label matcher used by reports
    #[arg(long, global = true)]
    app: Option<String>,
    /// Override the ENV label matcher used by reports
    #[arg(long, global = true)]
    env: Option<String>,
    /// Print the raw query results as JSON instead of the text view
    #[arg(long, global = true)]
    json: bool,
    #[command(subcommand)]
    command: Command,
}

#[derive(Subcommand)]
enum Command {
    /// Backend reachability, series count, dropped series, newest sample per metric
    Health,
    /// Metric names of this app (all names with --all), grouped by prefix
    Names {
        filter: Option<String>,
        #[arg(long)]
        all: bool,
    },
    /// Label keys of a metric, or the values of one label with series counts
    Labels {
        metric: String,
        label: Option<String>,
        #[arg(long, default_value = "24h")]
        since: String,
    },
    /// Instant query, raw PromQL/MetricsQL (no app/env injected)
    Query {
        promql: String,
        /// Evaluation time: 2h (ago) or 2026-09-21T14:30 (UTC); default now
        #[arg(long)]
        at: Option<String>,
    },
    /// Range query as a table: one row per step, one column per series
    Range {
        promql: String,
        #[arg(long, default_value = "24h")]
        since: String,
        /// Step such as 5m or 1h; default keeps the table under 48 rows
        #[arg(long)]
        step: Option<String>,
        /// Maximum number of series shown
        #[arg(long, default_value_t = 8)]
        limit: usize,
    },
    /// Timeline of deploy and security block markers
    Markers {
        #[arg(long, default_value = "7d")]
        since: String,
        /// deploy or block
        #[arg(long)]
        kind: Option<String>,
    },
    /// Canned reports that merge many queries into one answer
    #[command(subcommand)]
    Report(Report),
}

#[derive(Subcommand)]
enum Report {
    /// Overview of a period: traffic, speed, errors, markers, background work, peaks
    Daily {
        #[arg(long, default_value = "24h")]
        since: String,
    },
    /// Only what crosses a threshold, most severe first
    Issues {
        #[arg(long, default_value = "24h")]
        since: String,
    },
    /// Where server time goes: routes ranked by total time with bottleneck hints, cron, platform
    Performance {
        #[arg(long, default_value = "24h")]
        since: String,
        #[arg(long, default_value_t = 15)]
        limit: usize,
        /// Drill into one route: per host, method and status, plus its p95 curve
        #[arg(long)]
        route: Option<String>,
    },
    /// Before/after comparison around a deploy marker
    Deploy {
        /// Revision, or latest
        #[arg(default_value = "latest")]
        rev: String,
        #[arg(long, default_value = "2h")]
        window: String,
    },
    /// Unmatched-request spikes and whether a block stopped them
    Probes {
        #[arg(long, default_value = "24h")]
        since: String,
    },
}

pub struct Thresholds {
    pub slow_route_ms: f64,
    pub slow_route_min_hits: f64,
    pub php_bound_ms: f64,
    pub n_plus_one_queries: f64,
    pub high_memory_mb: f64,
    pub regression_pct: f64,
    pub probe_spike_factor: f64,
    pub probe_spike_min: f64,
    pub stale_cron_minutes: i64,
    pub stale_http_minutes: i64,
}

impl Default for Thresholds {
    fn default() -> Self {
        Self {
            slow_route_ms: 1000.0,
            slow_route_min_hits: 5.0,
            php_bound_ms: 300.0,
            n_plus_one_queries: 50.0,
            high_memory_mb: 64.0,
            regression_pct: 25.0,
            probe_spike_factor: 5.0,
            probe_spike_min: 50.0,
            stale_cron_minutes: 15,
            stale_http_minutes: 60,
        }
    }
}

pub struct Context {
    pub client: Client,
    pub app: String,
    pub env: String,
    pub thresholds: Thresholds,
    pub now: i64,
}

impl Context {
    pub fn selector(&self) -> String {
        format!(
            "app=\"{}\",env=\"{}\"",
            escape(&self.app),
            escape(&self.env)
        )
    }

    pub fn q(&self, template: &str, window: i64) -> String {
        template
            .replace("$S", &self.selector())
            .replace("$W", &format!("{}s", window))
    }

    pub fn end(&self) -> i64 {
        self.now - 60
    }

    pub fn header(&self, title: &str, start: i64, end: i64) -> String {
        format!(
            "{}  {} - {} UTC  app={} env={}",
            title,
            time::format_minute(start),
            time::format_minute(end),
            self.app,
            self.env
        )
    }
}

fn escape(value: &str) -> String {
    value.replace('\\', "\\\\").replace('"', "\\\"")
}

fn load_context(cli: &Cli) -> Result<Context, String> {
    let config = toolconfig::Config::load_or_exit("metrics");
    let backend = config
        .scalar("BACKEND")
        .unwrap_or_else(|| "prometheus".to_string());
    let client = Client::new(
        &backend,
        &config.require("URL"),
        &config.scalar("TOKEN").unwrap_or_default(),
        &config
            .scalar("DATASOURCE_UID")
            .unwrap_or_else(|| "victoriametrics".to_string()),
        cli.json,
    )?;
    if backend == "grafana" && config.scalar("TOKEN").unwrap_or_default().is_empty() {
        return Err("BACKEND=grafana needs a TOKEN (a Grafana service-account token, role Viewer) in config/tools/metrics.local".into());
    }

    let defaults = Thresholds::default();
    let float = |key: &str, default: f64| -> Result<f64, String> {
        config.number(key, default as usize).map(|n| n as f64)
    };
    let thresholds = Thresholds {
        slow_route_ms: float("SLOW_ROUTE_MS", defaults.slow_route_ms)?,
        slow_route_min_hits: float("SLOW_ROUTE_MIN_HITS", defaults.slow_route_min_hits)?,
        php_bound_ms: float("PHP_BOUND_MS", defaults.php_bound_ms)?,
        n_plus_one_queries: float("N_PLUS_ONE_QUERIES", defaults.n_plus_one_queries)?,
        high_memory_mb: float("HIGH_MEMORY_MB", defaults.high_memory_mb)?,
        regression_pct: float("REGRESSION_PCT", defaults.regression_pct)?,
        probe_spike_factor: float("PROBE_SPIKE_FACTOR", defaults.probe_spike_factor)?,
        probe_spike_min: float("PROBE_SPIKE_MIN", defaults.probe_spike_min)?,
        stale_cron_minutes: config
            .number("STALE_CRON_MINUTES", defaults.stale_cron_minutes as usize)?
            as i64,
        stale_http_minutes: config
            .number("STALE_HTTP_MINUTES", defaults.stale_http_minutes as usize)?
            as i64,
    };

    Ok(Context {
        client,
        app: cli
            .app
            .clone()
            .or_else(|| config.scalar("APP"))
            .unwrap_or_else(|| "app".to_string()),
        env: cli
            .env
            .clone()
            .or_else(|| config.scalar("ENV"))
            .unwrap_or_else(|| "prod".to_string()),
        thresholds,
        now: time::now(),
    })
}

fn run(cli: &Cli, ctx: &Context) -> Result<String, String> {
    match &cli.command {
        Command::Health => explore::health(ctx),
        Command::Names { filter, all } => explore::names(ctx, filter.as_deref(), *all),
        Command::Labels {
            metric,
            label,
            since,
        } => explore::labels(ctx, metric, label.as_deref(), since),
        Command::Query { promql, at } => explore::query(ctx, promql, at.as_deref()),
        Command::Range {
            promql,
            since,
            step,
            limit,
        } => explore::range(ctx, promql, since, step.as_deref(), *limit),
        Command::Markers { since, kind } => explore::markers(ctx, since, kind.as_deref()),
        Command::Report(report) => match report {
            Report::Daily { since } => reports::daily::run(ctx, since),
            Report::Issues { since } => reports::issues::run(ctx, since),
            Report::Performance {
                since,
                limit,
                route,
            } => reports::performance::run(ctx, since, *limit, route.as_deref()),
            Report::Deploy { rev, window } => reports::deploy::run(ctx, rev, window),
            Report::Probes { since } => reports::probes::run(ctx, since),
        },
    }
}

fn main() {
    let cli = Cli::parse();
    let ctx = match load_context(&cli) {
        Ok(ctx) => ctx,
        Err(message) => {
            eprintln!("error: {}", message);
            exit(2);
        }
    };

    match run(&cli, &ctx) {
        Ok(_) if cli.json => {
            println!(
                "{}",
                serde_json::to_string_pretty(&ctx.client.recorded()).unwrap_or_default()
            );
        }
        Ok(text) => print!("{}", text),
        Err(message) => {
            eprintln!("error: {}", message);
            exit(1);
        }
    }
}
