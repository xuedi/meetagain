mod baseline;
mod classify;
mod config;
mod lexer;

use std::collections::BTreeSet;
use std::fs;
use std::io::{IsTerminal, Write};
use std::path::{Path, PathBuf};
use std::process::{Command, ExitCode};
use std::time::{Duration, Instant};

use walkdir::{DirEntry, WalkDir};

use baseline::{Baseline, BASELINE_NAME};
use classify::Verdict;
use config::Config;

#[derive(PartialEq, Eq, Clone, Copy)]
enum Mode {
    Check,
    List,
    Stats,
    Suggest,
}

#[derive(Default, Clone, Copy)]
struct Counts {
    total: usize,
    directive: usize,
    tag: usize,
    aaa: usize,
    interface: usize,
    marker: usize,
    baselined: usize,
    violations: usize,
}

impl Counts {
    fn add(&mut self, other: &Counts) {
        self.total += other.total;
        self.directive += other.directive;
        self.tag += other.tag;
        self.aaa += other.aaa;
        self.interface += other.interface;
        self.marker += other.marker;
        self.baselined += other.baselined;
        self.violations += other.violations;
    }
}

struct Repo {
    id: String,
    label: String,
    baseline_file: PathBuf,
    baseline: Baseline,
    counts: Counts,
}

struct Violation {
    repo: usize,
    display: String,
    repo_relative: String,
    line: usize,
    col: usize,
    symbol: String,
    message: String,
    excerpt: String,
}

/// Rust masks SIGPIPE, so `comment-guard --list | head` would panic on the first blocked write.
/// The reports are meant to be piped, so restore the default: die quietly when the reader leaves.
#[cfg(unix)]
fn restore_sigpipe() {
    unsafe {
        libc::signal(libc::SIGPIPE, libc::SIG_DFL);
    }
}

#[cfg(not(unix))]
fn restore_sigpipe() {}

