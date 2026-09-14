use std::cmp::Reverse;
use std::collections::BTreeMap;
use std::env;
use std::ffi::OsString;
use std::fs;
use std::io::{self, BufRead, BufReader, IsTerminal, Write};
use std::os::unix::process::{CommandExt, ExitStatusExt};
use std::path::{Path, PathBuf};
use std::process::{Command, ExitCode, ExitStatus};
use std::sync::atomic::{AtomicBool, Ordering};
use std::time::{Duration, SystemTime, UNIX_EPOCH};

use serde_json::{json, Map, Value};

const TOOL: &str = "output-filter";
const DIST_FILE: &str = "config/tools/output-filter.dist.json";
const LOCAL_FILE: &str = "config/tools/output-filter.local.json";
const UNKNOWN_FILE: &str = "config/tools/output-filter.unknown.json";
const RUN_ENV: &str = "OUTPUT_FILTER_RUN";
const DEBUG_ENV: &str = "OUTPUT_FILTER_DEBUG";
const STATE_PREFIX: &str = "output-filter-run-";
const STATE_MAX_AGE: Duration = Duration::from_secs(24 * 60 * 60);

static INTERRUPTED: AtomicBool = AtomicBool::new(false);

fn main() -> ExitCode {
    let args: Vec<OsString> = env::args_os().skip(1).collect();
    if args.len() == 1 && args[0] == "--check" {
        return check(Path::new("."));
    }
    let Some(last) = args.last() else {
        eprintln!("usage: {} <shell> <shell flags> <command line>", TOOL);
        eprintln!("       {} --check", TOOL);
        return ExitCode::from(2);
    };
    let line = last.to_string_lossy().into_owned();
    let color = io::stderr().is_terminal();

    if env::var_os(DEBUG_ENV).is_some_and(|value| !value.is_empty()) {
        eprintln!("{}", paint(color, &line, "1"));
        return exec(&args);
    }
    let config = match Config::load(Path::new(".")) {
        Ok(config) => config,
        Err(error) => {
            eprintln!("{}: {} - running unfiltered", TOOL, error);
            return exec(&args);
        }
    };
    let Line::Command(command) = config.normalise(&line) else {
        return exec(&args);
    };

    let mut state = State::load(env::var(RUN_ENV).ok());
    match config.classify(&command) {
        Match::Ignore => {
            state.forget();
            exec(&args)
        }
        Match::Unknown => {
            state.forget();
            record_unknown(Path::new(UNKNOWN_FILE), &command);
            eprintln!("{}", paint(color, &format!("$ {}", command), "2"));
            exec(&args)
        }
        Match::Fold => run_step(&args, &line, &command, None, state, &config.options, color),
        Match::Label(label) => {
            let label = Some(label.to_string());
            run_step(&args, &line, &command, label, state, &config.options, color)
        }
    }
}

fn exec(args: &[OsString]) -> ExitCode {
    let error = Command::new(&args[0]).args(&args[1..]).exec();
    eprintln!("{}: cannot run {}: {}", TOOL, args[0].to_string_lossy(), error);
    ExitCode::from(127)
}

fn run_step(
    args: &[OsString],
    line: &str,
    command: &str,
    label: Option<String>,
    mut state: State,
    options: &Options,
    color: bool,
) -> ExitCode {
    let (label, continuing) = step_label(label, command, state.label.as_deref());
    let started = if continuing { state.started } else { now_ms() };
    let mut screen = Screen {
        out: io::stderr(),
        color,
        width: options.label_width,
    };
    if !continuing || color {
        if continuing {
            screen.rewind();
        }
        screen.open(&label);
    }

    install_interrupt_handler();
    let outcome = capture(args);
    let interrupted = INTERRUPTED.load(Ordering::SeqCst);
    match outcome {
        Ok((status, _)) if status.success() && !interrupted => {
            if !continuing || color {
                let elapsed = Duration::from_millis(now_ms().saturating_sub(started));
                screen.ok(elapsed, options.show_duration_from);
            }
            state.label = Some(label);
            state.started = started;
            state.save();
            if Path::new(&options.fail_log).exists() {
                let _ = fs::remove_file(&options.fail_log);
            }
            ExitCode::SUCCESS
        }
        Ok((status, output)) => {
            if continuing && !color {
                screen.open(&label);
            }
            screen.failed(interrupted);
            screen.detail(command);
            for tail_line in tail(&output, options.tail_lines) {
                screen.detail(&format!("| {}", tail_line));
            }
            let log = format!("$ {}\n{}\n", line, output.join("\n"));
            let written = match fs::write(&options.fail_log, log) {
                Ok(()) => format!("full output: {}", options.fail_log),
                Err(error) => format!("cannot write {}: {}", options.fail_log, error),
            };
            screen.detail(&written);
            state.forget();
            ExitCode::from(exit_code(status, interrupted))
        }
        Err(error) => {
            if continuing && !color {
                screen.open(&label);
            }
            screen.failed(false);
            screen.detail(&format!("cannot run {}: {}", args[0].to_string_lossy(), error));
            state.forget();
            ExitCode::from(127)
        }
    }
}

