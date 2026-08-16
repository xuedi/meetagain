use std::process::ExitCode;

use clap::{Parser, Subcommand};
use serde_json::Value;
use toolconfig::Config;

const TOKEN_PATH: &str = "/api/oauth/token";

#[derive(Parser)]
#[command(name = "api", about = "meetagain.org /api/v1/ OAuth2 client")]
struct Cli {
    #[command(subcommand)]
    command: Command,
}

#[derive(Subcommand)]
enum Command {
    /// GET /api/status
    Status,

    /// GET /api/v1/events
    Events {
        #[arg(long)]
        group: Option<String>,
        #[arg(long)]
        from: Option<String>,
        #[arg(long)]
        to: Option<String>,
        #[arg(long)]
        limit: Option<u32>,
        #[arg(long)]
        offset: Option<u32>,
        #[arg(long)]
        language: Option<String>,
    },

    /// GET /api/v1/events/{id}
    Event { id: String },

    /// GET /api/v1/groups
    Groups,

    /// GET /api/v1/groups/{slug}
    Group { slug: String },

    /// GET /api/v1/groups/{slug}/cms
    Cms {
        group_slug: String,
        #[arg(long)]
        language: Option<String>,
    },

    /// GET /api/v1/groups/{slug}/cms/{page-slug}
    CmsPage {
        group_slug: String,
        page_slug: String,
    },

    /// Admin log family (cron, sendlog, incidents). Requires user token.
    Logs {
        #[command(subcommand)]
        action: LogsCommand,
    },

    /// GET /api/v1/me
    Me,

    /// GET /api/v1/me/rsvps
    Rsvps,

    /// GET /api/v1/groups/{slug}/admin/settings
    GroupSettings { group_slug: String },

    /// GET /api/v1/groups/{slug}/admin/members
    GroupMembers { group_slug: String },

    /// POST /api/v1/events/{id}/rsvp
    RsvpAdd { event_id: String },

    /// DELETE /api/v1/events/{id}/rsvp
    RsvpRemove { event_id: String },

    /// POST /api/v1/events/{id}/comments
    CommentAdd { event_id: String, text: String },

    /// DELETE /api/v1/events/{id}/comments/{commentId}
    CommentDelete {
        event_id: String,
        comment_id: String,
    },

    /// Raw passthrough: api raw GET /api/v1/some/path [--user] [--body=<json>]
    Raw {
        method: String,
        path: String,
        #[arg(long)]
        user: bool,
        #[arg(long)]
        body: Option<String>,
    },
}

#[derive(Subcommand)]
enum LogsCommand {
    /// GET /api/v1/admin/logs/cron
    Cron {
        #[arg(long)]
        limit: Option<u32>,
    },
    /// GET /api/v1/admin/logs/cron/{id}
    CronDetail { id: String },
    /// GET /api/v1/admin/logs/sendlog
    Sendlog {
        #[arg(long)]
        limit: Option<u32>,
    },
    /// GET /api/v1/admin/logs/sendlog/{id}
    SendlogDetail { id: String },
    /// GET /api/v1/admin/security/incidents
    Incidents {
        #[arg(long)]
        limit: Option<u32>,
        #[arg(long)]
        since: Option<String>,
    },
    /// GET /api/v1/admin/security/incidents/{id}
    Incident { id: String },
}

#[derive(Clone, Copy)]
#[allow(dead_code)] // `None` is reserved for future public endpoints that must not leak a token.
enum TokenKind {
    Client,
    User,
    None,
}

fn save_config(cfg: &Config) {
    if let Err(message) = cfg.save() {
        eprintln!("warning: could not save config: {}", message);
    }
}

fn percent_encode(s: &str) -> String {
    let mut out = String::with_capacity(s.len());
    for b in s.bytes() {
        match b {
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'_' | b'.' | b'~' => {
                out.push(b as char);
            }
            _ => out.push_str(&format!("%{:02X}", b)),
        }
    }
    out
}

