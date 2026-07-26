use crate::config::Config;
use crate::lexer::Comment;

#[derive(Debug, Clone, PartialEq, Eq)]
pub enum Verdict {
    Directive,
    TagDoc,
    Aaa,
    Interface,
    Marker,
    NeedsJustification(String),
}

pub fn classify(comment: &Comment, config: &Config) -> Verdict {
    if comment.lines.is_empty() {
        return Verdict::Directive;
    }

    let directives = comment
        .lines
        .iter()
        .filter(|line| is_directive(line, config))
        .count();
    if directives == comment.lines.len() {
        return Verdict::Directive;
    }

    let structured = structured_lines(&comment.lines, &comment.hanging, config);
    if structured == comment.lines.len() {
        return Verdict::TagDoc;
    }

    if comment.lines.iter().all(|line| is_aaa(line, config)) {
        return Verdict::Aaa;
    }

    if comment.in_interface {
        let prose = comment.lines.len() - structured;
        if prose > config.interface_max_prose_lines {
            return Verdict::NeedsJustification(format!(
                "interface docblock runs {} prose lines (max {}); case 1 asks for a short summary",
                prose, config.interface_max_prose_lines
            ));
        }
        return Verdict::Interface;
    }

    if comment.lines.first().is_some_and(|line| is_marker(line, config)) {
        return Verdict::Marker;
    }

    Verdict::NeedsJustification("prose comment".to_string())
}

/// Count the lines that carry structure rather than prose. A tag continues onto the next lines two
/// ways: an unclosed type shape (`@return array{` ... `}`), or a hanging indent under the tag. Both
/// count as structure; a line back at the docblock's own margin starts prose again.
fn structured_lines(lines: &[String], hanging: &[bool], config: &Config) -> usize {
    let mut count = 0usize;
    let mut open = 0i32;
    let mut in_tag = false;

    for (index, line) in lines.iter().enumerate() {
        if is_directive(line, config) || is_known_tag(line, config) {
            count += 1;
            open = bracket_balance(line);
            in_tag = true;
            continue;
        }
        let continues = open > 0 || (in_tag && hanging.get(index).copied().unwrap_or(false));
        if continues {
            count += 1;
            open = (open + bracket_balance(line)).max(0);
            continue;
        }
        open = 0;
        in_tag = false;
    }

    count
}

fn bracket_balance(line: &str) -> i32 {
    line.chars().fold(0, |balance, c| match c {
        '{' | '(' | '[' => balance + 1,
        '}' | ')' | ']' => balance - 1,
        _ => balance,
    })
}

/// A section that both acts and asserts is still bare: `// Act & Assert`, `// Arrange / Act`.
fn is_aaa(line: &str, config: &Config) -> bool {
    let parts: Vec<&str> = line
        .split(['&', '+', '/', ','])
        .flat_map(|part| part.split_terminator(" and "))
        .map(str::trim)
        .filter(|part| !part.is_empty())
        .collect();
    !parts.is_empty()
        && parts
            .iter()
            .all(|part| config.aaa_markers.contains(&part.to_lowercase()))
}

fn is_directive(line: &str, config: &Config) -> bool {
    config
        .directives
        .iter()
        .any(|directive| starts_with_ignore_case(line, directive))
}

fn is_known_tag(line: &str, config: &Config) -> bool {
    let Some(rest) = line.strip_prefix('@') else {
        return false;
    };
    let name: String = rest
        .chars()
        .take_while(|c| c.is_ascii_alphanumeric() || *c == '-' || *c == '_')
        .collect();
    !name.is_empty() && config.tags.contains(&name.to_lowercase())
}

fn is_marker(line: &str, config: &Config) -> bool {
    let candidate = line.strip_prefix('@').unwrap_or(line).to_uppercase();
    config
        .markers
        .iter()
        .filter_map(|marker| candidate.strip_prefix(marker.as_str()))
        .any(|rest| {
            rest.chars()
                .next()
                .is_none_or(|c| !c.is_alphanumeric() && c != '_')
        })
}

