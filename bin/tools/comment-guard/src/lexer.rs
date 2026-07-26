//! Hand-written PHP scanner that extracts comments and attributes each one to the innermost
//! named enclosing symbol.
//!
//! A regex sweep cannot do this: `//` and `#` sequences inside single-quoted strings,
//! double-quoted strings and heredoc/nowdoc bodies are not comments, and `#[` opens an
//! attribute rather than a comment.

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum CommentKind {
    Line,
    Block,
    Doc,
}

#[derive(Debug, Clone)]
pub struct Comment {
    pub line: usize,
    pub col: usize,
    pub kind: CommentKind,
    pub lines: Vec<String>,
    /// Per line: was it indented past the docblock's `*` marker? A PHPDoc tag whose text wraps is
    /// written with a hanging indent, so that flag is what separates a continuation from new prose.
    pub hanging: Vec<bool>,
    pub symbol: String,
    pub in_interface: bool,
    own_line: bool,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
enum ScopeKind {
    Class,
    Interface,
    Trait,
    Enum,
    Function,
    Anon,
}

impl ScopeKind {
    fn is_type(self) -> bool {
        matches!(
            self,
            ScopeKind::Class | ScopeKind::Interface | ScopeKind::Trait | ScopeKind::Enum
        )
    }
}

#[derive(Debug, Clone)]
struct Scope {
    kind: ScopeKind,
    name: String,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
enum Pending {
    None,
    Type(ScopeKind),
    Function,
    Member,
}

struct Cursor<'a> {
    chars: &'a [char],
    pos: usize,
    line: usize,
    col: usize,
}

impl<'a> Cursor<'a> {
    fn new(chars: &'a [char]) -> Self {
        Self { chars, pos: 0, line: 1, col: 1 }
    }

    fn at_end(&self) -> bool {
        self.pos >= self.chars.len()
    }

    fn peek(&self, offset: usize) -> Option<char> {
        self.chars.get(self.pos + offset).copied()
    }

    fn bump(&mut self) {
        if self.pos >= self.chars.len() {
            return;
        }
        if self.chars[self.pos] == '\n' {
            self.line += 1;
            self.col = 1;
        } else {
            self.col += 1;
        }
        self.pos += 1;
    }

    fn bump_n(&mut self, count: usize) {
        for _ in 0..count {
            self.bump();
        }
    }

    fn slice(&self, from: usize, to: usize) -> String {
        self.chars[from..to].iter().collect()
    }

    fn peek_non_whitespace(&self) -> Option<char> {
        self.chars[self.pos..]
            .iter()
            .find(|c| !c.is_whitespace())
            .copied()
    }

