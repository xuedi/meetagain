use serde_json::Value;
use std::collections::{BTreeMap, HashMap};
use std::sync::atomic::{AtomicUsize, Ordering};
use std::sync::Mutex;
use std::thread;
use std::time::Duration;

pub type Labels = BTreeMap<String, String>;

#[derive(Debug, Clone)]
pub struct Sample {
    pub labels: Labels,
    pub value: f64,
}

#[derive(Debug, Clone)]
pub struct Series {
    pub labels: Labels,
    pub points: Vec<(i64, f64)>,
}

pub struct Client {
    agent: ureq::Agent,
    base: String,
    token: Option<String>,
    queries: AtomicUsize,
    record: Option<Mutex<Vec<Value>>>,
}

impl Client {
    pub fn new(
        backend: &str,
        url: &str,
        token: &str,
        datasource_uid: &str,
        record: bool,
    ) -> Result<Self, String> {
        let url = url.trim_end_matches('/');
        let base = match backend {
            "prometheus" => format!("{}/api/v1", url),
            "grafana" => format!(
                "{}/api/datasources/proxy/uid/{}/api/v1",
                url, datasource_uid
            ),
            other => {
                return Err(format!(
                    "unknown BACKEND '{}', expected prometheus or grafana",
                    other
                ))
            }
        };
        let agent = ureq::Agent::new_with_config(
            ureq::Agent::config_builder()
                .http_status_as_error(false)
                .timeout_global(Some(Duration::from_secs(60)))
                .build(),
        );

        Ok(Self {
            agent,
            base,
            token: (!token.is_empty()).then(|| token.to_string()),
            queries: AtomicUsize::new(0),
            record: record.then(|| Mutex::new(Vec::new())),
        })
    }

    pub fn query_count(&self) -> usize {
        self.queries.load(Ordering::Relaxed)
    }

    pub fn recorded(&self) -> Value {
        match &self.record {
            Some(record) => Value::Array(record.lock().map(|r| r.clone()).unwrap_or_default()),
            None => Value::Null,
        }
    }

    pub fn get(&self, path: &str, params: &[(&str, String)]) -> Result<Value, String> {
        self.queries.fetch_add(1, Ordering::Relaxed);
        let url = format!("{}/{}", self.base, path);
        let mut request = self.agent.get(&url);
        for (key, value) in params {
            request = request.query(*key, value);
        }
        if let Some(token) = &self.token {
            request = request.header("Authorization", &format!("Bearer {}", token));
        }
        let mut response = request.call().map_err(|e| format!("{}: {}", url, e))?;
        let status = response.status().as_u16();
        let body = response
            .body_mut()
            .read_to_string()
            .map_err(|e| format!("{}: {}", url, e))?;
        let json: Value = serde_json::from_str(&body).map_err(|_| {
            format!(
                "{} answered HTTP {} with non-JSON: {}",
                path,
                status,
                body.chars().take(300).collect::<String>()
            )
        })?;
        if json["status"] == "error" || status >= 400 {
            let reason = json["error"]
                .as_str()
                .or(json["message"].as_str())
                .unwrap_or("unknown error");
            let query = params
                .iter()
                .find(|(k, _)| *k == "query")
                .map(|(_, q)| q.as_str())
                .unwrap_or("");
            return Err(format!(
                "HTTP {} {}: {}{}",
                status,
                path,
                reason,
                if query.is_empty() {
                    String::new()
                } else {
                    format!("\n  query: {}", query)
                }
            ));
        }
        if let Some(record) = &self.record {
            if let Ok(mut record) = record.lock() {
                let params: serde_json::Map<String, Value> = params
                    .iter()
                    .map(|(k, v)| (k.to_string(), Value::String(v.clone())))
                    .collect();
                record.push(
                    serde_json::json!({ "path": path, "params": params, "data": json["data"] }),
                );
            }
        }
        Ok(json)
    }

    pub fn instant(&self, query: &str, at: i64) -> Result<Vec<Sample>, String> {
        let json = self.get(
            "query",
            &[
                ("query", query.to_string()),
                ("time", at.to_string()),
                ("nocache", "1".to_string()),
            ],
        )?;
        parse_vector(&json["data"])
    }

    pub fn range(
        &self,
        query: &str,
        start: i64,
        end: i64,
        step: i64,
    ) -> Result<Vec<Series>, String> {
        let json = self.get(
            "query_range",
            &[
                ("query", query.to_string()),
                ("start", start.to_string()),
                ("end", end.to_string()),
                ("step", step.to_string()),
                ("nocache", "1".to_string()),
            ],
        )?;
        parse_matrix(&json["data"])
    }

