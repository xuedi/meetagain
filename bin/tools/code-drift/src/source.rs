use phplex::{Block, BlockKind};

pub struct PhpFile {
    pub path: String,
    pub raw: String,
    pub code: String,
    pub blocks: Vec<Block>,
}

impl PhpFile {
    pub fn new(path: &str, raw: String) -> Self {
        let code = phplex::code_only(&raw);
        let blocks = phplex::outline(&code);
        Self {
            path: path.to_string(),
            raw,
            code,
            blocks,
        }
    }

    /// `(line number, original text, code-only text)`: patterns match the code-only text, so a
    /// string or a comment never trips them, and rows print the original.
    pub fn lines(&self) -> impl Iterator<Item = (usize, &str, &str)> {
        self.raw
            .lines()
            .zip(self.code.lines())
            .enumerate()
            .map(|(index, (raw, code))| (index + 1, raw, code))
    }

    pub fn code_lines(&self) -> impl Iterator<Item = &str> {
        self.code.lines()
    }

    /// Counted the way `wc -l` counts, so a length reads the same as it does in an editor.
    pub fn line_count(&self) -> usize {
        self.raw.matches('\n').count()
    }

    pub fn class_name(&self) -> &str {
        let file = self.path.rsplit('/').next().unwrap_or(&self.path);
        file.strip_suffix(".php").unwrap_or(file)
    }

    pub fn enclosing_method(&self, line: usize) -> Option<&str> {
        self.innermost(line, |block| block.kind == BlockKind::Function)
    }

    pub fn enclosing_symbol(&self, line: usize) -> Option<&str> {
        self.enclosing_method(line)
            .or_else(|| self.innermost(line, |block| block.kind.is_type()))
    }

    fn innermost(&self, line: usize, wanted: impl Fn(&Block) -> bool) -> Option<&str> {
        self.blocks
            .iter()
            .filter(|block| wanted(block) && !block.name.is_empty())
            .filter(|block| block.start_line <= line && line <= block.end_line)
            .max_by_key(|block| block.start_line)
            .map(|block| block.name.as_str())
    }
}

#[derive(Debug, Clone)]
pub struct Finding {
    pub detector: &'static str,
    pub path: String,
    pub symbol: Option<String>,
    pub row: String,
}

#[cfg(test)]
mod tests {
    use super::*;

    const SOURCE: &str = "<?php\nfinal class Widget\n{\n    private const A = 1;\n\n    public function go(): void\n    {\n        $f = function () {\n            $x = 1;\n        };\n    }\n}\n";

    #[test]
    fn a_line_inside_a_closure_belongs_to_the_method_around_it() {
        let file = PhpFile::new("src/Widget.php", SOURCE.to_string());
        assert_eq!(file.enclosing_method(9), Some("go"));
    }

    #[test]
    fn a_line_outside_every_method_belongs_to_the_class() {
        let file = PhpFile::new("src/Widget.php", SOURCE.to_string());
        assert_eq!(file.enclosing_method(4), None);
        assert_eq!(file.enclosing_symbol(4), Some("Widget"));
    }

    #[test]
    fn the_class_name_is_the_file_name() {
        let file = PhpFile::new("src/Service/Widget.php", SOURCE.to_string());
        assert_eq!(file.class_name(), "Widget");
        assert_eq!(file.line_count(), 12);
    }
}
