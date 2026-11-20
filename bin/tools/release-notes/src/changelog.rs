pub fn insert(existing: &str, version: &str, date: &str, body: &str) -> Result<String, String> {
    let heading = format!("## {} - {}", version, date);
    let own = format!("## {} - ", version);
    if existing.lines().any(|line| line.starts_with(&own)) {
        return Err(format!("the changelog already has an entry for {}", version));
    }
    let entry = format!("{}\n\n{}\n", heading, body.trim_end());

    let lines: Vec<&str> = existing.lines().collect();
    match lines.iter().position(|line| line.starts_with("## ")) {
        Some(index) => {
            let mut out = lines[..index].join("\n");
            if index > 0 {
                out.push('\n');
            }
            out.push_str(&entry);
            out.push('\n');
            out.push_str(&lines[index..].join("\n"));
            out.push('\n');
            Ok(out)
        }
        None => {
            let kept = existing.trim_end();
            if kept.is_empty() {
                Ok(entry)
            } else {
                Ok(format!("{}\n\n{}", kept, entry))
            }
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn an_empty_changelog_receives_only_the_entry() {
        let out = insert("", "1.0.1", "2026-11-20", "body\n").unwrap();

        assert_eq!(out, "## 1.0.1 - 2026-11-20\n\nbody\n");
    }

    #[test]
    fn a_changelog_without_entries_gets_the_entry_appended() {
        let out = insert("# Changelog\n\nIntro line.\n", "1.0.1", "2026-11-20", "body\n").unwrap();

        assert_eq!(out, "# Changelog\n\nIntro line.\n\n## 1.0.1 - 2026-11-20\n\nbody\n");
    }

    #[test]
    fn the_entry_goes_above_the_first_existing_entry() {
        let existing = "# Changelog\n\nIntro.\n\n## 1.0.1 - 2026-11-01\n\nold\n";

        let out = insert(existing, "1.1.0", "2026-11-20", "new\n").unwrap();

        assert_eq!(
            out,
            "# Changelog\n\nIntro.\n\n## 1.1.0 - 2026-11-20\n\nnew\n\n## 1.0.1 - 2026-11-01\n\nold\n"
        );
    }

    #[test]
    fn subheadings_inside_a_body_do_not_count_as_entries() {
        let existing = "# Changelog\n\n## 1.0.1 - 2026-11-01\n\n### Fixes\n\n- old\n";

        let out = insert(existing, "1.0.2", "2026-11-20", "### Fixes\n\n- new\n").unwrap();

        assert!(out.find("## 1.0.2").unwrap() < out.find("## 1.0.1").unwrap());
    }

    #[test]
    fn a_version_already_present_refuses() {
        let existing = "# Changelog\n\n## 1.0.1 - 2026-11-01\n\nold\n";

        assert!(insert(existing, "1.0.1", "2026-11-20", "x\n").is_err());
    }
}
