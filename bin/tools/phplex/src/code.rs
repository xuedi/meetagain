#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum BlockKind {
    Class,
    Interface,
    Trait,
    Enum,
    Function,
    Catch,
    Other,
}

impl BlockKind {
    pub fn is_type(self) -> bool {
        matches!(
            self,
            BlockKind::Class | BlockKind::Interface | BlockKind::Trait | BlockKind::Enum
        )
    }
}

/// One `{ ... }` block. `start_line` is the line of the keyword that opened it (`function`,
/// `class`, `catch`), so a multi-line signature counts towards the block, and `name` is empty for
/// closures, anonymous classes and every block that is not a declaration.
#[derive(Debug, Clone)]
pub struct Block {
    pub kind: BlockKind,
    pub name: String,
    pub start_line: usize,
    pub end_line: usize,
    pub parent: Option<usize>,
}

fn blank(c: char) -> char {
    if c == '\n' {
        '\n'
    } else {
        ' '
    }
}

fn is_ident_start(c: char) -> bool {
    c.is_ascii_alphabetic() || c == '_' || (c as u32) >= 0x80
}

fn is_ident_char(c: char) -> bool {
    c.is_ascii_alphanumeric() || c == '_' || (c as u32) >= 0x80
}

pub fn code_only(source: &str) -> String {
    let chars: Vec<char> = source.chars().collect();
    let mut out: Vec<char> = Vec::with_capacity(chars.len());
    let mut i = 0usize;
    let mut in_php = false;
    let at = |i: usize| chars.get(i).copied();

    while i < chars.len() {
        let c = chars[i];

        if !in_php {
            if c == '<' && at(i + 1) == Some('?') {
                in_php = true;
                out.push('<');
                out.push('?');
                i += 2;
                let php: String = chars[i..chars.len().min(i + 3)].iter().collect();
                if php.eq_ignore_ascii_case("php") {
                    out.extend(&chars[i..i + 3]);
                    i += 3;
                } else if at(i) == Some('=') {
                    out.push('=');
                    i += 1;
                }
            } else {
                out.push(blank(c));
                i += 1;
            }
            continue;
        }

        if c == '\'' || c == '"' || c == '`' {
            out.push(c);
            i += 1;
            while i < chars.len() {
                let d = chars[i];
                if d == '\\' {
                    out.push(blank(d));
                    if let Some(escaped) = at(i + 1) {
                        out.push(blank(escaped));
                    }
                    i += 2;
                    continue;
                }
                i += 1;
                if d == c {
                    out.push(d);
                    break;
                }
                out.push(blank(d));
            }
            continue;
        }

        if c == '<' && at(i + 1) == Some('<') && at(i + 2) == Some('<') {
            i = blank_heredoc(&chars, i, &mut out);
            continue;
        }

        if c == '#' && at(i + 1) == Some('[') {
            out.push('#');
            out.push('[');
            i += 2;
            continue;
        }

        if (c == '/' && at(i + 1) == Some('/')) || c == '#' {
            while i < chars.len() && chars[i] != '\n' {
                if chars[i] == '?' && at(i + 1) == Some('>') {
                    break;
                }
                out.push(' ');
                i += 1;
            }
            continue;
        }

        if c == '/' && at(i + 1) == Some('*') {
            out.push(' ');
            out.push(' ');
            i += 2;
            while i < chars.len() {
                if chars[i] == '*' && at(i + 1) == Some('/') {
                    out.push(' ');
                    out.push(' ');
                    i += 2;
                    break;
                }
                out.push(blank(chars[i]));
                i += 1;
            }
            continue;
        }

        if c == '?' && at(i + 1) == Some('>') {
            in_php = false;
            out.push('?');
            out.push('>');
            i += 2;
            continue;
        }

        out.push(c);
        i += 1;
    }

    out.into_iter().collect()
}

