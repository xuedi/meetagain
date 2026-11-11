// FuseSecurityProvider attack: 130 GETs against random 404s, each with a new
// session cookie value. Fuse trips at 100 events in 60s and returns
// BlockShortCircuit; SecurityService writes only the IP block, NO Incident
// row. logs_not_found should hold at most ~EVENTS_PER_IP_FUSE rows, well below
// the 130 an unblocked flood would write.
//
// This is the load-bearing DoS-resilience scenario.
//
// Run via: just testAttack fuseCookieRotation

import http from 'k6/http';
import { check, fail } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const REPORTS_DIR = '/reports/attack';
const SCENARIO = 'fuseCookieRotation';
const FUSE_LIMIT = 100;
const FUSE_SLACK = 10;
const REQUESTS = 130;
const BLOCK_MARKER = 'Temporarily blocked';
const SESSION_COOKIE = 'PHPSESSID';
const ASSERT_DELAY_SECONDS = 5;

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        checks: ['rate>0.95'],
    },
};

export default function () {
    let blockSawAt = -1;
    for (let i = 0; i < REQUESTS; i++) {
        // A new session cookie value per request gives every request its own
        // session key, so no per-session provider accumulates state and only
        // the per-IP fuse can catch it. Requests without a cookie would share
        // the ip: key and NotFoundProvider would block them first.
        const res = http.get(`${BASE_URL}/probe-${i}-${Date.now()}`, {
            tags: { name: 'GET /probe-* (rotated cookies)', phase: 'fuse-flood' },
            jar: new http.CookieJar(),
            cookies: { [SESSION_COOKIE]: `rotated${i}x${Date.now()}` },
            responseCallback: http.expectedStatuses(200, 403, 404),
        });

        if (blockSawAt < 0 && res.status === 403 && (res.body || '').includes(BLOCK_MARKER)) {
            blockSawAt = i + 1;
        }
    }

    const verify = http.get(`${BASE_URL}/en`, {
        tags: { name: 'GET /en', phase: 'post-fuse' },
        responseCallback: http.expectedStatuses(200, 403),
    });

    const blocked = check(verify, {
        'post-flood request returns 403': (r) => r.status === 403,
        'post-flood body contains block marker': (r) => (r.body || '').includes(BLOCK_MARKER),
        'fuse tripped within its limit': () => blockSawAt > 0 && blockSawAt <= FUSE_LIMIT + FUSE_SLACK,
    });

    if (!blocked) {
        fail(`fuse did not trip; first 403 at request ${blockSawAt}`);
    }
}

export function handleSummary(data) {
    // The row count looks back over this run only: an earlier scenario's 404
    // rows would otherwise count against the fuse limit. The few seconds on
    // top cover the gap until app:security:assert-state runs.
    const ownRun = `PT${Math.ceil(data.state.testRunDurationMs / 1000) + ASSERT_DELAY_SECONDS}S`;
    const expectations = {
        scenario: SCENARIO,
        expectations: [
            { kind: 'ipBlocked', ip: '172.18.0.1', expected: true },
            { kind: 'blockSnapshotPrimaryProvider', ip: '172.18.0.1', expected: 'fuse' },
            { kind: 'incidentCount', triggeredBy: 'fuse', since: 'PT5M', expected: 0 },
            { kind: 'logRowCount', table: 'logs_not_found', since: ownRun, lessThanOrEqual: FUSE_LIMIT + FUSE_SLACK },
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
