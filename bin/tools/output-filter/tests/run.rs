use std::env;
use std::fs;
use std::os::unix::fs::PermissionsExt;
use std::path::{Path, PathBuf};
use std::process::{Command, Output};

const BINARY: &str = env!("CARGO_BIN_EXE_output-filter");

struct Workspace {
    dir: PathBuf,
    run: String,
}

impl Workspace {
    fn new(name: &str, dist: &str) -> Self {
        let dir = env::temp_dir().join(format!("output-filter-test-{}-{}", name, std::process::id()));
        let _ = fs::remove_dir_all(&dir);
        fs::create_dir_all(dir.join("config/tools")).unwrap();
        fs::write(dir.join("config/tools/output-filter.dist.json"), dist).unwrap();
        Self {
            dir,
            run: format!("test-{}-{}", name, std::process::id()),
        }
    }

    fn run(&self, line: &str) -> Output {
        Command::new(BINARY)
            .current_dir(&self.dir)
            .env("OUTPUT_FILTER_RUN", &self.run)
            .env_remove("OUTPUT_FILTER_DEBUG")
            .args(["sh", "-cu", line])
            .output()
            .unwrap()
    }

    fn file(&self, name: &str) -> Option<String> {
        fs::read_to_string(self.dir.join(name)).ok()
    }
}

impl Drop for Workspace {
    fn drop(&mut self) {
        let _ = fs::remove_dir_all(&self.dir);
        let _ = fs::remove_file(env::temp_dir().join(format!("output-filter-run-{}.json", self.run)));
    }
}

fn stderr(output: &Output) -> Vec<String> {
    String::from_utf8_lossy(&output.stderr)
        .lines()
        .map(|line| {
            line.split_whitespace()
                .filter(|word| !word.chars().all(|c| c == '.'))
                .collect::<Vec<_>>()
                .join(" ")
        })
        .collect()
}

fn stdout(output: &Output) -> String {
    String::from_utf8_lossy(&output.stdout).into_owned()
}

#[test]
fn a_labelled_command_prints_one_status_line_and_hides_its_output() {
    let workspace = Workspace::new("label", r#"{"rules": [{"label": "Say hello", "commands": ["echo hello"]}]}"#);

    let output = workspace.run("echo hello");

    assert!(output.status.success());
    assert_eq!(vec!["Say hello OK"], stderr(&output));
    assert_eq!("", stdout(&output));
}

#[test]
fn the_next_command_with_the_same_label_joins_the_step() {
    let workspace = Workspace::new(
        "merge",
        r#"{"rules": [{"label": "Greet", "commands": ["echo hello", "echo again"]}], "fold": ["true"]}"#,
    );

    let first = workspace.run("echo hello");
    let second = workspace.run("echo again");
    let folded = workspace.run("true");

    assert_eq!(vec!["Greet OK"], stderr(&first));
    assert!(stderr(&second).is_empty());
    assert!(stderr(&folded).is_empty());
}

#[test]
fn an_unknown_command_runs_untouched_under_a_heading_and_is_recorded_once() {
    let workspace = Workspace::new("unknown", r#"{}"#);

    let first = workspace.run("echo other");
    let second = workspace.run("echo other");

    assert_eq!("other\n", stdout(&first));
    assert_eq!(vec!["$ echo other"], stderr(&first));
    assert_eq!(vec!["$ echo other"], stderr(&second));
    assert_eq!(
        Some("[\n  \"echo other\"\n]\n".to_string()),
        workspace.file("config/tools/output-filter.unknown.json")
    );
}

#[test]
fn an_ignored_command_runs_untouched_without_a_heading() {
    let workspace = Workspace::new("ignore", r#"{"ignore": ["echo *"]}"#);

    let output = workspace.run("echo quiet");

    assert_eq!("quiet\n", stdout(&output));
    assert!(stderr(&output).is_empty());
    assert_eq!(None, workspace.file("config/tools/output-filter.unknown.json"));
}

#[test]
fn a_nested_just_call_passes_through() {
    let workspace = Workspace::new("nested", r#"{}"#);
    let fake_just = workspace.dir.join("just");
    fs::write(&fake_just, "#!/bin/sh\necho nested\n").unwrap();
    fs::set_permissions(&fake_just, fs::Permissions::from_mode(0o755)).unwrap();

    let output = workspace.run("./just some-recipe");

    assert_eq!("nested\n", stdout(&output));
    assert!(stderr(&output).is_empty());
    assert_eq!(None, workspace.file("config/tools/output-filter.unknown.json"));
}

#[test]
fn a_failing_command_exits_with_its_code_and_writes_the_fail_log() {
    let workspace = Workspace::new(
        "failure",
        r#"{"rules": [{"label": "Break things", "commands": ["echo broken; exit 3"]}]}"#,
    );

    let output = workspace.run("echo broken; exit 3");

    assert_eq!(Some(3), output.status.code());
    assert_eq!(
        vec!["Break things FAILED", "echo broken; exit 3", "| broken", "full output: justFail.log"],
        stderr(&output)
    );
    assert_eq!(Some("$ echo broken; exit 3\nbroken\n".to_string()), workspace.file("justFail.log"));
}

#[test]
fn a_failing_folded_command_is_reported_under_the_open_step() {
    let workspace = Workspace::new(
        "fold-failure",
        r#"{"rules": [{"label": "Set up", "commands": ["echo one"]}], "fold": ["false"]}"#,
    );

    workspace.run("echo one");
    let output = workspace.run("false");

    assert_eq!(Some(1), output.status.code());
    assert_eq!("Set up FAILED", stderr(&output)[0]);
}

#[test]
fn a_success_after_a_failure_removes_the_fail_log() {
    let workspace = Workspace::new(
        "stale-log",
        r#"{"rules": [{"label": "Pass", "commands": ["true"]}, {"label": "Fail", "commands": ["false"]}]}"#,
    );

    workspace.run("false");
    let had_log = workspace.file("justFail.log").is_some();
    workspace.run("true");

    assert!(had_log);
    assert_eq!(None, workspace.file("justFail.log"));
}

#[test]
fn debug_runs_every_command_untouched_and_shows_it() {
    let workspace = Workspace::new("debug", r#"{"rules": [{"label": "Say hello", "commands": ["echo hello"]}]}"#);

    let output = Command::new(BINARY)
        .current_dir(&workspace.dir)
        .env("OUTPUT_FILTER_DEBUG", "1")
        .args(["sh", "-cu", "echo hello"])
        .output()
        .unwrap();

    assert_eq!("hello\n", stdout(&output));
    assert_eq!(vec!["echo hello"], stderr(&output));
}

#[test]
fn a_broken_config_warns_and_runs_the_command_unfiltered() {
    let workspace = Workspace::new("broken", "{");

    let output = workspace.run("echo still-runs");

    assert_eq!("still-runs\n", stdout(&output));
    assert!(String::from_utf8_lossy(&output.stderr).contains("running unfiltered"));
}

#[test]
fn check_reports_the_config() {
    let workspace = Workspace::new(
        "check",
        r#"{"rules": [{"label": "A", "commands": ["x", "x"]}], "ignore": ["echo *"], "aliases": {"PHP": "php"}}"#,
    );

    let output = Command::new(BINARY)
        .current_dir(Path::new(&workspace.dir))
        .arg("--check")
        .output()
        .unwrap();

    assert!(output.status.success());
    assert_eq!(
        "output-filter: config OK - 2 labelled commands, 0 fold, 1 ignore, 1 aliases\n  listed 2 times: x\n",
        stdout(&output)
    );
}
