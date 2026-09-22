pub struct Table {
    headers: Vec<String>,
    rows: Vec<Vec<String>>,
}

impl Table {
    pub fn new(headers: &[&str]) -> Self {
        Self {
            headers: headers.iter().map(|h| h.to_string()).collect(),
            rows: Vec::new(),
        }
    }

    pub fn row(&mut self, cells: Vec<String>) {
        self.rows.push(cells);
    }

    pub fn is_empty(&self) -> bool {
        self.rows.is_empty()
    }

    pub fn render(&self, indent: usize) -> String {
        let columns = self.headers.len();
        let mut widths: Vec<usize> = self.headers.iter().map(|h| h.chars().count()).collect();
        for row in &self.rows {
            for (i, cell) in row.iter().enumerate().take(columns) {
                widths[i] = widths[i].max(cell.chars().count());
            }
        }
        let numeric: Vec<bool> = (0..columns)
            .map(|i| {
                i > 0
                    && self.rows.iter().all(|row| {
                        row.get(i)
                            .map(|c| c.is_empty() || c == "-" || looks_numeric(c))
                            .unwrap_or(true)
                    })
            })
            .collect();

        let pad = " ".repeat(indent);
        let mut out = String::new();
        let mut line = |cells: &[String]| {
            let mut text = pad.clone();
            for (i, width) in widths.iter().enumerate() {
                let cell = cells.get(i).map(String::as_str).unwrap_or("");
                let gap = width - cell.chars().count();
                if numeric[i] {
                    text.push_str(&" ".repeat(gap));
                    text.push_str(cell);
                } else {
                    text.push_str(cell);
                    if i + 1 < columns {
                        text.push_str(&" ".repeat(gap));
                    }
                }
                if i + 1 < columns {
                    text.push_str("  ");
                }
            }
            out.push_str(text.trim_end());
            out.push('\n');
        };
        line(&self.headers);
        for row in &self.rows {
            line(row);
        }
        out
    }
}

fn looks_numeric(cell: &str) -> bool {
    let cell = cell.trim_start_matches(['+', '-']);
    cell.chars().next().is_some_and(|c| c.is_ascii_digit())
}

fn positive_zero(value: f64) -> f64 {
    if value == 0.0 {
        0.0
    } else {
        value
    }
}

pub fn num(value: f64) -> String {
    let value = positive_zero(value);
    let abs = value.abs();
    if abs >= 100.0 || value.fract() == 0.0 {
        format!("{:.0}", value)
    } else if abs >= 1.0 {
        format!("{:.1}", value)
    } else {
        format!("{:.2}", value)
    }
}

pub fn ms(value: f64) -> String {
    format!("{:.0}", positive_zero(value))
}

pub fn mb(value: f64) -> String {
    format!("{:.1}", positive_zero(value))
}

pub fn pct(ratio: f64) -> String {
    format!("{:.1}%", ratio * 100.0)
}

pub fn change(before: f64, after: f64) -> String {
    if before <= 0.0 {
        return "-".to_string();
    }
    let percent = ((after - before) / before * 100.0).round();
    format!("{:+}%", positive_zero(percent) as i64)
}

pub fn opt(value: Option<f64>, format: fn(f64) -> String) -> String {
    value.map(format).unwrap_or_else(|| "-".to_string())
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn numeric_columns_align_right() {
        let mut table = Table::new(&["route", "count"]);
        table.row(vec!["a".into(), "5".into()]);
        table.row(vec!["long_route".into(), "1234".into()]);
        assert_eq!(
            table.render(0),
            "route       count\na               5\nlong_route   1234\n"
        );
    }

    #[test]
    fn number_formats() {
        assert_eq!(num(1234.4), "1234");
        assert_eq!(num(12.34), "12.3");
        assert_eq!(num(0.123), "0.12");
        assert_eq!(num(3.0), "3");
        assert_eq!(num(Vec::<f64>::new().into_iter().sum()), "0");
        assert_eq!(pct(0.1234), "12.3%");
        assert_eq!(change(100.0, 150.0), "+50%");
        assert_eq!(change(0.0, 5.0), "-");
        assert_eq!(change(26.0, 25.9), "+0%");
    }
}
