// Shared SLO thresholds. Each scenario imports and may override.
//
// http_req_duration p(95) < 500ms is the default page-render budget for an
// anonymous browse path. Scenarios that hit slower endpoints (admin lists,
// search) raise their own per-tag threshold.
//
// http_req_failed < 0.01 means under 1% of requests may have failed responses
// (network errors or 5xx). Block-page 403s in attack scenarios are NOT
// failures - they are expected responses; attack scripts use
// `expected_responses: false` for those endpoints.

export const sharedThresholds = {
    http_req_duration: ['p(95)<500'],
    http_req_failed: ['rate<0.01'],
};

export const cliffThresholds = {
    http_req_duration: [
        { threshold: 'p(95)<1000', abortOnFail: true, delayAbortEval: '30s' },
    ],
    http_req_failed: [
        { threshold: 'rate<0.01', abortOnFail: true, delayAbortEval: '30s' },
    ],
};
