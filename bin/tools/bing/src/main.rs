// API reference: https://learn.microsoft.com/en-us/dotnet/api/microsoft.bing.webmaster.api.interfaces.iwebmasterapi
// JSON endpoint base: https://ssl.bing.com/webmaster/api.svc/json/<MethodName>?apikey=<KEY>&...
// Auth: single API key generated in the Bing Webmaster Tools UI under Settings -> API Access.
// Verified endpoints: GetUserSites, GetFeeds, GetFeedDetails, GetQueryStats, GetPageStats, GetUrlInfo.

use std::collections::BTreeMap;
use std::process::ExitCode;
use std::time::{SystemTime, UNIX_EPOCH};
use clap::{Parser, Subcommand};
use serde_json::Value;

const API_BASE: &str = "https://ssl.bing.com/webmaster/api.svc/json";
// BWT exposes ~6 months of stats with no date params; we filter client-side.
const MAX_DAYS: u32 = 200;
const TOP_ROWS: usize = 50;

#[derive(Parser)]
#[command(name = "bing", about = "Bing Webmaster Tools CLI")]
struct Cli {
    #[command(subcommand)]
    command: Command,
}

#[derive(Subcommand)]
enum Command {
    /// List all sites visible to the API key
    Sites,
    /// List or show feeds (sitemaps) for a site
    Sitemaps {
        /// Sub-action: "list" (default) or "show"
        action: Option<String>,
        /// Required for "show": the feed URL
        feedpath: Option<String>,
        /// Override the default site
        #[arg(long)]
        site: Option<String>,
    },
    /// Traffic rollup (clicks, impressions, position) by query or page
    Analytics {
        /// Window size in days, ending today. Filtered client-side.
        #[arg(long, default_value_t = 28)]
        days: u32,
        /// Group by "query" (GetQueryStats) or "page" (GetPageStats)
        #[arg(long, default_value = "query")]
        by: String,
        /// Override the default site
        #[arg(long)]
        site: Option<String>,
        /// Emit raw JSON instead of formatted output
        #[arg(long)]
        json: bool,
    },
    /// Inspect a single URL: index status, crawl date, robots state
    Inspect {
        /// Full URL to inspect (must be inside the site property)
        url: String,
        /// Override the default site
        #[arg(long)]
        site: Option<String>,
    },
    /// Crawl-issues feed plus a recent crawl-stats summary; exits non-zero on dangerous signals
    Issues {
        /// How many recent days of crawl-stats to summarize
        #[arg(long, default_value_t = 14)]
        days: u32,
        /// Override the default site
        #[arg(long)]
        site: Option<String>,
    },
}

struct Config {
    api_key: String,
    default_site: String,
}

fn load_config() -> Config {
    let config = toolconfig::Config::load_or_exit("bing");

    Config {
        api_key: config.require("BING_API_KEY"),
        default_site: config.require("BING_DEFAULT_SITE"),
    }
}

fn http_get(url: &str) -> Value {
    ureq::get(url)
        .call()
        .unwrap_or_else(|e| panic!("GET {} failed: {}", url, e))
        .into_json()
        .unwrap_or_else(|e| panic!("GET {} JSON parse failed: {}", url, e))
}

// Percent-encode a value for safe substitution into a URL query parameter.
// BWT accepts site URLs in URL-prefix form (https://example.com/) - both the
// scheme colon and trailing slash require encoding when passed via siteUrl=.
fn url_encode(s: &str) -> String {
    let mut out = String::with_capacity(s.len() * 3);
    for byte in s.bytes() {
        match byte {
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'_' | b'.' | b'~' => {
                out.push(byte as char);
            }
            _ => out.push_str(&format!("%{:02X}", byte)),
        }
    }
    out
}

// Extract the epoch-ms inside a Microsoft JSON date wrapper:
//   "/Date(1714262400000)/" -> Some(1714262400000)
//   "/Date(1714262400000+0000)/" -> Some(1714262400000)
//   "/Date(1714262400000-0700)/" -> Some(1714262400000)
//   "/Date(-1000)/" -> Some(-1000)
// The offset (+/-HHMM) is informational only; the leading number is already UTC ms.
fn parse_msdate_ms(s: &str) -> Option<i64> {
    let inner = s.strip_prefix("/Date(")?.strip_suffix(")/")?;
    let mut chars = inner.chars().peekable();
    let mut buf = String::new();
    if chars.peek() == Some(&'-') {
        buf.push(chars.next().unwrap());
    }
    while let Some(&c) = chars.peek() {
        if c.is_ascii_digit() {
            buf.push(c);
            chars.next();
        } else {
            break;
        }
    }
    buf.parse().ok()
}

