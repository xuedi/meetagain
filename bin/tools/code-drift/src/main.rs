macro_rules! regex {
    ($pattern:expr) => {{
        static RE: ::std::sync::OnceLock<::regex::Regex> = ::std::sync::OnceLock::new();
        RE.get_or_init(|| ::regex::Regex::new($pattern).unwrap())
    }};
}

mod allow;
mod config;
mod lines;
mod source;
mod structure;

use std::collections::HashSet;
use std::fs;
use std::io::IsTerminal;
use std::path::{Path, PathBuf};
use std::process::ExitCode;
use std::time::Instant;

use clap::Parser;
use walkdir::WalkDir;

use allow::{Allow, ALLOW_NAME};
use config::Config;
use lines::NameRules;
use source::{Finding, PhpFile};
use structure::TinyIndex;

struct Detector {
    id: &'static str,
    title: &'static str,
    rule: &'static str,
}

const DETECTORS: [Detector; 9] = [
    Detector {
        id: "tiny",
        title: "classes that have not earned their file",
        rule: "a class needs own dependencies, >1 caller, or its own unit test - else it is a private method",
    },
    Detector {
        id: "fat",
        title: "the largest classes and the longest methods",
        rule: "God objects with too many responsibilities -> extract to focused services",
    },
    Detector {
        id: "repo-in-controller",
        title: "repositories reached straight from a controller",
        rule: "repository accessed directly from controller -> go through a service",
    },
    Detector {
        id: "mutable-service",
        title: "services that are not readonly and hold no memo",
        rule: "services must be readonly unless a memo field is needed",
    },
    Detector {
        id: "fqcn",
        title: "fully-qualified class names used inline",
        rule: "use statements are always required - no FQCNs inline",
    },
    Detector {
        id: "static",
        title: "static methods outside enums",
        rule: "no static methods anywhere - use constructor injection",
    },
    Detector {
        id: "log-rethrow",
        title: "catch blocks that log and then rethrow",
        rule: "catch + log = handled; either log-only or rethrow-only (duplicate BugSink issues)",
    },
    Detector {
        id: "raw-sql",
        title: "native SQL in a repository",
        rule: "raw SQL is forbidden in repositories - use QueryBuilder",
    },
    Detector {
        id: "echo-name",
        title: "class names that repeat an ancestor folder",
        rule: "a class name must not repeat its ancestor folders' names - the namespace already says it",
    },
];

/// Find PHP code that has drifted from the written coding standards.
///
/// Detectors: tiny, fat, repo-in-controller, mutable-service, fqcn, static, log-rethrow,
/// raw-sql, echo-name. `fat` and the judgement tier of `tiny` are informational: they never fail
/// `--check` and accept no allow entry.
///
/// Allow entries live in each repo's tests/codeDriftAllow.txt, one per line:
/// `<detector> <path>[::<symbol>] // <reason>`.
///
/// Exit codes: 0 clean (or a report), 1 findings, stale or broken allow entries under
/// `--check`, 2 config error.
#[derive(Parser)]
#[command(name = "code-drift", version)]
struct Cli {
    /// A detector to run alone, a row limit per detector (default 10), or both
    args: Vec<String>,

    /// Print one `detector<TAB>count` line per detector and nothing else
    #[arg(long, conflicts_with = "check")]
    summary: bool,

    /// Guard mode: list only what fails and exit 1 on any finding or stale allow entry
    #[arg(long)]
    check: bool,
}

struct Repo {
    id: String,
    allow_file: String,
    allow: Allow,
}

struct Section {
    detector: &'static str,
    kept: Vec<Finding>,
    allowed: usize,
}