fn step_label(label: Option<String>, command: &str, open: Option<&str>) -> (String, bool) {
    match (label, open) {
        (None, Some(open)) => (open.to_string(), true),
        (None, None) => (command.to_string(), false),
        (Some(label), open) => {
            let continuing = open == Some(label.as_str());
            (label, continuing)
        }
    }
}

fn capture(args: &[OsString]) -> io::Result<(ExitStatus, Vec<String>)> {
    let (reader, writer) = io::pipe()?;
    let mut child = Command::new(&args[0])
        .args(&args[1..])
        .stdout(writer.try_clone()?)
        .stderr(writer)
        .spawn()?;
    let mut output = Vec::new();
    for line in BufReader::new(reader).split(b'\n') {
        output.push(String::from_utf8_lossy(&line?).trim_end_matches('\r').to_string());
    }
    Ok((child.wait()?, output))
}

fn tail(output: &[String], count: usize) -> Vec<&str> {
    let lines: Vec<&str> = output
        .iter()
        .map(|line| line.trim_end())
        .filter(|line| !line.is_empty())
        .collect();
    lines[lines.len().saturating_sub(count)..].to_vec()
}

fn exit_code(status: ExitStatus, interrupted: bool) -> u8 {
    match (status.code(), status.signal()) {
        (Some(0), _) if interrupted => 130,
        (Some(code), _) => u8::try_from(code).unwrap_or(1),
        (None, Some(signal)) => u8::try_from(128 + signal).unwrap_or(1),
        (None, None) => 1,
    }
}

#[derive(Debug, PartialEq)]
enum Line {
    Nested,
    Command(String),
}

#[derive(Debug, PartialEq)]
enum Match<'a> {
    Label(&'a str),
    Fold,
    Ignore,
    Unknown,
}

#[derive(Debug, PartialEq)]
enum Action {
    Label(String),
    Fold,
    Ignore,
}

#[derive(Debug)]
struct Rule {
    pattern: String,
    action: Action,
}

#[derive(Debug, PartialEq)]
struct Options {
    label_width: usize,
    tail_lines: usize,
    show_duration_from: u64,
    fail_log: String,
}

impl Default for Options {
    fn default() -> Self {
        Self {
            label_width: 48,
            tail_lines: 20,
            show_duration_from: 2,
            fail_log: "justFail.log".to_string(),
        }
    }
}

impl Options {
    fn apply(&mut self, value: &Value, at: &str) -> Result<(), String> {
        for (key, value) in as_object(value, at)? {
            let at = format!("{}.{}", at, key);
            match key.as_str() {
                "labelWidth" => self.label_width = as_number(value, &at)?,
                "tailLines" => self.tail_lines = as_number(value, &at)?,
                "showDurationFrom" => self.show_duration_from = as_number(value, &at)? as u64,
                "failLog" => self.fail_log = as_text(value, &at)?,
                _ => return Err(format!("{}: unknown option", at)),
            }
        }
        Ok(())
    }
}

#[derive(Debug, Default)]
struct Config {
    options: Options,
    aliases: Vec<(String, String)>,
    rules: Vec<Rule>,
}

impl Config {
    fn load(root: &Path) -> Result<Self, String> {
        let dist = read_json(&root.join(DIST_FILE))?
            .ok_or_else(|| format!("{} is missing", DIST_FILE))?;
        let local = read_json(&root.join(LOCAL_FILE))?;
        Self::from_json(&dist, local.as_ref())
    }