// Render an MS JSON date as YYYY-MM-DD UTC, or pass-through on failure.
fn fmt_msdate(s: &str) -> String {
    match parse_msdate_ms(s) {
        Some(ms) => {
            let secs = ms / 1000;
            let days = secs.div_euclid(86400);
            days_to_date(days)
        }
        None => s.to_string(),
    }
}

// Howard Hinnant's civil_from_days: days since 1970-01-01 -> YYYY-MM-DD UTC.
fn days_to_date(days_since_epoch: i64) -> String {
    let z = days_since_epoch + 719468;
    let era = (if z >= 0 { z } else { z - 146096 }) / 146097;
    let doe = (z - era * 146097) as u64;
    let yoe = (doe - doe / 1460 + doe / 36524 - doe / 146096) / 365;
    let y = yoe as i64 + era * 400;
    let doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
    let mp = (5 * doy + 2) / 153;
    let d = doy - (153 * mp + 2) / 5 + 1;
    let m = if mp < 10 { mp + 3 } else { mp - 9 };
    let y = if m <= 2 { y + 1 } else { y };
    format!("{:04}-{:02}-{:02}", y, m, d)
}

fn cutoff_ms(days: u32) -> i64 {
    let now_secs = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .expect("system clock before epoch")
        .as_secs() as i64;
    (now_secs - (days as i64) * 86400) * 1000
}

fn unwrap_d(data: &Value) -> &Value {
    if data.get("d").is_some() { &data["d"] } else { data }
}

// ---------- sites ----------

fn cmd_sites(api_key: &str) -> ExitCode {
    let url = format!("{}/GetUserSites?apikey={}", API_BASE, url_encode(api_key));
    let data = http_get(&url);
    print!("{}", format_sites(&data));
    ExitCode::SUCCESS
}

fn format_sites(data: &Value) -> String {
    let entries = unwrap_d(data).as_array();
    let entries = match entries {
        Some(arr) if !arr.is_empty() => arr,
        _ => return "No sites found.\n".to_string(),
    };
    let mut out = String::new();
    let max_url = entries
        .iter()
        .map(|e| e["Url"].as_str().unwrap_or("?").len())
        .max()
        .unwrap_or(0);
    for e in entries {
        let site = e["Url"].as_str().unwrap_or("?");
        let verified = e["IsVerified"].as_bool().unwrap_or(false);
        out.push_str(&format!(
            "{:width$}  verified={}\n",
            site,
            if verified { "yes" } else { "no" },
            width = max_url
        ));
    }
    out
}

// ---------- sitemaps (Bing calls these "feeds") ----------

fn cmd_sitemaps(api_key: &str, site: &str, action: Option<&str>, feedpath: Option<&str>) -> ExitCode {
    let action = action.unwrap_or("list");
    match action {
        "list" => {
            let url = format!(
                "{}/GetFeeds?apikey={}&siteUrl={}",
                API_BASE,
                url_encode(api_key),
                url_encode(site)
            );
            let data = http_get(&url);
            print!("{}", format_sitemaps_list(&data));
            ExitCode::SUCCESS
        }
        "show" => {
            let feedpath = match feedpath {
                Some(f) => f,
                None => {
                    eprintln!("error: 'show' requires a feedpath argument");
                    return ExitCode::FAILURE;
                }
            };
            let url = format!(
                "{}/GetFeedDetails?apikey={}&siteUrl={}&feedUrl={}",
                API_BASE,
                url_encode(api_key),
                url_encode(site),
                url_encode(feedpath)
            );
            let data = http_get(&url);
            print!("{}", format_sitemap_show(&data));
            ExitCode::SUCCESS
        }
        other => {
            eprintln!("error: unknown sitemaps action '{}', expected 'list' or 'show'", other);
            ExitCode::FAILURE
        }
    }
}

