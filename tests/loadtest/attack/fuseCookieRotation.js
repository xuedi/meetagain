// FuseSecurityProvider attack: 110 GETs against random 404s with rotating
// cookie jars per iteration. Fuse trips at 100 events in 60s and returns
// BlockShortCircuit; SecurityService writes only the IP block, NO Incident
// row. logs_not_found should hold at most ~EVENTS_PER_IP_FUSE rows.
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
const REQUESTS = 110;
const BLOCK_MARKER = 'Temporarily blocked';

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
        // Fresh cookie jar per request defeats per-session blocking; only
        // the per-IP fuse can catch it.
        const jar = http.cookieJar();
        jar.clear();

        const res = http.get(`${BASE_URL}/probe-${i}-${Date.now()}`, {
            tags: { name: 'GET /probe-* (rotated cookies)', phase: 'fuse-flood' },
            jar: http.cookieJar(),
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
        'fuse tripped at or before request 110': () => blockSawAt > 0 && blockSawAt <= REQUESTS,
    });

    if (!blocked) {
        fail(`fuse did not trip; first 403 at request ${blockSawAt}`);
    }
}

export function handleSummary(data) {
    const expectations = {
        scenario: SCENARIO,
        expectations: [
            { kind: 'ipBlocked', ip: '127.0.0.1', expected: true },
            { kind: 'blockSnapshotPrimaryProvider', ip: '127.0.0.1', expected: 'fuse' },
            { kind: 'incidentCount', triggeredBy: 'fuse', since: 'PT5M', expected: 0 },
            { kind: 'logRowCount', table: 'logs_not_found', since: 'PT5M', lessThanOrEqual: FUSE_LIMIT + 10 },
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
