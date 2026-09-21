//! Remembers which working tree last passed the whole commit-hook chain.
//!
//! The chain costs minutes and used to run once per `just test` and again for every commit
//! that followed it, although nothing had changed in between. This tool fingerprints the
//! content of every file that can change a test result and writes the fingerprint to a stamp
//! file when the chain passes; the pre-commit hook asks it first and skips the expensive hooks
//! while the tree still matches.
//!
//! The fingerprint is over content, never over timestamps or `.git/`: staging and committing
//! leave the working tree alone, so they leave the stamp valid, and a `touch` changes nothing.
//! Each `SCAN_ROOTS` entry is walked on its own with the git ignore rules of its own
//! repository, which is how a nested checkout that the outer repository excludes is covered.

use std::collections::{BTreeMap, BTreeSet};
use std::env;
use std::fs;
use std::io::ErrorKind;
use std::os::unix::ffi::OsStrExt;
use std::os::unix::fs::PermissionsExt;
use std::path::Path;
use std::process::ExitCode;
use std::time::{SystemTime, UNIX_EPOCH};

use ignore::WalkBuilder;
use rayon::prelude::*;
use toolconfig::Config;

const FORMAT: &str = "test-stamp v1";
const DIFF_LIMIT: usize = 10;

struct Rules {
    roots: Vec<String>,
    include_ignored: Vec<String>,
    exclude: Vec<String>,
    always_run: Vec<String>,
    stamp_file: String,
}

impl Rules {
    fn from_config(config: &Config) -> Self {
        Self {
            roots: config.list("SCAN_ROOTS"),
            include_ignored: config.list("INCLUDE_IGNORED"),
            exclude: config.list("EXCLUDE"),
            always_run: config.list("ALWAYS_RUN"),
            stamp_file: config.require("STAMP_FILE"),
        }
    }

    fn describe(&self) -> String {
        format!(
            "{}\nroots={}\ninclude_ignored={}\nexclude={}\nalways_run={}\n",
            FORMAT,
            self.roots.join(","),
            self.include_ignored.join(","),
            self.exclude.join(","),
            self.always_run.join(","),
        )
    }

    fn is_excluded(&self, path: &str) -> bool {
        path == self.stamp_file || self.exclude.iter().any(|prefix| is_under(path, prefix))
    }
}

/// Relative path -> `<blake3> <mode>`, where mode is `x` (executable), `-` or `l` (symlink).
type Entries = BTreeMap<String, String>;

struct Snapshot {
    digest: String,
    entries: Entries,
}

fn is_under(path: &str, prefix: &str) -> bool {
    let prefix = prefix.trim_end_matches('/');
    path == prefix || path.strip_prefix(prefix).is_some_and(|rest| rest.starts_with('/'))
}

fn relative(base: &Path, path: &Path) -> Option<String> {
    let rel = path.strip_prefix(base).ok()?;
    let rel = rel.to_str()?.trim_start_matches("./");
    (!rel.is_empty()).then(|| rel.to_string())
}

fn walk(base: &Path, start: &Path, git_rules: bool, into: &mut BTreeSet<String>) -> Result<(), String> {
    let walker = WalkBuilder::new(start)
        .standard_filters(git_rules)
        .hidden(false)
        .require_git(false)
        .filter_entry(|entry| entry.file_name() != ".git")
        .build();
    for entry in walker {
        let entry = entry.map_err(|e| e.to_string())?;
        if entry.file_type().is_some_and(|kind| kind.is_dir()) {
            continue;
        }
        if let Some(path) = relative(base, entry.path()) {
            into.insert(path);
        }
    }
    Ok(())
}

fn collect_paths(base: &Path, rules: &Rules) -> Result<BTreeSet<String>, String> {
    let mut paths = BTreeSet::new();
    for root in &rules.roots {
        let start = base.join(root);
        if !start.is_dir() {
            return Err(format!("SCAN_ROOTS entry {} is not a directory", root));
        }
        walk(base, &start, true, &mut paths)?;
    }
    for include in &rules.include_ignored {
        let start = base.join(include);
        if start.symlink_metadata().is_ok() {
            walk(base, &start, false, &mut paths)?;
        }
    }
    paths.retain(|path| !rules.is_excluded(path));
    Ok(paths)
}

