// RateLimitProvider attack: 11 failed logins. Symfony's login_throttling fires
// at 10 (TooManyLoginAttemptsAuthenticationException), LoginThrottleSubscriber
// hands it to SecurityService, RateLimitProvider returns Block immediately
// (login_throttling = instant block). The 11th login attempt should be 403'd.
//
// Run via: just testAttack rateLimitLogin

import http from 'k6/http';
import { check, fail } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const REPORTS_DIR = '/reports/attack';
const SCENARIO = 'rateLimitLogin';
const ATTEMPTS = 11;
const BLOCK_MARKER = 'Temporarily blocked';

const CSRF_RE = /name="_csrf_token" value="([^"]+)"/;

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        checks: ['rate>0.95'],
    },
};

function attemptLogin() {
    const get = http.get(`${BASE_URL}/en/login`, {
        tags: { name: 'GET /en/login' },
        responseCallback: http.expectedStatuses(200, 403),
    });
    if (get.status !== 200) {
        return get;
    }

    const csrfMatch = get.body.match(CSRF_RE);
    const csrf = csrfMatch !== null ? csrfMatch[1] : '';

    return http.post(`${BASE_URL}/en/login`, {
        _username: `attacker-${__VU}-${__ITER}@example.org`,
        _password: 'wrongPassword',
        _csrf_token: csrf,
    }, {
        redirects: 0,
        tags: { name: 'POST /en/login' },
        // Browsers send Origin automatically; Symfony's stateless CSRF requires
        // it. Without this the POST is rejected with 400 before login_throttling
        // can count the attempt. Note: NO X-Loadtest-Bypass header here -
        // the attack must hit the real limiter to fire SecurityService.
        headers: { Origin: BASE_URL },
        responseCallback: http.expectedStatuses(200, 302, 403),
    });
}

export default function () {
    for (let i = 0; i < ATTEMPTS; i++) {
        attemptLogin();
    }

    const verify = http.get(`${BASE_URL}/en`, {
        tags: { name: 'GET /en', phase: 'post-block' },
        responseCallback: http.expectedStatuses(200, 403),
    });

    const blocked = check(verify, {
        'post-spam request returns 403': (r) => r.status === 403,
        'post-spam body contains block marker': (r) => (r.body || '').includes(BLOCK_MARKER),
    });

    if (!blocked) {
        fail(`expected block page after ${ATTEMPTS} login attempts, got ${verify.status}`);
    }
}

export function handleSummary(data) {
    const expectations = {
        scenario: SCENARIO,
        expectations: [
            { kind: 'incidentCount', triggeredBy: 'rate_limit', since: 'PT5M', expected: 1 },
            { kind: 'ipBlocked', ip: '127.0.0.1', expected: true },
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