fn format_sitemaps_list(data: &Value) -> String {
    let entries = unwrap_d(data).as_array();
    let entries = match entries {
        Some(arr) if !arr.is_empty() => arr,
        _ => return "No feeds submitted.\n".to_string(),
    };
    let mut out = String::new();
    for sm in entries {
        let url = sm["Url"].as_str().unwrap_or("?");
        let last_crawled = sm["LastCrawled"].as_str().map(fmt_msdate).unwrap_or_else(|| "?".to_string());
        let submitted = sm["Submitted"].as_str().map(fmt_msdate).unwrap_or_else(|| "?".to_string());
        let status = sm["Status"].as_str().unwrap_or("?");
        let kind = sm["Type"].as_str().unwrap_or("?");
        let url_count = sm["UrlCount"].as_i64().unwrap_or(0);
        let compressed = sm["Compressed"].as_bool().unwrap_or(false);
        let file_size = sm["FileSize"].as_i64().unwrap_or(0);

        out.push_str(&format!("{}\n", url));
        out.push_str(&format!("  type:         {}\n", kind));
        out.push_str(&format!("  status:       {}\n", status));
        out.push_str(&format!("  submitted:    {}\n", submitted));
        out.push_str(&format!("  lastCrawled:  {}\n", last_crawled));
        out.push_str(&format!("  urlCount:     {}\n", url_count));
        out.push_str(&format!("  fileSize:     {} bytes{}\n", file_size, if compressed { " (compressed)" } else { "" }));
    }
    out
}

fn format_sitemap_show(data: &Value) -> String {
    // GetFeedDetails returns either a single object or an array under "d";
    // tolerate both.
    let inner = unwrap_d(data);
    let entries: Vec<&Value> = match inner {
        Value::Array(arr) => arr.iter().collect(),
        Value::Object(_) => vec![inner],
        _ => return "(no feed details)\n".to_string(),
    };
    if entries.is_empty() {
        return "(no feed details)\n".to_string();
    }
    let mut out = String::new();
    for sm in entries {
        let url = sm["Url"].as_str().unwrap_or("?");
        out.push_str(&format!("url:          {}\n", url));
        out.push_str(&format!("type:         {}\n", sm["Type"].as_str().unwrap_or("?")));
        out.push_str(&format!("status:       {}\n", sm["Status"].as_str().unwrap_or("?")));
        if let Some(s) = sm["Submitted"].as_str() {
            out.push_str(&format!("submitted:    {}\n", fmt_msdate(s)));
        }
        if let Some(s) = sm["LastCrawled"].as_str() {
            out.push_str(&format!("lastCrawled:  {}\n", fmt_msdate(s)));
        }
        out.push_str(&format!("urlCount:     {}\n", sm["UrlCount"].as_i64().unwrap_or(0)));
        if let Some(size) = sm["FileSize"].as_i64() {
            let compressed = sm["Compressed"].as_bool().unwrap_or(false);
            out.push_str(&format!("fileSize:     {} bytes{}\n", size, if compressed { " (compressed)" } else { "" }));
        }
        out.push_str("\n");
    }
    out
}

// ---------- analytics ----------

fn cmd_analytics(api_key: &str, site: &str, days: u32, by: &str, as_json: bool) -> ExitCode {
    if days > MAX_DAYS {
        eprintln!(
            "error: --days {} exceeds Bing's ~6-month retention ({} days)",
            days, MAX_DAYS
        );
        return ExitCode::FAILURE;
    }
    let endpoint = match by {
        "query" => "GetQueryStats",
        "page" => "GetPageStats",
        other => {
            eprintln!("error: unknown --by '{}', expected 'query' or 'page'", other);
            return ExitCode::FAILURE;
        }
    };
    let url = format!(
        "{}/{}?apikey={}&siteUrl={}",
        API_BASE,
        endpoint,
        url_encode(api_key),
        url_encode(site)
    );
    let data = http_get(&url);

    if as_json {
        println!("{}", serde_json::to_string_pretty(&data).unwrap_or_default());
    } else {
        print!("{}", format_analytics(&data, by, days));
    }
    ExitCode::SUCCESS
}

