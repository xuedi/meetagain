use std::env;
use std::fs;
use std::io::{IsTerminal, Write};
use std::path::{Path, PathBuf};
use std::process::{Command, ExitCode};
use std::time::{Duration, Instant};

use walkdir::WalkDir;

const VALID_DIAGRAM_TYPES: &[&str] = &[
    "flowchart",
    "graph",
    "sequenceDiagram",
    "stateDiagram",
    "stateDiagram-v2",
    "classDiagram",
    "erDiagram",
    "gantt",
    "pie",
    "journey",
    "gitGraph",
    "mindmap",
    "timeline",
    "quadrantChart",
    "requirementDiagram",
    "sankey-beta",
    "block-beta",
    "packet-beta",
    "architecture-beta",
    "xychart-beta",
    "kanban",
    "C4Context",
    "C4Container",
    "C4Component",
    "C4Dynamic",
    "C4Deployment",
];

#[derive(Debug)]
struct LintError {
    file: String,
    line: usize,
    column: Option<usize>,
    message: String,
}

impl std::fmt::Display for LintError {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self.column {
            Some(c) => write!(f, "{}:{}:{}: {}", self.file, self.line, c, self.message),
            None => write!(f, "{}:{}: {}", self.file, self.line, self.message),
        }
    }
}

fn lint_file(path: &Path) -> Vec<LintError> {
    let mut errors = Vec::new();
    let content = match fs::read_to_string(path) {
        Ok(s) => s,
        Err(_) => return errors,
    };
    let file_str = path.to_string_lossy().to_string();
    let lines: Vec<&str> = content.lines().collect();

    let mut i = 0;
    while i < lines.len() {
        let trimmed = lines[i].trim_start();
        let is_open = trimmed == "```mermaid"
            || trimmed.starts_with("```mermaid ")
            || trimmed.starts_with("```mermaid\t");
        if !is_open {
            i += 1;
            continue;
        }

        let opening_line_num = i + 1;
        let body_start = i + 1;
        let mut j = body_start;
        let mut found_close = false;
        while j < lines.len() {
            let lj = lines[j].trim_start();
            if lj == "```" || lj.starts_with("``` ") {
                found_close = true;
                break;
            }
            j += 1;
        }

        if !found_close {
            errors.push(LintError {
                file: file_str.clone(),
                line: opening_line_num,
                column: None,
                message: "unclosed mermaid block (no trailing ```)".to_string(),
            });
            break;
        }

        errors.extend(lint_block(&file_str, &lines[body_start..j], body_start + 1));
        i = j + 1;
    }

    errors
}

fn lint_block(file: &str, body: &[&str], first_line_num: usize) -> Vec<LintError> {
    let mut errors = Vec::new();

    let header_idx = body
        .iter()
        .position(|line| {
            let l = line.trim();
            !l.is_empty() && !l.starts_with("%%")
        });

    match header_idx {
        None => {
            errors.push(LintError {
                file: file.to_string(),
                line: first_line_num,
                column: None,
                message: "empty mermaid block".to_string(),
            });
            return errors;
        }
        Some(idx) => {
            let header_line = body[idx].trim();
            let known = VALID_DIAGRAM_TYPES.iter().any(|t| {
                header_line == *t
                    || header_line.starts_with(&format!("{} ", t))
                    || header_line.starts_with(&format!("{}\t", t))
            });
            if !known {
                let first_word = header_line.split_whitespace().next().unwrap_or("");
                errors.push(LintError {
                    file: file.to_string(),
                    line: first_line_num + idx,
                    column: None,
                    message: format!(
                        "unknown mermaid diagram type: '{}' (expected flowchart, sequenceDiagram, stateDiagram-v2, classDiagram, erDiagram, ...)",
                        first_word
                    ),
                });
            }
        }
    }

    for (idx, line) in body.iter().enumerate() {
        errors.extend(lint_labels(file, line, first_line_num + idx));
    }

    errors
}

fn lint_labels(file: &str, line: &str, line_num: usize) -> Vec<LintError> {
    let mut errors = Vec::new();
    let chars: Vec<char> = line.chars().collect();
    let n = chars.len();
    let mut i = 0;

    while i < n {
        let c = chars[i];
        let (closer, kind) = match c {
            '[' => (']', "bracket"),
            '{' => ('}', "brace"),
            _ => {
                i += 1;
                continue;
            }
        };

        let opener_idx = i;

        let mut content_start = opener_idx + 1;
        while content_start < n && chars[content_start] == c {
            content_start += 1;
        }

        if content_start < n && chars[content_start] == '"' {
            let mut j = content_start + 1;
            while j < n {
                if chars[j] == '"' {
                    break;
                }
                j += 1;
            }
            if j >= n {
                errors.push(LintError {
                    file: file.to_string(),
                    line: line_num,
                    column: Some(opener_idx + 1),
                    message: format!(
                        "quoted {} label is not closed on this line",
                        kind
                    ),
                });
                return errors;
            }
            i = j + 1;
            continue;
        }

        let mut j = content_start;
        let mut quote_col: Option<usize> = None;
        while j < n && chars[j] != closer {
            if chars[j] == '"' && quote_col.is_none() {
                quote_col = Some(j);
            }
            j += 1;
        }

        if let Some(col) = quote_col {
            errors.push(LintError {
                file: file.to_string(),
                line: line_num,
                column: Some(col + 1),
                message: format!(
                    "unquoted {} label contains a `\"`; mermaid stops parsing at the first quote. Wrap the whole label: {}\"...\"{}",
                    kind, c, closer
                ),
            });
        }

        i = if j < n { j + 1 } else { n };
    }

    errors
}

