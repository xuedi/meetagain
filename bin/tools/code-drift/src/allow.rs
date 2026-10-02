use std::path::Path;

pub const ALLOW_NAME: &str = "tests/codeDriftAllow.txt";

#[derive(Debug, Clone)]
pub struct Entry {
    pub detector: String,
    pub path: String,
    pub symbol: Option<String>,
    pub reason: String,
    pub line: usize,
    pub used: bool,
}

#[derive(Debug, Clone)]
pub struct Problem {
    pub line: usize,
    pub message: String,
}

#[derive(Debug, Default)]
pub struct Allow {
    pub entries: Vec<Entry>,
    pub problems: Vec<Problem>,
}

impl Allow {
    pub fn claim(&mut self, detector: &str, path: &str, symbol: Option<&str>) -> bool {
        let mut claimed = false;
        for entry in self.entries.iter_mut() {
            let symbol_matches = match &entry.symbol {
                None => true,
                Some(wanted) => symbol == Some(wanted.as_str()),
            };
            if entry.detector == detector && entry.path == path && symbol_matches {
                entry.used = true;
                claimed = true;
            }
        }
        claimed
    }
}

/// `Some(true)` for a detector whose findings name a symbol an entry can narrow to, `Some(false)`
/// for one whose findings are the whole file, `None` for a name no allow entry may use.
pub fn carries_symbol(detector: &str) -> Option<bool> {
    match detector {
        "repo-in-controller" | "fqcn" | "static" | "log-rethrow" | "raw-sql" => Some(true),
        "tiny" | "tiny-judgement" | "mutable-service" | "echo-name" => Some(false),
        _ => None,
    }
}

/// Parse one repo's allow file. Paths are relative to `repo_root`, the directory the file
/// belongs to, so an entry can only ever except a file that repo contains.
pub fn parse(content: &str, repo_root: &Path, is_core: bool) -> Allow {
    let mut allow = Allow::default();

    for (index, raw) in content.lines().enumerate() {
        let line = index + 1;
        let text = raw.trim();
        if text.is_empty() || text.starts_with('#') {
            continue;
        }
        match parse_line(text, repo_root, is_core) {
            Ok((detector, path, symbol, reason)) => allow.entries.push(Entry {
                detector,
                path,
                symbol,
                reason,
                line,
                used: false,
            }),
            Err(message) => allow.problems.push(Problem { line, message }),
        }
    }

    allow
}

type Parsed = (String, String, Option<String>, String);

