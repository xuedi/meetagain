//! Two-file config resolution shared by every tool under `bin/tools/`.
//!
//! A tool named `foo` reads `config/tools/foo.dist` (committed, required) and then
//! `config/tools/foo.local` (gitignored, optional). Paths resolve from the current working
//! directory, which is always the repo root, so `cargo run` and an installed binary behave
//! the same.
//!
//! The merge rule is what lets the private overlay extend the public rules instead of
//! restating them: values read through [`Config::list`] concatenate `.dist` then `.local`,
//! values read through [`Config::scalar`] take the last `.local` entry and fall back to
//! `.dist`. Which rule applies is decided by the accessor the tool calls, not by the file.

use std::fs;
use std::path::{Path, PathBuf};
use std::process::exit;

const CONFIG_DIR: &str = "config/tools";

pub struct Config {
    dist_path: PathBuf,
    local_path: PathBuf,
    dist_lines: Vec<String>,
    local_lines: Vec<String>,
}

impl Config {
    pub fn load(name: &str) -> Result<Self, String> {
        let dist_path = PathBuf::from(CONFIG_DIR).join(format!("{}.dist", name));
        let local_path = PathBuf::from(CONFIG_DIR).join(format!("{}.local", name));

        let dist = fs::read_to_string(&dist_path)
            .map_err(|e| format!("cannot read {}: {}", dist_path.display(), e))?;
        let local = fs::read_to_string(&local_path).unwrap_or_default();

        Ok(Self {
            dist_path,
            local_path,
            dist_lines: dist.lines().map(str::to_string).collect(),
            local_lines: local.lines().map(str::to_string).collect(),
        })
    }

    pub fn load_or_exit(name: &str) -> Self {
        match Self::load(name) {
            Ok(config) => config,
            Err(message) => {
                eprintln!("config error: {}", message);
                eprintln!("hint: tools read config/tools/ relative to the repo root - run them from there.");
                exit(2);
            }
        }
    }

    pub fn scalar(&self, key: &str) -> Option<String> {
        last_value(&self.local_lines, key).or_else(|| last_value(&self.dist_lines, key))
    }

    pub fn require(&self, key: &str) -> String {
        match self.scalar(key).filter(|value| !value.is_empty()) {
            Some(value) => value,
            None => {
                eprintln!(
                    "config error: {} is unset in {} and {}",
                    key,
                    self.dist_path.display(),
                    self.local_path.display()
                );
                exit(2);
            }
        }
    }

    pub fn list(&self, key: &str) -> Vec<String> {
        let mut merged = Vec::new();
        for lines in [&self.dist_lines, &self.local_lines] {
            for value in all_values(lines, key) {
                merged.extend(
                    value
                        .split(',')
                        .map(str::trim)
                        .filter(|part| !part.is_empty())
                        .map(str::to_string),
                );
            }
        }
        merged
    }

    /// Like [`Config::list`], but one entry per assignment rather than per comma-separated
    /// part - for values that may themselves contain a comma, such as a regex quantifier.
    pub fn raw_list(&self, key: &str) -> Vec<String> {
        let mut merged = Vec::new();
        for lines in [&self.dist_lines, &self.local_lines] {
            merged.extend(all_values(lines, key).into_iter().map(str::to_string));
        }
        merged
    }

    pub fn number(&self, key: &str, default: usize) -> Result<usize, String> {
        match self.scalar(key) {
            Some(value) => value
                .parse()
                .map_err(|_| format!("{} is not a number: {}", key, value)),
            None => Ok(default),
        }
    }

    /// Write a key into the `.local` overlay. Values a tool discovers at runtime (refreshed
    /// OAuth tokens) are credentials, so they never go back into the committed `.dist`.
    pub fn set(&mut self, key: &str, value: &str) {
        let assignment = format!("{}={}", key, value);
        for line in self.local_lines.iter_mut() {
            if value_of(line, key).is_some() {
                *line = assignment;
                return;
            }
        }
        self.local_lines.push(assignment);
    }

