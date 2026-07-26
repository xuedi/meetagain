use clap::{Parser, Subcommand};
use serde_json::Value;

#[derive(Parser)]
#[command(name = "bugsink", about = "BugSink issue tracker CLI")]
struct Cli {
    #[command(subcommand)]
    command: Command,
}

#[derive(Subcommand)]
enum Command {
    /// List latest N issues (default: 10), sorted by last_seen desc
    List {
        /// Number of issues to show
        #[arg(default_value_t = 10)]
        n: usize,
        /// Skip the first N issues (for browsing older issues)
        #[arg(long, default_value_t = 0)]
        offset: usize,
        /// Only show unresolved issues
        #[arg(long)]
        unresolved: bool,
    },
    /// Show issue metadata and latest event stack trace
    Show {
        /// Issue UUID
        uuid: String,
    },
}

struct Config {
    url: String,
    token: String,
    project: String,
}

fn load_config() -> Config {
    let config = toolconfig::Config::load_or_exit("bugsink");

    Config {
        url: config.require("BUGTRACKER_URL"),
        token: config.require("BUGTRACKER_TOKEN"),
        project: config.require("BUGTRACKER_PROJECT"),
    }
}

fn get(url: &str, token: &str) -> Value {
    ureq::get(url)
        .set("Authorization", &format!("Bearer {}", token))
        .call()
        .unwrap_or_else(|e| panic!("HTTP request failed for {}: {}", url, e))
        .into_json()
        .unwrap_or_else(|e| panic!("JSON parse failed for {}: {}", url, e))
}

fn issues_from(data: Value) -> Vec<Value> {
    match data {
        Value::Array(arr) => arr,
        Value::Object(ref obj) => {
            obj.get("results")
                .and_then(|r| r.as_array())
                .cloned()
                .unwrap_or_default()
        }
        _ => vec![],
    }
}

fn issue_title(issue: &Value) -> String {
    let t = issue["calculated_type"].as_str().unwrap_or("");
    let v = issue["calculated_value"].as_str().unwrap_or("");
    match (t.is_empty(), v.is_empty()) {
        (false, false) => format!("{}: {}", t, v),
        (false, true)  => t.to_string(),
        (true,  false) => v.to_string(),
        _              => issue["title"].as_str().unwrap_or("?").to_string(),
    }
}

fn issue_count(issue: &Value) -> String {
    issue.get("digested_event_count")
        .or_else(|| issue.get("stored_event_count"))
        .or_else(|| issue.get("event_count"))
        .or_else(|| issue.get("times_seen"))
        .map(|v| v.to_string())
        .unwrap_or_else(|| "?".to_string())
}

fn cmd_list(cfg: &Config, n: usize, offset: usize, unresolved: bool) {
    let resolved_filter = if unresolved { "&is_resolved=false" } else { "" };
    let url = format!(
        "{}/api/canonical/0/issues/?project={}&sort=last_seen&order=desc{}",
        cfg.url, cfg.project, resolved_filter
    );
    let data = get(&url, &cfg.token);
    let issues = issues_from(data);

    if issues.is_empty() {
        println!("No issues found.");
        return;
    }

    for issue in issues.iter().skip(offset).take(n) {
        let id = issue["id"].as_str().unwrap_or("?");
        let title = issue_title(issue);
        let last = issue["last_seen"].as_str().unwrap_or("?");
        let last = if last.len() >= 10 { &last[..10] } else { last };
        let count = issue_count(issue);
        let resolved = if issue["is_resolved"].as_bool().unwrap_or(false) { " [resolved]" } else { "" };
        let muted = if issue["is_muted"].as_bool().unwrap_or(false) { " [muted]" } else { "" };

        println!("{}  {}{}{} - {} events, last seen {}", id, title, resolved, muted, count, last);
    }
}

fn cmd_show(cfg: &Config, uuid: &str) {
    let url = format!("{}/api/canonical/0/issues/{}/", cfg.url, uuid);
    let issue = get(&url, &cfg.token);

    let title = issue_title(&issue);
    let id = issue["id"].as_str().unwrap_or("?");
    let resolved = issue["is_resolved"].as_bool().unwrap_or(false);
    let muted = issue["is_muted"].as_bool().unwrap_or(false);
    let count = issue_count(&issue);
    let first = issue["first_seen"].as_str().unwrap_or("?");
    let first = if first.len() >= 19 { &first[..19] } else { first };
    let last = issue["last_seen"].as_str().unwrap_or("?");
    let last = if last.len() >= 19 { &last[..19] } else { last };

    println!("Title:    {}", title);
    println!("ID:       {}", id);
    println!("Resolved: {}  Muted: {}", resolved, muted);
    println!("Events:   {}", count);
    println!("First:    {}", first);
    println!("Last:     {}", last);

    // Fetch latest event
    let events_url = format!(
        "{}/api/canonical/0/events/?issue={}&order=desc",
        cfg.url, uuid
    );
    let events_data = get(&events_url, &cfg.token);
    let events = issues_from(events_data);

    if let Some(event) = events.first() {
        let event_id = event["id"].as_str().unwrap_or("");
        if !event_id.is_empty() {
            let st_url = format!(
                "{}/api/canonical/0/events/{}/stacktrace/",
                cfg.url, event_id
            );
            let st_body = ureq::get(&st_url)
                .set("Authorization", &format!("Bearer {}", cfg.token))
                .call()
                .unwrap_or_else(|e| panic!("stacktrace request failed: {}", e))
                .into_string()
                .unwrap_or_else(|e| panic!("stacktrace read failed: {}", e));

            println!();
            println!("--- Latest Event Stack Trace ---");
            println!("{}", st_body);
        }
    }
}

fn main() {
    let cli = Cli::parse();
    let cfg = load_config();

    match cli.command {
        Command::List { n, offset, unresolved } => cmd_list(&cfg, n, offset, unresolved),
        Command::Show { uuid } => cmd_show(&cfg, &uuid),
    }
}