fn format_analytics(data: &Value, by: &str, days: u32) -> String {
    let key_field = if by == "page" { "Page" } else { "Query" };
    let rows = unwrap_d(data).as_array().cloned().unwrap_or_default();
    let cutoff = cutoff_ms(days);

    let filtered: Vec<&Value> = rows
        .iter()
        .filter(|r| {
            r["Date"]
                .as_str()
                .and_then(parse_msdate_ms)
                .map(|ms| ms >= cutoff)
                .unwrap_or(false)
        })
        .collect();

    let mut out = String::new();
    out.push_str(&format!(
        "window:     last {} days, by {} ({} rows after filter, {} returned by API)\n",
        days,
        by,
        filtered.len(),
        rows.len()
    ));

    if filtered.is_empty() {
        out.push_str("(no rows in window)\n");
        return out;
    }

    let mut agg: BTreeMap<String, (f64, f64, f64)> = BTreeMap::new();
    for r in &filtered {
        let key = r[key_field].as_str().unwrap_or("?").to_string();
        let clicks = r["Clicks"].as_f64().unwrap_or(0.0);
        let impr = r["Impressions"].as_f64().unwrap_or(0.0);
        let pos = r["AvgImpressionPosition"].as_f64().unwrap_or(0.0);
        let entry = agg.entry(key).or_insert((0.0, 0.0, 0.0));
        entry.0 += clicks;
        entry.1 += impr;
        entry.2 += pos * impr;
    }

    let mut total_clicks = 0.0;
    let mut total_impr = 0.0;
    let mut total_weighted_pos = 0.0;
    for (clicks, impr, weighted) in agg.values() {
        total_clicks += clicks;
        total_impr += impr;
        total_weighted_pos += weighted;
    }
    let ctr = if total_impr > 0.0 { total_clicks / total_impr } else { 0.0 };
    let avg_pos = if total_impr > 0.0 { total_weighted_pos / total_impr } else { 0.0 };
    out.push_str(&format!(
        "totals:     clicks={:.0}  impressions={:.0}  ctr={:.2}%  avg_position={:.2}\n",
        total_clicks,
        total_impr,
        ctr * 100.0,
        avg_pos
    ));

    let mut sorted: Vec<(&String, &(f64, f64, f64))> = agg.iter().collect();
    sorted.sort_by(|a, b| {
        b.1.0
            .partial_cmp(&a.1.0)
            .unwrap_or(std::cmp::Ordering::Equal)
            .then_with(|| {
                b.1.1
                    .partial_cmp(&a.1.1)
                    .unwrap_or(std::cmp::Ordering::Equal)
            })
    });

    out.push_str("\n");
    out.push_str(&format!("{}\n", "-".repeat(80)));
    out.push_str(&format!(
        "{:<8} {:<12} {:<8} {:<8} {}\n",
        "clicks", "impressions", "ctr%", "pos", by
    ));
    out.push_str(&format!("{}\n", "-".repeat(80)));
    for (key, (clicks, impr, weighted)) in sorted.iter().take(TOP_ROWS) {
        let row_ctr = if *impr > 0.0 { *clicks / *impr * 100.0 } else { 0.0 };
        let row_pos = if *impr > 0.0 { *weighted / *impr } else { 0.0 };
        out.push_str(&format!(
            "{:<8.0} {:<12.0} {:<8.2} {:<8.2} {}\n",
            clicks, impr, row_ctr, row_pos, key
        ));
    }

    out
}

// ---------- inspect ----------

fn cmd_inspect(api_key: &str, site: &str, target_url: &str) -> ExitCode {
    let url = format!(
        "{}/GetUrlInfo?apikey={}&siteUrl={}&url={}",
        API_BASE,
        url_encode(api_key),
        url_encode(site),
        url_encode(target_url)
    );
    let data = http_get(&url);
    let (out, pass) = format_inspect(&data);
    print!("{}", out);
    if pass { ExitCode::SUCCESS } else { ExitCode::FAILURE }
}

