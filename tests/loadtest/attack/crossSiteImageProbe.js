// A third-party page embeds enough images pointing at missing paths to reach the
// NotFoundProvider block threshold. The browser marks each request cross-site.
// The score is still recorded, but a cross-site-only score never recommends an IP
// block: the viewer's IP stays usable for everyone else, and a client that holds a
// real session cookie is not stopped at all.
//
// Run via: just testAttack crossSiteImageProbe

import http from 'k6/http';
import { check, fail } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const REPORTS_DIR = '/reports/attack';
const SCENARIO = 'crossSiteImageProbe';
const PROBE_COUNT = 40;
const BLOCK_MARKER = 'Temporarily blocked';

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        checks: ['rate>0.95'],
    },
};

export default function () {
    const jar = new http.CookieJar();
    const warm = http.get(`${BASE_URL}/en/login`, { jar, responseCallback: http.expectedStatuses(200, 403) });
    if (warm.status !== 200) {
        fail(`could not start a session: ${warm.status}`);
    }

    for (let i = 0; i < PROBE_COUNT; i++) {
        http.get(`${BASE_URL}/missing-image-${i}-${Date.now()}.png`, {
            headers: { 'Sec-Fetch-Site': 'cross-site', 'Sec-Fetch-Dest': 'image' },
            tags: { name: 'GET /missing-image-* (cross-site)', phase: 'embed' },
            responseCallback: http.expectedStatuses(200, 403, 404),
        });
    }

    const verify = http.get(`${BASE_URL}/en`, {
        jar,
        tags: { name: 'GET /en', phase: 'post-embed' },
        responseCallback: http.expectedStatuses(200, 403),
    });

    const open = check(verify, {
        'a visitor with a session is not blocked': (r) => r.status === 200,
        'no block marker in the body': (r) => !(r.body || '').includes(BLOCK_MARKER),
    });

    if (!open) {
        fail(`expected the session visitor to stay unblocked, got ${verify.status}`);
    }
}

export function handleSummary(data) {
    const expectations = {
        scenario: SCENARIO,
        expectations: [
            { kind: 'ipBlocked', ip: '172.18.0.1', expected: false },
        ],
    };
    return {
        [`${REPORTS_DIR}/${SCENARIO}.expectations.json`]: JSON.stringify(expectations, null, 2),
        [`${REPORTS_DIR}/${SCENARIO}.k6-summary.json`]: JSON.stringify(data, null, 2),
        stdout: textSummary(data),
    };
}

function textSummary(data) {
    const checks = data.metrics.checks ? data.metrics.checks.values : { passes: 0, fails: 0 };
    return `\n${SCENARIO}: checks ${checks.passes} passed, ${checks.fails} failed\n`;
}