/// Keeps the `<<<LABEL` opener and whatever follows the closing label, blanks the body.
fn blank_heredoc(chars: &[char], mut i: usize, out: &mut Vec<char>) -> usize {
    let start = i;
    i += 3;
    while matches!(chars.get(i), Some(' ') | Some('\t')) {
        i += 1;
    }
    let quote = match chars.get(i) {
        Some(q @ ('"' | '\'')) => {
            i += 1;
            Some(*q)
        }
        _ => None,
    };
    let label_start = i;
    while i < chars.len() && is_ident_char(chars[i]) {
        i += 1;
    }
    let label: Vec<char> = chars[label_start..i].to_vec();
    if let Some(q) = quote {
        if chars.get(i) == Some(&q) {
            i += 1;
        }
    }
    out.extend(&chars[start..i]);
    if label.is_empty() {
        return i;
    }

    while i < chars.len() && chars[i] != '\n' {
        out.push(chars[i]);
        i += 1;
    }

    while i < chars.len() {
        out.push(blank(chars[i]));
        i += 1;
        let mut probe = i;
        while matches!(chars.get(probe), Some(' ') | Some('\t')) {
            probe += 1;
        }
        let closes = label
            .iter()
            .enumerate()
            .all(|(k, l)| chars.get(probe + k) == Some(l))
            && !chars
                .get(probe + label.len())
                .copied()
                .is_some_and(is_ident_char);
        if closes {
            while i < probe + label.len() {
                out.push(' ');
                i += 1;
            }
            return i;
        }
        while i < chars.len() && chars[i] != '\n' {
            out.push(blank(chars[i]));
            i += 1;
        }
    }
    i
}

#[derive(PartialEq, Eq)]
enum Previous {
    Access,
    New,
    Other,
}

struct Pending {
    kind: BlockKind,
    name: Option<String>,
    line: usize,
    depth: i32,
}

/// Finds the blocks of text already passed through [`code_only`].
pub fn outline(code: &str) -> Vec<Block> {
    let chars: Vec<char> = code.chars().collect();
    let mut blocks: Vec<Block> = Vec::new();
    let mut stack: Vec<usize> = Vec::new();
    let mut pending: Option<Pending> = None;
    let mut previous = Previous::Other;
    let mut paren = 0i32;
    let mut line = 1usize;
    let mut i = 0usize;

    while i < chars.len() {
        let c = chars[i];

        if c == '\n' {
            line += 1;
            i += 1;
            continue;
        }
        if c.is_whitespace() {
            i += 1;
            continue;
        }

        if c == '$' && chars.get(i + 1).copied().is_some_and(is_ident_start) {
            i += 1;
            while i < chars.len() && is_ident_char(chars[i]) {
                i += 1;
            }
            previous = Previous::Other;
            continue;
        }

        if is_ident_start(c) {
            let start = i;
            while i < chars.len() && is_ident_char(chars[i]) {
                i += 1;
            }
            let word: String = chars[start..i].iter().collect();
            let lower = word.to_ascii_lowercase();

            if let Some(p) = pending.as_mut() {
                if p.name.is_none() {
                    let anonymous = lower == "extends" || lower == "implements";
                    p.name = Some(if anonymous { String::new() } else { word });
                }
                previous = Previous::Other;
                continue;
            }
            if previous == Previous::Access {
                previous = Previous::Other;
                continue;
            }

            let kind = match lower.as_str() {
                "class" if paren == 0 || previous == Previous::New => Some(BlockKind::Class),
                "interface" if paren == 0 => Some(BlockKind::Interface),
                "trait" if paren == 0 => Some(BlockKind::Trait),
                "enum" if paren == 0 => Some(BlockKind::Enum),
                "function" => Some(BlockKind::Function),
                "catch" => Some(BlockKind::Catch),
                _ => None,
            };
            if let Some(kind) = kind {
                pending = Some(Pending {
                    kind,
                    name: if kind == BlockKind::Catch {
                        Some(String::new())
                    } else {
                        None
                    },
                    line,
                    depth: paren,
                });
            }
            previous = if lower == "new" {
                Previous::New
            } else {
                Previous::Other
            };
            continue;
        }

        let next = chars.get(i + 1).copied();
        if (c == '-' && next == Some('>')) || (c == ':' && next == Some(':')) {
            previous = Previous::Access;
            i += 2;
            continue;
        }
        previous = Previous::Other;
        i += 1;

        match c {
            '(' => {
                if let Some(p) = pending.as_mut() {
                    if p.name.is_none() {
                        p.name = Some(String::new());
                    }
                }
                paren += 1;
            }
            ')' => {
                paren -= 1;
                if pending.as_ref().is_some_and(|p| p.depth > paren) {
                    pending = None;
                }
            }
            ';' => {
                if pending.as_ref().is_some_and(|p| p.depth == paren) {
                    pending = None;
                }
            }
            '{' => {
                let opened = match pending.take() {
                    Some(p) if p.depth == paren => (p.kind, p.name.unwrap_or_default(), p.line),
                    other => {
                        pending = other;
                        (BlockKind::Other, String::new(), line)
                    }
                };
                blocks.push(Block {
                    kind: opened.0,
                    name: opened.1,
                    start_line: opened.2,
                    end_line: line,
                    parent: stack.last().copied(),
                });
                stack.push(blocks.len() - 1);
            }
            '}' => {
                if let Some(index) = stack.pop() {
                    blocks[index].end_line = line;
                }
            }
            _ => {}
        }
    }

    for index in stack {
        blocks[index].end_line = line;
    }
    blocks
}

