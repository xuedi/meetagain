//! Resolves every repository path written as inline code in markdown.
//!
//! Documentation earns its keep by pointing at real code. A path that silently rots after a
//! refactor is worse than no path at all: it sends the reader to a file that does not exist
//! and quietly teaches them to distrust the rest of the page. This guard turns that class of
//! rot into a commit-time failure.
//!
//! A token counts as a repository path when it appears in an inline code span and its first
//! segment is one of `PATH_PREFIXES`. Fenced blocks are skipped - a shell transcript or a
//! diagram legitimately names paths that were never meant to resolve. Two escape hatches keep
//! the check honest: `PLACEHOLDERS` for deliberately illustrative paths (`plugins/your-plugin/`),
//! and `IGNORE` for build output that only exists after a compile.

use std::env;
use std::fs;
use std::io::{IsTerminal, Write};
use std::path::{Path, PathBuf};
use std::process::{Command, ExitCode};
use std::time::{Duration, Instant};

use walkdir::WalkDir;

#[derive(Debug)]
struct Finding {
    file: String,
    line: usize,
    path: String,
}

impl std::fmt::Display for Finding {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        write!(f, "{}:{}: `{}` does not exist", self.file, self.line, self.path)
    }
}

struct Rules {
    prefixes: Vec<String>,
    placeholders: Vec<String>,
    ignore: Vec<String>,
}

/// Inline code spans on one line: the text between single backticks, pairwise.
fn inline_code_spans(line: &str) -> Vec<&str> {
    let mut spans = Vec::new();
    let bytes = line.as_bytes();
    let mut i = 0;
    while i < bytes.len() {
        if bytes[i] != b'`' {
            i += 1;
            continue;
        }
        let start = i + 1;
        let mut j = start;
        while j < bytes.len() && bytes[j] != b'`' {
            j += 1;
        }
        if j >= bytes.len() {
            break;
        }
        if j > start {
            spans.push(&line[start..j]);
        }
        i = j + 1;
    }
    spans
}

fn is_repo_path(token: &str, rules: &Rules) -> bool {
    let Some((head, rest)) = token.split_once('/') else {
        return false;
    };
    if rest.is_empty() {
        return false;
    }
    rules.prefixes.iter().any(|p| p == head)
}

fn is_exempt(token: &str, rules: &Rules) -> bool {
    // Globs and placeholder syntax, brace expansion, an elision, a `Class::MEMBER` reference,
    // and anything with whitespace - a shell invocation, not a path.
    let not_a_literal_path = ['*', '<', '$', '{', ' ', '…'];
    if token.contains(not_a_literal_path) || token.contains("...") || token.contains("::") {
        return true;
    }
    if rules.placeholders.iter().any(|p| token.contains(p.as_str())) {
        return true;
    }
    rules
        .ignore
        .iter()
        .any(|prefix| token == prefix || token.starts_with(&format!("{}/", prefix)))
}

fn check_file(path: &Path, rules: &Rules) -> Vec<Finding> {
    let mut findings = Vec::new();
    let Ok(content) = fs::read_to_string(path) else {
        return findings;
    };
    let file = path.to_string_lossy().to_string();
    let mut in_fence = false;

    for (idx, line) in content.lines().enumerate() {
        if line.trim_start().starts_with("```") {
            in_fence = !in_fence;
            continue;
        }
        if in_fence {
            continue;
        }
        for token in inline_code_spans(line) {
            let token = token.trim();
            if !is_repo_path(token, rules) || is_exempt(token, rules) {
                continue;
            }
            if Path::new(token).exists() {
                continue;
            }
            findings.push(Finding {
                file: file.clone(),
                line: idx + 1,
                path: token.to_string(),
            });
        }
    }

    findings
}

fn collect_md_files(root: &Path) -> Vec<PathBuf> {
    let mut files = Vec::new();
    for entry in WalkDir::new(root)
        .follow_links(false)
        .into_iter()
        .filter_entry(|e| {
            let name = e.file_name().to_string_lossy();
            name != "target" && name != "node_modules" && name != "vendor"
        })
        .filter_map(|e| e.ok())
    {
        let p = entry.path();
        if !p.is_file() {
            continue;
        }
        if p.extension().and_then(|s| s.to_str()) != Some("md") {
            continue;
        }
        files.push(p.to_path_buf());
    }
    files.sort();
    files
}

