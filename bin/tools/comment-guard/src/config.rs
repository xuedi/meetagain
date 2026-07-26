use std::collections::HashSet;
use std::path::PathBuf;

pub struct Config {
    pub scan_paths: Vec<PathBuf>,
    pub exclude_paths: Vec<PathBuf>,
    pub exclude_dirs: HashSet<String>,
    pub interface_max_prose_lines: usize,
    pub markers: Vec<String>,
    pub directives: Vec<String>,
    pub tags: HashSet<String>,
    pub aaa_markers: HashSet<String>,
    pub policy_doc: String,
}

pub fn load() -> Result<Config, String> {
    let config = toolconfig::Config::load("comment-guard")?;

    let scan_paths: Vec<PathBuf> = config
        .list("SCAN_PATHS")
        .into_iter()
        .map(PathBuf::from)
        .collect();
    if scan_paths.is_empty() {
        return Err("SCAN_PATHS is empty".to_string());
    }

    Ok(Config {
        scan_paths,
        exclude_paths: config
            .list("EXCLUDE_PATHS")
            .into_iter()
            .map(PathBuf::from)
            .collect(),
        exclude_dirs: config.list("EXCLUDE_DIRS").into_iter().collect(),
        interface_max_prose_lines: config.number("INTERFACE_MAX_PROSE_LINES", 4)?,
        markers: config
            .list("MARKERS")
            .into_iter()
            .map(|m| m.to_uppercase())
            .collect(),
        directives: config.list("DIRECTIVES"),
        tags: config
            .list("TAGS")
            .into_iter()
            .map(|t| t.to_lowercase())
            .collect(),
        aaa_markers: config
            .list("AAA_MARKERS")
            .into_iter()
            .map(|m| m.to_lowercase())
            .collect(),
        policy_doc: config
            .scalar("POLICY_DOC")
            .unwrap_or_else(|| "the project's comment policy".to_string()),
    })
}
