mod rules;

use std::collections::HashSet;
use std::fs;
use std::io::{self, BufRead, IsTerminal, Write};
use std::path::{Path, PathBuf};
use std::process::{Command, ExitCode};
use std::time::{Duration, Instant};

use walkdir::{DirEntry, WalkDir};

use rules::Rules;

struct Scope {
    exclude_paths: Vec<PathBuf>,
    exclude_dirs: HashSet<String>,
    extensions: HashSet<String>,
}

impl Scope {
    fn from_config(config: &toolconfig::Config) -> Self {
        Self {
            exclude_paths: config
                .list("EXCLUDE_PATHS")
                .into_iter()
                .map(PathBuf::from)
                .collect(),
            exclude_dirs: config.list("EXCLUDE_DIRS").into_iter().collect(),
            extensions: config
                .list("EXTENSIONS")
                .into_iter()
                .map(|e| e.to_lowercase())
                .collect(),
        }
    }
}

/// Match an entry against EXCLUDE_DIRS (basename match for directories only) OR
/// EXCLUDE_PATHS (path-prefix match, applies to both files and directories so a single file
/// path can be excluded).
///
/// Path matching strips a leading `./` so paths produced by walking from `.` line up with the
/// path strings the user wrote in the config.
fn is_excluded(entry: &DirEntry, scope: &Scope) -> bool {
    if entry.file_type().is_dir() {
        let name_excluded = entry
            .file_name()
            .to_str()
            .map(|name| scope.exclude_dirs.contains(name))
            .unwrap_or(false);
        if name_excluded {
            return true;
        }
    }

    let normalized = entry.path().strip_prefix("./").unwrap_or(entry.path());
    scope
        .exclude_paths
        .iter()
        .any(|p| normalized == p.as_path() || normalized.starts_with(p))
}

/// Same path-prefix check used for filtering staged file lists (which never have a `./` prefix
/// since they come from `git diff --cached`).
fn path_excluded(path: &Path, scope: &Scope) -> bool {
    if scope.exclude_paths.iter().any(|p| path.starts_with(p)) {
        return true;
    }
    path.components().any(|c| match c.as_os_str().to_str() {
        Some(name) => scope.exclude_dirs.contains(name),
        None => false,
    })
}

fn has_allowed_extension(path: &Path, extensions: &HashSet<String>) -> bool {
    path.extension()
        .and_then(|s| s.to_str())
        .map(|s| extensions.contains(&s.to_lowercase()))
        .unwrap_or(false)
}

/// Files staged for commit, with EXCLUDE_PATHS / EXCLUDE_DIRS applied. Used by --staged mode.
/// Filters out deletions (status `D`) so we don't try to read removed files.
fn staged_files(scope: &Scope) -> Vec<PathBuf> {
    let output = Command::new("git")
        .args(["diff", "--cached", "--name-only", "--diff-filter=ACMR"])
        .output();

    let output = match output {
        Ok(o) if o.status.success() => o,
        Ok(o) => {
            eprintln!(
                "git diff --cached failed: {}",
                String::from_utf8_lossy(&o.stderr)
            );
            return Vec::new();
        }
        Err(e) => {
            eprintln!("cannot run git: {}", e);
            return Vec::new();
        }
    };

    let stdout = String::from_utf8_lossy(&output.stdout);
    stdout
        .lines()
        .filter(|line| !line.is_empty())
        .map(PathBuf::from)
        .filter(|path| !path_excluded(path, scope))
        .collect()
}

fn scan_file(path: &Path, rules: &Rules) -> io::Result<Vec<String>> {
    let file = fs::File::open(path)?;
    let reader = io::BufReader::new(file);
    let normalized = path.strip_prefix("./").unwrap_or(path);
    let display = normalized.display().to_string();
    let mut rendered = Vec::new();

    for (index, line_result) in reader.lines().enumerate() {
        let Ok(line) = line_result else {
            // Non-UTF8 line; skip the rest of the file rather than panicking.
            return Ok(rendered);
        };
        for finding in rules.scan_line(&display, index + 1, &line) {
            rendered.push(finding.render(&display));
        }
    }
    Ok(rendered)
}

fn scan_one_file(path: &Path, rules: &Rules, extensions: &HashSet<String>) -> Vec<String> {
    if !path.is_file() || !has_allowed_extension(path, extensions) {
        return Vec::new();
    }
    match scan_file(path, rules) {
        Ok(findings) => findings,
        Err(e) => {
            eprintln!("warning: cannot read {}: {}", path.display(), e);
            Vec::new()
        }
    }
}