struct Fat {
    classes: Vec<(usize, String)>,
    methods: Vec<(usize, String, String)>,
}

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
    let cli = Cli::parse();

    let mut only: Option<&'static str> = None;
    let mut limit = 10usize;
    for arg in &cli.args {
        if let Ok(number) = arg.parse::<usize>() {
            limit = number;
        } else if let Some(detector) = DETECTORS.iter().find(|d| d.id == arg) {
            only = Some(detector.id);
        } else {
            eprintln!("code-drift: unknown detector `{}`", arg);
            return ExitCode::from(2);
        }
    }

    let config = match config::load() {
        Ok(config) => config,
        Err(message) => {
            eprintln!("code-drift: config error: {}", message);
            eprintln!("hint: run it from the repo root, where config/tools/ lives.");
            return ExitCode::from(2);
        }
    };

    let start = Instant::now();
    let files = load_files(&config);
    let mut repos = discover_repos();
    let wants = |id: &str| only.is_none_or(|o| o == id);

    let mut sections: Vec<Section> = Vec::new();
    let mut ran: HashSet<&'static str> = HashSet::new();

    if wants("tiny") {
        let index = tiny_index(&config);
        let mut rows: Vec<(usize, Finding)> = files
            .iter()
            .filter_map(|file| structure::tiny(file, &index, config.tiny_max_lines))
            .collect();
        rows.sort_by(|a, b| a.0.cmp(&b.0).then_with(|| a.1.path.cmp(&b.1.path)));
        for tier in ["tiny", "tiny-judgement"] {
            let findings = rows
                .iter()
                .filter(|(_, finding)| finding.detector == tier)
                .map(|(_, finding)| finding.clone())
                .collect();
            sections.push(settle(&mut repos, tier, findings));
            ran.insert(tier);
        }
    }

    let fat = wants("fat").then(|| Fat {
        classes: structure::fat_classes(&files),
        methods: structure::fat_methods(&files),
    });

    let rules = NameRules {
        layer_words: config.layer_words.clone(),
        families: config.name_families.clone(),
        family_paths: config.name_family_paths.clone(),
    };
    let declared = if wants("mutable-service") {
        lines::declared_classes(&files)
    } else {
        HashSet::new()
    };
    for detector in DETECTORS.iter().skip(2) {
        if !wants(detector.id) {
            continue;
        }
        let findings: Vec<Finding> = files
            .iter()
            .flat_map(|file| match detector.id {
                "repo-in-controller" => lines::repo_in_controller(file),
                "mutable-service" => lines::mutable_service(file, &declared)
                    .into_iter()
                    .collect(),
                "fqcn" => lines::fqcn(file),
                "static" => lines::static_methods(file),
                "log-rethrow" => structure::log_rethrow(file),
                "raw-sql" => lines::raw_sql(file),
                "echo-name" => lines::echo_name(file, &rules).into_iter().collect(),
                _ => Vec::new(),
            })
            .collect();
        sections.push(settle(&mut repos, detector.id, findings));
        ran.insert(detector.id);
    }

    let stale = stale_entries(&repos, &ran);
    let problems = broken_lines(&repos);

    if cli.summary {
        print_summary(&sections, fat.as_ref());
        return ExitCode::SUCCESS;
    }

    if cli.check {
        return check(&sections, &problems, &stale, files.len(), start);
    }

    report(&sections, fat.as_ref(), limit);
    print_problems(&problems);
    print_stale(&stale);
    println!();
    println!(
        "{} production PHP files scanned in {} ms.",
        files.len(),
        start.elapsed().as_millis()
    );
    println!("Rules: {}", config.policy_doc);
    ExitCode::SUCCESS
}

// -- files ------------------------------------------------------------------

fn load_files(config: &Config) -> Vec<PhpFile> {
    let mut roots: Vec<PathBuf> = Vec::new();
    for path in &config.scan_paths {
        let is_source = path.file_name().and_then(|n| n.to_str()) == Some(&config.source_subdir);
        if is_source {
            roots.push(path.clone());
        } else {
            roots.extend(config::expand(&format!(
                "{}/*/{}",
                path.display(),
                config.source_subdir
            )));
        }
    }

    let mut paths: Vec<PathBuf> = Vec::new();
    for root in roots.iter().filter(|root| root.is_dir()) {
        for entry in WalkDir::new(root)
            .follow_links(false)
            .into_iter()
            .filter_entry(|entry| {
                !(entry.file_type().is_dir()
                    && entry
                        .file_name()
                        .to_str()
                        .is_some_and(|name| config.exclude_dirs.iter().any(|d| d == name)))
            })
            .filter_map(|entry| entry.ok())
        {
            if entry.file_type().is_file() && has_extension(entry.path(), "php") {
                paths.push(entry.path().to_path_buf());
            }
        }
    }
    paths.sort();
    paths.dedup();

    paths
        .iter()
        .filter_map(|path| {
            let raw = read_lossy(path)?;
            Some(PhpFile::new(&normalize(path), raw))
        })
        .collect()
}

