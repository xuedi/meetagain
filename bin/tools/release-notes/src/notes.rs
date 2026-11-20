#[derive(Clone, Copy, Debug, PartialEq, Eq, PartialOrd, Ord)]
pub enum Level {
    Patch,
    Minor,
    Major,
}

pub struct PullRequest {
    pub number: u32,
    pub labels: Vec<String>,
}

pub struct Texts {
    pub patch: String,
    pub minor: String,
    pub major: String,
    pub maintenance_limit: usize,
}

struct Kind {
    prefix: &'static str,
    heading: &'static str,
    noun: &'static str,
    collapsible: bool,
}

const KINDS: [Kind; 11] = [
    Kind { prefix: "feature", heading: "Features", noun: "feature", collapsible: false },
    Kind { prefix: "bug", heading: "Fixes", noun: "fix", collapsible: false },
    Kind { prefix: "security", heading: "Security", noun: "security", collapsible: false },
    Kind { prefix: "perf", heading: "Performance", noun: "performance", collapsible: false },
    Kind { prefix: "plugin", heading: "Plugins", noun: "plugin", collapsible: false },
    Kind { prefix: "ui", heading: "Interface", noun: "interface", collapsible: false },
    Kind { prefix: "deps", heading: "Dependencies", noun: "dependency", collapsible: true },
    Kind { prefix: "ops", heading: "Operations", noun: "operations", collapsible: true },
    Kind { prefix: "refactor", heading: "Internal", noun: "internal", collapsible: true },
    Kind { prefix: "test", heading: "Tests", noun: "test", collapsible: true },
    Kind { prefix: "docs", heading: "Documentation", noun: "documentation", collapsible: true },
];

pub fn level_of(prs: &[PullRequest]) -> Result<Level, String> {
    if prs.is_empty() {
        return Err("no pull request merged into main since the last version".to_string());
    }
    let mut highest = Level::Patch;
    for (index, pr) in prs.iter().enumerate() {
        let mut found: Vec<Level> = Vec::new();
        for label in &pr.labels {
            match label.trim().to_lowercase().as_str() {
                "patch" => found.push(Level::Patch),
                "minor" => found.push(Level::Minor),
                "major" => found.push(Level::Major),
                _ => {}
            }
        }
        let level = match found.len() {
            0 if index == 0 => {
                return Err(format!(
                    "pull request #{} is the newest merged into main and has no patch, minor or major label",
                    pr.number
                ))
            }
            0 => Level::Patch,
            1 => found[0],
            _ => {
                return Err(format!(
                    "pull request #{} carries more than one of the patch, minor and major labels",
                    pr.number
                ))
            }
        };
        highest = highest.max(level);
    }
    Ok(highest)
}

pub fn bump(previous: &str, level: Level) -> Result<String, String> {
    let parts: Vec<&str> = previous.split('.').collect();
    let numbers: Option<Vec<u64>> = parts
        .iter()
        .map(|part| {
            let plain = !part.is_empty()
                && part.chars().all(|c| c.is_ascii_digit())
                && (part.len() == 1 || !part.starts_with('0'));
            if plain { part.parse().ok() } else { None }
        })
        .collect();
    let (major, minor, patch) = match numbers.as_deref() {
        Some([major, minor, patch]) => (*major, *minor, *patch),
        _ => return Err(format!("the previous version is not X.Y.Z: {}", previous)),
    };
    Ok(match level {
        Level::Patch => format!("{}.{}.{}", major, minor, patch + 1),
        Level::Minor => format!("{}.{}.0", major, minor + 1),
        Level::Major => format!("{}.0.0", major + 1),
    })
}

fn kind_of(subject: &str) -> Option<(usize, &str)> {
    let (word, rest) = subject.split_once(": ")?;
    let word = word.trim().to_lowercase();
    let index = KINDS.iter().position(|kind| kind.prefix == word)?;
    Some((index, rest.trim()))
}

pub fn body(subjects: &[String], level: Level, texts: &Texts, compare: Option<&str>) -> String {
    let mut sorted: Vec<Vec<String>> = vec![Vec::new(); KINDS.len() + 1];
    for subject in subjects.iter().map(|s| s.trim()).filter(|s| !s.is_empty()) {
        let (slot, text) = match kind_of(subject) {
            Some((index, text)) => (index, text),
            None => (KINDS.len(), subject),
        };
        if !text.is_empty() && !sorted[slot].iter().any(|existing| existing == text) {
            sorted[slot].push(text.to_string());
        }
    }

    let opening = match level {
        Level::Patch => &texts.patch,
        Level::Minor => &texts.minor,
        Level::Major => &texts.major,
    };
    let mut out = vec![opening.trim().to_string()];

    for (slot, bullets) in sorted.iter().enumerate() {
        if bullets.is_empty() {
            continue;
        }
        let (heading, noun, collapsible) = match KINDS.get(slot) {
            Some(kind) => (kind.heading, kind.noun, kind.collapsible),
            None => ("Other changes", "other", true),
        };
        let mut section = format!("### {}\n", heading);
        if collapsible && bullets.len() > texts.maintenance_limit {
            section.push_str(&format!("\n- {} {} changes", bullets.len(), noun));
        } else {
            for bullet in bullets {
                section.push_str(&format!("\n- {}", bullet));
            }
        }
        out.push(section);
    }

    if let Some(url) = compare {
        out.push(format!("**Full changelog:** {}", url));
    }
    out.join("\n\n") + "\n"
}