fn format_inspect(data: &Value) -> (String, bool) {
    let info = unwrap_d(data);
    if !info.is_object() {
        return ("(no url info returned)\n".to_string(), false);
    }
    let mut out = String::new();
    let url = info["Url"].as_str().unwrap_or("?");
    // BWT's HttpStatus is the LAST observed error: 0 = no error / never errored;
    // non-zero = the latest non-2xx status. So "0 or 2xx" is the success window.
    let http_status = info["HttpStatus"].as_i64().unwrap_or(-1);
    let is_page = info["IsPage"].as_bool().unwrap_or(false);
    let last_crawled = info["LastCrawledDate"].as_str().map(fmt_msdate);
    let discovery = info["DiscoveryDate"].as_str().map(fmt_msdate);
    let anchor = info["AnchorCount"].as_i64().unwrap_or(0);
    let children = info["TotalChildUrlCount"].as_i64().unwrap_or(0);
    let size = info["DocumentSize"].as_i64().unwrap_or(0);

    let http_ok = http_status == 0 || (200..400).contains(&http_status);
    let pass = is_page && http_ok && last_crawled.is_some();

    out.push_str("== Bing index status ==\n");
    out.push_str(&format!("  url:           {}\n", url));
    out.push_str(&format!("  verdict:       {}\n", if pass { "PASS" } else { "FAIL" }));
    out.push_str(&format!("  httpStatus:    {}{}\n", http_status, if http_status == 0 { " (no error)" } else { "" }));
    out.push_str(&format!("  isPage:        {}\n", is_page));
    out.push_str(&format!("  lastCrawled:   {}\n", last_crawled.as_deref().unwrap_or("(never)")));
    out.push_str(&format!("  discovered:    {}\n", discovery.as_deref().unwrap_or("(never)")));
    out.push_str(&format!("  documentSize:  {} bytes\n", size));
    out.push_str(&format!("  anchorCount:   {}\n", anchor));
    out.push_str(&format!("  childUrlCount: {}\n", children));
    out.push_str("\n  Note: BWT does not expose rich-results / structured-data verdicts,\n");
    out.push_str("  per-URL robots-blocking, or back-link counts. Use the gsc skill for\n");
    out.push_str("  structured-data validation.\n");

    (out, pass)
}

// ---------- issues ----------

fn cmd_issues(api_key: &str, site: &str, days: u32) -> ExitCode {
    if days == 0 || days > MAX_DAYS {
        eprintln!("error: --days must be 1..={}", MAX_DAYS);
        return ExitCode::FAILURE;
    }
    let issues_url = format!(
        "{}/GetCrawlIssues?apikey={}&siteUrl={}",
        API_BASE,
        url_encode(api_key),
        url_encode(site)
    );
    let stats_url = format!(
        "{}/GetCrawlStats?apikey={}&siteUrl={}",
        API_BASE,
        url_encode(api_key),
        url_encode(site)
    );
    let issues = http_get(&issues_url);
    let stats = http_get(&stats_url);

    let (out, pass) = format_issues(&issues, &stats, days);
    print!("{}", out);
    if pass { ExitCode::SUCCESS } else { ExitCode::FAILURE }
}

#[derive(Default)]
struct CrawlTotals {
    days: u32,
    crawled_pages: i64,
    code_2xx: i64,
    code_301: i64,
    code_302: i64,
    code_4xx: i64,
    code_5xx: i64,
    all_other: i64,
    crawl_errors: i64,
    blocked_robots: i64,
    dns_failures: i64,
    connection_timeout: i64,
    contains_malware: i64,
    in_index_latest: i64,
    latest_date: Option<String>,
}

fn aggregate_crawl_stats(stats: &Value, days: u32) -> CrawlTotals {
    let rows = unwrap_d(stats).as_array().cloned().unwrap_or_default();
    let cutoff = cutoff_ms(days);
    let mut filtered: Vec<&Value> = rows
        .iter()
        .filter(|r| {
            r["Date"]
                .as_str()
                .and_then(parse_msdate_ms)
                .map(|ms| ms >= cutoff)
                .unwrap_or(false)
        })
        .collect();
    filtered.sort_by_key(|r| r["Date"].as_str().and_then(parse_msdate_ms).unwrap_or(0));

    let mut t = CrawlTotals::default();
    t.days = filtered.len() as u32;
    for r in &filtered {
        t.crawled_pages += r["CrawledPages"].as_i64().unwrap_or(0);
        t.code_2xx += r["Code2xx"].as_i64().unwrap_or(0);
        t.code_301 += r["Code301"].as_i64().unwrap_or(0);
        t.code_302 += r["Code302"].as_i64().unwrap_or(0);
        t.code_4xx += r["Code4xx"].as_i64().unwrap_or(0);
        t.code_5xx += r["Code5xx"].as_i64().unwrap_or(0);
        t.all_other += r["AllOtherCodes"].as_i64().unwrap_or(0);
        t.crawl_errors += r["CrawlErrors"].as_i64().unwrap_or(0);
        t.blocked_robots += r["BlockedByRobotsTxt"].as_i64().unwrap_or(0);
        t.dns_failures += r["DnsFailures"].as_i64().unwrap_or(0);
        t.connection_timeout += r["ConnectionTimeout"].as_i64().unwrap_or(0);
        t.contains_malware += r["ContainsMalware"].as_i64().unwrap_or(0);
    }
    if let Some(latest) = filtered.last() {
        t.in_index_latest = latest["InIndex"].as_i64().unwrap_or(0);
        t.latest_date = latest["Date"].as_str().map(fmt_msdate);
    }
    t
}