fn tiny_index(config: &Config) -> TinyIndex {
    let mut index = TinyIndex::default();
    for pattern in &config.reference_paths {
        for root in config::expand(pattern) {
            for entry in WalkDir::new(&root).into_iter().filter_map(|e| e.ok()) {
                let path = entry.path();
                let wanted = config
                    .reference_extensions
                    .iter()
                    .any(|extension| has_extension(path, extension));
                if entry.file_type().is_file() && wanted {
                    if let Some(content) = read_lossy(path) {
                        index.add_reference(&normalize(path), &content);
                    }
                }
            }
        }
    }
    for pattern in &config.test_paths {
        for root in config::expand(pattern) {
            for entry in WalkDir::new(&root).into_iter().filter_map(|e| e.ok()) {
                if let Some(name) = entry.file_name().to_str() {
                    if name.ends_with("Test.php") {
                        index.add_test_file(name);
                    }
                }
            }
        }
    }
    index
}

fn has_extension(path: &Path, extension: &str) -> bool {
    path.extension().and_then(|e| e.to_str()) == Some(extension)
}

fn read_lossy(path: &Path) -> Option<String> {
    fs::read(path)
        .ok()
        .map(|bytes| String::from_utf8_lossy(&bytes).into_owned())
}

fn normalize(path: &Path) -> String {
    let text = path.to_string_lossy().replace('\\', "/");
    text.strip_prefix("./").unwrap_or(&text).to_string()
}

// -- repos and allow entries ------------------------------------------------

fn discover_repos() -> Vec<Repo> {
    let mut repos = vec![make_repo(String::new())];
    if let Ok(entries) = fs::read_dir("plugins") {
        let mut names: Vec<String> = entries
            .filter_map(|entry| entry.ok())
            .filter(|entry| entry.path().is_dir())
            .filter_map(|entry| entry.file_name().to_str().map(str::to_string))
            .collect();
        names.sort();
        repos.extend(
            names
                .into_iter()
                .map(|name| make_repo(format!("plugins/{}", name))),
        );
    }
    repos
}

fn make_repo(id: String) -> Repo {
    let root = if id.is_empty() {
        PathBuf::from(".")
    } else {
        PathBuf::from(&id)
    };
    let file = root.join(ALLOW_NAME);
    let allow = match fs::read_to_string(&file) {
        Ok(content) => allow::parse(&content, &root, id.is_empty()),
        Err(_) => Allow::default(),
    };
    Repo {
        id,
        allow_file: normalize(&file),
        allow,
    }
}

fn repo_for(repos: &[Repo], path: &str) -> usize {
    repos
        .iter()
        .position(|repo| !repo.id.is_empty() && path.starts_with(&format!("{}/", repo.id)))
        .unwrap_or(0)
}

fn repo_relative<'a>(path: &'a str, repo_id: &str) -> &'a str {
    if repo_id.is_empty() {
        return path;
    }
    path.strip_prefix(&format!("{}/", repo_id)).unwrap_or(path)
}

fn settle(repos: &mut [Repo], detector: &'static str, findings: Vec<Finding>) -> Section {
    let mut section = Section {
        detector,
        kept: Vec::new(),
        allowed: 0,
    };
    for finding in findings {
        let index = repo_for(repos, &finding.path);
        let relative = repo_relative(&finding.path, &repos[index].id).to_string();
        if repos[index]
            .allow
            .claim(detector, &relative, finding.symbol.as_deref())
        {
            section.allowed += 1;
        } else {
            section.kept.push(finding);
        }
    }
    section
}