    pub fn save(&self) -> Result<(), String> {
        let mut content = self.local_lines.join("\n");
        if !content.ends_with('\n') {
            content.push('\n');
        }
        fs::write(&self.local_path, content)
            .map_err(|e| format!("cannot write {}: {}", self.local_path.display(), e))
    }

    pub fn dist_path(&self) -> &Path {
        &self.dist_path
    }

    pub fn local_path(&self) -> &Path {
        &self.local_path
    }
}

fn value_of<'a>(line: &'a str, key: &str) -> Option<&'a str> {
    let trimmed = line.trim();
    if trimmed.is_empty() || trimmed.starts_with('#') {
        return None;
    }
    let (found, value) = trimmed.split_once('=')?;
    if found.trim() != key {
        return None;
    }
    Some(value.trim().trim_matches('"').trim_matches('\''))
}

fn all_values<'a>(lines: &'a [String], key: &str) -> Vec<&'a str> {
    lines.iter().filter_map(|line| value_of(line, key)).collect()
}

fn last_value(lines: &[String], key: &str) -> Option<String> {
    all_values(lines, key).last().map(|value| value.to_string())
}

#[cfg(test)]
mod tests {
    use super::*;

    fn config(dist: &str, local: &str) -> Config {
        Config {
            dist_path: PathBuf::from("config/tools/test.dist"),
            local_path: PathBuf::from("config/tools/test.local"),
            dist_lines: dist.lines().map(str::to_string).collect(),
            local_lines: local.lines().map(str::to_string).collect(),
        }
    }

    #[test]
    fn list_keys_concatenate_dist_then_local() {
        let config = config("WORDS=alpha,beta", "WORDS=gamma");

        assert_eq!(vec!["alpha", "beta", "gamma"], config.list("WORDS"));
    }

    #[test]
    fn list_key_present_only_in_dist_is_returned_unchanged() {
        let config = config("WORDS=alpha,beta", "");

        assert_eq!(vec!["alpha", "beta"], config.list("WORDS"));
    }

    #[test]
    fn scalar_keys_are_replaced_by_the_overlay() {
        let config = config("MAX=4", "MAX=9");

        assert_eq!(Some("9".to_string()), config.scalar("MAX"));
    }

    #[test]
    fn scalar_falls_back_to_dist_when_the_overlay_is_silent() {
        let config = config("MAX=4", "OTHER=1");

        assert_eq!(Some("4".to_string()), config.scalar("MAX"));
    }

    #[test]
    fn commented_and_blank_lines_are_ignored() {
        let config = config("# WORDS=commented\n\nWORDS=alpha", "");

        assert_eq!(vec!["alpha"], config.list("WORDS"));
    }

    #[test]
    fn a_key_that_is_a_prefix_of_another_does_not_match() {
        let config = config("TOKEN=a\nTOKEN_KIND=b", "");

        assert_eq!(Some("a".to_string()), config.scalar("TOKEN"));
        assert_eq!(Some("b".to_string()), config.scalar("TOKEN_KIND"));
    }

    #[test]
    fn quoted_values_are_unwrapped() {
        let config = config("URL=\"https://example.test\"", "");

        assert_eq!(Some("https://example.test".to_string()), config.scalar("URL"));
    }

    #[test]
    fn set_replaces_an_existing_overlay_line_and_appends_a_new_one() {
        let mut config = config("TOKEN=", "TOKEN=old\n# keep me");

        config.set("TOKEN", "new");
        config.set("REFRESH", "value");

        assert_eq!(
            vec!["TOKEN=new", "# keep me", "REFRESH=value"],
            config.local_lines
        );
    }

    #[test]
    fn raw_list_keeps_commas_inside_a_single_entry() {
        let config = config("PATTERNS=a=x{3,}\nPATTERNS=b=y", "PATTERNS=c=z");

        assert_eq!(vec!["a=x{3,}", "b=y", "c=z"], config.raw_list("PATTERNS"));
    }

    #[test]
    fn number_uses_the_default_when_the_key_is_absent() {
        let config = config("", "");

        assert_eq!(Ok(7), config.number("MAX", 7));
    }
}