    pub fn instant_many(
        &self,
        queries: &[(&str, String)],
        at: i64,
    ) -> Result<HashMap<String, Vec<Sample>>, String> {
        let results: Vec<(String, Result<Vec<Sample>, String>)> = thread::scope(|scope| {
            let handles: Vec<_> = queries
                .iter()
                .map(|(key, query)| scope.spawn(move || (key.to_string(), self.instant(query, at))))
                .collect();
            handles
                .into_iter()
                .map(|h| h.join().expect("query thread panicked"))
                .collect()
        });

        let mut map = HashMap::new();
        for (key, result) in results {
            map.insert(key, result?);
        }
        Ok(map)
    }
}

pub fn parse_vector(data: &Value) -> Result<Vec<Sample>, String> {
    match data["resultType"].as_str() {
        Some("vector") => Ok(data["result"]
            .as_array()
            .map(|items| {
                items
                    .iter()
                    .filter_map(|item| {
                        let value = parse_number(&item["value"][1])?;
                        Some(Sample {
                            labels: parse_labels(&item["metric"]),
                            value,
                        })
                    })
                    .collect()
            })
            .unwrap_or_default()),
        Some("scalar") => Ok(parse_number(&data["result"][1])
            .map(|value| {
                vec![Sample {
                    labels: Labels::new(),
                    value,
                }]
            })
            .unwrap_or_default()),
        other => Err(format!("expected an instant vector, got {:?}", other)),
    }
}

pub fn parse_matrix(data: &Value) -> Result<Vec<Series>, String> {
    if data["resultType"].as_str() != Some("matrix") {
        return Err(format!(
            "expected a matrix, got {:?}",
            data["resultType"].as_str()
        ));
    }
    Ok(data["result"]
        .as_array()
        .map(|items| {
            items
                .iter()
                .map(|item| Series {
                    labels: parse_labels(&item["metric"]),
                    points: item["values"]
                        .as_array()
                        .map(|values| {
                            values
                                .iter()
                                .filter_map(|pair| {
                                    Some((pair[0].as_f64()? as i64, parse_number(&pair[1])?))
                                })
                                .collect()
                        })
                        .unwrap_or_default(),
                })
                .collect()
        })
        .unwrap_or_default())
}

fn parse_labels(metric: &Value) -> Labels {
    metric
        .as_object()
        .map(|object| {
            object
                .iter()
                .map(|(k, v)| (k.clone(), v.as_str().unwrap_or("").to_string()))
                .collect()
        })
        .unwrap_or_default()
}

fn parse_number(value: &Value) -> Option<f64> {
    let number = value.as_str()?.parse::<f64>().ok()?;
    number.is_finite().then_some(number)
}

pub fn by_label(samples: &[Sample], label: &str) -> BTreeMap<String, f64> {
    samples
        .iter()
        .map(|s| (s.labels.get(label).cloned().unwrap_or_default(), s.value))
        .collect()
}

pub fn single(samples: &[Sample]) -> Option<f64> {
    samples.first().map(|s| s.value)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn vector_skips_nan_and_keeps_labels() {
        let data: Value =
            serde_json::from_str(include_str!("../tests/fixtures/vector_routes.json")).unwrap();
        let samples = parse_vector(&data["data"]).unwrap();
        assert_eq!(samples.len(), 2);
        assert_eq!(samples[0].labels["route"], "app_event_details");
        assert_eq!(samples[0].value, 120.5);
    }

    #[test]
    fn matrix_parses_points() {
        let data: Value =
            serde_json::from_str(include_str!("../tests/fixtures/matrix_unmatched.json")).unwrap();
        let series = parse_matrix(&data["data"]).unwrap();
        assert_eq!(series.len(), 2);
        assert_eq!(series[0].points[0], (1790000000, 3.0));
        assert_eq!(series[0].points.len(), 3);
    }

    #[test]
    fn scalar_is_a_single_unlabelled_sample() {
        let data = serde_json::json!({"resultType": "scalar", "result": [1790000000, "42"]});
        let samples = parse_vector(&data).unwrap();
        assert_eq!(samples.len(), 1);
        assert!(samples[0].labels.is_empty());
        assert_eq!(samples[0].value, 42.0);
    }

    #[test]
    fn unknown_backend_is_rejected() {
        assert!(Client::new("influx", "http://x", "", "uid", false).is_err());
    }

    #[test]
    fn grafana_backend_goes_through_the_datasource_proxy() {
        let client = Client::new("grafana", "https://grafana.example/", "t", "vm", false).unwrap();
        assert_eq!(
            client.base,
            "https://grafana.example/api/datasources/proxy/uid/vm/api/v1"
        );
    }
}