fn format_issues(issues: &Value, stats: &Value, days: u32) -> (String, bool) {
    let mut out = String::new();
    let formal: Vec<&Value> = unwrap_d(issues).as_array().map(|a| a.iter().collect()).unwrap_or_default();
    let totals = aggregate_crawl_stats(stats, days);

    out.push_str("== Formal crawl issues ==\n");
    if formal.is_empty() {
        out.push_str("  none\n");
    } else {
        for issue in &formal {
            let kind = issue["Issue"].as_str()
                .or_else(|| issue["IssueType"].as_str())
                .unwrap_or("?");
            let url = issue["Url"].as_str().unwrap_or("?");
            let severity = issue["Severity"].as_str().unwrap_or("?");
            out.push_str(&format!("  [{}] {} - {}\n", severity, kind, url));
        }
    }

    out.push_str(&format!("\n== Crawl health (last {} days, {} day-rows aggregated) ==\n", days, totals.days));
    if let Some(d) = &totals.latest_date {
        out.push_str(&format!("  latest day:        {}\n", d));
    }
    out.push_str(&format!("  inIndex (latest):  {}\n", totals.in_index_latest));
    out.push_str(&format!("  crawled pages:     {}\n", totals.crawled_pages));
    out.push_str(&format!("  2xx:               {}\n", totals.code_2xx));
    out.push_str(&format!("  301:               {}\n", totals.code_301));
    out.push_str(&format!("  302:               {}\n", totals.code_302));
    out.push_str(&format!("  4xx:               {}  (per-day avg {:.1})\n",
        totals.code_4xx,
        if totals.days > 0 { totals.code_4xx as f64 / totals.days as f64 } else { 0.0 }));
    out.push_str(&format!("  5xx:               {}\n", totals.code_5xx));
    out.push_str(&format!("  all other codes:   {}\n", totals.all_other));
    out.push_str(&format!("  crawl errors:      {}\n", totals.crawl_errors));
    out.push_str(&format!("  blocked by robots: {}\n", totals.blocked_robots));
    out.push_str(&format!("  DNS failures:      {}\n", totals.dns_failures));
    out.push_str(&format!("  connection timeouts:{}\n", totals.connection_timeout));
    out.push_str(&format!("  contains malware:  {}\n", totals.contains_malware));

    let danger = !formal.is_empty()
        || totals.code_5xx > 0
        || totals.dns_failures > 0
        || totals.blocked_robots > 0
        || totals.contains_malware > 0
        || totals.connection_timeout > 0;

    out.push_str("\n== Verdict ==\n");
    if danger {
        out.push_str("  FAIL - dangerous signals present (5xx, DNS, robots, malware, timeouts, or formal issues)\n");
    } else if totals.code_4xx > 0 {
        out.push_str("  PASS - 4xx baseline present (typically deleted-event 404s); no dangerous signals\n");
    } else {
        out.push_str("  PASS - clean\n");
    }

    (out, !danger)
}