fn collect_md_files(root: &Path) -> Vec<PathBuf> {
    let mut files = Vec::new();
    for entry in WalkDir::new(root)
        .follow_links(false)
        .into_iter()
        .filter_entry(|e| {
            let name = e.file_name().to_string_lossy();
            name != "target" && name != "node_modules"
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
            eprintln!("mermaid-guard: failed to query git index, falling back to a full scan");
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
    println!("mermaid-guard - lint mermaid blocks in markdown files");
    println!();
    println!("USAGE:");
    println!("    mermaid-guard [OPTIONS] [PATH...]");
    println!();
    println!("OPTIONS:");
    println!("    --staged    Lint only files staged for commit (used by pre-commit hook)");
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

    let config = toolconfig::Config::load_or_exit("mermaid-guard");
    let roots: Vec<PathBuf> = config
        .list("SCAN_PATHS")
        .into_iter()
        .map(PathBuf::from)
        .collect();
    if roots.is_empty() {
        eprintln!("mermaid-guard: config error: SCAN_PATHS is empty");
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
        // No work, no output - keep pre-commit silent on routine commits
        return ExitCode::SUCCESS;
    }

    let start = Instant::now();
    print_header(env!("CARGO_PKG_VERSION"), &scope);

    let total = files.len();
    let mut all_errors: Vec<LintError> = Vec::new();
    let mut bad_files = 0usize;

    for (i, f) in files.iter().enumerate() {
        let errs = lint_file(f);
        let mark = if errs.is_empty() { '.' } else { 'F' };
        if !errs.is_empty() {
            bad_files += 1;
            all_errors.extend(errs);
        }
        emit_progress(mark, i + 1, total);
    }

    if !all_errors.is_empty() {
        println!();
        println!();
        println!(
            "There {} {} error{}:",
            if all_errors.len() == 1 { "was" } else { "were" },
            all_errors.len(),
            if all_errors.len() == 1 { "" } else { "s" }
        );
        println!();
        for (idx, e) in all_errors.iter().enumerate() {
            println!("{}) {}", idx + 1, e);
        }
    }

    println!();
    println!();
    println!("Time: {}", format_duration(start.elapsed()));
    println!();

    let exit = if all_errors.is_empty() {
        print_summary(true, &format!("OK ({} file{}, 0 errors)", total, plural_s(total)));
        ExitCode::SUCCESS
    } else {
        print_summary(
            false,
            &format!(
                "ERRORS! ({} error{} in {} of {} file{})",
                all_errors.len(),
                plural_s(all_errors.len()),
                bad_files,
                total,
                plural_s(total)
            ),
        );
        ExitCode::from(1)
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
    println!("mermaid-guard {} - mermaid lint", version);
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
    let use_color = std::io::stdout().is_terminal();
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

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn flags_unquoted_label_with_double_quote() {
        let errs = lint_labels("test.md", r#"    A[say "hi"]"#, 5);
        assert_eq!(errs.len(), 1);
        assert!(errs[0].message.contains("unquoted"));
        assert!(errs[0].message.contains('"'));
    }

    #[test]
    fn accepts_quoted_label_with_inner_quote_safely() {
        let errs = lint_labels("test.md", r#"    A["plain text"]"#, 5);
        assert!(errs.is_empty());
    }

    #[test]
    fn accepts_unquoted_label_without_quotes() {
        let errs = lint_labels("test.md", r#"    A[plain text] --> B[other]"#, 5);
        assert!(errs.is_empty());
    }

    #[test]
    fn flags_unquoted_brace_with_double_quote() {
        let errs = lint_labels("test.md", r#"    Q{is "x" set?}"#, 5);
        assert_eq!(errs.len(), 1);
    }

    #[test]
    fn flags_unknown_diagram_type() {
        let body = vec!["flowchard TD", "    A --> B"];
        let errs = lint_block("test.md", &body, 1);
        assert!(errs.iter().any(|e| e.message.contains("unknown mermaid diagram type")));
    }

    #[test]
    fn accepts_known_diagram_types() {
        for header in &[
            "flowchart TD",
            "flowchart LR",
            "sequenceDiagram",
            "stateDiagram-v2",
            "classDiagram",
            "erDiagram",
        ] {
            let body = vec![*header];
            let errs = lint_block("test.md", &body, 1);
            assert!(errs.iter().all(|e| !e.message.contains("unknown")));
        }
    }

    #[test]
    fn empty_block_is_an_error() {
        let body: Vec<&str> = vec!["", "  ", "%% just a comment"];
        let errs = lint_block("test.md", &body, 1);
        assert_eq!(errs.len(), 1);
        assert!(errs[0].message.contains("empty"));
    }
}