fn get_client_token(cfg: &mut Config) -> String {
    if let Some(tok) = cfg.scalar("OAUTH_ACCESS_TOKEN") {
        if !tok.is_empty() {
            return tok;
        }
    }
    let url = format!("{}{}", cfg.require("API_URL"), TOKEN_PATH);
    let client_id = cfg.require("OAUTH_CLIENT_ID");
    let client_secret = cfg.require("OAUTH_CLIENT_SECRET");
    let resp = status_blind_agent().post(&url).send_form([
        ("grant_type", "client_credentials"),
        ("client_id", &client_id),
        ("client_secret", &client_secret),
        ("scope", "api"),
    ]);
    let body: Value = match resp {
        Ok(mut r) if r.status().is_success() => r
            .body_mut()
            .read_json()
            .unwrap_or_else(|e| panic!("client_credentials response parse failed: {}", e)),
        Ok(mut r) => {
            let code = r.status().as_u16();
            let body = r.body_mut().read_to_string().unwrap_or_default();
            eprintln!("error: client_credentials grant returned HTTP {}", code);
            eprintln!("{}", body);
            std::process::exit(1);
        }
        Err(e) => {
            eprintln!("error: client_credentials request failed: {}", e);
            std::process::exit(2);
        }
    };
    let access = body["access_token"]
        .as_str()
        .unwrap_or_else(|| panic!("client_credentials response had no access_token: {}", body))
        .to_string();
    cfg.set("OAUTH_ACCESS_TOKEN", &access);
    save_config(cfg);
    access
}

fn get_user_token(cfg: &mut Config) -> String {
    if let Some(tok) = cfg.scalar("OAUTH_USER_ACCESS_TOKEN") {
        if !tok.is_empty() {
            return tok;
        }
    }
    let refresh = cfg.scalar("OAUTH_USER_REFRESH_TOKEN").unwrap_or_default();
    if refresh.is_empty() {
        eprintln!(
            "error: No user refresh token. Re-run the authorization_code flow and populate OAUTH_USER_REFRESH_TOKEN in {}.",
            cfg.local_path().display()
        );
        std::process::exit(1);
    }
    let url = format!("{}{}", cfg.require("API_URL"), TOKEN_PATH);
    let client_id = cfg.require("OAUTH_CLIENT_ID");
    let client_secret = cfg.require("OAUTH_CLIENT_SECRET");
    let resp = status_blind_agent().post(&url).send_form([
        ("grant_type", "refresh_token"),
        ("refresh_token", &refresh),
        ("client_id", &client_id),
        ("client_secret", &client_secret),
    ]);
    let body: Value = match resp {
        Ok(mut r) if r.status().is_success() => r
            .body_mut()
            .read_json()
            .unwrap_or_else(|e| panic!("refresh_token response parse failed: {}", e)),
        Ok(mut r) => {
            let code = r.status().as_u16();
            let body = r.body_mut().read_to_string().unwrap_or_default();
            eprintln!("error: refresh_token grant returned HTTP {}", code);
            eprintln!("{}", body);
            eprintln!("hint: refresh token may have expired or been revoked; re-run the authorization_code flow.");
            std::process::exit(1);
        }
        Err(e) => {
            eprintln!("error: refresh_token request failed: {}", e);
            std::process::exit(2);
        }
    };
    let access = body["access_token"]
        .as_str()
        .unwrap_or_else(|| panic!("refresh_token response had no access_token: {}", body))
        .to_string();
    cfg.set("OAUTH_USER_ACCESS_TOKEN", &access);
    if let Some(new_refresh) = body["refresh_token"].as_str() {
        if !new_refresh.is_empty() {
            cfg.set("OAUTH_USER_REFRESH_TOKEN", new_refresh);
        }
    }
    save_config(cfg);
    access
}

fn pat_token(cfg: &Config) -> Option<String> {
    cfg.scalar("PAT_TOKEN").filter(|s| !s.is_empty())
}

fn fetch_token(cfg: &mut Config, kind: TokenKind) -> Option<String> {
    if matches!(kind, TokenKind::None) {
        return None;
    }
    if let Some(pat) = pat_token(cfg) {
        return Some(pat);
    }
    match kind {
        TokenKind::Client => Some(get_client_token(cfg)),
        TokenKind::User => Some(get_user_token(cfg)),
        TokenKind::None => None,
    }
}

// HTTP error statuses must surface as Ok responses so callers can read the
// body and run the 401 retry; only transport failures become Err.
fn status_blind_agent() -> ureq::Agent {
    ureq::Agent::new_with_config(
        ureq::Agent::config_builder()
            .http_status_as_error(false)
            .build(),
    )
}

fn send_once(
    base: &str,
    token: Option<&str>,
    method: &str,
    path: &str,
    body: Option<&str>,
) -> Result<(u16, String), String> {
    let url = format!("{}{}", base, path);
    let mut builder = ureq::http::Request::builder().method(method).uri(&url);
    if let Some(t) = token {
        builder = builder.header("Authorization", format!("Bearer {}", t));
    }
    let agent = status_blind_agent();
    let resp = match body {
        Some(b) => {
            let req = builder
                .header("Content-Type", "application/json")
                .body(b.to_string())
                .map_err(|e| format!("{}", e))?;
            agent.run(req)
        }
        None => {
            let req = builder.body(()).map_err(|e| format!("{}", e))?;
            agent.run(req)
        }
    };
    match resp {
        Ok(mut r) => {
            let status = r.status().as_u16();
            let text = r.body_mut().read_to_string().unwrap_or_default();
            Ok((status, text))
        }
        Err(e) => Err(format!("{}", e)),
    }
}