#[cfg(test)]
mod tests {
    use super::*;

    fn pr(number: u32, labels: &[&str]) -> PullRequest {
        PullRequest { number, labels: labels.iter().map(|l| l.to_string()).collect() }
    }

    fn texts(limit: usize) -> Texts {
        Texts {
            patch: "PATCH TEXT".to_string(),
            minor: "MINOR TEXT".to_string(),
            major: "MAJOR TEXT".to_string(),
            maintenance_limit: limit,
        }
    }

    fn lines(items: &[&str]) -> Vec<String> {
        items.iter().map(|s| s.to_string()).collect()
    }

    #[test]
    fn the_highest_label_wins() {
        let prs = [pr(3, &["patch"]), pr(2, &["major"]), pr(1, &["minor"])];

        assert_eq!(level_of(&prs), Ok(Level::Major));
    }

    #[test]
    fn an_unlabelled_older_pull_request_counts_as_patch() {
        let prs = [pr(3, &["minor"]), pr(2, &[]), pr(1, &["bug"])];

        assert_eq!(level_of(&prs), Ok(Level::Minor));
    }

    #[test]
    fn an_unlabelled_newest_pull_request_refuses() {
        let prs = [pr(9, &["docs"]), pr(8, &["major"])];

        let message = level_of(&prs).unwrap_err();

        assert!(message.contains("#9"));
    }

    #[test]
    fn two_level_labels_refuse_naming_the_pull_request() {
        let prs = [pr(5, &["patch"]), pr(4, &["minor", "major"])];

        let message = level_of(&prs).unwrap_err();

        assert!(message.contains("#4"));
    }

    #[test]
    fn no_pull_request_refuses() {
        assert!(level_of(&[]).is_err());
    }

    #[test]
    fn labels_match_case_insensitively() {
        assert_eq!(level_of(&[pr(1, &["Minor"])]), Ok(Level::Minor));
    }

    #[test]
    fn bumps_reset_the_lower_parts() {
        assert_eq!(bump("1.3.7", Level::Patch).unwrap(), "1.3.8");
        assert_eq!(bump("1.3.7", Level::Minor).unwrap(), "1.4.0");
        assert_eq!(bump("1.3.7", Level::Major).unwrap(), "2.0.0");
        assert_eq!(bump("1.0.0", Level::Patch).unwrap(), "1.0.1");
    }

    #[test]
    fn a_malformed_previous_version_refuses() {
        for bad in ["1.0", "1.0.0.0", "v1.0.0", "1.0.x", "01.0.0", "", "1..0"] {
            assert!(bump(bad, Level::Patch).is_err(), "{}", bad);
        }
    }

    #[test]
    fn bullets_sort_under_headings_in_table_order() {
        let subjects = lines(&["bug: fixed login", "feature: added captcha", "Security: closed a hole"]);

        let text = body(&subjects, Level::Minor, &texts(8), None);

        let features = text.find("### Features").unwrap();
        let fixes = text.find("### Fixes").unwrap();
        let security = text.find("### Security").unwrap();
        assert!(features < fixes && fixes < security);
        assert!(text.starts_with("MINOR TEXT\n\n"));
        assert!(text.contains("- added captcha"));
        assert!(!text.contains("feature:"));
    }

    #[test]
    fn unknown_and_missing_prefixes_land_in_other_changes() {
        let subjects = lines(&["added a thing", "module: reworked the tree"]);

        let text = body(&subjects, Level::Patch, &texts(8), None);

        assert!(text.contains("### Other changes"));
        assert!(text.contains("- added a thing"));
        assert!(text.contains("- module: reworked the tree"));
    }

    #[test]
    fn identical_bullets_merge() {
        let subjects = lines(&["deps: bumped symfony", "deps: bumped symfony", "ui: bumped symfony"]);

        let text = body(&subjects, Level::Patch, &texts(8), None);

        assert_eq!(text.matches("- bumped symfony").count(), 2);
    }

    #[test]
    fn a_maintenance_section_past_the_limit_collapses() {
        let subjects = lines(&["refactor: a", "refactor: b", "refactor: c"]);

        let text = body(&subjects, Level::Patch, &texts(2), None);

        assert!(text.contains("- 3 internal changes"));
        assert!(!text.contains("- a"));
    }

    #[test]
    fn a_maintenance_section_at_the_limit_stays_open() {
        let subjects = lines(&["test: a", "test: b"]);

        let text = body(&subjects, Level::Patch, &texts(2), None);

        assert!(text.contains("- a") && text.contains("- b"));
    }

    #[test]
    fn a_visible_section_never_collapses() {
        let subjects = lines(&["feature: a", "feature: b", "feature: c"]);

        let text = body(&subjects, Level::Patch, &texts(1), None);

        assert!(text.contains("- a") && text.contains("- c"));
    }

    #[test]
    fn other_changes_collapse_past_the_limit() {
        let subjects = lines(&["one", "two", "three"]);

        let text = body(&subjects, Level::Patch, &texts(2), None);

        assert!(text.contains("- 3 other changes"));
    }

    #[test]
    fn the_body_ends_with_the_compare_link() {
        let subjects = lines(&["bug: fixed"]);

        let text = body(&subjects, Level::Patch, &texts(8), Some("https://example.org/compare/1.0.0...abc"));

        assert!(text.ends_with("**Full changelog:** https://example.org/compare/1.0.0...abc\n"));
    }
}