    fn from_json(dist: &Value, local: Option<&Value>) -> Result<Self, String> {
        let mut config = Config::default();
        let mut aliases = BTreeMap::new();
        config.apply(dist, DIST_FILE, &mut aliases)?;
        if let Some(local) = local {
            config.apply(local, LOCAL_FILE, &mut aliases)?;
        }
        config.aliases = aliases.into_iter().collect();
        config.aliases.sort_by_key(|(_, text)| Reverse(text.len()));
        Ok(config)
    }

    fn apply(&mut self, json: &Value, source: &str, aliases: &mut BTreeMap<String, String>) -> Result<(), String> {
        let object = json
            .as_object()
            .ok_or_else(|| format!("{}: the top level must be an object", source))?;
        for (key, value) in object {
            let at = format!("{} {}", source, key);
            match key.as_str() {
                "options" => self.options.apply(value, &at)?,
                "aliases" => {
                    for (name, text) in as_object(value, &at)? {
                        aliases.insert(name.clone(), as_text(text, &format!("{}.{}", at, name))?);
                    }
                }
                "rules" => {
                    for (index, rule) in as_array(value, &at)?.iter().enumerate() {
                        self.add_rule(rule, &format!("{}[{}]", at, index))?;
                    }
                }
                "fold" => self.add_patterns(value, &at, || Action::Fold)?,
                "ignore" => self.add_patterns(value, &at, || Action::Ignore)?,
                _ => return Err(format!("{}: unknown key", at)),
            }
        }
        Ok(())
    }

    fn add_rule(&mut self, rule: &Value, at: &str) -> Result<(), String> {
        let mut label = None;
        let mut commands = None;
        for (key, value) in as_object(rule, at)? {
            match key.as_str() {
                "label" => label = Some(as_text(value, &format!("{}.label", at))?),
                "commands" => commands = Some(as_patterns(value, &format!("{}.commands", at))?),
                _ => return Err(format!("{}.{}: unknown key", at, key)),
            }
        }
        let label = label.ok_or_else(|| format!("{}: missing \"label\"", at))?;
        let commands = commands.ok_or_else(|| format!("{}: missing \"commands\"", at))?;
        for pattern in commands {
            self.rules.push(Rule {
                pattern,
                action: Action::Label(label.clone()),
            });
        }
        Ok(())
    }

    fn add_patterns(&mut self, value: &Value, at: &str, action: impl Fn() -> Action) -> Result<(), String> {
        for pattern in as_patterns(value, at)? {
            self.rules.push(Rule {
                pattern,
                action: action(),
            });
        }
        Ok(())
    }

    fn normalise(&self, line: &str) -> Line {
        let line = line.trim();
        let program = line.split_whitespace().next().unwrap_or_default();
        if Path::new(program).file_name().is_some_and(|name| name == "just") {
            return Line::Nested;
        }
        let mut command = line.to_string();
        for (name, text) in &self.aliases {
            command = command.replace(text.as_str(), name);
        }
        Line::Command(command)
    }

    fn classify(&self, command: &str) -> Match<'_> {
        let mut best: Option<(usize, &Action)> = None;
        for rule in &self.rules {
            if !wildcard_match(&rule.pattern, command) {
                continue;
            }
            let weight = rule.pattern.len() - rule.pattern.matches('*').count();
            if best.is_none_or(|(heaviest, _)| weight >= heaviest) {
                best = Some((weight, &rule.action));
            }
        }
        match best {
            Some((_, Action::Label(label))) => Match::Label(label),
            Some((_, Action::Fold)) => Match::Fold,
            Some((_, Action::Ignore)) => Match::Ignore,
            None => Match::Unknown,
        }
    }
}

fn read_json(path: &Path) -> Result<Option<Value>, String> {
    match fs::read_to_string(path) {
        Ok(text) => serde_json::from_str(&text)
            .map(Some)
            .map_err(|error| format!("{}: {}", path.display(), error)),
        Err(error) if error.kind() == io::ErrorKind::NotFound => Ok(None),
        Err(error) => Err(format!("cannot read {}: {}", path.display(), error)),
    }
}

fn as_object<'a>(value: &'a Value, at: &str) -> Result<&'a Map<String, Value>, String> {
    value.as_object().ok_or_else(|| format!("{}: expected an object", at))
}

fn as_array<'a>(value: &'a Value, at: &str) -> Result<&'a Vec<Value>, String> {
    value.as_array().ok_or_else(|| format!("{}: expected a list", at))
}