fn parse_line(text: &str, repo_root: &Path, is_core: bool) -> Result<Parsed, String> {
    let shape = "expected `<detector> <path>[::<symbol>] // <reason>`";

    let Some((key, reason)) = text.split_once("//") else {
        return Err(format!("missing reason; {} in `{}`", shape, text));
    };
    let reason = reason.trim();
    if reason.is_empty() {
        return Err(format!(
            "empty reason; every entry must say why in `{}`",
            text
        ));
    }

    let parts: Vec<&str> = key.split_whitespace().collect();
    let [detector, target] = parts[..] else {
        return Err(format!("{} in `{}`", shape, key.trim()));
    };

    let Some(has_symbol) = carries_symbol(detector) else {
        return Err(format!(
            "`{}` is not a detector an entry can allow",
            detector
        ));
    };

    let (path, symbol) = match target.split_once("::") {
        Some((path, symbol)) => {
            if symbol.is_empty() {
                return Err(format!("empty symbol after `::` in `{}`", target));
            }
            (path, Some(symbol.to_string()))
        }
        None => (target, None),
    };
    if symbol.is_some() && !has_symbol {
        return Err(format!(
            "`{}` findings carry no symbol; name the file alone in `{}`",
            detector, target
        ));
    }

    if path.contains(['*', '?', '[', ']', '{', '}', ':']) {
        return Err(format!(
            "`{}` must name one file - no pattern, no line number",
            path
        ));
    }
    if is_core && path.starts_with("plugins/") {
        return Err(format!(
            "`{}` belongs to another repo; put it in plugins/<name>/{}",
            path, ALLOW_NAME
        ));
    }
    if path.starts_with('/') || path.contains("..") {
        return Err(format!(
            "`{}` must be a path relative to the repo root",
            path
        ));
    }
    if !repo_root.join(path).is_file() {
        return Err(format!("`{}` is not an existing file", path));
    }

    Ok((
        detector.to_string(),
        path.to_string(),
        symbol,
        reason.to_string(),
    ))
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::fs;
    use std::path::PathBuf;

    fn temp_repo(name: &str, files: &[&str]) -> PathBuf {
        let root = std::env::temp_dir().join(format!("code-drift-allow-{}", name));
        for file in files {
            let full = root.join(file);
            fs::create_dir_all(full.parent().unwrap()).unwrap();
            fs::write(&full, "<?php\n").unwrap();
        }
        root
    }

    #[test]
    fn parses_an_entry_with_a_symbol_and_a_reason() {
        let root = temp_repo("symbol", &["src/Repository/Rows.php"]);
        let allow = parse(
            "# header\nraw-sql src/Repository/Rows.php::insertRows // INSERT IGNORE has no DQL form\n",
            &root,
            true,
        );
        assert!(allow.problems.is_empty());
        assert_eq!(allow.entries.len(), 1);
        let entry = &allow.entries[0];
        assert_eq!(entry.detector, "raw-sql");
        assert_eq!(entry.path, "src/Repository/Rows.php");
        assert_eq!(entry.symbol.as_deref(), Some("insertRows"));
        assert_eq!(entry.reason, "INSERT IGNORE has no DQL form");
        assert_eq!(entry.line, 2);
    }

    #[test]
    fn parses_a_file_wide_entry() {
        let root = temp_repo("file-wide", &["src/Naming/NamingThing.php"]);
        let allow = parse(
            "echo-name src/Naming/NamingThing.php // would collide\n",
            &root,
            true,
        );
        assert!(allow.problems.is_empty());
        assert_eq!(allow.entries[0].symbol, None);
    }

    #[test]
    fn rejects_a_line_without_a_reason() {
        let root = temp_repo("no-reason", &["src/A.php"]);
        let allow = parse("static src/A.php::go\n", &root, true);
        assert!(allow.entries.is_empty());
        assert!(allow.problems[0].message.contains("missing reason"));
    }

    #[test]
    fn rejects_an_empty_reason() {
        let root = temp_repo("empty-reason", &["src/A.php"]);
        let allow = parse("static src/A.php::go //   \n", &root, true);
        assert!(allow.problems[0].message.contains("empty reason"));
    }

    #[test]
    fn rejects_an_unknown_detector() {
        let root = temp_repo("unknown", &["src/A.php"]);
        let allow = parse("statics src/A.php // why\n", &root, true);
        assert!(allow.problems[0].message.contains("not a detector"));
    }

    #[test]
    fn rejects_the_informational_fat_detector() {
        let root = temp_repo("fat", &["src/A.php"]);
        let allow = parse("fat src/A.php // long on purpose\n", &root, true);
        assert!(allow.problems[0].message.contains("not a detector"));
    }

    #[test]
    fn rejects_a_path_that_does_not_exist() {
        let root = temp_repo("missing", &["src/A.php"]);
        let allow = parse("static src/Gone.php // why\n", &root, true);
        assert!(allow.problems[0].message.contains("not an existing file"));
    }

    #[test]
    fn rejects_a_directory() {
        let root = temp_repo("directory", &["src/Entity/A.php"]);
        let allow = parse("static src/Entity // why\n", &root, true);
        assert!(allow.problems[0].message.contains("not an existing file"));
    }

    #[test]
    fn rejects_a_cross_repo_path_in_the_core_file() {
        let root = temp_repo("cross-repo", &["plugins/books/src/A.php"]);
        let allow = parse("static plugins/books/src/A.php // why\n", &root, true);
        assert!(allow.problems[0].message.contains("another repo"));
    }

    #[test]
    fn rejects_a_pattern_or_a_line_number() {
        let root = temp_repo("pattern", &["src/A.php"]);
        let allow = parse(
            "static src/*.php // why\nstatic src/A.php:12 // why\n",
            &root,
            true,
        );
        assert_eq!(allow.problems.len(), 2);
        assert!(allow
            .problems
            .iter()
            .all(|p| p.message.contains("one file")));
    }

    #[test]
    fn rejects_a_symbol_on_a_file_level_detector() {
        let root = temp_repo("file-level-symbol", &["src/Service/A.php"]);
        let allow = parse(
            "mutable-service src/Service/A.php::go // why\n",
            &root,
            true,
        );
        assert!(allow.problems[0].message.contains("carry no symbol"));
    }

    #[test]
    fn a_symbol_entry_claims_only_its_own_symbol() {
        let root = temp_repo("claim-symbol", &["src/A.php"]);
        let mut allow = parse("static src/A.php::make // why\n", &root, true);
        assert!(!allow.claim("static", "src/A.php", Some("other")));
        assert!(!allow.entries[0].used);
        assert!(allow.claim("static", "src/A.php", Some("make")));
        assert!(allow.entries[0].used);
    }

    #[test]
    fn a_file_entry_claims_every_finding_of_its_detector_in_the_file() {
        let root = temp_repo("claim-file", &["src/A.php"]);
        let mut allow = parse("static src/A.php // why\n", &root, true);
        assert!(allow.claim("static", "src/A.php", Some("one")));
        assert!(allow.claim("static", "src/A.php", Some("two")));
        assert!(!allow.claim("raw-sql", "src/A.php", Some("one")));
    }

    #[test]
    fn an_unclaimed_entry_stays_unused() {
        let root = temp_repo("stale", &["src/A.php"]);
        let allow = parse("static src/A.php // why\n", &root, true);
        assert!(!allow.entries[0].used);
    }
}