fn main() -> ExitCode {
    let staged_only = std::env::args().any(|a| a == "--staged");
    let config = toolconfig::Config::load_or_exit("leak-guard");
    let scope = Scope::from_config(&config);
    let rules = match Rules::from_config(&config) {
        Ok(rules) => rules,
        Err(message) => {
            eprintln!(
                "config error in {}: {}",
                config.dist_path().display(),
                message
            );
            return ExitCode::from(2);
        }
    };

    // Collect targets up front so we know the total for progress display.
    let files: Vec<PathBuf> = if staged_only {
        staged_files(&scope)
            .into_iter()
            .filter(|p| has_allowed_extension(p, &scope.extensions) && p.is_file())
            .collect()
    } else {
        WalkDir::new(".")
            .follow_links(false)
            .into_iter()
            .filter_entry(|e| !is_excluded(e, &scope))
            .filter_map(|e| e.ok())
            .filter(|e| e.file_type().is_file())
            .map(|e| e.path().to_path_buf())
            .filter(|p| has_allowed_extension(p, &scope.extensions))
            .collect()
    };

    if files.is_empty() {
        // Nothing to scan - stay silent (typical pre-commit run with nothing in scope).
        return ExitCode::SUCCESS;
    }

    let target = if staged_only { "staged files" } else { "project tree" };
    let scope_line = format!("{} ({})", target, rules.summary());

    let start = Instant::now();
    print_header(env!("CARGO_PKG_VERSION"), &scope_line);

    let total = files.len();
    let mut all_matches: Vec<String> = Vec::new();
    let mut bad_files = 0usize;

    for (i, path) in files.iter().enumerate() {
        let matches = scan_one_file(path, &rules, &scope.extensions);
        let mark = if matches.is_empty() { '.' } else { 'F' };
        if !matches.is_empty() {
            bad_files += 1;
            all_matches.extend(matches);
        }
        emit_progress(mark, i + 1, total);
    }

    if !all_matches.is_empty() {
        println!();
        println!();
        println!(
            "There {} {} finding{}:",
            if all_matches.len() == 1 { "was" } else { "were" },
            all_matches.len(),
            if all_matches.len() == 1 { "" } else { "s" }
        );
        println!();
        for (idx, m) in all_matches.iter().enumerate() {
            println!("{}) {}", idx + 1, m);
        }
    }

    println!();
    println!();
    println!("Time: {}", format_duration(start.elapsed()));
    println!();

    let exit = if all_matches.is_empty() {
        print_summary(
            true,
            &format!("OK ({} file{}, 0 matches)", total, plural_s(total)),
        );
        ExitCode::SUCCESS
    } else {
        print_summary(
            false,
            &format!(
                "FAILURES! ({} match{} in {} of {} file{})",
                all_matches.len(),
                if all_matches.len() == 1 { "" } else { "es" },
                bad_files,
                total,
                plural_s(total)
            ),
        );
        ExitCode::FAILURE
    };

    // Two trailing blank lines so the next command in the hook chain or `just test`
    // doesn't visually glue onto our summary line.
    println!();
    println!();

    exit
}

// -- phpunit-style reporter -------------------------------------------------

const DOT_WIDTH: usize = 63;

fn print_header(version: &str, scope: &str) {
    println!("leak-guard {} - credential and vocabulary scanner", version);
    println!();
    println!("Runtime:       Rust release");
    println!("Configuration: {}", scope);
    println!();
}

fn emit_progress(mark: char, done: usize, total: usize) {
    print!("{}", mark);
    let _ = io::stdout().flush();

    let at_row_end = done % DOT_WIDTH == 0;
    let at_total = done == total;
    if !(at_row_end || at_total) {
        return;
    }

    let chars_in_row = if at_row_end { DOT_WIDTH } else { done % DOT_WIDTH };
    let pad = DOT_WIDTH.saturating_sub(chars_in_row);
    let total_w = total.to_string().len();
    let pct = (done * 100) / total.max(1);
    println!(
        "{} {:>width$} / {} ({:>3}%)",
        " ".repeat(pad),
        done,
        total,
        pct,
        width = total_w
    );
}

fn format_duration(d: Duration) -> String {
    let secs = d.as_secs();
    let millis = d.subsec_millis();
    let mins = secs / 60;
    let s = secs % 60;
    format!("{:02}:{:02}.{:03}", mins, s, millis)
}

fn print_summary(ok: bool, msg: &str) {
    let use_color = io::stdout().is_terminal();
    if use_color {
        let code = if ok { "30;42" } else { "37;41" };
        println!("\x1b[{}m{}\x1b[0m", code, msg);
    } else {
        println!("{}", msg);
    }
}

fn plural_s(n: usize) -> &'static str {
    if n == 1 { "" } else { "s" }
}