fn as_text(value: &Value, at: &str) -> Result<String, String> {
    value
        .as_str()
        .map(str::trim)
        .filter(|text| !text.is_empty())
        .map(str::to_string)
        .ok_or_else(|| format!("{}: expected a non-empty string", at))
}

fn as_patterns(value: &Value, at: &str) -> Result<Vec<String>, String> {
    as_array(value, at)?
        .iter()
        .enumerate()
        .map(|(index, pattern)| as_text(pattern, &format!("{}[{}]", at, index)))
        .collect()
}

fn as_number(value: &Value, at: &str) -> Result<usize, String> {
    value
        .as_u64()
        .map(|number| number as usize)
        .ok_or_else(|| format!("{}: expected a whole number", at))
}

fn wildcard_match(pattern: &str, text: &str) -> bool {
    let parts: Vec<&str> = pattern.split('*').collect();
    let [first, middle @ .., last] = parts.as_slice() else {
        return pattern == text;
    };
    if text.len() < first.len() + last.len() || !text.starts_with(first) || !text.ends_with(last) {
        return false;
    }
    let mut rest = &text[first.len()..text.len() - last.len()];
    for part in middle {
        match rest.find(part) {
            Some(index) => rest = &rest[index + part.len()..],
            None => return false,
        }
    }
    true
}

struct State {
    path: Option<PathBuf>,
    label: Option<String>,
    started: u64,
}

impl State {
    fn load(run: Option<String>) -> Self {
        let path = run
            .filter(|id| !id.is_empty() && id.chars().all(|c| c.is_ascii_alphanumeric() || c == '-'))
            .map(|id| env::temp_dir().join(format!("{}{}.json", STATE_PREFIX, id)));
        let stored: Option<Value> = path
            .as_ref()
            .and_then(|path| fs::read_to_string(path).ok())
            .and_then(|text| serde_json::from_str(&text).ok());
        let label = stored
            .as_ref()
            .and_then(|value| value.get("label"))
            .and_then(Value::as_str)
            .map(str::to_string);
        let started = stored
            .as_ref()
            .and_then(|value| value.get("started"))
            .and_then(Value::as_u64)
            .unwrap_or(0);
        Self { path, label, started }
    }

    fn save(&self) {
        let Some(path) = &self.path else {
            return;
        };
        if !path.exists() {
            remove_stale_states();
        }
        let _ = fs::write(path, json!({"label": self.label, "started": self.started}).to_string());
    }

    fn forget(&mut self) {
        if self.label.is_some() {
            self.label = None;
            self.save();
        }
    }
}

fn remove_stale_states() {
    let Ok(entries) = fs::read_dir(env::temp_dir()) else {
        return;
    };
    for entry in entries.flatten() {
        if !entry.file_name().to_string_lossy().starts_with(STATE_PREFIX) {
            continue;
        }
        let stale = entry
            .metadata()
            .and_then(|metadata| metadata.modified())
            .ok()
            .and_then(|modified| modified.elapsed().ok())
            .is_some_and(|age| age > STATE_MAX_AGE);
        if stale {
            let _ = fs::remove_file(entry.path());
        }
    }
}

fn now_ms() -> u64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map(|elapsed| elapsed.as_millis() as u64)
        .unwrap_or(0)
}

fn record_unknown(path: &Path, command: &str) {
    let mut known: Vec<String> = fs::read_to_string(path)
        .ok()
        .and_then(|text| serde_json::from_str(&text).ok())
        .unwrap_or_default();
    if known.iter().any(|seen| seen == command) {
        return;
    }
    known.push(command.to_string());
    known.sort();
    if let Ok(text) = serde_json::to_string_pretty(&known) {
        let _ = fs::write(path, text + "\n");
    }
}

fn check(root: &Path) -> ExitCode {
    let config = match Config::load(root) {
        Ok(config) => config,
        Err(error) => {
            eprintln!("{}: {}", TOOL, error);
            return ExitCode::from(2);
        }
    };
    let count = |wanted: fn(&Action) -> bool| config.rules.iter().filter(|rule| wanted(&rule.action)).count();
    println!(
        "{}: config OK - {} labelled commands, {} fold, {} ignore, {} aliases",
        TOOL,
        count(|action| matches!(action, Action::Label(_))),
        count(|action| *action == Action::Fold),
        count(|action| *action == Action::Ignore),
        config.aliases.len()
    );
    let mut seen: BTreeMap<&str, usize> = BTreeMap::new();
    for rule in &config.rules {
        *seen.entry(rule.pattern.as_str()).or_default() += 1;
    }
    for (pattern, times) in seen.iter().filter(|(_, times)| **times > 1) {
        println!("  listed {} times: {}", times, pattern);
    }
    let unknown: Vec<String> = fs::read_to_string(root.join(UNKNOWN_FILE))
        .ok()
        .and_then(|text| serde_json::from_str(&text).ok())
        .unwrap_or_default();
    if !unknown.is_empty() {
        println!("  {} commands without a rule in {}", unknown.len(), UNKNOWN_FILE);
    }
    ExitCode::SUCCESS
}