fn api_request(
    cfg: &mut Config,
    kind: TokenKind,
    method: &str,
    path: &str,
    body: Option<&str>,
) -> (u16, String) {
    let base = cfg.require("API_URL");
    let mut token = fetch_token(cfg, kind);
    let (status, text) = match send_once(&base, token.as_deref(), method, path, body) {
        Ok(pair) => pair,
        Err(e) => {
            eprintln!("error: {} {} failed: {}", method, path, e);
            std::process::exit(2);
        }
    };
    if status == 401 && !matches!(kind, TokenKind::None) {
        if pat_token(cfg).is_some() {
            eprintln!("error: {} {} returned 401. PAT may be revoked or expired - issue a new one at {}/profile/access-tokens", method, path, base);
            eprintln!("{}", text);
            std::process::exit(1);
        }
        match kind {
            TokenKind::Client => cfg.set("OAUTH_ACCESS_TOKEN", ""),
            TokenKind::User => cfg.set("OAUTH_USER_ACCESS_TOKEN", ""),
            TokenKind::None => {}
        }
        save_config(cfg);
        token = fetch_token(cfg, kind);
        match send_once(&base, token.as_deref(), method, path, body) {
            Ok((s2, t2)) => {
                if s2 == 401 {
                    eprintln!("error: {} {} returned 401 twice; token cannot be refreshed.", method, path);
                    eprintln!("{}", t2);
                    std::process::exit(1);
                }
                return (s2, t2);
            }
            Err(e) => {
                eprintln!("error: {} {} retry failed: {}", method, path, e);
                std::process::exit(2);
            }
        }
    }
    (status, text)
}

fn print_json_or_die(method: &str, path: &str, status: u16, body: String) {
    if !(200..300).contains(&status) {
        eprintln!("error: {} {} returned HTTP {}", method, path, status);
        eprintln!("{}", body);
        std::process::exit(1);
    }
    if body.is_empty() {
        return;
    }
    match serde_json::from_str::<Value>(&body) {
        Ok(v) => println!(
            "{}",
            serde_json::to_string_pretty(&v).unwrap_or_else(|_| body.clone())
        ),
        Err(_) => println!("{}", body),
    }
}

fn run_get(cfg: &mut Config, kind: TokenKind, path: &str) {
    let (s, b) = api_request(cfg, kind, "GET", path, None);
    print_json_or_die("GET", path, s, b);
}

fn run_send(cfg: &mut Config, kind: TokenKind, method: &str, path: &str, body: Option<&str>) {
    let (s, b) = api_request(cfg, kind, method, path, body);
    print_json_or_die(method, path, s, b);
}

struct QueryBuilder {
    parts: Vec<String>,
}

impl QueryBuilder {
    fn new() -> Self {
        Self { parts: Vec::new() }
    }
    fn add_str(mut self, key: &str, val: Option<&str>) -> Self {
        if let Some(v) = val {
            if !v.is_empty() {
                self.parts.push(format!("{}={}", key, percent_encode(v)));
            }
        }
        self
    }
    fn add_u32(mut self, key: &str, val: Option<u32>) -> Self {
        if let Some(v) = val {
            self.parts.push(format!("{}={}", key, v));
        }
        self
    }
    fn build(self) -> String {
        if self.parts.is_empty() {
            String::new()
        } else {
            format!("?{}", self.parts.join("&"))
        }
    }
}

