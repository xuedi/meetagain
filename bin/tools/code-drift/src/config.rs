use std::fs;
use std::path::{Path, PathBuf};

pub struct Config {
    pub scan_paths: Vec<PathBuf>,
    pub source_subdir: String,
    pub exclude_dirs: Vec<String>,
    pub reference_paths: Vec<String>,
    pub reference_extensions: Vec<String>,
    pub test_paths: Vec<String>,
    pub layer_words: Vec<String>,
    pub name_families: Vec<String>,
    pub name_family_paths: Vec<String>,
    pub tiny_max_lines: usize,
    pub policy_doc: String,
}

pub fn load() -> Result<Config, String> {
    let config = toolconfig::Config::load("code-drift")?;

    let scan_paths: Vec<PathBuf> = config
        .list("SCAN_PATHS")
        .into_iter()
        .map(PathBuf::from)
        .collect();
    if scan_paths.is_empty() {
        return Err("SCAN_PATHS is empty".to_string());
    }
    let source_subdir = config
        .scalar("SOURCE_SUBDIR")
        .filter(|value| !value.is_empty())
        .ok_or("SOURCE_SUBDIR is unset")?;

    Ok(Config {
        scan_paths,
        source_subdir,
        exclude_dirs: config.list("EXCLUDE_DIRS"),
        reference_paths: config.list("REFERENCE_PATHS"),
        reference_extensions: config.list("REFERENCE_EXTENSIONS"),
        test_paths: config.list("TEST_PATHS"),
        layer_words: config.list("LAYER_WORDS"),
        name_families: config.list("NAME_FAMILIES"),
        name_family_paths: config
            .list("NAME_FAMILY_PATHS")
            .into_iter()
            .map(|path| path.trim_end_matches('/').to_string())
            .collect(),
        tiny_max_lines: config.number("TINY_MAX_LINES", 60)?,
        policy_doc: config
            .scalar("POLICY_DOC")
            .unwrap_or_else(|| "the project's coding standards".to_string()),
    })
}

/// Resolves a configured path whose segments may be `*`, meaning every directory at that level.
pub fn expand(pattern: &str) -> Vec<PathBuf> {
    let mut found = vec![PathBuf::new()];
    for segment in pattern.split('/').filter(|s| !s.is_empty()) {
        let mut next = Vec::new();
        for base in &found {
            if segment == "*" {
                next.extend(subdirectories(base));
            } else {
                next.push(base.join(segment));
            }
        }
        found = next;
    }
    found.into_iter().filter(|path| path.exists()).collect()
}

fn subdirectories(base: &Path) -> Vec<PathBuf> {
    let dir = if base.as_os_str().is_empty() {
        Path::new(".")
    } else {
        base
    };
    let mut dirs: Vec<PathBuf> = fs::read_dir(dir)
        .map(|entries| {
            entries
                .filter_map(|entry| entry.ok())
                .filter(|entry| entry.path().is_dir())
                .map(|entry| base.join(entry.file_name()))
                .collect()
        })
        .unwrap_or_default();
    dirs.sort();
    dirs
}
