use std::collections::{HashMap, HashSet};

use phplex::BlockKind;
use regex::Regex;

use crate::source::{Finding, PhpFile};

pub fn log_rethrow(file: &PhpFile) -> Vec<Finding> {
    let logs = regex!(r"->(error|warning|critical|alert|emergency)\(");
    let throws = regex!(r"(^|\s)throw ");
    let lines: Vec<&str> = file.code_lines().collect();

    file.blocks
        .iter()
        .filter(|block| block.kind == BlockKind::Catch)
        .filter(|block| {
            let body = &lines[block.start_line - 1..block.end_line.min(lines.len())];
            body.iter().any(|l| logs.is_match(l)) && body.iter().any(|l| throws.is_match(l))
        })
        .map(|block| Finding {
            detector: "log-rethrow",
            path: file.path.clone(),
            symbol: file.enclosing_method(block.start_line).map(str::to_string),
            row: format!("{}:{}", file.path, block.start_line),
        })
        .collect()
}

/// Longest files first. Entities, enums and fixtures are long by nature.
pub fn fat_classes(files: &[PhpFile]) -> Vec<(usize, String)> {
    let mut rows: Vec<(usize, String)> = files
        .iter()
        .filter(|f| {
            !["/Entity/", "/Enum/", "/DataFixtures/"]
                .iter()
                .any(|d| f.path.contains(d))
        })
        .map(|f| (f.line_count(), f.path.clone()))
        .collect();
    rows.sort_by(|a, b| b.0.cmp(&a.0).then_with(|| a.1.cmp(&b.1)));
    rows
}

/// Longest methods first, measured from the `function` keyword to the closing brace.
pub fn fat_methods(files: &[PhpFile]) -> Vec<(usize, String, String)> {
    let mut rows = Vec::new();
    for file in files.iter().filter(|f| !f.path.contains("/DataFixtures/")) {
        for block in &file.blocks {
            let in_type = block
                .parent
                .is_some_and(|parent| file.blocks[parent].kind.is_type());
            if block.kind == BlockKind::Function && !block.name.is_empty() && in_type {
                rows.push((
                    block.end_line - block.start_line,
                    file.path.clone(),
                    block.name.clone(),
                ));
            }
        }
    }
    rows.sort_by(|a, b| {
        b.0.cmp(&a.0)
            .then_with(|| a.1.cmp(&b.1))
            .then_with(|| a.2.cmp(&b.2))
    });
    rows
}

/// Which files mention which words, and which classes have a test of their own.
#[derive(Default)]
pub struct TinyIndex {
    files_by_word: HashMap<String, Vec<usize>>,
    file_ids: HashMap<String, usize>,
    test_files: HashSet<String>,
}

impl TinyIndex {
    pub fn add_reference(&mut self, path: &str, content: &str) {
        let id = self.file_ids.len();
        self.file_ids.insert(path.to_string(), id);
        let words: HashSet<&str> = content
            .split(|c: char| !(c.is_alphanumeric() || c == '_'))
            .filter(|word| !word.is_empty())
            .collect();
        for word in words {
            self.files_by_word
                .entry(word.to_string())
                .or_default()
                .push(id);
        }
    }

    pub fn add_test_file(&mut self, file_name: &str) {
        self.test_files.insert(file_name.to_string());
    }

    /// Files that mention `class` as a whole word, its own file excluded.
    pub fn callers(&self, class: &str, own_path: &str) -> usize {
        let Some(ids) = self.files_by_word.get(class) else {
            return 0;
        };
        let own = self.file_ids.get(own_path);
        ids.iter().filter(|id| Some(*id) != own).count()
    }

    pub fn has_test(&self, class: &str) -> bool {
        self.test_files.contains(&format!("{}Test.php", class))
    }
}

