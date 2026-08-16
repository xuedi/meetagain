use std::fs;
use std::process::ExitCode;
use std::time::{SystemTime, UNIX_EPOCH};
use clap::{Parser, Subcommand};
use jsonwebtoken::{encode, Algorithm, EncodingKey, Header};
use serde_json::{json, Value};

const SCOPE: &str = "https://www.googleapis.com/auth/webmasters.readonly";
const ANALYTICS_ROW_LIMIT: u64 = 25000;
const MAX_DAYS: u32 = 480;

#[derive(Parser)]
#[command(name = "gsc", about = "Google Search Console CLI")]
struct Cli {
    #[command(subcommand)]
    command: Command,
}

#[derive(Subcommand)]
enum Command {
    /// List all GSC site properties accessible to the service account
    Sites,
    /// List or show sitemaps for a site property
    Sitemaps {
        /// Sub-action: "list" (default) or "show"
        action: Option<String>,
        /// Required for "show": the sitemap feedpath URL
        feedpath: Option<String>,
        /// Override the default site
        #[arg(long)]
        site: Option<String>,
    },
    /// Search analytics rollup (clicks, impressions, CTR, position)
    Analytics {
        /// Window size in days, ending today. Max 480 (16-month GSC limit).
        #[arg(long, default_value_t = 28)]
        days: u32,
        /// Comma-separated dimensions: query, page, country, device, date, searchAppearance
        #[arg(long, default_value = "query,page")]
        dimensions: String,
        /// Override the default site
        #[arg(long)]
        site: Option<String>,
        /// Emit raw JSON instead of formatted output
        #[arg(long)]
        json: bool,
    },
    /// Inspect a single URL: index status, mobile usability, rich results
    Inspect {
        /// Full URL to inspect (must be inside the site property)
        url: String,
        /// Override the default site
        #[arg(long)]
        site: Option<String>,
    },
}

struct Config {
    service_account_json: String,
    default_site: String,
}

struct ServiceAccount {
    client_email: String,
    private_key: String,
    token_uri: String,
}

fn load_config() -> Config {
    let config = toolconfig::Config::load_or_exit("gsc");

    Config {
        service_account_json: config.require("GSC_SERVICE_ACCOUNT_JSON"),
        default_site: config.require("GSC_DEFAULT_SITE"),
    }
}

fn load_service_account(path: &str) -> ServiceAccount {
    let content = fs::read_to_string(path)
        .unwrap_or_else(|e| panic!("cannot read service account JSON at {}: {}", path, e));
    let v: Value = serde_json::from_str(&content)
        .unwrap_or_else(|e| panic!("invalid JSON in service account file: {}", e));

    ServiceAccount {
        client_email: v["client_email"]
            .as_str()
            .expect("service account JSON missing client_email")
            .to_string(),
        private_key: v["private_key"]
            .as_str()
            .expect("service account JSON missing private_key")
            .to_string(),
        token_uri: v["token_uri"]
            .as_str()
            .unwrap_or("https://oauth2.googleapis.com/token")
            .to_string(),
    }
}

// Token is not cached to disk: every CLI invocation is short-lived and disk
// caching would require safely storing a bearer credential. The ~1s cost of
// minting + exchanging on each invocation is the deliberate tradeoff.
fn mint_jwt(sa: &ServiceAccount, scope: &str, now: u64) -> String {
    let claims = json!({
        "iss": sa.client_email,
        "scope": scope,
        "aud": sa.token_uri,
        "iat": now,
        "exp": now + 3600,
    });
    let header = Header::new(Algorithm::RS256);
    let key = EncodingKey::from_rsa_pem(sa.private_key.as_bytes())
        .unwrap_or_else(|e| panic!("invalid RSA private key: {}", e));
    encode(&header, &claims, &key)
        .unwrap_or_else(|e| panic!("JWT encoding failed: {}", e))
}

fn exchange_token(jwt: &str, token_uri: &str) -> String {
    let resp: Value = ureq::post(token_uri)
        .send_form([
            ("grant_type", "urn:ietf:params:oauth:grant-type:jwt-bearer"),
            ("assertion", jwt),
        ])
        .unwrap_or_else(|e| panic!("token exchange failed: {}", e))
        .body_mut()
        .read_json()
        .unwrap_or_else(|e| panic!("token response not JSON: {}", e));

    resp["access_token"]
        .as_str()
        .unwrap_or_else(|| panic!("no access_token in response: {}", resp))
        .to_string()
}

