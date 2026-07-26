use std::path::{Path, PathBuf};

pub const BASELINE_NAME: &str = "tests/importantCodeComments.txt";

#[derive(Debug, Clone)]
pub struct Entry {
    pub path: String,
    pub symbol: String,
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
pub struct Baseline {
    pub entries: Vec<Entry>,
    pub problems: Vec<Problem>,
}

impl Baseline {
    pub fn claim(&mut self, path: &str, symbol: &str) -> Option<&Entry> {
        let index = self
            .entries
            .iter()
            .position(|e| e.path == path && e.symbol == symbol)?;
        self.entries[index].used = true;
        Some(&self.entries[index])
    }
}

/// Parse a baseline file. `repo_root` is the directory the baseline belongs to; entries are
/// resolved against it so a line pointing at a file the owning repo does not contain is rejected.
pub fn parse(content: &str, repo_root: &Path, is_core: bool) -> Baseline {
    let mut baseline = Baseline::default();

    for (index, raw) in content.lines().enumerate() {
        let line = index + 1;
        let text = raw.trim();
        if text.is_empty() || text.starts_with('#') {
            continue;
        }

        let Some((key, reason)) = text.split_once("//") else {
            baseline.problems.push(Problem {
                line,
                message: format!("missing reason; expected `path::symbol // why` in `{}`", text),
            });
            continue;
        };
        let reason = reason.trim();
        if reason.is_empty() {
            baseline.problems.push(Problem {
                line,
                message: format!("empty reason; every entry must say why in `{}`", text),
            });
            continue;
        }

        let Some((path, symbol)) = key.trim().rsplit_once("::") else {
            baseline.problems.push(Problem {
                line,
                message: format!("missing `::` between path and symbol in `{}`", key.trim()),
            });
            continue;
        };
        let path = path.trim().to_string();
        let symbol = symbol.trim().to_string();

        if is_core && path.starts_with("plugins/") {
            baseline.problems.push(Problem {
                line,
                message: format!(
                    "`{}` belongs to another repo; put it in plugins/<name>/{}",
                    path, BASELINE_NAME
                ),
            });
            continue;
        }
        if path.starts_with('/') || path.contains("..") {
            baseline.problems.push(Problem {
                line,
                message: format!("`{}` must be a path relative to the repo root", path),
            });
            continue;
        }
        if !repo_root.join(&path).is_file() {
            baseline.problems.push(Problem {
                line,
                message: format!("`{}` does not exist", path),
            });
            continue;
        }

        baseline.entries.push(Entry {
            path,
            symbol,
            reason: reason.to_string(),
            line,
            used: false,
        });
    }

    baseline
}

pub fn baseline_path(repo_root: &Path) -> PathBuf {
    repo_root.join(BASELINE_NAME)
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::fs;

    fn temp_repo(files: &[&str]) -> PathBuf {
        let root = std::env::temp_dir().join(format!("comment-guard-test-{}", files.len()));
        for file in files {
            let full = root.join(file);
            fs::create_dir_all(full.parent().unwrap()).unwrap();
            fs::write(&full, "<?php\n").unwrap();
        }
        root
    }

    #[test]
    fn parses_an_entry_with_a_reason() {
        let root = temp_repo(&["src/Service/Foo.php"]);
        let baseline = parse(
            "# header\nsrc/Service/Foo.php::resolve // numbered steps: four sources in priority order\n",
            &root,
            true,
        );
        assert!(baseline.problems.is_empty());
        assert_eq!(baseline.entries.len(), 1);
        assert_eq!(baseline.entries[0].path, "src/Service/Foo.php");
        assert_eq!(baseline.entries[0].symbol, "resolve");
        assert_eq!(
            baseline.entries[0].reason,
            "numbered steps: four sources in priority order"
        );
    }

    #[test]
    fn parses_a_file_scope_entry() {
        let root = temp_repo(&["config/bootstrap.php", "src/A.php"]);
        let baseline = parse("config/bootstrap.php:: // generated header\n", &root, true);
        assert!(baseline.problems.is_empty());
        assert_eq!(baseline.entries[0].symbol, "");
    }

    #[test]
    fn rejects_a_reasonless_line() {
        let root = temp_repo(&["src/A.php", "src/B.php", "src/C.php"]);
        let baseline = parse("src/A.php::go\n", &root, true);
        assert_eq!(baseline.entries.len(), 0);
        assert!(baseline.problems[0].message.contains("missing reason"));
    }

    #[test]
    fn rejects_an_empty_reason() {
        let root = temp_repo(&["src/A.php", "src/B.php", "src/C.php", "src/D.php"]);
        let baseline = parse("src/A.php::go //   \n", &root, true);
        assert_eq!(baseline.entries.len(), 0);
        assert!(baseline.problems[0].message.contains("empty reason"));
    }

    #[test]
    fn rejects_a_cross_repo_path_in_the_core_baseline() {
        let root = temp_repo(&["a.php", "b.php", "c.php", "d.php", "e.php"]);
        let baseline = parse("plugins/books/src/A.php::go // why\n", &root, true);
        assert_eq!(baseline.entries.len(), 0);
        assert!(baseline.problems[0].message.contains("another repo"));
    }

    #[test]
    fn rejects_a_path_that_does_not_exist() {
        let root = temp_repo(&["a.php", "b.php", "c.php", "d.php", "e.php", "f.php"]);
        let baseline = parse("src/Gone.php::go // why\n", &root, true);
        assert_eq!(baseline.entries.len(), 0);
        assert!(baseline.problems[0].message.contains("does not exist"));
    }

    #[test]
    fn claiming_marks_the_entry_used() {
        let root = temp_repo(&["a.php", "b.php", "c.php", "d.php", "e.php", "f.php", "g.php"]);
        let mut baseline = parse("a.php::go // why\n", &root, true);
        assert!(baseline.claim("a.php", "go").is_some());
        assert!(baseline.entries[0].used);
        assert!(baseline.claim("a.php", "other").is_none());
    }
}