fn hash_entry(base: &Path, path: &str) -> Result<Option<String>, String> {
    let full = base.join(path);
    let read = || -> std::io::Result<String> {
        let meta = fs::symlink_metadata(&full)?;
        if meta.file_type().is_symlink() {
            let target = fs::read_link(&full)?;
            return Ok(format!("{} l", blake3::hash(target.as_os_str().as_bytes()).to_hex()));
        }
        let mode = if meta.permissions().mode() & 0o111 != 0 { "x" } else { "-" };
        Ok(format!("{} {}", blake3::hash(&fs::read(&full)?).to_hex(), mode))
    };
    match read() {
        Ok(value) => Ok(Some(value)),
        Err(e) if e.kind() == ErrorKind::NotFound => Ok(None),
        Err(e) => Err(format!("cannot read {}: {}", path, e)),
    }
}

fn digest_of(rules: &Rules, entries: &Entries) -> String {
    let mut hasher = blake3::Hasher::new();
    hasher.update(rules.describe().as_bytes());
    for (path, value) in entries {
        hasher.update(value.as_bytes());
        hasher.update(b" ");
        hasher.update(path.as_bytes());
        hasher.update(b"\n");
    }
    hasher.finalize().to_hex().to_string()
}

fn snapshot(base: &Path, rules: &Rules) -> Result<Snapshot, String> {
    let paths: Vec<String> = collect_paths(base, rules)?.into_iter().collect();
    let hashed: Vec<(String, Option<String>)> = paths
        .into_par_iter()
        .map(|path| hash_entry(base, &path).map(|value| (path, value)))
        .collect::<Result<_, _>>()?;
    let entries: Entries = hashed
        .into_iter()
        .filter_map(|(path, value)| value.map(|value| (path, value)))
        .collect();
    Ok(Snapshot { digest: digest_of(rules, &entries), entries })
}

fn render_stamp(snapshot: &Snapshot) -> String {
    let written = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map(|d| d.as_secs())
        .unwrap_or(0);
    let mut out = format!(
        "# {} - the working tree that last passed the whole hook chain. Written by test-stamp; do not edit.\n\
         digest={}\nwritten={}\n",
        FORMAT, snapshot.digest, written
    );
    for (path, value) in &snapshot.entries {
        out.push_str(&format!("{} {}\n", value, path));
    }
    out
}

fn parse_stamp(text: &str) -> Option<Snapshot> {
    let mut lines = text.lines();
    if !lines.next()?.starts_with(&format!("# {} ", FORMAT)) {
        return None;
    }
    let mut digest = None;
    let mut entries = Entries::new();
    for line in lines {
        if let Some(value) = line.strip_prefix("digest=") {
            digest = Some(value.to_string());
        } else if line.starts_with("written=") {
            continue;
        } else {
            let (hash, rest) = line.split_once(' ')?;
            let (mode, path) = rest.split_once(' ')?;
            entries.insert(path.to_string(), format!("{} {}", hash, mode));
        }
    }
    Some(Snapshot { digest: digest?, entries })
}

fn diff(old: &Entries, new: &Entries) -> Vec<String> {
    let mut lines = Vec::new();
    for (path, value) in new {
        match old.get(path) {
            None => lines.push(format!("added    {}", path)),
            Some(previous) if previous != value => lines.push(format!("changed  {}", path)),
            _ => {}
        }
    }
    for path in old.keys().filter(|path| !new.contains_key(*path)) {
        lines.push(format!("removed  {}", path));
    }
    lines.sort_by(|a, b| a[9..].cmp(&b[9..]));
    lines
}

fn hook_name(hook: &str) -> &str {
    let name = hook.rsplit('/').next().unwrap_or(hook);
    name.strip_suffix(".bash").unwrap_or(name)
}

fn check(base: &Path, rules: &Rules) -> Result<bool, String> {
    let Some(stored) = fs::read_to_string(base.join(&rules.stamp_file)).ok() else {
        eprintln!("test-stamp: no {} yet - running the full chain", rules.stamp_file);
        return Ok(false);
    };
    let Some(stored) = parse_stamp(&stored) else {
        eprintln!("test-stamp: {} is from another format - running the full chain", rules.stamp_file);
        return Ok(false);
    };
    let current = snapshot(base, rules)?;
    if current.digest == stored.digest {
        return Ok(true);
    }
    eprintln!("test-stamp: the tree differs from {} - running the full chain", rules.stamp_file);
    let changes = diff(&stored.entries, &current.entries);
    if changes.is_empty() {
        eprintln!("  the test-stamp config changed");
    }
    for line in changes.iter().take(DIFF_LIMIT) {
        eprintln!("  {}", line);
    }
    if changes.len() > DIFF_LIMIT {
        eprintln!("  ... and {} more", changes.len() - DIFF_LIMIT);
    }
    Ok(false)
}