fn main() -> ExitCode {
    restore_sigpipe();

    let mut mode = Mode::Check;
    let mut staged_only = false;
    let mut paths: Vec<PathBuf> = Vec::new();

    for arg in std::env::args().skip(1) {
        match arg.as_str() {
            "--staged" => staged_only = true,
            "--list" => mode = Mode::List,
            "--stats" => mode = Mode::Stats,
            "--suggest" => mode = Mode::Suggest,
            "-h" | "--help" => {
                print_help(&policy_doc());
                return ExitCode::SUCCESS;
            }
            other if other.starts_with('-') => {
                eprintln!("comment-guard: unknown option `{}`", other);
                print_help(&policy_doc());
                return ExitCode::from(2);
            }
            other => paths.push(PathBuf::from(other)),
        }
    }

    let config = match config::load() {
        Ok(config) => config,
        Err(message) => {
            eprintln!("comment-guard: config error: {}", message);
            return ExitCode::from(2);
        }
    };

    let mut repos = discover_repos();

    if mode == Mode::List {
        print_index(&repos);
        return ExitCode::SUCCESS;
    }

    let files = collect_files(&config, staged_only, &paths);
    if files.is_empty() {
        return ExitCode::SUCCESS;
    }

    let full_scan = !staged_only && paths.is_empty();
    let quiet = mode != Mode::Check;
    let scope = if staged_only {
        "staged PHP files".to_string()
    } else if !paths.is_empty() {
        paths
            .iter()
            .map(|p| p.display().to_string())
            .collect::<Vec<_>>()
            .join(", ")
    } else {
        format!("{} ({} repos)", config_scope(&config), repos.len())
    };

    let start = Instant::now();
    if !quiet {
        print_header(env!("CARGO_PKG_VERSION"), &scope);
    }

    let total_files = files.len();
    let mut violations: Vec<Violation> = Vec::new();

    for (index, file) in files.iter().enumerate() {
        let display = normalize(file);
        let repo_index = repo_for(&repos, &display);
        let repo_relative = strip_repo_prefix(&display, &repos[repo_index].id);

        let source = match fs::read_to_string(file) {
            Ok(source) => source,
            Err(_) => {
                if !quiet {
                    emit_progress('S', index + 1, total_files);
                }
                continue;
            }
        };

        let mut file_violations = 0usize;
        for comment in lexer::scan(&source) {
            repos[repo_index].counts.total += 1;
            match classify::classify(&comment, &config) {
                Verdict::Directive => repos[repo_index].counts.directive += 1,
                Verdict::TagDoc => repos[repo_index].counts.tag += 1,
                Verdict::Aaa => repos[repo_index].counts.aaa += 1,
                Verdict::Interface => repos[repo_index].counts.interface += 1,
                Verdict::Marker => repos[repo_index].counts.marker += 1,
                Verdict::NeedsJustification(message) => {
                    let baselined = repos[repo_index]
                        .baseline
                        .claim(&repo_relative, &comment.symbol)
                        .is_some();
                    if baselined {
                        repos[repo_index].counts.baselined += 1;
                    } else {
                        repos[repo_index].counts.violations += 1;
                        file_violations += 1;
                        violations.push(Violation {
                            repo: repo_index,
                            display: display.clone(),
                            repo_relative: repo_relative.clone(),
                            line: comment.line,
                            col: comment.col,
                            symbol: comment.symbol.clone(),
                            message,
                            excerpt: excerpt(&comment.lines),
                        });
                    }
                }
            }
        }

        if !quiet {
            emit_progress(
                if file_violations == 0 { '.' } else { 'F' },
                index + 1,
                total_files,
            );
        }
    }

    match mode {
        Mode::Suggest => {
            print_suggestions(&repos, &violations);
            return ExitCode::SUCCESS;
        }
        Mode::Stats => {
            print_stats(&repos);
            return ExitCode::SUCCESS;
        }
        _ => {}
    }

    let stale = if full_scan { collect_stale(&repos) } else { Vec::new() };
    let problems = collect_problems(&repos);

    print_violations(&repos, &violations);
    print_problems(&problems);
    print_stale(&stale);

    let mut totals = Counts::default();
    for repo in &repos {
        totals.add(&repo.counts);
    }
    print_totals(&totals);

    println!();
    println!("Time: {}", format_duration(start.elapsed()));
    println!();

    let failures = violations.len() + problems.len() + stale.len();
    let exit = if failures == 0 {
        print_summary(
            true,
            &format!(
                "OK ({} file{}, {} comment{}, 0 violations)",
                total_files,
                plural_s(total_files),
                totals.total,
                plural_s(totals.total)
            ),
        );
        ExitCode::SUCCESS
    } else {
        print_summary(
            false,
            &format!(
                "FAILURES! ({} violation{}, {} stale baseline entr{}, {} broken baseline line{})",
                violations.len(),
                plural_s(violations.len()),
                stale.len(),
                if stale.len() == 1 { "y" } else { "ies" },
                problems.len(),
                plural_s(problems.len())
            ),
        );
        ExitCode::from(1)
    };

    println!();
    println!();

    exit
}

// -- repos ------------------------------------------------------------------

fn discover_repos() -> Vec<Repo> {
    let mut repos = vec![make_repo(String::new(), PathBuf::from("."))];

    if let Ok(entries) = fs::read_dir("plugins") {
        let mut names: Vec<String> = entries
            .filter_map(|entry| entry.ok())
            .filter(|entry| entry.path().is_dir())
            .filter_map(|entry| entry.file_name().to_str().map(str::to_string))
            .collect();
        names.sort();
        for name in names {
            let id = format!("plugins/{}", name);
            let root = PathBuf::from(&id);
            repos.push(make_repo(id, root));
        }
    }

    repos
}

fn make_repo(id: String, root: PathBuf) -> Repo {
    let label = if id.is_empty() { "core".to_string() } else { id.clone() };
    let baseline_file = baseline::baseline_path(&root);
    let is_core = id.is_empty();
    let baseline = match fs::read_to_string(&baseline_file) {
        Ok(content) => baseline::parse(&content, &root, is_core),
        Err(_) => Baseline::default(),
    };
    Repo {
        id,
        label,
        baseline_file: PathBuf::from(normalize(&baseline_file)),
        baseline,
        counts: Counts::default(),
    }
}

fn repo_for(repos: &[Repo], path: &str) -> usize {
    repos
        .iter()
        .position(|repo| !repo.id.is_empty() && path.starts_with(&format!("{}/", repo.id)))
        .unwrap_or(0)
}