    fn only_blank_before_on_line(&self, start: usize) -> bool {
        let mut i = start;
        while i > 0 {
            let c = self.chars[i - 1];
            if c == '\n' {
                return true;
            }
            if !c.is_whitespace() {
                return false;
            }
            i -= 1;
        }
        true
    }
}

fn is_ident_start(c: char) -> bool {
    c.is_ascii_alphabetic() || c == '_' || (c as u32) >= 0x80
}

fn is_ident_char(c: char) -> bool {
    c.is_ascii_alphanumeric() || c == '_' || (c as u32) >= 0x80
}

pub fn scan(source: &str) -> Vec<Comment> {
    let chars: Vec<char> = source.chars().collect();
    let mut cur = Cursor::new(&chars);

    let mut comments: Vec<Comment> = Vec::new();
    let mut floating: Vec<usize> = Vec::new();
    let mut stack: Vec<Scope> = Vec::new();
    let mut pending_scope: Option<Scope> = None;
    let mut pending = Pending::None;
    let mut pending_member: Option<String> = None;
    let mut in_php = false;
    let mut paren_depth = 0i32;
    let mut after_member_access = false;
    let mut last_flush_line = 0usize;
    let mut last_flush_target = String::new();

    while !cur.at_end() {
        if !in_php {
            if cur.peek(0) == Some('<') && cur.peek(1) == Some('?') {
                in_php = true;
                cur.bump_n(2);
                if lowercase_at(&chars, cur.pos, "php") {
                    cur.bump_n(3);
                } else if cur.peek(0) == Some('=') {
                    cur.bump();
                }
            } else {
                cur.bump();
            }
            continue;
        }

        let c = cur.peek(0).unwrap();

        if c == '\'' || c == '"' || c == '`' {
            consume_quoted(&mut cur, c);
            after_member_access = false;
            continue;
        }

        if c == '<' && cur.peek(1) == Some('<') && cur.peek(2) == Some('<') {
            consume_heredoc(&mut cur);
            after_member_access = false;
            continue;
        }

        if c == '#' && cur.peek(1) == Some('[') {
            cur.bump_n(2);
            after_member_access = false;
            continue;
        }

        if (c == '/' && cur.peek(1) == Some('/')) || c == '#' {
            let comment = consume_line_comment(&mut cur, &stack);
            push(&mut comments, &mut floating, comment, last_flush_line, &last_flush_target);
            after_member_access = false;
            continue;
        }

        if c == '/' && cur.peek(1) == Some('*') {
            let comment = consume_block_comment(&mut cur, &stack);
            push(&mut comments, &mut floating, comment, last_flush_line, &last_flush_target);
            after_member_access = false;
            continue;
        }

        if c == '?' && cur.peek(1) == Some('>') {
            in_php = false;
            cur.bump_n(2);
            continue;
        }

        if c == ':' && cur.peek(1) == Some(':') {
            cur.bump_n(2);
            after_member_access = true;
            continue;
        }

        if c == '-' && cur.peek(1) == Some('>') {
            cur.bump_n(2);
            after_member_access = true;
            continue;
        }

        if c == '(' {
            if pending == Pending::Type(ScopeKind::Class) {
                pending_scope = Some(Scope { kind: ScopeKind::Class, name: String::new() });
            } else if pending == Pending::Function {
                pending_scope = Some(Scope { kind: ScopeKind::Function, name: String::new() });
            }
            pending = Pending::None;
            paren_depth += 1;
            cur.bump();
            after_member_access = false;
            continue;
        }

        if c == ')' {
            paren_depth -= 1;
            cur.bump();
            after_member_access = false;
            continue;
        }

        if c == '{' {
            let scope = pending_scope.take().unwrap_or(Scope {
                kind: ScopeKind::Anon,
                name: String::new(),
            });
            let target = match (&pending_member, scope.kind.is_type() && !scope.name.is_empty()) {
                (Some(member), _) => member.clone(),
                (None, true) => scope.name.clone(),
                (None, false) => innermost_named(&stack),
            };
            flush(&mut comments, &mut floating, &target, scope.kind == ScopeKind::Interface);
            last_flush_line = cur.line;
            last_flush_target = target;
            stack.push(scope);
            pending_member = None;
            pending = Pending::None;
            cur.bump();
            after_member_access = false;
            continue;
        }

        if c == '}' {
            let target = pending_member
                .clone()
                .unwrap_or_else(|| innermost_named(&stack));
            flush(&mut comments, &mut floating, &target, false);
            last_flush_line = cur.line;
            last_flush_target = target;
            stack.pop();
            pending_member = None;
            pending = Pending::None;
            pending_scope = None;
            cur.bump();
            after_member_access = false;
            continue;
        }

        if c == ';' {
            let target = pending_member
                .clone()
                .unwrap_or_else(|| innermost_named(&stack));
            flush(&mut comments, &mut floating, &target, false);
            last_flush_line = cur.line;
            last_flush_target = target;
            pending_member = None;
            pending = Pending::None;
            pending_scope = None;
            cur.bump();
            after_member_access = false;
            continue;
        }

        if c == '$' && is_ident_start(cur.peek(1).unwrap_or(' ')) {
            cur.bump();
            let start = cur.pos;
            while !cur.at_end() && is_ident_char(cur.peek(0).unwrap()) {
                cur.bump();
            }
            if paren_depth == 0 && pending_member.is_none() && at_member_level(&stack) {
                pending_member = Some(cur.slice(start, cur.pos));
            }
            after_member_access = false;
            continue;
        }

        if is_ident_start(c) {
            let start = cur.pos;
            while !cur.at_end() && is_ident_char(cur.peek(0).unwrap()) {
                cur.bump();
            }
            let word = cur.slice(start, cur.pos);
            let lower = word.to_ascii_lowercase();
            let was_member_access = after_member_access;
            after_member_access = false;

            match pending {
                Pending::Type(kind) => {
                    let anonymous = lower == "extends" || lower == "implements";
                    pending_scope = Some(Scope {
                        kind,
                        name: if anonymous { String::new() } else { word.clone() },
                    });
                    pending = Pending::None;
                    if !anonymous {
                        continue;
                    }
                }
                Pending::Function => {
                    pending_scope = Some(Scope { kind: ScopeKind::Function, name: word.clone() });
                    if at_member_level(&stack) {
                        pending_member = Some(word.clone());
                    }
                    pending = Pending::None;
                    continue;
                }
                Pending::Member => {
                    // `public const string FOO = ...` - the first word is the type, not the name.
                    if cur.peek_non_whitespace().is_some_and(is_ident_start) {
                        continue;
                    }
                    if pending_member.is_none() {
                        pending_member = Some(word.clone());
                    }
                    pending = Pending::None;
                    continue;
                }
                Pending::None => {}
            }

            if was_member_access || paren_depth != 0 {
                continue;
            }

            pending = match lower.as_str() {
                "class" => Pending::Type(ScopeKind::Class),
                "interface" => Pending::Type(ScopeKind::Interface),
                "trait" => Pending::Type(ScopeKind::Trait),
                "enum" => Pending::Type(ScopeKind::Enum),
                "function" => Pending::Function,
                "const" if at_member_level(&stack) => Pending::Member,
                "case" if innermost_is_enum(&stack) => Pending::Member,
                _ => Pending::None,
            };
            continue;
        }

        cur.bump();
        after_member_access = false;
    }

    let target = innermost_named(&stack);
    flush(&mut comments, &mut floating, &target, false);

    merge_adjacent_line_comments(comments)
}

fn lowercase_at(chars: &[char], pos: usize, needle: &str) -> bool {
    needle
        .chars()
        .enumerate()
        .all(|(i, want)| chars.get(pos + i).map(|c| c.to_ascii_lowercase()) == Some(want))
}

fn at_member_level(stack: &[Scope]) -> bool {
    match stack.last() {
        None => true,
        Some(scope) => scope.kind.is_type(),
    }
}

fn innermost_is_enum(stack: &[Scope]) -> bool {
    matches!(stack.last(), Some(scope) if scope.kind == ScopeKind::Enum)
}

fn innermost_named(stack: &[Scope]) -> String {
    stack
        .iter()
        .rev()
        .find(|scope| !scope.name.is_empty())
        .map(|scope| scope.name.clone())
        .unwrap_or_default()
}

/// A comment trailing the statement it annotates (`case Active = 'active'; // paid and recurring`)
/// belongs to the symbol that statement just closed, not to the next one down the file.
fn push(
    comments: &mut Vec<Comment>,
    floating: &mut Vec<usize>,
    mut comment: Comment,
    last_flush_line: usize,
    last_flush_target: &str,
) {
    if !comment.own_line && comment.line == last_flush_line {
        comment.symbol = last_flush_target.to_string();
        comments.push(comment);
        return;
    }
    floating.push(comments.len());
    comments.push(comment);
}

fn flush(comments: &mut [Comment], floating: &mut Vec<usize>, target: &str, interface: bool) {
    for index in floating.drain(..) {
        comments[index].symbol = target.to_string();
        if interface {
            comments[index].in_interface = true;
        }
    }
}

fn consume_quoted(cur: &mut Cursor, quote: char) {
    cur.bump();
    while !cur.at_end() {
        let c = cur.peek(0).unwrap();
        if c == '\\' {
            cur.bump_n(2);
            continue;
        }
        cur.bump();
        if c == quote {
            return;
        }
    }
}

fn consume_heredoc(cur: &mut Cursor) {
    cur.bump_n(3);
    while matches!(cur.peek(0), Some(' ') | Some('\t')) {
        cur.bump();
    }
    let quote = match cur.peek(0) {
        Some(q @ ('"' | '\'')) => {
            cur.bump();
            Some(q)
        }
        _ => None,
    };
    let start = cur.pos;
    while !cur.at_end() && is_ident_char(cur.peek(0).unwrap()) {
        cur.bump();
    }
    let label: Vec<char> = cur.chars[start..cur.pos].to_vec();
    if let Some(q) = quote {
        if cur.peek(0) == Some(q) {
            cur.bump();
        }
    }
    if label.is_empty() {
        return;
    }

    skip_to_next_line(cur);
    while !cur.at_end() {
        let mut probe = cur.pos;
        while matches!(cur.chars.get(probe), Some(' ') | Some('\t')) {
            probe += 1;
        }
        if label_ends_here(cur.chars, probe, &label) {
            while cur.pos < probe {
                cur.bump();
            }
            cur.bump_n(label.len());
            return;
        }
        skip_to_next_line(cur);
    }
}

fn skip_to_next_line(cur: &mut Cursor) {
    while !cur.at_end() && cur.peek(0) != Some('\n') {
        cur.bump();
    }
    if !cur.at_end() {
        cur.bump();
    }
}

fn label_ends_here(chars: &[char], pos: usize, label: &[char]) -> bool {
    for (i, expected) in label.iter().enumerate() {
        if chars.get(pos + i) != Some(expected) {
            return false;
        }
    }
    !chars
        .get(pos + label.len())
        .copied()
        .map(is_ident_char)
        .unwrap_or(false)
}

fn consume_line_comment(cur: &mut Cursor, stack: &[Scope]) -> Comment {
    let line = cur.line;
    let col = cur.col;
    let own_line = cur.only_blank_before_on_line(cur.pos);
    cur.bump_n(if cur.peek(0) == Some('#') { 1 } else { 2 });

    let start = cur.pos;
    while !cur.at_end() && cur.peek(0) != Some('\n') {
        if cur.peek(0) == Some('?') && cur.peek(1) == Some('>') {
            break;
        }
        cur.bump();
    }
    let text = cur.slice(start, cur.pos).trim().to_string();
    let empty = text.is_empty();

    Comment {
        line,
        col,
        kind: CommentKind::Line,
        lines: if empty { Vec::new() } else { vec![text] },
        hanging: if empty { Vec::new() } else { vec![false] },
        symbol: innermost_named(stack),
        in_interface: stack.iter().any(|s| s.kind == ScopeKind::Interface),
        own_line,
    }
}

fn consume_block_comment(cur: &mut Cursor, stack: &[Scope]) -> Comment {
    let line = cur.line;
    let col = cur.col;
    let own_line = cur.only_blank_before_on_line(cur.pos);
    let doc = cur.peek(2) == Some('*') && cur.peek(3) != Some('/');
    cur.bump_n(2);

    let start = cur.pos;
    while !cur.at_end() {
        if cur.peek(0) == Some('*') && cur.peek(1) == Some('/') {
            break;
        }
        cur.bump();
    }
    let raw = cur.slice(start, cur.pos);
    cur.bump_n(2);

    let (lines, hanging) = strip_block_body(&raw);

    Comment {
        line,
        col,
        kind: if doc { CommentKind::Doc } else { CommentKind::Block },
        lines,
        hanging,
        symbol: innermost_named(stack),
        in_interface: stack.iter().any(|s| s.kind == ScopeKind::Interface),
        own_line,
    }
}

fn strip_block_body(raw: &str) -> (Vec<String>, Vec<bool>) {
    let mut lines = Vec::new();
    let mut hanging = Vec::new();

    for raw_line in raw.lines() {
        let body = match raw_line.trim_start().strip_prefix('*') {
            Some(rest) => rest.strip_prefix(' ').unwrap_or(rest),
            None => raw_line,
        };
        let text = body.trim();
        if text.is_empty() {
            continue;
        }
        hanging.push(body.starts_with([' ', '\t']));
        lines.push(text.to_string());
    }

    (lines, hanging)
}

/// A run of `//` lines is one thought, so report and count it as one comment.
fn merge_adjacent_line_comments(comments: Vec<Comment>) -> Vec<Comment> {
    let mut merged: Vec<Comment> = Vec::with_capacity(comments.len());
    for comment in comments {
        let joinable = merged.last().is_some_and(|previous| {
            previous.kind == CommentKind::Line
                && comment.kind == CommentKind::Line
                && previous.own_line
                && comment.own_line
                && previous.symbol == comment.symbol
                && previous.line + previous.lines.len().max(1) == comment.line
        });
        if joinable {
            let last = merged.last_mut().unwrap();
            last.lines.extend(comment.lines);
            last.hanging.extend(comment.hanging);
        } else {
            merged.push(comment);
        }
    }
    merged
}

#[cfg(test)]
mod tests {
    use super::*;