fn get_access_token(sa: &ServiceAccount) -> String {
    let now = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .expect("system clock before epoch")
        .as_secs();
    let jwt = mint_jwt(sa, SCOPE, now);
    exchange_token(&jwt, &sa.token_uri)
}

fn http_get(url: &str, token: &str) -> Value {
    ureq::get(url)
        .header("Authorization", &format!("Bearer {}", token))
        .call()
        .unwrap_or_else(|e| panic!("GET {} failed: {}", url, e))
        .body_mut()
        .read_json()
        .unwrap_or_else(|e| panic!("GET {} JSON parse failed: {}", url, e))
}

fn http_post(url: &str, token: &str, body: &Value) -> Value {
    ureq::post(url)
        .header("Authorization", &format!("Bearer {}", token))
        .send_json(body)
        .unwrap_or_else(|e| panic!("POST {} failed: {}", url, e))
        .body_mut()
        .read_json()
        .unwrap_or_else(|e| panic!("POST {} JSON parse failed: {}", url, e))
}

// Percent-encode a value for safe substitution into a URL path segment.
// GSC uses property identifiers like "sc-domain:meetagain.org" and
// "https://meetagain.org/" - both require encoding.
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

// ---------- sites ----------

fn cmd_sites(token: &str) -> ExitCode {
    let url = "https://searchconsole.googleapis.com/webmasters/v3/sites";
    let data = http_get(url, token);
    print!("{}", format_sites(&data));
    ExitCode::SUCCESS
}

fn format_sites(data: &Value) -> String {
    let entries = data["siteEntry"].as_array();
    let entries = match entries {
        Some(arr) if !arr.is_empty() => arr,
        _ => return "No site properties found.\n".to_string(),
    };
    let mut out = String::new();
    let max_url = entries
        .iter()
        .map(|e| e["siteUrl"].as_str().unwrap_or("?").len())
        .max()
        .unwrap_or(0);
    for e in entries {
        let site = e["siteUrl"].as_str().unwrap_or("?");
        let perm = e["permissionLevel"].as_str().unwrap_or("?");
        out.push_str(&format!("{:width$}  {}\n", site, perm, width = max_url));
    }
    out
}

// ---------- sitemaps ----------