fn strip_repo_prefix(path: &str, repo_id: &str) -> String {
    if repo_id.is_empty() {
        return path.to_string();
    }
    path.strip_prefix(&format!("{}/", repo_id))
        .unwrap_or(path)
        .to_string()
}

// -- file collection --------------------------------------------------------

fn collect_files(config: &Config, staged_only: bool, paths: &[PathBuf]) -> Vec<PathBuf> {
    if staged_only {
        return staged_php_files(config);
    }

    let roots: Vec<PathBuf> = if paths.is_empty() {
        config.scan_paths.clone()
    } else {
        paths.to_vec()
    };

    let mut files: Vec<PathBuf> = Vec::new();
    for root in &roots {
        if root.is_file() {
            if is_php(root) {
                files.push(root.clone());
            }
            continue;
        }
        for entry in WalkDir::new(root)
            .follow_links(false)
            .into_iter()
            .filter_entry(|entry| !is_excluded(entry, config))
            .filter_map(|entry| entry.ok())
        {
            let path = entry.path();
            if entry.file_type().is_file() && is_php(path) {
                files.push(path.to_path_buf());
            }
        }
    }
    files.sort();
    files.dedup();
    files
}

fn staged_php_files(config: &Config) -> Vec<PathBuf> {
    let output = Command::new("git")
        .args(["diff", "--cached", "--name-only", "--diff-filter=ACMR"])
        .output();
    let stdout = match output {
        Ok(output) if output.status.success() => output.stdout,
        _ => {
            eprintln!("comment-guard: cannot query the git index");
            return Vec::new();
        }
    };
    String::from_utf8_lossy(&stdout)
        .lines()
        .map(PathBuf::from)
        .filter(|path| is_php(path) && path.is_file() && !path_excluded(path, config))
        .collect()
}

fn is_php(path: &Path) -> bool {
    path.extension().and_then(|s| s.to_str()) == Some("php")
}

fn is_excluded(entry: &DirEntry, config: &Config) -> bool {
    if entry.file_type().is_dir() {
        let excluded_by_name = entry
            .file_name()
            .to_str()
            .map(|name| config.exclude_dirs.contains(name))
            .unwrap_or(false);
        if excluded_by_name {
            return true;
        }
    }
    let normalized = entry.path().strip_prefix("./").unwrap_or(entry.path());
    config
        .exclude_paths
        .iter()
        .any(|excluded| normalized == excluded.as_path() || normalized.starts_with(excluded))
}

fn path_excluded(path: &Path, config: &Config) -> bool {
    if config
        .exclude_paths
        .iter()
        .any(|excluded| path.starts_with(excluded))
    {
        return true;
    }
    path.components().any(|component| match component.as_os_str().to_str() {
        Some(name) => config.exclude_dirs.contains(name),
        None => false,
    })
}

fn normalize(path: &Path) -> String {
    let text = path.to_string_lossy().replace('\\', "/");
    text.strip_prefix("./").unwrap_or(&text).to_string()
}

fn config_scope(config: &Config) -> String {
    config
        .scan_paths
        .iter()
        .map(|p| p.display().to_string())
        .collect::<Vec<_>>()
        .join(", ")
}

// -- findings ---------------------------------------------------------------

struct Stale {
    file: String,
    line: usize,
    path: String,
    symbol: String,
}

fn collect_stale(repos: &[Repo]) -> Vec<Stale> {
    let mut stale = Vec::new();
    for repo in repos {
        for entry in &repo.baseline.entries {
            if entry.used {
                continue;
            }
            stale.push(Stale {
                file: normalize(&repo.baseline_file),
                line: entry.line,
                path: entry.path.clone(),
                symbol: entry.symbol.clone(),
            });
        }
    }
    stale
}

fn collect_problems(repos: &[Repo]) -> Vec<(String, usize, String)> {
    let mut problems = Vec::new();
    for repo in repos {
        for problem in &repo.baseline.problems {
            problems.push((
                normalize(&repo.baseline_file),
                problem.line,
                problem.message.clone(),
            ));
        }
    }
    problems
}

// -- reporting --------------------------------------------------------------