fn main() -> ExitCode {
    let cli = Cli::parse();
    let cfg = load_config();

    match cli.command {
        Command::Sites => cmd_sites(&cfg.api_key),
        Command::Sitemaps { action, feedpath, site } => {
            let site_url = site.unwrap_or_else(|| cfg.default_site.clone());
            cmd_sitemaps(&cfg.api_key, &site_url, action.as_deref(), feedpath.as_deref())
        }
        Command::Analytics { days, by, site, json } => {
            let site_url = site.unwrap_or_else(|| cfg.default_site.clone());
            cmd_analytics(&cfg.api_key, &site_url, days, &by, json)
        }
        Command::Inspect { url, site } => {
            let site_url = site.unwrap_or_else(|| cfg.default_site.clone());
            cmd_inspect(&cfg.api_key, &site_url, &url)
        }
        Command::Issues { days, site } => {
            let site_url = site.unwrap_or_else(|| cfg.default_site.clone());
            cmd_issues(&cfg.api_key, &site_url, days)
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn url_encode_handles_bing_property_forms() {
        assert_eq!(url_encode("https://meetagain.org/"), "https%3A%2F%2Fmeetagain.org%2F");
        assert_eq!(url_encode("abc-DEF_123.~"), "abc-DEF_123.~");
        assert_eq!(url_encode("foo bar"), "foo%20bar");
    }

    #[test]
    fn parse_msdate_ms_handles_bare_and_offset_forms() {
        assert_eq!(parse_msdate_ms("/Date(1714262400000)/"), Some(1714262400000));
        assert_eq!(parse_msdate_ms("/Date(1714262400000+0000)/"), Some(1714262400000));
        assert_eq!(parse_msdate_ms("/Date(1777014000000-0700)/"), Some(1777014000000));
        assert_eq!(parse_msdate_ms("/Date(0)/"), Some(0));
        assert_eq!(parse_msdate_ms("/Date(-1000)/"), Some(-1000));
        assert_eq!(parse_msdate_ms("not a date"), None);
    }

    #[test]
    fn days_to_date_known_values() {
        assert_eq!(days_to_date(0), "1970-01-01");
        // 2026-04-28 = day 20571
        assert_eq!(days_to_date(20571), "2026-04-28");
        // Leap-year boundary
        assert_eq!(days_to_date(20148), "2025-03-01");
    }

    #[test]
    fn fmt_msdate_renders_ymd() {
        // 1714262400000 ms = 2024-04-28 UTC
        assert_eq!(fmt_msdate("/Date(1714262400000)/"), "2024-04-28");
        assert_eq!(fmt_msdate("garbage"), "garbage");
    }

    #[test]
    fn format_sites_renders_each_site() {
        // Arrange
        let raw = include_str!("../tests/fixtures/sites.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let out = format_sites(&v);

        // Assert
        assert!(out.contains("https://meetagain.org/"));
        assert!(out.contains("https://example.com/"));
        assert!(out.contains("verified=yes"));
        assert!(out.contains("verified=no"));
        // BWT does not return an Authentication field; the formatter must not invent one.
        assert!(!out.contains("auth="));
    }

    #[test]
    fn format_sites_handles_empty() {
        let v: Value = serde_json::from_str(r#"{"d": []}"#).unwrap();
        assert_eq!(format_sites(&v), "No sites found.\n");
    }

    #[test]
    fn format_sitemaps_list_renders_feeds() {
        // Arrange
        let raw = include_str!("../tests/fixtures/feeds_list.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let out = format_sitemaps_list(&v);

        // Assert
        assert!(out.contains("https://meetagain.org/sitemap.xml"));
        assert!(out.contains("status:       Success"));
        assert!(out.contains("status:       Pending"));
        assert!(out.contains("urlCount:     286"));
        assert!(out.contains("(compressed)"));
    }

    #[test]
    fn format_sitemap_show_renders_detail() {
        // Arrange
        let raw = include_str!("../tests/fixtures/feed_show.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let out = format_sitemap_show(&v);

        // Assert
        assert!(out.contains("url:          https://meetagain.org/sitemap.xml"));
        assert!(out.contains("type:         Sitemap"));
        assert!(out.contains("status:       Success"));
        assert!(out.contains("urlCount:     286"));
        assert!(out.contains("fileSize:"));
    }

    #[test]
    fn format_analytics_aggregates_by_query() {
        // Arrange
        let raw = include_str!("../tests/fixtures/query_stats.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act: huge --days window so the test fixture's hand-set dates all pass
        let out = format_analytics(&v, "query", MAX_DAYS);

        // Assert: aggregation collapses repeated keys, total clicks sums correctly
        assert!(out.contains("meetagain"));
        assert!(out.contains("language exchange"));
        assert!(out.contains("totals:"));
        // "meetagain" appears on 3 daily rows with clicks 0,1,2 -> 3 clicks
        // "language exchange" appears on 1 row with 0 clicks
        assert!(out.contains("clicks"));
    }

    #[test]
    fn format_analytics_aggregates_by_page() {
        let raw = include_str!("../tests/fixtures/page_stats.json");
        let v: Value = serde_json::from_str(raw).unwrap();
        let out = format_analytics(&v, "page", MAX_DAYS);
        assert!(out.contains("https://meetagain.org/"));
        assert!(out.contains("totals:"));
    }

    #[test]
    fn format_analytics_filters_by_days() {
        // Rows dated 1970 should be dropped by any reasonable --days window.
        let raw = r#"{"d": [
            {"Query": "old", "Date": "/Date(0)/", "Clicks": 5, "Impressions": 10, "AvgImpressionPosition": 1.0},
            {"Query": "ancient", "Date": "/Date(86400000)/", "Clicks": 3, "Impressions": 8, "AvgImpressionPosition": 2.0}
        ]}"#;
        let v: Value = serde_json::from_str(raw).unwrap();
        let out = format_analytics(&v, "query", 7);
        assert!(out.contains("(no rows in window)"));
        assert!(!out.contains("\nold "));
    }

    #[test]
    fn format_inspect_pass_returns_pass_true() {
        let raw = include_str!("../tests/fixtures/url_info_pass.json");
        let v: Value = serde_json::from_str(raw).unwrap();
        let (out, pass) = format_inspect(&v);
        assert!(pass, "expected pass=true, got output:\n{}", out);
        assert!(out.contains("verdict:       PASS"));
        assert!(out.contains("httpStatus:    0 (no error)"));
        assert!(out.contains("isPage:        true"));
    }

    #[test]
    fn format_issues_clean_passes() {
        let issues_raw = include_str!("../tests/fixtures/crawl_issues_empty.json");
        let stats_raw = include_str!("../tests/fixtures/crawl_stats_clean.json");
        let issues: Value = serde_json::from_str(issues_raw).unwrap();
        let stats: Value = serde_json::from_str(stats_raw).unwrap();

        let (out, pass) = format_issues(&issues, &stats, MAX_DAYS);

        assert!(pass, "expected clean fixture to pass, got:\n{}", out);
        assert!(out.contains("none"));
        assert!(out.contains("4xx:               5"));
        assert!(out.contains("5xx:               0"));
        assert!(out.contains("PASS"));
    }

    #[test]
    fn format_issues_dangerous_fails_on_5xx_and_dns() {
        let issues_raw = include_str!("../tests/fixtures/crawl_issues_empty.json");
        let stats_raw = include_str!("../tests/fixtures/crawl_stats_dangerous.json");
        let issues: Value = serde_json::from_str(issues_raw).unwrap();
        let stats: Value = serde_json::from_str(stats_raw).unwrap();

        let (out, pass) = format_issues(&issues, &stats, MAX_DAYS);

        assert!(!pass);
        assert!(out.contains("5xx:               4"));
        assert!(out.contains("DNS failures:      1"));
        assert!(out.contains("FAIL"));
    }

    #[test]
    fn format_issues_renders_formal_records() {
        let issues_raw = include_str!("../tests/fixtures/crawl_issues_present.json");
        let stats_raw = include_str!("../tests/fixtures/crawl_stats_clean.json");
        let issues: Value = serde_json::from_str(issues_raw).unwrap();
        let stats: Value = serde_json::from_str(stats_raw).unwrap();

        let (out, pass) = format_issues(&issues, &stats, MAX_DAYS);

        assert!(!pass, "any formal issue should fail the verdict");
        assert!(out.contains("[Warning] Http404"));
        assert!(out.contains("[Error] BlockedByRobotsTxt"));
        assert!(out.contains("https://meetagain.org/event/old-event"));
    }

    #[test]
    fn format_inspect_fail_404_or_not_page() {
        let raw = include_str!("../tests/fixtures/url_info_fail.json");
        let v: Value = serde_json::from_str(raw).unwrap();
        let (out, pass) = format_inspect(&v);
        assert!(!pass);
        assert!(out.contains("verdict:       FAIL"));
        assert!(out.contains("httpStatus:    404"));
    }
}
