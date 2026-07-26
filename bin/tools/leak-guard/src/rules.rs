use regex::Regex;
use toolconfig::Config;

pub struct Rules {
    pub words: Vec<String>,
    pub patterns: Vec<Pattern>,
    allowances: Vec<Allowance>,
    words_exclude_paths: Vec<String>,
}

pub struct Pattern {
    pub name: String,
    regex: Regex,
}

struct Allowance {
    path: String,
    pattern: String,
}

pub enum Finding {
    Word {
        line_no: usize,
        col: usize,
        word: String,
        content: String,
    },
    Credential {
        line_no: usize,
        pattern: String,
    },
}

impl Finding {
    pub fn render(&self, path: &str) -> String {
        match self {
            Finding::Word {
                line_no,
                col,
                word,
                content,
            } => format!("{}:{}:{}: [{}] {}", path, line_no, col, word, content),
            // The matched text is withheld on purpose: echoing it would print the very
            // credential the guard exists to keep out of terminals, CI logs and paste buffers.
            Finding::Credential { line_no, pattern } => {
                format!("{}:{}: [{}] credential-shaped value (content withheld)", path, line_no, pattern)
            }
        }
    }
}

impl Rules {
    pub fn from_config(config: &Config) -> Result<Self, String> {
        let words: Vec<String> = config
            .list("WORDS")
            .into_iter()
            .map(|word| word.to_lowercase())
            .collect();

        let mut patterns = Vec::new();
        for entry in config.raw_list("PATTERNS") {
            let (name, source) = entry
                .split_once('=')
                .ok_or_else(|| format!("PATTERNS entry is not `name=regex`: {}", entry))?;
            let regex = Regex::new(source)
                .map_err(|e| format!("PATTERNS entry `{}` has an invalid regex: {}", name, e))?;
            patterns.push(Pattern {
                name: name.trim().to_string(),
                regex,
            });
        }

        let mut allowances = Vec::new();
        for entry in config.list("ALLOW") {
            let (path, pattern) = entry
                .split_once("::")
                .ok_or_else(|| format!("ALLOW entry is not `path::pattern-name`: {}", entry))?;
            allowances.push(Allowance {
                path: path.trim().to_string(),
                pattern: pattern.trim().to_string(),
            });
        }

        for allowance in &allowances {
            if !patterns.iter().any(|p| p.name == allowance.pattern) {
                return Err(format!(
                    "ALLOW entry names an unknown pattern: {}",
                    allowance.pattern
                ));
            }
        }

        if words.is_empty() && patterns.is_empty() {
            return Err("neither WORDS nor PATTERNS is set, so there is nothing to scan for".to_string());
        }

        Ok(Self {
            words,
            patterns,
            allowances,
            words_exclude_paths: config.list("WORDS_EXCLUDE_PATHS"),
        })
    }

    pub fn scan_line(&self, path: &str, line_no: usize, line: &str) -> Vec<Finding> {
        let mut findings = Vec::new();

        if !self.vocabulary_exempt(path) {
            let lower = line.to_lowercase();
            for word in &self.words {
                if let Some(col) = lower.find(word) {
                    findings.push(Finding::Word {
                        line_no,
                        col: col + 1,
                        word: word.clone(),
                        content: line.trim_end().to_string(),
                    });
                }
            }
        }

        for pattern in &self.patterns {
            if pattern.regex.is_match(line) && !self.is_allowed(path, &pattern.name) {
                findings.push(Finding::Credential {
                    line_no,
                    pattern: pattern.name.clone(),
                });
            }
        }

        findings
    }

    fn is_allowed(&self, path: &str, pattern: &str) -> bool {
        self.allowances
            .iter()
            .any(|allowance| allowance.pattern == pattern && path.starts_with(&allowance.path))
    }

    /// Directories that are allowed to name the words but not to hold the credentials, so the
    /// two families part company here rather than at the directory walk.
    fn vocabulary_exempt(&self, path: &str) -> bool {
        self.words_exclude_paths
            .iter()
            .any(|prefix| path.starts_with(prefix))
    }