fn cmd_sitemaps(token: &str, site: &str, action: Option<&str>, feedpath: Option<&str>) -> ExitCode {
    let action = action.unwrap_or("list");
    match action {
        "list" => {
            let url = format!(
                "https://searchconsole.googleapis.com/webmasters/v3/sites/{}/sitemaps",
                url_encode(site)
            );
            let data = http_get(&url, token);
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
                "https://searchconsole.googleapis.com/webmasters/v3/sites/{}/sitemaps/{}",
                url_encode(site),
                url_encode(feedpath)
            );
            let data = http_get(&url, token);
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
    let entries = data["sitemap"].as_array();
    let entries = match entries {
        Some(arr) if !arr.is_empty() => arr,
        _ => return "No sitemaps submitted.\n".to_string(),
    };
    let mut out = String::new();
    for sm in entries {
        let path = sm["path"].as_str().unwrap_or("?");
        let last_sub = sm["lastSubmitted"].as_str().unwrap_or("?");
        let last_dl = sm["lastDownloaded"].as_str().unwrap_or("?");
        let is_index = sm["isSitemapsIndex"].as_bool().unwrap_or(false);
        let kind = sm["type"].as_str().unwrap_or("?");
        let errors = sm["errors"].as_str().unwrap_or("0");
        let warnings = sm["warnings"].as_str().unwrap_or("0");
        let (submitted, indexed) = sitemap_content_counts(sm);

        out.push_str(&format!("{}\n", path));
        out.push_str(&format!("  type:           {}{}\n", kind, if is_index { " (sitemaps-index)" } else { "" }));
        out.push_str(&format!("  lastSubmitted:  {}\n", last_sub));
        out.push_str(&format!("  lastDownloaded: {}\n", last_dl));
        out.push_str(&format!("  errors:         {}\n", errors));
        out.push_str(&format!("  warnings:       {}\n", warnings));
        out.push_str(&format!("  submitted:      {}\n", submitted));
        out.push_str(&format!("  indexed:        {}\n", indexed));
    }
    out
}

fn format_sitemap_show(sm: &Value) -> String {
    let mut out = String::new();
    let path = sm["path"].as_str().unwrap_or("?");
    out.push_str(&format!("path:           {}\n", path));
    out.push_str(&format!("type:           {}\n", sm["type"].as_str().unwrap_or("?")));
    out.push_str(&format!("isSitemapsIndex:{}\n", sm["isSitemapsIndex"].as_bool().unwrap_or(false)));
    out.push_str(&format!("lastSubmitted:  {}\n", sm["lastSubmitted"].as_str().unwrap_or("?")));
    out.push_str(&format!("lastDownloaded: {}\n", sm["lastDownloaded"].as_str().unwrap_or("?")));
    out.push_str(&format!("errors:         {}\n", sm["errors"].as_str().unwrap_or("0")));
    out.push_str(&format!("warnings:       {}\n", sm["warnings"].as_str().unwrap_or("0")));

    if let Some(contents) = sm["contents"].as_array() {
        out.push_str("contents:\n");
        for c in contents {
            let kind = c["type"].as_str().unwrap_or("?");
            let submitted = c["submitted"].as_str().unwrap_or("0");
            let indexed = c["indexed"].as_str().unwrap_or("0");
            out.push_str(&format!("  - type={}  submitted={}  indexed={}\n", kind, submitted, indexed));
        }
    }
    out
}

fn sitemap_content_counts(sm: &Value) -> (String, String) {
    let mut submitted: u64 = 0;
    let mut indexed: u64 = 0;
    if let Some(arr) = sm["contents"].as_array() {
        for c in arr {
            submitted += c["submitted"].as_str().and_then(|s| s.parse().ok()).unwrap_or(0);
            indexed += c["indexed"].as_str().and_then(|s| s.parse().ok()).unwrap_or(0);
        }
    }
    (submitted.to_string(), indexed.to_string())
}

// ---------- analytics ----------

fn cmd_analytics(token: &str, site: &str, days: u32, dimensions: &str, as_json: bool) -> ExitCode {
    if days > MAX_DAYS {
        eprintln!(
            "error: --days {} exceeds GSC's 16-month historical limit ({} days)",
            days, MAX_DAYS
        );
        return ExitCode::FAILURE;
    }
    let dim_list: Vec<String> = dimensions
        .split(',')
        .map(|s| s.trim().to_string())
        .filter(|s| !s.is_empty())
        .collect();
    for d in &dim_list {
        if !matches!(
            d.as_str(),
            "query" | "page" | "country" | "device" | "date" | "searchAppearance"
        ) {
            eprintln!("error: unknown dimension '{}'", d);
            return ExitCode::FAILURE;
        }
    }

    let (start, end) = date_range(days);
    let body = json!({
        "startDate": start,
        "endDate": end,
        "dimensions": dim_list,
        "rowLimit": ANALYTICS_ROW_LIMIT,
        "dataState": "all",
    });
    let url = format!(
        "https://searchconsole.googleapis.com/webmasters/v3/sites/{}/searchAnalytics/query",
        url_encode(site)
    );
    let data = http_post(&url, token, &body);

    if as_json {
        println!("{}", serde_json::to_string_pretty(&data).unwrap_or_default());
    } else {
        print!("{}", format_analytics(&data, &dim_list, &start, &end));
    }
    ExitCode::SUCCESS
}

fn date_range(days: u32) -> (String, String) {
    let now = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .expect("system clock before epoch")
        .as_secs();
    let end_days = now / 86400;
    let start_days = end_days.saturating_sub(days as u64);
    (days_to_date(start_days), days_to_date(end_days))
}

// Convert "days since 1970-01-01" into a YYYY-MM-DD string. Avoids pulling in
// chrono just for this: dates are always UTC, no timezone juggling needed.
fn days_to_date(days_since_epoch: u64) -> String {
    // Howard Hinnant's civil_from_days algorithm.
    let z = days_since_epoch as i64 + 719468;
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

fn format_analytics(data: &Value, dimensions: &[String], start: &str, end: &str) -> String {
    let rows = data["rows"].as_array().cloned().unwrap_or_default();
    let mut out = String::new();
    out.push_str(&format!(
        "window:     {} -> {} ({} dimensions: {})\n",
        start,
        end,
        dimensions.len(),
        dimensions.join(",")
    ));
    out.push_str(&format!("rows:       {}\n", rows.len()));

    let mut total_clicks = 0.0;
    let mut total_impr = 0.0;
    let mut weighted_pos = 0.0;
    for r in &rows {
        let clicks = r["clicks"].as_f64().unwrap_or(0.0);
        let impr = r["impressions"].as_f64().unwrap_or(0.0);
        let pos = r["position"].as_f64().unwrap_or(0.0);
        total_clicks += clicks;
        total_impr += impr;
        weighted_pos += pos * impr;
    }
    let ctr = if total_impr > 0.0 { total_clicks / total_impr } else { 0.0 };
    let avg_pos = if total_impr > 0.0 { weighted_pos / total_impr } else { 0.0 };
    out.push_str(&format!(
        "totals:     clicks={:.0}  impressions={:.0}  ctr={:.2}%  avg_position={:.2}\n",
        total_clicks,
        total_impr,
        ctr * 100.0,
        avg_pos
    ));

    if rows.len() as u64 == ANALYTICS_ROW_LIMIT {
        out.push_str(&format!(
            "WARNING: hit {}-row cap - results truncated; narrow the window or drop a dimension\n",
            ANALYTICS_ROW_LIMIT
        ));
    }

    if rows.is_empty() {
        out.push_str("(no rows in window)\n");
        return out;
    }

    let mut sorted: Vec<&Value> = rows.iter().collect();
    sorted.sort_by(|a, b| {
        b["clicks"]
            .as_f64()
            .unwrap_or(0.0)
            .partial_cmp(&a["clicks"].as_f64().unwrap_or(0.0))
            .unwrap_or(std::cmp::Ordering::Equal)
    });

    out.push_str("\n");
    out.push_str(&format!("{}\n", "-".repeat(80)));
    out.push_str(&format!(
        "{:<8} {:<12} {:<8} {:<8} {}\n",
        "clicks", "impressions", "ctr%", "pos", "keys"
    ));
    out.push_str(&format!("{}\n", "-".repeat(80)));
    for r in sorted.iter().take(50) {
        let clicks = r["clicks"].as_f64().unwrap_or(0.0);
        let impr = r["impressions"].as_f64().unwrap_or(0.0);
        let pos = r["position"].as_f64().unwrap_or(0.0);
        let row_ctr = if impr > 0.0 { clicks / impr * 100.0 } else { 0.0 };
        let keys: Vec<String> = r["keys"]
            .as_array()
            .map(|a| a.iter().map(|v| v.as_str().unwrap_or("?").to_string()).collect())
            .unwrap_or_default();
        out.push_str(&format!(
            "{:<8.0} {:<12.0} {:<8.2} {:<8.2} {}\n",
            clicks,
            impr,
            row_ctr,
            pos,
            keys.join(" | ")
        ));
    }

    out
}

// ---------- inspect ----------

fn cmd_inspect(token: &str, site: &str, target_url: &str) -> ExitCode {
    let body = json!({
        "inspectionUrl": target_url,
        "siteUrl": site,
        "languageCode": "en-US",
    });
    let url = "https://searchconsole.googleapis.com/v1/urlInspection/index:inspect";
    let data = http_post(url, token, &body);

    let (out, pass) = format_inspect(&data);
    print!("{}", out);
    if pass { ExitCode::SUCCESS } else { ExitCode::FAILURE }
}

fn format_inspect(data: &Value) -> (String, bool) {
    let mut out = String::new();
    let result = &data["inspectionResult"];

    out.push_str("== Index status ==\n");
    let idx = &result["indexStatusResult"];
    let verdict = idx["verdict"].as_str().unwrap_or("?");
    out.push_str(&format!("  verdict:         {}\n", verdict));
    out.push_str(&format!("  coverageState:   {}\n", idx["coverageState"].as_str().unwrap_or("?")));
    out.push_str(&format!("  robotsTxtState:  {}\n", idx["robotsTxtState"].as_str().unwrap_or("?")));
    out.push_str(&format!("  indexingState:   {}\n", idx["indexingState"].as_str().unwrap_or("?")));
    out.push_str(&format!("  pageFetchState:  {}\n", idx["pageFetchState"].as_str().unwrap_or("?")));
    out.push_str(&format!("  lastCrawlTime:   {}\n", idx["lastCrawlTime"].as_str().unwrap_or("?")));
    out.push_str(&format!("  crawledAs:       {}\n", idx["crawledAs"].as_str().unwrap_or("?")));
    out.push_str(&format!("  googleCanonical: {}\n", idx["googleCanonical"].as_str().unwrap_or("?")));
    out.push_str(&format!("  userCanonical:   {}\n", idx["userCanonical"].as_str().unwrap_or("?")));
    let referring = idx["referringUrls"].as_array().map(|a| a.len()).unwrap_or(0);
    out.push_str(&format!("  referringUrls:   {}\n", referring));

    out.push_str("\n== Mobile usability ==\n");
    let mob = &result["mobileUsabilityResult"];
    if mob.is_object() {
        out.push_str(&format!("  verdict: {}\n", mob["verdict"].as_str().unwrap_or("?")));
        let issues = mob["issues"].as_array().cloned().unwrap_or_default();
        out.push_str(&format!("  issues:  {}\n", issues.len()));
        for i in issues.iter().take(10) {
            out.push_str(&format!(
                "    - [{}] {}\n",
                i["severity"].as_str().unwrap_or("?"),
                i["message"].as_str().unwrap_or("?")
            ));
        }
    } else {
        out.push_str("  (not present)\n");
    }

    out.push_str("\n== Rich results ==\n");
    let rr = &result["richResultsResult"];
    if rr.is_object() {
        out.push_str(&format!("  verdict: {}\n", rr["verdict"].as_str().unwrap_or("?")));
        let items = rr["detectedItems"].as_array().cloned().unwrap_or_default();
        if items.is_empty() {
            out.push_str("  (no detectedItems)\n");
        }
        for item in &items {
            let kind = item["richResultType"].as_str().unwrap_or("?");
            let detected = item["items"].as_array().cloned().unwrap_or_default();
            out.push_str(&format!("  - {} ({} items)\n", kind, detected.len()));
            for di in &detected {
                let name = di["name"].as_str().unwrap_or("?");
                let issues = di["issues"].as_array().cloned().unwrap_or_default();
                out.push_str(&format!("    name: {}  issues: {}\n", name, issues.len()));
                for issue in issues.iter().take(5) {
                    out.push_str(&format!(
                        "      [{}] {}\n",
                        issue["severity"].as_str().unwrap_or("?"),
                        issue["issueMessage"].as_str().unwrap_or("?")
                    ));
                }
            }
        }
    } else {
        out.push_str("  (not present)\n");
    }

    let amp = &result["ampResult"];
    if amp.is_object() {
        out.push_str("\n== AMP ==\n");
        out.push_str(&format!("  verdict: {}\n", amp["verdict"].as_str().unwrap_or("?")));
        let issues = amp["issues"].as_array().cloned().unwrap_or_default();
        out.push_str(&format!("  issues:  {}\n", issues.len()));
    }

    let pass = verdict == "PASS";
    (out, pass)
}

fn main() -> ExitCode {
    let cli = Cli::parse();
    let cfg = load_config();
    let sa = load_service_account(&cfg.service_account_json);
    let token = get_access_token(&sa);

    match cli.command {
        Command::Sites => cmd_sites(&token),
        Command::Sitemaps { action, feedpath, site } => {
            let site_url = site.unwrap_or_else(|| cfg.default_site.clone());
            cmd_sitemaps(&token, &site_url, action.as_deref(), feedpath.as_deref())
        }
        Command::Analytics { days, dimensions, site, json } => {
            let site_url = site.unwrap_or_else(|| cfg.default_site.clone());
            cmd_analytics(&token, &site_url, days, &dimensions, json)
        }
        Command::Inspect { url, site } => {
            let site_url = site.unwrap_or_else(|| cfg.default_site.clone());
            cmd_inspect(&token, &site_url, &url)
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use jsonwebtoken::{decode, DecodingKey, Validation};
    use serde_json::Value as Json;

    fn test_sa() -> ServiceAccount {
        let key = include_str!("../tests/fixtures/test_private.pem");
        ServiceAccount {
            client_email: "test@example.iam.gserviceaccount.com".to_string(),
            private_key: key.to_string(),
            token_uri: "https://oauth2.googleapis.com/token".to_string(),
        }
    }

    #[test]
    fn mint_jwt_round_trips_and_has_expected_claims() {
        // Arrange
        let sa = test_sa();
        let pubkey = include_str!("../tests/fixtures/test_public.pem");

        // Act
        let now: u64 = 1_700_000_000;
        let token = mint_jwt(&sa, SCOPE, now);

        // Assert: header parses, signature verifies, claims match
        let mut v = Validation::new(Algorithm::RS256);
        v.set_audience(&["https://oauth2.googleapis.com/token"]);
        v.validate_exp = false;
        let dk = DecodingKey::from_rsa_pem(pubkey.as_bytes()).unwrap();
        let decoded = decode::<Json>(&token, &dk, &v).unwrap();

        let claims = decoded.claims;
        assert_eq!(claims["iss"].as_str().unwrap(), sa.client_email);
        assert_eq!(claims["scope"].as_str().unwrap(), SCOPE);
        assert_eq!(claims["aud"].as_str().unwrap(), sa.token_uri);
        assert_eq!(claims["iat"].as_u64().unwrap(), now);
        assert_eq!(claims["exp"].as_u64().unwrap(), now + 3600);
        assert_eq!(decoded.header.alg, Algorithm::RS256);
    }

    #[test]
    fn format_sites_renders_each_property() {
        // Arrange
        let raw = include_str!("../tests/fixtures/sites.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let out = format_sites(&v);

        // Assert
        assert!(out.contains("sc-domain:meetagain.org"));
        assert!(out.contains("siteOwner"));
        assert!(out.contains("https://example.com/"));
    }

    #[test]
    fn format_sitemaps_list_aggregates_content_counts() {
        // Arrange
        let raw = include_str!("../tests/fixtures/sitemaps_list.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let out = format_sitemaps_list(&v);

        // Assert
        assert!(out.contains("https://meetagain.org/sitemap.xml"));
        assert!(out.contains("submitted:      150"));
        assert!(out.contains("indexed:        140"));
        assert!(out.contains("lastDownloaded:"));
    }

    #[test]
    fn format_sitemap_show_lists_contents() {
        // Arrange
        let raw = include_str!("../tests/fixtures/sitemap_show.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let out = format_sitemap_show(&v);

        // Assert
        assert!(out.contains("path:           https://meetagain.org/sitemap.xml"));
        assert!(out.contains("type=web"));
        assert!(out.contains("submitted=120"));
    }

    #[test]
    fn format_analytics_warns_on_row_cap() {
        // Arrange
        let raw = include_str!("../tests/fixtures/analytics.json");
        let v: Value = serde_json::from_str(raw).unwrap();
        let dims = vec!["query".to_string(), "page".to_string()];

        // Act
        let out = format_analytics(&v, &dims, "2026-01-01", "2026-01-29");

        // Assert
        assert!(out.contains("WARNING: hit 25000-row cap"));
        assert!(out.contains("totals:"));
    }

    #[test]
    fn format_inspect_pass_returns_pass_true() {
        // Arrange
        let raw = include_str!("../tests/fixtures/inspect_pass.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let (out, pass) = format_inspect(&v);

        // Assert
        assert!(pass);
        assert!(out.contains("verdict:         PASS"));
        assert!(out.contains("Rich results"));
        assert!(out.contains("Event"));
    }

    #[test]
    fn format_inspect_fail_returns_pass_false() {
        // Arrange
        let raw = include_str!("../tests/fixtures/inspect_fail.json");
        let v: Value = serde_json::from_str(raw).unwrap();

        // Act
        let (_out, pass) = format_inspect(&v);

        // Assert
        assert!(!pass);
    }

    #[test]
    fn url_encode_handles_gsc_property_forms() {
        assert_eq!(url_encode("sc-domain:meetagain.org"), "sc-domain%3Ameetagain.org");
        assert_eq!(url_encode("https://meetagain.org/"), "https%3A%2F%2Fmeetagain.org%2F");
    }

    #[test]
    fn days_to_date_known_values() {
        // 1970-01-01 = day 0
        assert_eq!(days_to_date(0), "1970-01-01");
        // 2026-04-26 = day 20569 (verified out-of-band)
        assert_eq!(days_to_date(20569), "2026-04-26");
        // Leap-year boundary
        assert_eq!(days_to_date(20148), "2025-03-01");
    }
}