/// `(lines, finding)`. A class earns its file with its own dependencies, more than one caller,
/// or its own unit test. One that injects something but is small and single-caller lands in the
/// judgement tier: a chain of those is what makes a feature hard to follow, and no rule can
/// decide that.
pub fn tiny(file: &PhpFile, index: &TinyIndex, max_lines: usize) -> Option<(usize, Finding)> {
    let lines = file.line_count();
    if lines > max_lines {
        return None;
    }
    let code: Vec<&str> = file.code_lines().collect();
    let any = |pattern: &Regex| code.iter().any(|l| pattern.is_match(l));

    if !any(regex!(r"^(abstract |final )*(readonly )*class ")) {
        return None;
    }
    if any(regex!(r"^\s*(interface|enum|trait) "))
        || any(regex!(
            r"^\s*(abstract class|.*\bimplements\b|.*\bextends\b)"
        ))
        || any(regex!(r"^#\[(As|Autoconfigure|Route|When)"))
        || any(regex!(r"AutowireIterator|TaggedIterator|AutowireLocator"))
    {
        return None;
    }

    let promoted = regex!(
        r"^\s+(private|protected|public)\s+(readonly\s+)?[^ (]+\s+\$[a-zA-Z_]+\s*(=[^,]*)?,?\s*$"
    );
    let own_dependency =
        regex!(r"^\s+(private|protected)\s+(readonly\s+)?[^ (]+\s+\$[a-zA-Z_]+\s*(=[^,]*)?,?\s*$");
    let deps = code.iter().filter(|l| own_dependency.is_match(l)).count();
    let public = code.iter().filter(|l| promoted.is_match(l)).count() - deps;
    if deps == 0 && public > 0 {
        return None;
    }

    let class = file.class_name();
    let callers = index.callers(class, &file.path);
    if callers > 1 {
        return None;
    }

    if deps == 0 {
        if index.has_test(class) {
            return None;
        }
        return Some((
            lines,
            Finding {
                detector: "tiny",
                path: file.path.clone(),
                symbol: None,
                row: format!("{:>4}  {:<70} callers:{}", lines, file.path, callers),
            },
        ));
    }
    Some((
        lines,
        Finding {
            detector: "tiny-judgement",
            path: file.path.clone(),
            symbol: None,
            row: format!(
                "{:>4}  {:<70} callers:{} deps:{}",
                lines, file.path, callers, deps
            ),
        },
    ))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn php(path: &str, body: &str) -> PhpFile {
        PhpFile::new(path, format!("<?php\n\n{}", body))
    }

    fn index_with(references: &[(&str, &str)], tests: &[&str]) -> TinyIndex {
        let mut index = TinyIndex::default();
        for (path, content) in references {
            index.add_reference(path, content);
        }
        for test in tests {
            index.add_test_file(test);
        }
        index
    }

    const HELPER: &str = "final class Slugger\n{\n    public function slug(string $s): string\n    {\n        return $s;\n    }\n}\n";
    const INJECTING: &str = "final readonly class Slugger\n{\n    public function __construct(\n        private Clock $clock,\n    ) {}\n}\n";

    #[test]
    fn log_rethrow_flags_a_catch_that_logs_and_throws() {
        let file = php(
            "src/Service/Sync.php",
            "final class Sync\n{\n    public function run(): void\n    {\n        try {\n            $this->go();\n        } catch (E $e) {\n            $this->logger->error('failed');\n            throw $e;\n        }\n    }\n}\n",
        );
        let found = log_rethrow(&file);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].row, "src/Service/Sync.php:9");
        assert_eq!(found[0].symbol.as_deref(), Some("run"));
    }

    #[test]
    fn log_rethrow_accepts_log_only_and_rethrow_only() {
        let file = php(
            "src/Service/Sync.php",
            "final class Sync\n{\n    public function run(): void\n    {\n        try {\n        } catch (E $e) {\n            $this->logger->error('failed');\n        }\n        try {\n        } catch (E $e) {\n            throw $e;\n        }\n    }\n}\n",
        );
        assert!(log_rethrow(&file).is_empty());
    }

    #[test]
    fn log_rethrow_is_not_fooled_by_a_brace_in_a_string() {
        let file = php(
            "src/Service/Sync.php",
            "final class Sync\n{\n    public function run(): void\n    {\n        try {\n        } catch (E $e) {\n            $this->logger->error('}');\n        }\n        throw new E();\n    }\n}\n",
        );
        assert!(log_rethrow(&file).is_empty());
    }

    #[test]
    fn fat_methods_counts_from_signature_to_closing_brace() {
        let file = php(
            "src/Service/Long.php",
            "final class Long\n{\n    public function go(\n        int $a,\n    ): string {\n        return '{';\n    }\n}\n",
        );
        assert_eq!(
            fat_methods(&[file]),
            vec![(4, "src/Service/Long.php".to_string(), "go".to_string())]
        );
    }

    #[test]
    fn fat_classes_skip_entities() {
        let entity = php("src/Entity/Row.php", "final class Row {}\n");
        let service = php("src/Service/Rows.php", "final class Rows {}\n");
        assert_eq!(fat_classes(&[entity, service]).len(), 1);
    }

    #[test]
    fn tiny_flags_a_helper_with_one_caller_and_no_test() {
        let file = php("src/Service/Slugger.php", HELPER);
        let index = index_with(
            &[
                ("src/Service/Slugger.php", "class Slugger"),
                ("src/Service/Caller.php", "new Slugger()"),
            ],
            &[],
        );
        let (_, finding) = tiny(&file, &index, 60).unwrap();
        assert_eq!(finding.detector, "tiny");
        assert!(finding.row.ends_with("callers:1"));
    }

    #[test]
    fn tiny_skips_a_class_with_two_callers() {
        let file = php("src/Service/Slugger.php", HELPER);
        let index = index_with(
            &[
                ("src/Service/A.php", "Slugger"),
                ("templates/b.html.twig", "Slugger"),
            ],
            &[],
        );
        assert!(tiny(&file, &index, 60).is_none());
    }

    #[test]
    fn tiny_counts_whole_words_only() {
        let file = php("src/Service/Slugger.php", HELPER);
        let index = index_with(
            &[("src/A.php", "SluggerFactory"), ("src/B.php", "Slugger")],
            &[],
        );
        assert!(tiny(&file, &index, 60).is_some());
    }

    #[test]
    fn tiny_skips_a_class_with_its_own_test() {
        let file = php("src/Service/Slugger.php", HELPER);
        let index = index_with(&[], &["SluggerTest.php"]);
        assert!(tiny(&file, &index, 60).is_none());
    }

    #[test]
    fn tiny_puts_an_injecting_class_in_the_judgement_tier() {
        let file = php("src/Service/Slugger.php", INJECTING);
        let (_, finding) = tiny(&file, &TinyIndex::default(), 60).unwrap();
        assert_eq!(finding.detector, "tiny-judgement");
        assert!(finding.row.ends_with("deps:1"));
    }

    #[test]
    fn tiny_skips_a_value_object() {
        let file = php(
            "src/Model/Point.php",
            "final readonly class Point\n{\n    public function __construct(\n        public int $x,\n        public int $y,\n    ) {}\n}\n",
        );
        assert!(tiny(&file, &TinyIndex::default(), 60).is_none());
    }

    #[test]
    fn tiny_skips_a_tagged_chain_head_and_a_contract_implementation() {
        let chain = php(
            "src/Service/Chain.php",
            "final readonly class Chain\n{\n    public function __construct(\n        #[AutowireIterator('x')] private iterable $links,\n    ) {}\n}\n",
        );
        let implementation = php(
            "src/Service/Impl.php",
            "final class Impl implements Contract\n{\n}\n",
        );
        assert!(tiny(&chain, &TinyIndex::default(), 60).is_none());
        assert!(tiny(&implementation, &TinyIndex::default(), 60).is_none());
    }

    #[test]
    fn tiny_skips_a_class_over_the_size_limit() {
        let file = php("src/Service/Slugger.php", HELPER);
        assert!(tiny(&file, &TinyIndex::default(), 5).is_none());
    }
}