#[cfg(test)]
mod tests {
    use super::*;

    fn named(source: &str) -> Vec<(BlockKind, String, usize, usize)> {
        outline(&code_only(source))
            .into_iter()
            .filter(|b| b.kind != BlockKind::Other)
            .map(|b| (b.kind, b.name, b.start_line, b.end_line))
            .collect()
    }

    #[test]
    fn code_only_keeps_every_line_in_place() {
        let source = "<?php\n// note\n$a = 'x\ny';\n/* a\n b */\n$b = 1;\n";
        let code = code_only(source);
        assert_eq!(code.lines().count(), source.lines().count());
        assert_eq!(code.lines().nth(6), Some("$b = 1;"));
    }

    #[test]
    fn code_only_blanks_comments_and_string_bodies() {
        let code = code_only("<?php\n$a = \"{$x} // y\"; // gone\n");
        assert!(!code.contains("gone"));
        assert!(!code.contains('{'));
        assert!(code.contains("$a = \""));
    }

    #[test]
    fn code_only_blanks_a_heredoc_body_and_keeps_what_follows_it() {
        let code = code_only("<?php\n$sql = <<<SQL\n    SELECT { FROM x\n    SQL;\n$b = 1;\n");
        assert!(!code.contains("SELECT"));
        assert!(code.contains("    ;"));
        assert!(code.contains("$b = 1;"));
    }

    #[test]
    fn code_only_keeps_attributes() {
        assert!(code_only("<?php\n#[Route('/x')]\n").contains("#[Route("));
    }

    #[test]
    fn finds_a_class_and_its_methods() {
        let source = "<?php\nfinal class Widget\n{\n    public function go(): void\n    {\n        $x = 1;\n    }\n}\n";
        assert_eq!(
            named(source),
            vec![
                (BlockKind::Class, "Widget".to_string(), 2, 8),
                (BlockKind::Function, "go".to_string(), 4, 7),
            ]
        );
    }

    #[test]
    fn a_brace_inside_a_string_does_not_end_the_method() {
        let source =
            "<?php\nclass A {\n    public function go(): string {\n        return '}';\n    }\n}\n";
        assert_eq!(
            named(source)[1],
            (BlockKind::Function, "go".to_string(), 3, 5)
        );
    }

    #[test]
    fn a_multi_line_signature_counts_from_the_function_keyword() {
        let source =
            "<?php\nclass A {\n    public function go(\n        int $a,\n    ): void {\n    }\n}\n";
        assert_eq!(
            named(source)[1],
            (BlockKind::Function, "go".to_string(), 3, 6)
        );
    }

    #[test]
    fn a_return_type_named_like_a_keyword_does_not_open_a_type() {
        let source = "<?php\nclass A {\n    public function go(): Enum {\n    }\n}\n";
        assert_eq!(
            named(source)[1],
            (BlockKind::Function, "go".to_string(), 3, 4)
        );
    }

    #[test]
    fn closures_and_anonymous_classes_have_no_name() {
        let source = "<?php\nclass A {\n    public function go(): void {\n        $f = function ($x) use ($y) {};\n        $o = new class extends B {};\n    }\n}\n";
        let found = named(source);
        assert_eq!(found[2], (BlockKind::Function, String::new(), 4, 4));
        assert_eq!(found[3], (BlockKind::Class, String::new(), 5, 5));
    }

    #[test]
    fn a_body_less_method_opens_no_block() {
        let source = "<?php\ninterface I {\n    public function go(): void;\n}\n";
        assert_eq!(
            named(source),
            vec![(BlockKind::Interface, "I".to_string(), 2, 4)]
        );
    }

    #[test]
    fn class_constant_fetch_is_not_a_declaration() {
        let source = "<?php\nclass A {\n    public function go(): string {\n        return B::class;\n    }\n}\n";
        assert_eq!(named(source).len(), 2);
    }

    #[test]
    fn catch_blocks_are_found() {
        let source = "<?php\nclass A {\n    public function go(): void {\n        try {\n        } catch (E $e) {\n            throw $e;\n        }\n    }\n}\n";
        assert_eq!(named(source)[2], (BlockKind::Catch, String::new(), 5, 7));
    }

    #[test]
    fn blocks_know_their_parent() {
        let source = "<?php\nclass A {\n    public function go(): void {\n        if (true) {\n        }\n    }\n}\n";
        let blocks = outline(&code_only(source));
        assert_eq!(blocks[2].parent, Some(1));
        assert_eq!(blocks[1].parent, Some(0));
    }
}