fn stale_entries(repos: &[Repo], ran: &HashSet<&'static str>) -> Vec<String> {
    let mut stale = Vec::new();
    for repo in repos {
        for entry in &repo.allow.entries {
            if entry.used || !ran.contains(entry.detector.as_str()) {
                continue;
            }
            let target = match &entry.symbol {
                Some(symbol) => format!("{}::{}", entry.path, symbol),
                None => entry.path.clone(),
            };
            stale.push(format!(
                "{}:{}: {} {} // {}",
                repo.allow_file, entry.line, entry.detector, target, entry.reason
            ));
        }
    }
    stale
}

fn broken_lines(repos: &[Repo]) -> Vec<String> {
    repos
        .iter()
        .flat_map(|repo| {
            repo.allow.problems.iter().map(move |problem| {
                format!("{}:{}: {}", repo.allow_file, problem.line, problem.message)
            })
        })
        .collect()
}

// -- output -----------------------------------------------------------------

/// `fat` reports the longest method's length rather than a count of offenders.
fn print_summary(sections: &[Section], fat: Option<&Fat>) {
    for detector in &DETECTORS {
        if detector.id == "fat" {
            if let Some(fat) = fat {
                println!("fat\t{}", fat.methods.first().map_or(0, |m| m.0));
            }
            continue;
        }
        for section in sections
            .iter()
            .filter(|s| s.detector.starts_with(detector.id))
        {
            println!("{}\t{}", section.detector, section.kept.len());
        }
    }
}

fn heading(detector: &Detector) {
    let name = if std::io::stdout().is_terminal() {
        format!("\x1b[1m== {}\x1b[0m", detector.id)
    } else {
        format!("== {}", detector.id)
    };
    println!();
    println!("{}  {}", name, detector.title);
    println!("   rule: {}", detector.rule);
    println!();
}

fn report(sections: &[Section], fat: Option<&Fat>, limit: usize) {
    for detector in &DETECTORS {
        match detector.id {
            "tiny" => {
                let Some(tiny) = sections.iter().find(|s| s.detector == "tiny") else {
                    continue;
                };
                heading(detector);
                print_rows(tiny, limit);
                println!();
                println!(
                    "   {} fail all three tests{}",
                    tiny.kept.len(),
                    allowed_note(tiny)
                );
                println!();
                println!("   judgement tier - small and single-caller, but injects something");
                println!();
                if let Some(judgement) = sections.iter().find(|s| s.detector == "tiny-judgement") {
                    print_rows(judgement, limit);
                    println!();
                    println!(
                        "   {} in judgement tier{}",
                        judgement.kept.len(),
                        allowed_note(judgement)
                    );
                }
            }
            "fat" => {
                let Some(fat) = fat else { continue };
                heading(detector);
                println!(
                    "   classes by length (entities, enums and fixtures excluded - long by nature)"
                );
                println!();
                for (length, path) in fat.classes.iter().take(limit) {
                    println!("{:>6}  {}", length, path);
                }
                println!();
                println!("   methods by length");
                println!();
                for (length, path, name) in fat.methods.iter().take(limit) {
                    println!("{:>6}  {}  {}", length, path, name);
                }
            }
            id => {
                let Some(section) = sections.iter().find(|s| s.detector == id) else {
                    continue;
                };
                heading(detector);
                if section.kept.is_empty() {
                    println!("   (clean)");
                    if section.allowed > 0 {
                        println!("   ({} allowed)", section.allowed);
                    }
                } else {
                    print_rows(section, limit);
                    println!();
                    println!("   {} flagged{}", section.kept.len(), allowed_note(section));
                }
            }
        }
    }
}

fn print_rows(section: &Section, limit: usize) {
    if section.kept.is_empty() {
        println!("   (clean)");
    }
    for finding in section.kept.iter().take(limit) {
        println!("{}", finding.row);
    }
}

fn allowed_note(section: &Section) -> String {
    if section.allowed == 0 {
        String::new()
    } else {
        format!(", {} allowed", section.allowed)
    }
}

fn print_problems(problems: &[String]) {
    if problems.is_empty() {
        return;
    }
    println!();
    println!("Broken allow lines:");
    println!();
    for problem in problems {
        println!("  {}", problem);
    }
}