    pub fn summary(&self) -> String {
        format!(
            "{} pattern{}, {} word{}",
            self.patterns.len(),
            if self.patterns.len() == 1 { "" } else { "s" },
            self.words.len(),
            if self.words.len() == 1 { "" } else { "s" }
        )
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    const DIST: &str = "../../../config/tools/leak-guard.dist";
    const FIXTURES: &str = "tests/fixtures/patterns.txt";

    fn rules(entries: &[&str]) -> Rules {
        let mut patterns = Vec::new();
        for entry in entries {
            let (name, source) = entry.split_once('=').unwrap();
            patterns.push(Pattern {
                name: name.to_string(),
                regex: Regex::new(source).unwrap(),
            });
        }
        Rules {
            words: Vec::new(),
            patterns,
            allowances: Vec::new(),
            words_exclude_paths: Vec::new(),
        }
    }

    fn shipped_patterns() -> Rules {
        let dist = std::fs::read_to_string(DIST)
            .unwrap_or_else(|e| panic!("cannot read {} from the crate directory: {}", DIST, e));
        let entries: Vec<&str> = dist
            .lines()
            .filter_map(|line| line.trim().strip_prefix("PATTERNS="))
            .collect();
        rules(&entries)
    }

    /// The examples are stored spliced by `SPLICE` so the file never holds a complete
    /// credential-shaped literal; see the header of the fixture file for why.
    fn fixtures() -> Vec<(String, String, String)> {
        const SPLICE: &str = "<>";
        let content = std::fs::read_to_string(FIXTURES)
            .unwrap_or_else(|e| panic!("cannot read {}: {}", FIXTURES, e));
        content
            .lines()
            .map(str::trim)
            .filter(|line| !line.is_empty() && !line.starts_with('#'))
            .map(|line| {
                let parts: Vec<&str> = line.splitn(3, '|').collect();
                assert_eq!(3, parts.len(), "fixture line is not `name|match|near-miss`: {}", line);
                (
                    parts[0].to_string(),
                    parts[1].replace(SPLICE, ""),
                    parts[2].replace(SPLICE, ""),
                )
            })
            .collect()
    }

    fn hits(rules: &Rules, line: &str) -> Vec<String> {
        rules
            .scan_line("some/file.php", 1, line)
            .into_iter()
            .filter_map(|finding| match finding {
                Finding::Credential { pattern, .. } => Some(pattern),
                Finding::Word { .. } => None,
            })
            .collect()
    }

    #[test]
    fn every_shipped_pattern_has_a_fixture() {
        let shipped: Vec<String> = shipped_patterns()
            .patterns
            .iter()
            .map(|pattern| pattern.name.clone())
            .collect();
        let covered: Vec<String> = fixtures().into_iter().map(|(name, _, _)| name).collect();

        let uncovered: Vec<&String> = shipped.iter().filter(|name| !covered.contains(name)).collect();
        let stale: Vec<&String> = covered.iter().filter(|name| !shipped.contains(name)).collect();

        assert!(uncovered.is_empty(), "patterns without a fixture: {:?}", uncovered);
        assert!(stale.is_empty(), "fixtures for patterns that no longer exist: {:?}", stale);
    }

    #[test]
    fn each_pattern_matches_its_example_and_misses_its_near_miss() {
        let rules = shipped_patterns();
        let mut failures: Vec<String> = Vec::new();

        for (name, positive, negative) in fixtures() {
            if !hits(&rules, &positive).contains(&name) {
                failures.push(format!("{}: did not match its example", name));
            }
            if hits(&rules, &negative).contains(&name) {
                failures.push(format!("{}: matched its near-miss", name));
            }
        }

        assert!(failures.is_empty(), "{}", failures.join("\n"));
    }

    #[test]
    fn an_allow_entry_suppresses_one_pattern_at_one_path() {
        let mut rules = rules(&["secret=SECRET_[A-Z]+", "other=OTHER_[A-Z]+"]);
        rules.allowances.push(Allowance {
            path: ".env.dist".to_string(),
            pattern: "secret".to_string(),
        });

        assert!(rules.scan_line(".env.dist", 1, "SECRET_VALUE").is_empty());
        assert!(!rules.scan_line(".env.dist", 1, "OTHER_VALUE").is_empty());
        assert!(!rules.scan_line("src/Other.php", 1, "SECRET_VALUE").is_empty());
    }

    #[test]
    fn a_credential_finding_never_renders_the_matched_text() {
        let rules = rules(&["secret=SECRET_[A-Z]+"]);

        let findings = rules.scan_line("src/Leak.php", 12, "$k = 'SECRET_ABCDEF';");
        let rendered = findings[0].render("src/Leak.php");

        assert!(!rendered.contains("SECRET_ABCDEF"));
        assert!(rendered.contains("src/Leak.php:12"));
        assert!(rendered.contains("[secret]"));
    }

    #[test]
    fn word_findings_still_show_the_line_so_they_can_be_fixed() {
        let rules = word_rules(&[]);

        let findings = rules.scan_line("src/A.php", 3, "// a Forbidden mention");

        assert_eq!(
            "src/A.php:3:6: [forbidden] // a Forbidden mention",
            findings[0].render("src/A.php")
        );
    }

    #[test]
    fn words_exclude_paths_exempts_the_vocabulary_family_but_not_the_credentials() {
        let mut rules = word_rules(&["docs/private"]);
        rules.patterns.push(Pattern {
            name: "secret".to_string(),
            regex: Regex::new("SECRET_[A-Z]+").unwrap(),
        });

        assert!(rules
            .scan_line("docs/private/a.md", 1, "a forbidden mention")
            .is_empty());
        assert!(!rules
            .scan_line("docs/private/a.md", 1, "SECRET_ABCDEF")
            .is_empty());
        assert!(!rules
            .scan_line("docs/public/a.md", 1, "a forbidden mention")
            .is_empty());
    }

    fn word_rules(exempt: &[&str]) -> Rules {
        Rules {
            words: vec!["forbidden".to_string()],
            patterns: Vec::new(),
            allowances: Vec::new(),
            words_exclude_paths: exempt.iter().map(|p| p.to_string()).collect(),
        }
    }
}