    fn symbols(source: &str) -> Vec<(String, String)> {
        scan(source)
            .into_iter()
            .map(|c| (c.symbol, c.lines.join(" ")))
            .collect()
    }

    #[test]
    fn ignores_slashes_inside_a_single_quoted_string() {
        let found = scan("<?php\n$url = 'https://example.org/path';\n");
        assert!(found.is_empty());
    }

    #[test]
    fn ignores_hash_inside_a_double_quoted_string() {
        let found = scan("<?php\n$anchor = \"section #4 of the page\";\n");
        assert!(found.is_empty());
    }

    #[test]
    fn ignores_hash_lines_inside_a_heredoc() {
        let source = "<?php\n$help = <<<HELP\n# not a comment\n// also not a comment\nHELP;\n$x = 1;\n";
        assert!(scan(source).is_empty());
    }

    #[test]
    fn ignores_hash_lines_inside_a_nowdoc() {
        let source = "<?php\n$help = <<<'HELP'\n# not a comment\nHELP;\n";
        assert!(scan(source).is_empty());
    }

    #[test]
    fn heredoc_label_inside_the_body_does_not_close_it() {
        let source = "<?php\n$x = <<<SQL\n    SQLITE keyword\n    SQL;\n# real comment\n";
        let found = scan(source);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].lines, vec!["real comment"]);
    }

    #[test]
    fn attribute_syntax_is_not_a_comment() {
        let source = "<?php\nclass A {\n    #[Route('/x')]\n    public function go(): void {}\n}\n";
        assert!(scan(source).is_empty());
    }

    #[test]
    fn hash_comment_is_still_a_comment() {
        let source = "<?php\nclass A {\n    public function go(): void { # why\n    }\n}\n";
        let found = scan(source);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].symbol, "go");
    }

    #[test]
    fn docblock_attaches_to_the_method_below_it() {
        let source = "<?php\nclass A {\n    /**\n     * Prose.\n     */\n    public function go(): void {}\n}\n";
        assert_eq!(symbols(source), vec![("go".to_string(), "Prose.".to_string())]);
    }

    #[test]
    fn docblock_attaches_to_the_constant_below_it() {
        let source = "<?php\nclass A {\n    // bounds the walk\n    private const MAX_STEPS = 5;\n}\n";
        assert_eq!(
            symbols(source),
            vec![("MAX_STEPS".to_string(), "bounds the walk".to_string())]
        );
    }

    #[test]
    fn docblock_attaches_to_the_property_below_it() {
        let source = "<?php\nclass A {\n    /** @var array<int, string> */\n    private array $names = [];\n}\n";
        assert_eq!(symbols(source)[0].0, "names");
    }

    #[test]
    fn docblock_attaches_to_the_class_below_it() {
        let source = "<?php\n/**\n * Prose.\n */\nfinal class Widget {}\n";
        assert_eq!(symbols(source), vec![("Widget".to_string(), "Prose.".to_string())]);
    }

    #[test]
    fn file_header_comment_has_no_symbol() {
        let source = "<?php\n\n// generated file\n\ndeclare(strict_types=1);\n";
        assert_eq!(symbols(source), vec![(String::new(), "generated file".to_string())]);
    }

    #[test]
    fn comment_in_a_nested_closure_belongs_to_the_enclosing_method() {
        let source = "<?php\nclass A {\n    public function go(array $rows): void {\n        usort($rows, function ($a, $b) {\n            // stable order\n            return 0;\n        });\n    }\n}\n";
        assert_eq!(symbols(source), vec![("go".to_string(), "stable order".to_string())]);
    }

    #[test]
    fn comment_in_a_match_arm_belongs_to_the_enclosing_method() {
        let source = "<?php\nclass A {\n    public function go(int $x): int {\n        return match (true) {\n            // fallthrough\n            default => 0,\n        };\n    }\n}\n";
        assert_eq!(symbols(source), vec![("go".to_string(), "fallthrough".to_string())]);
    }

    #[test]
    fn interface_docblocks_are_marked() {
        let source = "<?php\ninterface Filter {\n    /**\n     * Narrows the result.\n     */\n    public function apply(): void;\n}\n";
        let found = scan(source);
        assert_eq!(found.len(), 1);
        assert!(found[0].in_interface);
        assert_eq!(found[0].symbol, "apply");
    }

    #[test]
    fn docblock_on_the_interface_itself_is_marked() {
        let source = "<?php\n/**\n * Contract.\n */\ninterface Filter {}\n";
        let found = scan(source);
        assert_eq!(found.len(), 1);
        assert!(found[0].in_interface);
        assert_eq!(found[0].symbol, "Filter");
    }

    #[test]
    fn class_docblock_is_not_marked_as_interface() {
        let source = "<?php\n/**\n * Prose.\n */\nfinal class Widget implements Filter {}\n";
        let found = scan(source);
        assert!(!found[0].in_interface);
    }

    #[test]
    fn anonymous_class_does_not_steal_the_symbol() {
        let source = "<?php\nclass A {\n    public function go(): object {\n        return new class extends B {\n            // why\n            public int $x = 1;\n        };\n    }\n}\n";
        assert_eq!(symbols(source), vec![("x".to_string(), "why".to_string())]);
    }

    #[test]
    fn type_hints_named_like_keywords_do_not_rename_the_method() {
        let source = "<?php\nclass A {\n    public function go(Enum $a, Widget $b): void {\n        // body note\n    }\n}\n";
        assert_eq!(symbols(source), vec![("go".to_string(), "body note".to_string())]);
    }

    #[test]
    fn class_constant_fetch_is_not_a_declaration() {
        let source = "<?php\nclass A {\n    public function go(): string {\n        $n = Widget::class;\n        // note\n        return $n;\n    }\n}\n";
        assert_eq!(symbols(source), vec![("go".to_string(), "note".to_string())]);
    }

    #[test]
    fn adjacent_line_comments_merge_into_one() {
        let source = "<?php\nclass A {\n    public function go(): void {\n        // 1. first\n        // 2. second\n        $x = 1;\n    }\n}\n";
        let found = scan(source);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].lines, vec!["1. first", "2. second"]);
    }

    #[test]
    fn trailing_comment_does_not_merge_with_the_next_line() {
        let source = "<?php\nclass A {\n    public function go(): void {\n        $x = 1; // trailing\n        // standalone\n    }\n}\n";
        assert_eq!(scan(source).len(), 2);
    }

    #[test]
    fn enum_cases_carry_their_own_symbol() {
        let source = "<?php\nenum Status: string {\n    // legacy spelling kept for stored rows\n    case Active = 'active';\n}\n";
        assert_eq!(symbols(source)[0].0, "Active");
    }

    #[test]
    fn a_trailing_comment_belongs_to_the_case_it_sits_on() {
        let source = "<?php\nenum Status: string {\n    case Pending = 'pending'; // checkout started\n    case Active = 'active'; // paid and recurring\n}\n";
        assert_eq!(
            symbols(source),
            vec![
                ("Pending".to_string(), "checkout started".to_string()),
                ("Active".to_string(), "paid and recurring".to_string()),
            ]
        );
    }

    #[test]
    fn a_trailing_comment_on_a_constant_belongs_to_that_constant() {
        let source = "<?php\nclass A {\n    private const MAX = 5; // bounds the walk\n    private const MIN = 1;\n}\n";
        assert_eq!(symbols(source)[0].0, "MAX");
    }

    #[test]
    fn switch_case_is_not_treated_as_an_enum_case() {
        let source = "<?php\nclass A {\n    public function go(int $x): void {\n        switch ($x) {\n            case 1:\n                // note\n                break;\n        }\n    }\n}\n";
        assert_eq!(symbols(source), vec![("go".to_string(), "note".to_string())]);
    }

    #[test]
    fn inline_html_outside_php_tags_is_not_scanned() {
        let source = "<div>// not php</div>\n<?php\n// real\n";
        let found = scan(source);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].lines, vec!["real"]);
    }

    #[test]
    fn escaped_quote_does_not_end_the_string() {
        let source = "<?php\n$x = 'it\\'s // not a comment';\n$y = 2;\n";
        assert!(scan(source).is_empty());
    }

    #[test]
    fn line_and_column_are_reported() {
        let source = "<?php\nclass A {\n    public function go(): void {\n        $x = 1; // why\n    }\n}\n";
        let found = scan(source);
        assert_eq!(found[0].line, 4);
        assert_eq!(found[0].col, 17);
    }
}