fn print_stale(stale: &[String]) {
    if stale.is_empty() {
        return;
    }
    println!();
    println!("Stale allow entries (they match no finding - delete or update them):");
    println!();
    for entry in stale {
        println!("  {}", entry);
    }
}

fn check(
    sections: &[Section],
    problems: &[String],
    stale: &[String],
    file_count: usize,
    start: Instant,
) -> ExitCode {
    let counted: Vec<&Section> = sections
        .iter()
        .filter(|s| s.detector != "tiny-judgement" && !s.kept.is_empty())
        .collect();
    let findings: usize = counted.iter().map(|s| s.kept.len()).sum();

    for section in &counted {
        println!();
        println!("{} ({}):", section.detector, section.kept.len());
        for finding in &section.kept {
            println!("  {}", finding.row.trim_end());
        }
    }
    print_problems(problems);
    print_stale(stale);

    let elapsed = start.elapsed().as_millis();
    if findings + problems.len() + stale.len() == 0 {
        println!(
            "code-drift: OK ({} files, 0 findings, {} ms)",
            file_count, elapsed
        );
        return ExitCode::SUCCESS;
    }
    println!();
    println!(
        "code-drift: FAILURES! ({} finding{}, {} stale allow entr{}, {} broken allow line{})",
        findings,
        if findings == 1 { "" } else { "s" },
        stale.len(),
        if stale.len() == 1 { "y" } else { "ies" },
        problems.len(),
        if problems.len() == 1 { "" } else { "s" }
    );
    ExitCode::from(1)
}

#[cfg(test)]
mod tests {
    use super::*;

    fn repos() -> Vec<Repo> {
        ["", "plugins/shelf"]
            .iter()
            .map(|id| Repo {
                id: id.to_string(),
                allow_file: ALLOW_NAME.to_string(),
                allow: Allow::default(),
            })
            .collect()
    }

    #[test]
    fn plugin_files_land_in_their_own_repo_with_a_relative_path() {
        let repos = repos();
        assert_eq!(repo_for(&repos, "src/Service/A.php"), 0);
        assert_eq!(repo_for(&repos, "plugins/shelf/src/A.php"), 1);
        assert_eq!(
            repo_relative("plugins/shelf/src/A.php", "plugins/shelf"),
            "src/A.php"
        );
    }

    #[test]
    fn a_reflowed_statement_still_matches_its_entry() {
        let root = std::env::temp_dir().join("code-drift-reflow");
        fs::create_dir_all(root.join("src/Repository")).unwrap();
        fs::write(root.join("src/Repository/RowRepository.php"), "<?php\n").unwrap();

        let one_line = "<?php\nfinal class RowRepository\n{\n    public function insertRows(): void\n    {\n        $this->connection->executeStatement('INSERT IGNORE INTO rows VALUES (1)');\n    }\n}\n";
        let reflowed = "<?php\nfinal class RowRepository\n{\n    public function insertRows(): void\n    {\n        $sql = 'x';\n\n        $this->connection\n            ->executeStatement(\n                'INSERT IGNORE INTO rows VALUES (1)',\n            );\n    }\n}\n";

        for source in [one_line, reflowed] {
            let mut repos = repos();
            repos[0].allow = allow::parse(
                "raw-sql src/Repository/RowRepository.php::insertRows // no DQL form\n",
                &root,
                true,
            );
            let file = PhpFile::new("src/Repository/RowRepository.php", source.to_string());
            let section = settle(&mut repos, "raw-sql", lines::raw_sql(&file));
            assert_eq!(section.allowed, 1);
            assert!(section.kept.is_empty());
            assert!(repos[0].allow.entries[0].used);
        }
    }

    #[test]
    fn an_entry_whose_detector_did_not_run_is_not_stale() {
        let root = std::env::temp_dir().join("code-drift-stale");
        fs::create_dir_all(root.join("src")).unwrap();
        fs::write(root.join("src/A.php"), "<?php\n").unwrap();
        let mut repos = repos();
        repos[0].allow = allow::parse("static src/A.php // why\n", &root, true);

        assert!(stale_entries(&repos, &HashSet::from(["fqcn"])).is_empty());
        assert_eq!(stale_entries(&repos, &HashSet::from(["static"])).len(), 1);
    }
}