fn write(base: &Path, rules: &Rules, expected: &str) -> Result<bool, String> {
    let current = snapshot(base, rules)?;
    if current.digest != expected {
        eprintln!("test-stamp: the tree changed while the chain ran - no stamp written");
        return Ok(false);
    }
    let target = base.join(&rules.stamp_file);
    let temporary = base.join(format!("{}.tmp", rules.stamp_file));
    fs::write(&temporary, render_stamp(&current))
        .and_then(|_| fs::rename(&temporary, &target))
        .map_err(|e| format!("cannot write {}: {}", rules.stamp_file, e))?;
    eprintln!("test-stamp: {} written ({} files)", rules.stamp_file, current.entries.len());
    Ok(true)
}

fn print_help() {
    println!("test-stamp - skip the commit-hook tests while the tree matches the last green run");
    println!();
    println!("usage:");
    println!("  test-stamp fingerprint       print the digest of the current tree");
    println!("  test-stamp write <digest>    write the stamp if the tree still has <digest>");
    println!("  test-stamp check             exit 0 when the tree matches the stamp, 1 otherwise");
    println!("  test-stamp always-run <hook> exit 0 when the hook runs even on a fresh stamp");
    println!("  test-stamp files             list every fingerprinted file with its hash");
    println!();
    println!("config: config/tools/test-stamp.dist, overlaid by config/tools/test-stamp.local");
}

fn run(args: &[String]) -> Result<bool, String> {
    let base = Path::new(".");
    let rules = Rules::from_config(&Config::load_or_exit("test-stamp"));
    match args {
        [command] if command == "fingerprint" => {
            println!("{}", snapshot(base, &rules)?.digest);
            Ok(true)
        }
        [command, digest] if command == "write" => write(base, &rules, digest),
        [command] if command == "check" => check(base, &rules),
        [command, hook] if command == "always-run" => {
            Ok(rules.always_run.iter().any(|name| name == hook_name(hook)))
        }
        [command] if command == "files" => {
            for (path, value) in snapshot(base, &rules)?.entries {
                println!("{} {}", value, path);
            }
            Ok(true)
        }
        _ => {
            print_help();
            Err("unknown or incomplete command".to_string())
        }
    }
}

