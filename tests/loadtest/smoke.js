// Plumbing check. Confirms k6 reaches the dev stack and the dashboard exports.
// Invoke: docker compose --profile tools run --rm loadtest run /scripts/smoke.js

import http from 'k6/http';
import { check } from 'k6';
import { withLoadtestHeaders } from './lib/headers.js';

export const options = {
    vus: 1,
    iterations: 1,
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

export default function () {
    const res = http.get(`${BASE_URL}/en/`, withLoadtestHeaders());
    check(res, {
        'frontpage returns 200': (r) => r.status === 200,
    });
}