fn install_interrupt_handler() {
    extern "C" fn on_interrupt(_signal: libc::c_int) {
        INTERRUPTED.store(true, Ordering::SeqCst);
    }

    unsafe {
        let mut action: libc::sigaction = std::mem::zeroed();
        action.sa_sigaction = on_interrupt as extern "C" fn(libc::c_int) as libc::sighandler_t;
        action.sa_flags = libc::SA_RESTART;
        libc::sigemptyset(&mut action.sa_mask);
        libc::sigaction(libc::SIGINT, &action, std::ptr::null_mut());
    }
}

struct Screen<W: Write> {
    out: W,
    color: bool,
    width: usize,
}

impl<W: Write> Screen<W> {
    fn rewind(&mut self) {
        let _ = write!(self.out, "\x1b[1A\r\x1b[2K");
    }

    fn open(&mut self, label: &str) {
        let dots = ".".repeat(self.width.saturating_sub(label.chars().count()).max(3));
        let _ = write!(self.out, "{} {} ", label, dots);
        let _ = self.out.flush();
    }

    fn ok(&mut self, elapsed: Duration, show_duration_from: u64) {
        let status = paint(self.color, "OK", "32");
        let duration = if elapsed.as_secs() >= show_duration_from {
            paint(self.color, &format!(" {:>4}", format_duration(elapsed)), "2")
        } else {
            String::new()
        };
        let _ = writeln!(self.out, "{}{}", status, duration);
    }

    fn failed(&mut self, interrupted: bool) {
        let text = if interrupted {
            "FAILED (interrupted)"
        } else {
            "FAILED"
        };
        let _ = writeln!(self.out, "{}", paint(self.color, text, "1;31"));
    }

    fn detail(&mut self, text: &str) {
        let _ = writeln!(self.out, "  {}", text);
    }
}

fn paint(color: bool, text: &str, code: &str) -> String {
    if color {
        format!("\x1b[{}m{}\x1b[0m", code, text)
    } else {
        text.to_string()
    }
}