fn starts_with_ignore_case(haystack: &str, needle: &str) -> bool {
    haystack
        .get(..needle.len())
        .is_some_and(|head| head.eq_ignore_ascii_case(needle))
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::lexer::scan;

    fn config() -> Config {
        Config {
            scan_paths: vec![".".into()],
            exclude_paths: Vec::new(),
            exclude_dirs: Default::default(),
            interface_max_prose_lines: 4,
            markers: vec!["TODO".to_string(), "FIXME".to_string()],
            directives: vec![
                "@phpstan".to_string(),
                "@psalm-suppress".to_string(),
                "@mago-".to_string(),
                "phpcs:".to_string(),
                "@codeCoverageIgnore".to_string(),
            ],
            tags: ["param", "return", "var", "throws", "deprecated"]
                .iter()
                .map(|s| s.to_string())
                .collect(),
            aaa_markers: ["arrange", "act", "assert"]
                .iter()
                .map(|s| s.to_string())
                .collect(),
            policy_doc: "docs/src/core-development/tooling.md".to_string(),
        }
    }

    fn verdict(source: &str) -> Verdict {
        let comments = scan(source);
        assert_eq!(comments.len(), 1, "expected exactly one comment");
        classify(&comments[0], &config())
    }

    #[test]
    fn tool_directives_are_allowed() {
        assert_eq!(
            verdict("<?php\nclass A {\n    public function go(): void {\n        // @phpstan-ignore-next-line\n        $x = 1;\n    }\n}\n"),
            Verdict::Directive
        );
    }

    #[test]
    fn mago_directives_are_allowed() {
        assert_eq!(
            verdict("<?php\nclass A {\n    /** @mago-expect best-practices/no-else-clause */\n    public function go(): void {}\n}\n"),
            Verdict::Directive
        );
    }

    #[test]
    fn tag_only_docblocks_are_allowed() {
        assert_eq!(
            verdict("<?php\nclass A {\n    /**\n     * @param array{id: int} $row\n     * @return list<string>\n     */\n    public function go(array $row): array { return []; }\n}\n"),
            Verdict::TagDoc
        );
    }

    #[test]
    fn a_wrapped_array_shape_is_still_a_tag_docblock() {
        let source = "<?php\nclass A {\n    /**\n     * @return array{\n     *     mode: string,\n     *     days: list<int>,\n     * }\n     */\n    public function go(): array { return []; }\n}\n";
        assert_eq!(verdict(source), Verdict::TagDoc);
    }

    #[test]
    fn a_hanging_indent_continues_the_tag_above_it() {
        let source = "<?php\nclass A {\n    /**\n     * @deprecated use goFaster() instead;\n     *             the old path no longer honours the scope filter.\n     */\n    public function go(): void {}\n}\n";
        assert_eq!(verdict(source), Verdict::TagDoc);
    }

    #[test]
    fn prose_after_a_closed_tag_is_still_a_violation() {
        let source = "<?php\nclass A {\n    /**\n     * @return list<string>\n     * Callers should sort this themselves.\n     */\n    public function go(): array { return []; }\n}\n";
        assert!(matches!(verdict(source), Verdict::NeedsJustification(_)));
    }

    #[test]
    fn a_tag_docblock_with_a_prose_summary_is_a_violation() {
        assert!(matches!(
            verdict("<?php\nclass A {\n    /**\n     * Builds the thing.\n     *\n     * @return list<string>\n     */\n    public function go(): array { return []; }\n}\n"),
            Verdict::NeedsJustification(_)
        ));
    }

    #[test]
    fn bare_aaa_markers_are_allowed() {
        assert_eq!(
            verdict("<?php\nclass ATest {\n    public function testGo(): void {\n        // Arrange\n        $x = 1;\n    }\n}\n"),
            Verdict::Aaa
        );
    }

    #[test]
    fn combined_aaa_markers_are_allowed() {
        for marker in ["Act & Assert", "Arrange / Act", "Arrange, Act and Assert", "Act + Assert"] {
            let source = format!(
                "<?php\nclass ATest {{\n    public function testGo(): void {{\n        // {}\n        $x = 1;\n    }}\n}}\n",
                marker
            );
            assert_eq!(verdict(&source), Verdict::Aaa, "{}", marker);
        }
    }

    #[test]
    fn an_aaa_marker_with_an_explanation_is_a_violation() {
        assert!(matches!(
            verdict("<?php\nclass ATest {\n    public function testGo(): void {\n        // Arrange - two hosts on the same domain\n        $x = 1;\n    }\n}\n"),
            Verdict::NeedsJustification(_)
        ));
    }

    #[test]
    fn interface_docblocks_are_allowed() {
        assert_eq!(
            verdict("<?php\ninterface Filter {\n    /**\n     * Narrows the visible set.\n     */\n    public function apply(): void;\n}\n"),
            Verdict::Interface
        );
    }

    #[test]
    fn an_over_long_interface_docblock_is_a_violation() {
        let source = "<?php\ninterface Filter {\n    /**\n     * One.\n     * Two.\n     * Three.\n     * Four.\n     * Five.\n     */\n    public function apply(): void;\n}\n";
        assert!(matches!(verdict(source), Verdict::NeedsJustification(_)));
    }

    #[test]
    fn markers_are_allowed_but_tracked() {
        assert_eq!(
            verdict("<?php\nclass A {\n    public function go(): void {\n        // TODO: drop once the old rows are migrated\n        $x = 1;\n    }\n}\n"),
            Verdict::Marker
        );
    }

    #[test]
    fn a_word_starting_with_a_marker_is_not_a_marker() {
        assert!(matches!(
            verdict("<?php\nclass A {\n    public function go(): void {\n        // TODOS are tracked elsewhere\n        $x = 1;\n    }\n}\n"),
            Verdict::NeedsJustification(_)
        ));
    }

    #[test]
    fn prose_in_a_method_body_is_a_violation() {
        assert!(matches!(
            verdict("<?php\nclass A {\n    public function go(): void {\n        // we do this because the API is weird\n        $x = 1;\n    }\n}\n"),
            Verdict::NeedsJustification(_)
        ));
    }

    #[test]
    fn a_prose_docblock_on_a_method_is_a_violation() {
        assert!(matches!(
            verdict("<?php\nclass A {\n    /**\n     * Builds a recurrence pattern from the given interval.\n     */\n    public static function fromInterval(int $i): self { return new self(); }\n}\n"),
            Verdict::NeedsJustification(_)
        ));
    }
}
