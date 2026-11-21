// A third-party page auto-submits login forms to this site. The posts carry no valid
// CSRF token and the browser marks them cross-site. They are refused before the login
// throttle, so they neither consume it nor reach the IP block, and a visitor with a
// real session cookie is not affected. All posts name one account: that is what fills
// the per-username throttle (20 attempts) on code that still counts them.
//
// Run via: just testAttack crossSiteLoginPosts

import http from 'k6/http';
import { check, fail } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const REPORTS_DIR = '/reports/attack';
const SCENARIO = 'crossSiteLoginPosts';
const POSTS = 30;
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

    for (let i = 0; i < POSTS; i++) {
        http.post(`${BASE_URL}/en/login`, {
            _username: 'victim@example.org',
            _password: 'x',
        }, {
            redirects: 0,
            headers: { Origin: 'https://attacker.example', 'Sec-Fetch-Site': 'cross-site' },
            tags: { name: 'POST /en/login (cross-site)', phase: 'auto-post' },
            responseCallback: http.expectedStatuses(200, 302, 403),
        });
    }

    const verify = http.get(`${BASE_URL}/en`, {
        jar,
        tags: { name: 'GET /en', phase: 'post-posts' },
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