fn format_duration(elapsed: Duration) -> String {
    let seconds = elapsed.as_secs();
    if seconds < 60 {
        format!("{}s", seconds)
    } else {
        format!("{}m {:02}s", seconds / 60, seconds % 60)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn config() -> Config {
        let dist = json!({
            "options": {"tailLines": 5},
            "aliases": {
                "DOCKER": "docker-compose -f compose.yml",
                "PHP": "docker-compose -f compose.yml exec php"
            },
            "rules": [
                {"label": "Stop containers", "commands": ["DOCKER down"]},
                {"label": "Enable plugins", "commands": ["PHP app:plugin enable*"]}
            ],
            "fold": ["PHP cache:clear"],
            "ignore": ["PHP bin/console *"]
        });
        let local = json!({
            "options": {"labelWidth": 30},
            "rules": [{"label": "Enable one plugin", "commands": ["PHP app:plugin enable books"]}],
            "ignore": ["DOCKER down"]
        });
        Config::from_json(&dist, Some(&local)).unwrap()
    }

    fn error(dist: Value) -> String {
        Config::from_json(&dist, None).unwrap_err()
    }

    #[test]
    fn the_overlay_adds_rules_and_overrides_single_options() {
        let config = config();

        assert_eq!(30, config.options.label_width);
        assert_eq!(5, config.options.tail_lines);
        assert_eq!(6, config.rules.len());
    }

    #[test]
    fn a_typo_in_the_config_is_an_error_not_a_silent_miss() {
        assert!(error(json!({"rule": []})).contains("rule: unknown key"));
        assert!(error(json!({"options": {"width": 3}})).contains("options.width: unknown option"));
        assert!(error(json!({"rules": [{"label": "X"}]})).contains("missing \"commands\""));
        assert!(error(json!({"rules": [{"label": "X", "command": ["y"]}]})).contains("command: unknown key"));
        assert!(error(json!({"fold": "PHP cache:clear"})).contains("expected a list"));
        assert!(error(json!({"ignore": [""]})).contains("expected a non-empty string"));
    }

    #[test]
    fn aliases_replace_the_longest_expansion_first() {
        let config = config();

        assert_eq!(
            Line::Command("PHP app:plugin enable all".to_string()),
            config.normalise("docker-compose -f compose.yml exec php app:plugin enable all")
        );
        assert_eq!(
            Line::Command("DOCKER up -d".to_string()),
            config.normalise("docker-compose -f compose.yml up -d")
        );
    }

    #[test]
    fn a_nested_just_call_is_not_a_command() {
        assert_eq!(
            Line::Nested,
            config().normalise("/usr/bin/just --justfile=/home/someone/project/justfile appAssets")
        );
    }

    #[test]
    fn the_longest_matching_pattern_wins_and_a_tie_goes_to_the_overlay() {
        let config = config();

        assert_eq!(Match::Label("Enable one plugin"), config.classify("PHP app:plugin enable books"));
        assert_eq!(Match::Label("Enable plugins"), config.classify("PHP app:plugin enable all"));
        assert_eq!(Match::Fold, config.classify("PHP cache:clear"));
        assert_eq!(Match::Ignore, config.classify("PHP bin/console debug:router"));
        assert_eq!(Match::Ignore, config.classify("DOCKER down"));
        assert_eq!(Match::Unknown, config.classify("rm -rf var/"));
    }

    #[test]
    fn a_star_matches_any_run_of_characters() {
        assert!(wildcard_match("a*c", "abbc"));
        assert!(wildcard_match("a*c", "ac"));
        assert!(wildcard_match("*", "anything"));
        assert!(wildcard_match("a*b*c", "a-b-c"));
        assert!(!wildcard_match("a*c", "ab"));
        assert!(!wildcard_match("a*b*c", "a-c-b"));
        assert!(!wildcard_match("abc", "abcd"));
    }

    #[test]
    fn a_step_continues_when_its_label_is_already_open() {
        assert_eq!(("Compile".to_string(), true), step_label(Some("Compile".to_string()), "x", Some("Compile")));
        assert_eq!(("Compile".to_string(), false), step_label(Some("Compile".to_string()), "x", Some("Other")));
        assert_eq!(("Compile".to_string(), false), step_label(Some("Compile".to_string()), "x", None));
    }

    #[test]
    fn a_folded_command_joins_the_open_step_or_stands_as_itself() {
        assert_eq!(("Compile".to_string(), true), step_label(None, "PHP cache:clear", Some("Compile")));
        assert_eq!(("PHP cache:clear".to_string(), false), step_label(None, "PHP cache:clear", None));
    }

    #[test]
    fn the_tail_skips_blank_lines_and_keeps_the_last_ones() {
        let output: Vec<String> = ["one", "   ", "", "two  ", "three"].iter().map(|line| line.to_string()).collect();

        assert_eq!(vec!["two", "three"], tail(&output, 2));
        assert_eq!(vec!["one", "two", "three"], tail(&output, 20));
    }

    #[test]
    fn exit_codes_follow_the_child() {
        assert_eq!(3, exit_code(ExitStatus::from_raw(3 << 8), false));
        assert_eq!(130, exit_code(ExitStatus::from_raw(2), true));
        assert_eq!(130, exit_code(ExitStatus::from_raw(0), true));
    }

    #[test]
    fn an_unknown_command_is_recorded_once_and_sorted() {
        let path = env::temp_dir().join(format!("output-filter-unknown-{}.json", std::process::id()));
        let _ = fs::remove_file(&path);

        record_unknown(&path, "zz last");
        record_unknown(&path, "aa first");
        record_unknown(&path, "zz last");
        let recorded = fs::read_to_string(&path).unwrap();
        fs::remove_file(&path).unwrap();

        assert_eq!("[\n  \"aa first\",\n  \"zz last\"\n]\n", recorded);
    }

    #[test]
    fn durations_switch_to_minutes_after_a_minute() {
        assert_eq!("42s", format_duration(Duration::from_secs(42)));
        assert_eq!("1m 42s", format_duration(Duration::from_secs(102)));
    }
}
