// NotFoundProvider attack: 30 distinct probe URLs from one session triggers
// the block at the 30th hit (BLOCK_AT_PROBES). The 31st request should land on
// the block page (403 + admin_security.block_message_title body marker).
//
// Run via: just testAttack notFoundProbe

import http from 'k6/http';
import { check, fail } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const REPORTS_DIR = '/reports/attack';
const SCENARIO = 'notFoundProbe';
const PROBE_COUNT = 30;
const BLOCK_MARKER = 'Temporarily blocked';

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        checks: ['rate>0.95'],
    },
};

export default function () {
    let lastStatus = 0;
    for (let i = 0; i < PROBE_COUNT; i++) {
        const res = http.get(`${BASE_URL}/random-probe-${i}-${Date.now()}`, {
            tags: { name: 'GET /random-probe-*', phase: 'pre-block' },
            responseCallback: http.expectedStatuses(200, 403, 404),
        });
        lastStatus = res.status;
    }

    const verify = http.get(`${BASE_URL}/en`, {
        tags: { name: 'GET /en', phase: 'post-block' },
        responseCallback: http.expectedStatuses(200, 403),
    });

    const blocked = check(verify, {
        'post-probe request returns 403': (r) => r.status === 403,
        'post-probe body contains block marker': (r) => (r.body || '').includes(BLOCK_MARKER),
    });

    if (!blocked) {
        fail(`expected block page after ${PROBE_COUNT} probes, got ${verify.status}`);
    }
}

export function handleSummary(data) {
    const expectations = {
        scenario: SCENARIO,
        expectations: [
            { kind: 'incidentCount', triggeredBy: 'not_found', since: 'PT5M', expected: 1 },
            { kind: 'ipBlocked', ip: '172.18.0.1', expected: true },
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
