use std::time::{SystemTime, UNIX_EPOCH};

pub fn now() -> i64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map(|d| d.as_secs() as i64)
        .unwrap_or(0)
}

pub fn parse_duration(input: &str) -> Option<i64> {
    let input = input.trim();
    let split = input.find(|c: char| !c.is_ascii_digit())?;
    let (number, unit) = input.split_at(split);
    let number: i64 = number.parse().ok()?;
    let seconds = match unit {
        "s" => 1,
        "m" => 60,
        "h" => 3600,
        "d" => 86_400,
        "w" => 604_800,
        _ => return None,
    };
    (number > 0).then_some(number * seconds)
}

pub fn parse_point(input: &str, now: i64) -> Result<i64, String> {
    if let Some(seconds) = parse_duration(input) {
        return Ok(now - seconds);
    }
    parse_iso(input).ok_or_else(|| {
        format!(
            "cannot read '{}' - use 30m, 24h, 7d or 2026-09-21[T14:30]",
            input
        )
    })
}

fn parse_iso(input: &str) -> Option<i64> {
    let (date, time) = match input.split_once(['T', ' ']) {
        Some((date, time)) => (date, Some(time)),
        None => (input, None),
    };
    let mut parts = date.split('-').map(|p| p.parse::<i64>());
    let (year, month, day) = (
        parts.next()?.ok()?,
        parts.next()?.ok()?,
        parts.next()?.ok()?,
    );
    if parts.next().is_some() || !(1..=12).contains(&month) || !(1..=31).contains(&day) {
        return None;
    }
    let (hour, minute) = match time {
        Some(time) => {
            let (h, m) = time.split_once(':')?;
            (h.parse::<i64>().ok()?, m.get(..2)?.parse::<i64>().ok()?)
        }
        None => (0, 0),
    };
    Some(days_from_civil(year, month, day) * 86_400 + hour * 3600 + minute * 60)
}

fn days_from_civil(year: i64, month: i64, day: i64) -> i64 {
    let y = if month <= 2 { year - 1 } else { year };
    let era = if y >= 0 { y } else { y - 399 } / 400;
    let yoe = y - era * 400;
    let mp = (month + 9) % 12;
    let doy = (153 * mp + 2) / 5 + day - 1;
    let doe = yoe * 365 + yoe / 4 - yoe / 100 + doy;
    era * 146_097 + doe - 719_468
}

fn civil_from_days(days: i64) -> (i64, i64, i64) {
    let z = days + 719_468;
    let era = if z >= 0 { z } else { z - 146_096 } / 146_097;
    let doe = z - era * 146_097;
    let yoe = (doe - doe / 1460 + doe / 36_524 - doe / 146_096) / 365;
    let doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
    let mp = (5 * doy + 2) / 153;
    let day = doy - (153 * mp + 2) / 5 + 1;
    let month = if mp < 10 { mp + 3 } else { mp - 9 };
    (yoe + era * 400 + i64::from(month <= 2), month, day)
}

pub fn format_minute(timestamp: i64) -> String {
    let (year, month, day) = civil_from_days(timestamp.div_euclid(86_400));
    let seconds = timestamp.rem_euclid(86_400);
    format!(
        "{:04}-{:02}-{:02} {:02}:{:02}",
        year,
        month,
        day,
        seconds / 3600,
        seconds % 3600 / 60
    )
}

pub fn format_short(timestamp: i64) -> String {
    format_minute(timestamp)[5..].to_string()
}

pub fn format_age(seconds: i64) -> String {
    match seconds {
        s if s < 120 => format!("{}s", s),
        s if s < 7200 => format!("{}m", s / 60),
        s if s < 172_800 => format!("{}h", s / 3600),
        s => format!("{}d", s / 86_400),
    }
}

pub fn format_span(seconds: i64) -> String {
    match seconds {
        s if s % 604_800 == 0 => format!("{}w", s / 604_800),
        s if s % 86_400 == 0 => format!("{}d", s / 86_400),
        s if s % 3600 == 0 => format!("{}h", s / 3600),
        s if s % 60 == 0 => format!("{}m", s / 60),
        s => format!("{}s", s),
    }
}

pub fn anchored_start(start: i64, end: i64, step: i64) -> i64 {
    end - (end - start) / step * step
}

pub fn auto_step(span: i64, max_rows: i64) -> i64 {
    const STEPS: [i64; 10] = [
        60, 300, 900, 1800, 3600, 10_800, 21_600, 43_200, 86_400, 604_800,
    ];
    STEPS
        .into_iter()
        .find(|step| span / step <= max_rows)
        .unwrap_or(604_800)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn durations() {
        assert_eq!(parse_duration("90m"), Some(5400));
        assert_eq!(parse_duration("24h"), Some(86_400));
        assert_eq!(parse_duration("7d"), Some(604_800));
        assert_eq!(parse_duration("0h"), None);
        assert_eq!(parse_duration("h"), None);
        assert_eq!(parse_duration("3y"), None);
    }

    #[test]
    fn iso_round_trips() {
        let ts = parse_point("2026-09-21T14:30", 0).unwrap();
        assert_eq!(format_minute(ts), "2026-09-21 14:30");
        assert_eq!(
            format_minute(parse_point("2024-02-29", 0).unwrap()),
            "2024-02-29 00:00"
        );
        assert_eq!(format_minute(0), "1970-01-01 00:00");
    }

    #[test]
    fn relative_point_counts_back_from_now() {
        assert_eq!(parse_point("1h", 10_000).unwrap(), 6400);
        assert!(parse_point("yesterday", 0).is_err());
    }

    #[test]
    fn auto_step_keeps_rows_bounded() {
        assert_eq!(auto_step(3600, 48), 300);
        assert_eq!(auto_step(86_400, 48), 1800);
        assert_eq!(auto_step(604_800, 48), 21_600);
    }

    #[test]
    fn the_grid_ends_on_the_window_end() {
        assert_eq!(anchored_start(0, 10_000, 3600), 2800);
        assert_eq!(anchored_start(0, 7200, 3600), 0);
    }

    #[test]
    fn spans_and_ages() {
        assert_eq!(format_span(86_400), "1d");
        assert_eq!(format_span(5400), "90m");
        assert_eq!(format_age(59), "59s");
        assert_eq!(format_age(4000), "66m");
    }
}