fn print_violations(repos: &[Repo], violations: &[Violation]) {
    if violations.is_empty() {
        return;
    }
    println!();
    println!();
    println!(
        "There {} {} unjustified comment{}:",
        if violations.len() == 1 { "was" } else { "were" },
        violations.len(),
        plural_s(violations.len())
    );

    let mut number = 0usize;
    for (index, repo) in repos.iter().enumerate() {
        let mine: Vec<&Violation> = violations.iter().filter(|v| v.repo == index).collect();
        if mine.is_empty() {
            continue;
        }
        println!();
        println!("{} - baseline: {}", repo.label, repo.baseline_file.display());
        for violation in mine {
            number += 1;
            println!(
                "{}) {}:{}:{}: {} in `{}`: {}",
                number,
                violation.display,
                violation.line,
                violation.col,
                violation.message,
                symbol_label(&violation.repo_relative, &violation.symbol),
                violation.excerpt
            );
        }
    }
}

fn print_problems(problems: &[(String, usize, String)]) {
    if problems.is_empty() {
        return;
    }
    println!();
    println!();
    println!("Broken baseline lines:");
    println!();
    for (index, (file, line, message)) in problems.iter().enumerate() {
        println!("{}) {}:{}: {}", index + 1, file, line, message);
    }
}

fn print_stale(stale: &[Stale]) {
    if stale.is_empty() {
        return;
    }
    println!();
    println!();
    println!("Stale baseline entries (nothing there needs justifying any more):");
    println!();
    for (index, entry) in stale.iter().enumerate() {
        println!(
            "{}) {}:{}: {}::{} - delete the entry",
            index + 1,
            entry.file,
            entry.line,
            entry.path,
            entry.symbol
        );
    }
}

fn print_totals(totals: &Counts) {
    println!();
    println!();
    println!("Comments: {}", totals.total);
    println!("  type and tag docblocks  {}", totals.tag);
    println!("  tool directives         {}", totals.directive);
    println!("  AAA markers             {}", totals.aaa);
    println!("  interface contracts     {}", totals.interface);
    println!("  TODO / FIXME markers    {}", totals.marker);
    println!("  baselined               {}", totals.baselined);
    println!("  unjustified             {}", totals.violations);
}

fn print_stats(repos: &[Repo]) {
    let mut totals = Counts::default();
    println!(
        "{:<24} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7}",
        "repo", "total", "tag", "direct", "AAA", "iface", "marker", "base", "unjust"
    );
    for repo in repos {
        if repo.counts.total == 0 {
            continue;
        }
        totals.add(&repo.counts);
        println!(
            "{:<24} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7}",
            repo.label,
            repo.counts.total,
            repo.counts.tag,
            repo.counts.directive,
            repo.counts.aaa,
            repo.counts.interface,
            repo.counts.marker,
            repo.counts.baselined,
            repo.counts.violations
        );
    }
    println!(
        "{:<24} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7} {:>7}",
        "all",
        totals.total,
        totals.tag,
        totals.directive,
        totals.aaa,
        totals.interface,
        totals.marker,
        totals.baselined,
        totals.violations
    );
}

fn print_index(repos: &[Repo]) {
    let mut printed = false;
    for repo in repos {
        if repo.baseline.entries.is_empty() {
            continue;
        }
        printed = true;
        println!("{} - {}", repo.label, repo.baseline_file.display());
        println!();
        for entry in &repo.baseline.entries {
            println!("  {}::{}", entry.path, entry.symbol);
            println!("      {}", entry.reason);
        }
        println!();
    }
    if !printed {
        println!("No baseline entries yet. Every comment in the tree is allowed by shape.");
    }
}

fn print_suggestions(repos: &[Repo], violations: &[Violation]) {
    if violations.is_empty() {
        println!("Nothing to suggest - no unjustified comments.");
        return;
    }
    println!("# Ready-to-paste baseline lines. Fill in every reason - a blank one is rejected.");
    println!("# Never paste one without asking the user first.");
    for (index, repo) in repos.iter().enumerate() {
        let keys: BTreeSet<String> = violations
            .iter()
            .filter(|violation| violation.repo == index)
            .map(|violation| format!("{}::{}", violation.repo_relative, violation.symbol))
            .collect();
        if keys.is_empty() {
            continue;
        }
        println!();
        println!("# --- {} ---", repo.baseline_file.display());
        for key in keys {
            println!("{} // ", key);
        }
    }
}

fn excerpt(lines: &[String]) -> String {
    let mut text = lines.join(" | ");
    if text.chars().count() > 90 {
        text = text.chars().take(87).collect::<String>() + "...";
    }
    text
}