fn collect_roots(roots: &[PathBuf]) -> Vec<PathBuf> {
    let mut files = Vec::new();
    for root in roots {
        if root.is_dir() {
            files.extend(collect_md_files(root));
        } else if root.is_file() {
            files.push(root.clone());
        }
    }
    files
}

fn staged_md_files(roots: &[PathBuf]) -> Vec<PathBuf> {
    let out = Command::new("git")
        .args(["diff", "--cached", "--name-only", "--diff-filter=ACMR"])
        .output();
    let stdout = match out {
        Ok(o) if o.status.success() => o.stdout,
        _ => {
            eprintln!("docs-guard: failed to query git index, falling back to a full scan");
            return collect_roots(roots);
        }
    };
    String::from_utf8_lossy(&stdout)
        .lines()
        .map(PathBuf::from)
        .filter(|p| {
            roots.iter().any(|root| p.starts_with(root))
                && p.extension().and_then(|s| s.to_str()) == Some("md")
                && p.exists()
        })
        .collect()
}

fn print_help() {
    println!("docs-guard - resolve repository paths referenced in markdown");
    println!();
    println!("USAGE:");
    println!("    docs-guard [OPTIONS] [PATH...]");
    println!();
    println!("OPTIONS:");
    println!("    --staged    Check only files staged for commit (used by pre-commit hook)");
    println!("    -h, --help  Show this help");
    println!();
    println!("ARGS:");
    println!("    [PATH...]   Files or directories to scan (default: SCAN_PATHS from the config)");
}

fn main() -> ExitCode {
    let args: Vec<String> = env::args().collect();
    let mut staged_only = false;
    let mut paths: Vec<PathBuf> = Vec::new();

    for arg in &args[1..] {
        match arg.as_str() {
            "--staged" => staged_only = true,
            "-h" | "--help" => {
                print_help();
                return ExitCode::SUCCESS;
            }
            other => paths.push(PathBuf::from(other)),
        }
    }

    let config = toolconfig::Config::load_or_exit("docs-guard");
    let roots: Vec<PathBuf> = config
        .list("SCAN_PATHS")
        .into_iter()
        .map(PathBuf::from)
        .collect();
    if roots.is_empty() {
        eprintln!("docs-guard: config error: SCAN_PATHS is empty");
        return ExitCode::from(2);
    }

    let rules = Rules {
        prefixes: config.list("PATH_PREFIXES"),
        placeholders: config.list("PLACEHOLDERS"),
        ignore: config.list("IGNORE"),
    };
    if rules.prefixes.is_empty() {
        eprintln!("docs-guard: config error: PATH_PREFIXES is empty");
        return ExitCode::from(2);
    }

    let scope = if staged_only {
        "staged markdown files".to_string()
    } else {
        let listed = if paths.is_empty() { &roots } else { &paths };
        listed
            .iter()
            .map(|p| p.display().to_string())
            .collect::<Vec<_>>()
            .join(", ")
    };

    let files: Vec<PathBuf> = if staged_only {
        staged_md_files(&roots)
    } else if paths.is_empty() {
        collect_roots(&roots)
    } else {
        collect_roots(&paths)
    };

    if files.is_empty() {
        return ExitCode::SUCCESS;
    }

    let start = Instant::now();
    print_header(env!("CARGO_PKG_VERSION"), &scope);

    let total = files.len();
    let mut all: Vec<Finding> = Vec::new();
    let mut bad_files = 0usize;

    for (i, f) in files.iter().enumerate() {
        let found = check_file(f, &rules);
        let mark = if found.is_empty() { '.' } else { 'F' };
        if !found.is_empty() {
            bad_files += 1;
            all.extend(found);
        }
        emit_progress(mark, i + 1, total);
    }

    if !all.is_empty() {
        println!();
        println!();
        println!(
            "There {} {} unresolved path{}:",
            if all.len() == 1 { "was" } else { "were" },
            all.len(),
            plural_s(all.len())
        );
        println!();
        for (idx, f) in all.iter().enumerate() {
            println!("{}) {}", idx + 1, f);
        }
    }

    println!();
    println!();
    println!("Time: {}", format_duration(start.elapsed()));
    println!();

    let exit = if all.is_empty() {
        print_summary(
            true,
            &format!("OK ({} file{}, 0 unresolved)", total, plural_s(total)),
        );
        ExitCode::SUCCESS
    } else {
        print_summary(
            false,
            &format!(
                "ERRORS! ({} unresolved path{} in {} of {} file{})",
                all.len(),
                plural_s(all.len()),
                bad_files,
                total,
                plural_s(total)
            ),
        );
        ExitCode::from(1)
    };

    println!();
    println!();

    exit
}