fn main() -> ExitCode {
    let args: Vec<String> = env::args().skip(1).collect();
    if matches!(args.first().map(String::as_str), Some("-h" | "--help")) {
        print_help();
        return ExitCode::SUCCESS;
    }
    match run(&args) {
        Ok(true) => ExitCode::SUCCESS,
        Ok(false) => ExitCode::from(1),
        Err(message) => {
            eprintln!("test-stamp: {}", message);
            ExitCode::from(2)
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::path::PathBuf;

    struct Tree(PathBuf);

    impl Tree {
        fn new(name: &str) -> Self {
            let dir = env::temp_dir().join(format!("test-stamp-{}-{}", name, std::process::id()));
            let _ = fs::remove_dir_all(&dir);
            fs::create_dir_all(dir.join(".git/info")).unwrap();
            Tree(dir)
        }

        fn put(&self, path: &str, content: &str) {
            let full = self.0.join(path);
            fs::create_dir_all(full.parent().unwrap()).unwrap();
            fs::write(full, content).unwrap();
        }

        fn digest(&self, rules: &Rules) -> String {
            snapshot(&self.0, rules).unwrap().digest
        }

        fn paths(&self, rules: &Rules) -> Vec<String> {
            snapshot(&self.0, rules).unwrap().entries.into_keys().collect()
        }
    }

    impl Drop for Tree {
        fn drop(&mut self) {
            let _ = fs::remove_dir_all(&self.0);
        }
    }

    fn rules() -> Rules {
        Rules {
            roots: vec![".".to_string()],
            include_ignored: vec![],
            exclude: vec![],
            always_run: vec!["01-leak-guard".to_string()],
            stamp_file: "testPassing.lock".to_string(),
        }
    }

    #[test]
    fn a_content_edit_changes_the_digest_but_rewriting_the_same_bytes_does_not() {
        let tree = Tree::new("content");
        tree.put("src/a.php", "one");
        let before = tree.digest(&rules());

        tree.put("src/a.php", "one");
        let same = tree.digest(&rules());
        tree.put("src/a.php", "two");
        let edited = tree.digest(&rules());

        assert_eq!(before, same);
        assert_ne!(before, edited);
    }

    #[test]
    fn losing_the_executable_bit_changes_the_digest() {
        let tree = Tree::new("mode");
        tree.put("bin/hook.bash", "#!/bin/sh");
        let path = tree.0.join("bin/hook.bash");
        fs::set_permissions(&path, fs::Permissions::from_mode(0o755)).unwrap();
        let executable = tree.digest(&rules());

        fs::set_permissions(&path, fs::Permissions::from_mode(0o644)).unwrap();

        assert_ne!(executable, tree.digest(&rules()));
    }

    #[test]
    fn git_internals_and_ignored_files_are_skipped_unless_included() {
        let tree = Tree::new("ignored");
        tree.put(".gitignore", "/var/\n/.env.local\n");
        tree.put(".git/index", "binary");
        tree.put("var/cache.php", "x");
        tree.put(".env.local", "KEY=1");
        tree.put("src/a.php", "a");
        let mut with_include = rules();
        with_include.include_ignored = vec![".env.local".to_string()];

        assert_eq!(vec![".gitignore", "src/a.php"], tree.paths(&rules()));
        assert_eq!(vec![".env.local", ".gitignore", "src/a.php"], tree.paths(&with_include));
    }

    #[test]
    fn a_checkout_excluded_by_the_outer_repository_comes_back_as_its_own_root() {
        let tree = Tree::new("nested");
        tree.put(".git/info/exclude", "/plugins/private/\n");
        tree.put("plugins/private/.git/HEAD", "ref");
        tree.put("plugins/public/a.php", "a");
        tree.put("plugins/private/.gitignore", "/cache/\n");
        tree.put("plugins/private/b.php", "b");
        tree.put("plugins/private/cache/c.php", "c");
        let mut with_root = rules();
        with_root.roots.push("plugins/private".to_string());

        assert_eq!(vec!["plugins/public/a.php"], tree.paths(&rules()));
        assert_eq!(
            vec!["plugins/private/.gitignore", "plugins/private/b.php", "plugins/public/a.php"],
            tree.paths(&with_root)
        );
    }

    #[test]
    fn exclude_drops_a_prefix_but_not_a_sibling_sharing_its_name() {
        let tree = Tree::new("exclude");
        tree.put("docs/src/a.md", "a");
        tree.put("docs/srcx/b.md", "b");
        let mut excluding = rules();
        excluding.exclude = vec!["docs/src".to_string()];

        assert_eq!(vec!["docs/srcx/b.md"], tree.paths(&excluding));
    }

    #[test]
    fn a_config_change_alone_changes_the_digest() {
        let tree = Tree::new("config");
        tree.put("src/a.php", "a");
        let mut other = rules();
        other.always_run.push("04-docs-guard".to_string());

        assert_ne!(tree.digest(&rules()), tree.digest(&other));
    }

    #[test]
    fn write_refuses_a_tree_that_moved_and_check_accepts_the_one_it_wrote() {
        let tree = Tree::new("write");
        tree.put("src/a.php", "a");
        let before = tree.digest(&rules());
        tree.put("src/a.php", "edited during the run");

        assert!(!write(&tree.0, &rules(), &before).unwrap());
        assert!(!tree.0.join("testPassing.lock").exists());

        let now = tree.digest(&rules());
        assert!(write(&tree.0, &rules(), &now).unwrap());
        assert!(check(&tree.0, &rules()).unwrap());
    }

    #[test]
    fn the_stamp_survives_a_round_trip() {
        let tree = Tree::new("roundtrip");
        tree.put("src/a b.php", "a");
        let written = snapshot(&tree.0, &rules()).unwrap();

        let parsed = parse_stamp(&render_stamp(&written)).unwrap();

        assert_eq!(written.digest, parsed.digest);
        assert_eq!(written.entries, parsed.entries);
    }

    #[test]
    fn diff_names_changed_added_and_removed_paths_in_path_order() {
        let old: Entries = [("a", "1 -"), ("b", "1 -"), ("c", "1 -")]
            .into_iter()
            .map(|(p, v)| (p.to_string(), v.to_string()))
            .collect();
        let new: Entries = [("a", "1 -"), ("b", "2 -"), ("d", "1 -")]
            .into_iter()
            .map(|(p, v)| (p.to_string(), v.to_string()))
            .collect();

        assert_eq!(vec!["changed  b", "removed  c", "added    d"], diff(&old, &new));
    }

    #[test]
    fn hook_names_match_with_or_without_path_and_extension() {
        assert_eq!("01-leak-guard", hook_name("bin/commit-hooks/01-leak-guard.bash"));
        assert_eq!("01-leak-guard", hook_name("01-leak-guard"));
    }
}