fn symbol_label(path: &str, symbol: &str) -> String {
    if symbol.is_empty() {
        format!("{}::", path)
    } else {
        format!("{}::{}", path, symbol)
    }
}

fn policy_doc() -> String {
    config::load()
        .map(|config| config.policy_doc)
        .unwrap_or_else(|_| "the project's comment policy".to_string())
}

fn print_help(policy_doc: &str) {
    println!("comment-guard - enforce the comment policy in {}", policy_doc);
    println!();
    println!("USAGE:");
    println!("    comment-guard [OPTIONS] [PATH...]");
    println!();
    println!("OPTIONS:");
    println!("    --staged    Scan only staged .php files (the pre-commit hook path)");
    println!("    --list      Print the important-code index from every baseline");
    println!("    --stats     Print comment counts per repo, no per-finding output");
    println!("    --suggest   Print baseline-shaped lines for the current violations");
    println!("    -h, --help  Show this help");
    println!();
    println!("ARGS:");
    println!("    [PATH...]   Files or directories to scan (default: SCAN_PATHS from the config)");
    println!();
    println!("EXIT CODES:");
    println!("    0  clean");
    println!("    1  unjustified comments, stale or broken baseline entries");
    println!("    2  config error");
    println!();
    println!("Baseline: <repo>/{}", BASELINE_NAME);
}

// -- phpunit-style reporter -------------------------------------------------

const DOT_WIDTH: usize = 63;

fn print_header(version: &str, scope: &str) {
    println!("comment-guard {} - PHP comment policy", version);
    println!();
    println!("Runtime:       Rust release");
    println!("Configuration: {}", scope);
    println!();
}

fn emit_progress(mark: char, done: usize, total: usize) {
    print!("{}", mark);
    let _ = std::io::stdout().flush();

    let at_row_end = done % DOT_WIDTH == 0;
    let at_total = done == total;
    if !(at_row_end || at_total) {
        return;
    }

    let chars_in_row = if at_row_end { DOT_WIDTH } else { done % DOT_WIDTH };
    let pad = DOT_WIDTH.saturating_sub(chars_in_row);
    let total_width = total.to_string().len();
    let percent = (done * 100) / total.max(1);
    println!(
        "{} {:>width$} / {} ({:>3}%)",
        " ".repeat(pad),
        done,
        total,
        percent,
        width = total_width
    );
}

fn format_duration(duration: Duration) -> String {
    let seconds = duration.as_secs();
    format!("{:02}:{:02}.{:03}", seconds / 60, seconds % 60, duration.subsec_millis())
}

fn print_summary(ok: bool, message: &str) {
    if std::io::stdout().is_terminal() {
        let code = if ok { "30;42" } else { "37;41" };
        println!("\x1b[{}m{}\x1b[0m", code, message);
    } else {
        println!("{}", message);
    }
}

fn plural_s(count: usize) -> &'static str {
    if count == 1 { "" } else { "s" }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn repos() -> Vec<Repo> {
        vec![
            Repo {
                id: String::new(),
                label: "core".to_string(),
                baseline_file: PathBuf::from(BASELINE_NAME),
                baseline: Baseline::default(),
                counts: Counts::default(),
            },
            Repo {
                id: "plugins/books".to_string(),
                label: "plugins/books".to_string(),
                baseline_file: PathBuf::from("plugins/books").join(BASELINE_NAME),
                baseline: Baseline::default(),
                counts: Counts::default(),
            },
        ]
    }

    #[test]
    fn core_files_land_in_the_core_repo() {
        assert_eq!(repo_for(&repos(), "src/Service/Foo.php"), 0);
        assert_eq!(repo_for(&repos(), "plugins/autoload.php"), 0);
    }

    #[test]
    fn plugin_files_land_in_their_own_repo() {
        assert_eq!(repo_for(&repos(), "plugins/books/src/Kernel.php"), 1);
    }

    #[test]
    fn plugin_paths_are_stored_relative_to_the_plugin() {
        assert_eq!(
            strip_repo_prefix("plugins/books/src/Kernel.php", "plugins/books"),
            "src/Kernel.php"
        );
        assert_eq!(strip_repo_prefix("src/Kernel.php", ""), "src/Kernel.php");
    }
}