// -- phpunit-style reporter -------------------------------------------------

const DOT_WIDTH: usize = 63;

fn print_header(version: &str, scope: &str) {
    println!("docs-guard {} - markdown path resolution", version);
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
    if std::io::stdout().is_terminal() {
        let code = if ok { "30;42" } else { "37;41" };
        println!("\x1b[{}m{}\x1b[0m", code, msg);
    } else {
        println!("{}", msg);
    }
}

fn plural_s(n: usize) -> &'static str {
    if n == 1 { "" } else { "s" }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn rules() -> Rules {
        Rules {
            prefixes: vec!["src".into(), "docs".into(), "plugins".into()],
            placeholders: vec!["your-plugin".into(), "XXX".into()],
            ignore: vec!["public/media".into(), "tests/reports".into()],
        }
    }

    #[test]
    fn extracts_each_inline_code_span() {
        assert_eq!(
            vec!["a/b.php", "c/d.php"],
            inline_code_spans("see `a/b.php` and `c/d.php` today")
        );
    }

    #[test]
    fn an_unclosed_backtick_yields_no_span() {
        assert!(inline_code_spans("see `a/b.php and more").is_empty());
    }

    #[test]
    fn only_known_prefixes_count_as_repo_paths() {
        assert!(is_repo_path("src/Service/Foo.php", &rules()));
        assert!(!is_repo_path("vendor/acme/Foo.php", &rules()));
        assert!(!is_repo_path("schema.org/Event", &rules()));
    }

    #[test]
    fn a_bare_token_without_a_segment_is_not_a_path() {
        assert!(!is_repo_path("src", &rules()));
        assert!(!is_repo_path("src/", &rules()));
    }

    #[test]
    fn placeholders_and_globs_are_exempt() {
        assert!(is_exempt("plugins/your-plugin/src/Kernel.php", &rules()));
        assert!(is_exempt("migrations/VersionXXX.php", &rules()));
        assert!(is_exempt("plugins/<name>/migrations/", &rules()));
        assert!(is_exempt("src/**/*.php", &rules()));
    }

    #[test]
    fn tokens_that_are_not_literal_paths_are_exempt() {
        assert!(is_exempt("config/tools/{gsc,bing}.local", &rules()));
        assert!(is_exempt("tests/Functional/.../SomeTest.php", &rules()));
        assert!(is_exempt("src/AssetMapper/AppBundle.php::SOURCES", &rules()));
        assert!(is_exempt("bin/console lint:container", &rules()));
    }

    #[test]
    fn ignored_prefixes_cover_build_output_but_not_siblings() {
        assert!(is_exempt("public/media/app.js", &rules()));
        assert!(!is_exempt("public/mediaservice/app.js", &rules()));
    }

    #[test]
    fn fenced_blocks_are_skipped_but_prose_is_checked() {
        let dir = env::temp_dir().join("docs-guard-fence-test");
        let _ = fs::create_dir_all(&dir);
        let file = dir.join("sample.md");
        fs::write(
            &file,
            "prose `src/Missing/One.php` here\n\n```bash\ncat `src/Missing/Two.php`\n```\n",
        )
        .unwrap();

        let found = check_file(&file, &rules());

        assert_eq!(1, found.len());
        assert_eq!("src/Missing/One.php", found[0].path);
        assert_eq!(1, found[0].line);
        let _ = fs::remove_dir_all(&dir);
    }
}
