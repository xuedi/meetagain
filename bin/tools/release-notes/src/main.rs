mod changelog;
mod notes;

use std::fs;
use std::io::{self, Read};
use std::process::ExitCode;

use clap::{Parser, Subcommand};

use notes::{PullRequest, Texts};

#[derive(Parser)]
#[command(name = "release-notes", about = "Version and body of a release, and the changelog entry")]
struct Cli {
    #[command(subcommand)]
    mode: Mode,
}

#[derive(Subcommand)]
enum Mode {
    /// Read commit subjects (newest first) from stdin; print the next version, then the body.
    /// Exit 0 done, 1 refused, 3 nothing to release.
    Notes {
        #[arg(long)]
        previous: String,
        /// `<number>=<comma-separated labels>`, newest pull request first
        #[arg(long = "pr")]
        prs: Vec<String>,
        #[arg(long)]
        compare: Option<String>,
    },
    /// Read a body from stdin and insert it as the newest entry of a changelog file.
    Changelog {
        path: String,
        #[arg(long)]
        version: String,
        #[arg(long)]
        date: String,
    },
}

fn main() -> ExitCode {
    match Cli::parse().mode {
        Mode::Notes { previous, prs, compare } => run_notes(&previous, &prs, compare.as_deref()),
        Mode::Changelog { path, version, date } => run_changelog(&path, &version, &date),
    }
}

fn read_stdin() -> Result<String, String> {
    let mut text = String::new();
    io::stdin()
        .read_to_string(&mut text)
        .map_err(|e| format!("cannot read stdin: {}", e))?;
    Ok(text)
}

fn refuse(message: &str) -> ExitCode {
    eprintln!("release-notes: {}", message);
    ExitCode::from(1)
}

fn parse_pr(argument: &str) -> Result<PullRequest, String> {
    let (number, labels) = argument.split_once('=').unwrap_or((argument, ""));
    let number = number
        .trim()
        .parse()
        .map_err(|_| format!("--pr is not `number=labels`: {}", argument))?;
    let labels = labels
        .split(',')
        .map(|label| label.trim().to_string())
        .filter(|label| !label.is_empty())
        .collect();
    Ok(PullRequest { number, labels })
}

fn run_notes(previous: &str, prs: &[String], compare: Option<&str>) -> ExitCode {
    let input = match read_stdin() {
        Ok(text) => text,
        Err(message) => return refuse(&message),
    };
    let subjects: Vec<String> = input
        .lines()
        .map(|line| line.trim().to_string())
        .filter(|line| !line.is_empty())
        .collect();
    if subjects.is_empty() {
        eprintln!("release-notes: no commit since {}, nothing to release", previous);
        return ExitCode::from(3);
    }

    let mut parsed = Vec::new();
    for argument in prs {
        match parse_pr(argument) {
            Ok(pr) => parsed.push(pr),
            Err(message) => return refuse(&message),
        }
    }
    let level = match notes::level_of(&parsed) {
        Ok(level) => level,
        Err(message) => return refuse(&message),
    };
    let version = match notes::bump(previous, level) {
        Ok(version) => version,
        Err(message) => return refuse(&message),
    };

    let config = toolconfig::Config::load_or_exit("release-notes");
    let limit = match config.number("MAINTENANCE_LIMIT", 8) {
        Ok(limit) => limit,
        Err(message) => {
            eprintln!("config error: {}", message);
            return ExitCode::from(2);
        }
    };
    let texts = Texts {
        patch: config.require("PATCH"),
        minor: config.require("MINOR"),
        major: config.require("MAJOR"),
        maintenance_limit: limit,
    };

    println!("{}", version);
    print!("{}", notes::body(&subjects, level, &texts, compare));
    ExitCode::SUCCESS
}

fn run_changelog(path: &str, version: &str, date: &str) -> ExitCode {
    let body = match read_stdin() {
        Ok(text) => text,
        Err(message) => return refuse(&message),
    };
    let existing = match fs::read_to_string(path) {
        Ok(text) => text,
        Err(e) => return refuse(&format!("cannot read {}: {}", path, e)),
    };
    let updated = match changelog::insert(&existing, version, date, &body) {
        Ok(text) => text,
        Err(message) => return refuse(&message),
    };
    match fs::write(path, updated) {
        Ok(()) => ExitCode::SUCCESS,
        Err(e) => refuse(&format!("cannot write {}: {}", path, e)),
    }
}