fn dispatch(cli: Cli, cfg: &mut Config) -> ExitCode {
    match cli.command {
        Command::Status => {
            run_get(cfg, TokenKind::Client, "/api/status");
        }
        Command::Events {
            group,
            from,
            to,
            limit,
            offset,
            language,
        } => {
            let qs = QueryBuilder::new()
                .add_str("group", group.as_deref())
                .add_str("from", from.as_deref())
                .add_str("to", to.as_deref())
                .add_u32("limit", limit)
                .add_u32("offset", offset)
                .add_str("language", language.as_deref())
                .build();
            run_get(cfg, TokenKind::Client, &format!("/api/v1/events{}", qs));
        }
        Command::Event { id } => {
            run_get(
                cfg,
                TokenKind::Client,
                &format!("/api/v1/events/{}", percent_encode(&id)),
            );
        }
        Command::Groups => run_get(cfg, TokenKind::Client, "/api/v1/groups"),
        Command::Group { slug } => run_get(
            cfg,
            TokenKind::Client,
            &format!("/api/v1/groups/{}", percent_encode(&slug)),
        ),
        Command::Cms {
            group_slug,
            language,
        } => {
            let qs = QueryBuilder::new()
                .add_str("language", language.as_deref())
                .build();
            run_get(
                cfg,
                TokenKind::Client,
                &format!("/api/v1/groups/{}/cms{}", percent_encode(&group_slug), qs),
            );
        }
        Command::CmsPage {
            group_slug,
            page_slug,
        } => run_get(
            cfg,
            TokenKind::Client,
            &format!(
                "/api/v1/groups/{}/cms/{}",
                percent_encode(&group_slug),
                percent_encode(&page_slug)
            ),
        ),
        Command::Logs { action } => match action {
            LogsCommand::Cron { limit } => {
                let qs = QueryBuilder::new().add_u32("limit", limit).build();
                run_get(cfg, TokenKind::User, &format!("/api/v1/admin/logs/cron{}", qs));
            }
            LogsCommand::CronDetail { id } => run_get(
                cfg,
                TokenKind::User,
                &format!("/api/v1/admin/logs/cron/{}", percent_encode(&id)),
            ),
            LogsCommand::Sendlog { limit } => {
                let qs = QueryBuilder::new().add_u32("limit", limit).build();
                run_get(
                    cfg,
                    TokenKind::User,
                    &format!("/api/v1/admin/logs/sendlog{}", qs),
                );
            }
            LogsCommand::SendlogDetail { id } => run_get(
                cfg,
                TokenKind::User,
                &format!("/api/v1/admin/logs/sendlog/{}", percent_encode(&id)),
            ),
            LogsCommand::Incidents { limit, since } => {
                let qs = QueryBuilder::new()
                    .add_u32("limit", limit)
                    .add_str("since", since.as_deref())
                    .build();
                run_get(
                    cfg,
                    TokenKind::User,
                    &format!("/api/v1/admin/security/incidents{}", qs),
                );
            }
            LogsCommand::Incident { id } => run_get(
                cfg,
                TokenKind::User,
                &format!("/api/v1/admin/security/incidents/{}", percent_encode(&id)),
            ),
        },
        Command::Me => run_get(cfg, TokenKind::User, "/api/v1/me"),
        Command::Rsvps => run_get(cfg, TokenKind::User, "/api/v1/me/rsvps"),
        Command::GroupSettings { group_slug } => run_get(
            cfg,
            TokenKind::User,
            &format!(
                "/api/v1/groups/{}/admin/settings",
                percent_encode(&group_slug)
            ),
        ),
        Command::GroupMembers { group_slug } => run_get(
            cfg,
            TokenKind::User,
            &format!(
                "/api/v1/groups/{}/admin/members",
                percent_encode(&group_slug)
            ),
        ),
        Command::RsvpAdd { event_id } => run_send(
            cfg,
            TokenKind::User,
            "POST",
            &format!("/api/v1/events/{}/rsvp", percent_encode(&event_id)),
            None,
        ),
        Command::RsvpRemove { event_id } => run_send(
            cfg,
            TokenKind::User,
            "DELETE",
            &format!("/api/v1/events/{}/rsvp", percent_encode(&event_id)),
            None,
        ),
        Command::CommentAdd { event_id, text } => {
            let body = serde_json::json!({ "text": text }).to_string();
            run_send(
                cfg,
                TokenKind::User,
                "POST",
                &format!("/api/v1/events/{}/comments", percent_encode(&event_id)),
                Some(&body),
            );
        }
        Command::CommentDelete {
            event_id,
            comment_id,
        } => run_send(
            cfg,
            TokenKind::User,
            "DELETE",
            &format!(
                "/api/v1/events/{}/comments/{}",
                percent_encode(&event_id),
                percent_encode(&comment_id)
            ),
            None,
        ),
        Command::Raw {
            method,
            path,
            user,
            body,
        } => {
            let kind = if user { TokenKind::User } else { TokenKind::Client };
            run_send(cfg, kind, &method.to_uppercase(), &path, body.as_deref());
        }
    }
    ExitCode::SUCCESS
}

fn main() -> ExitCode {
    let cli = Cli::parse();
    let mut cfg = Config::load_or_exit("api");
    dispatch(cli, &mut cfg)
}
