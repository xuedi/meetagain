// AccessDeniedProvider attack: 15 unauthenticated GETs to distinct admin URLs.
// AccessDeniedProvider trips when distinctPaths >= 8 && hits >= 15. The 16th
// request should land on the block page rather than the firewall's 403.
//
// Run via: just testAttack accessDeniedScript

import http from 'k6/http';
import { check, fail } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const REPORTS_DIR = '/reports/attack';
const SCENARIO = 'accessDeniedScript';
const BLOCK_MARKER = 'Temporarily blocked';

// Each path must redirect (302) to /en/login when accessed unauthenticated -
// only those fire AccessDeniedException and reach AccessDeniedSubscriber.
// AccessDeniedProvider trips at distinctPaths >= 8 && hits >= 15, so we use
// 15 distinct existing admin GET routes (404s would not record an event).
const ADMIN_PATHS = [
    '/en/admin/member',
    '/en/admin/events',
    '/en/admin/hosts',
    '/en/admin/locations',
    '/en/admin/cms',
    '/en/admin/system',
    '/en/admin/security/incidents',
    '/en/admin/security/rate-limiting',
    '/en/admin/security/permissions',
    '/en/admin/email/announcements',
    '/en/admin/email/blocklist',
    '/en/admin/logs/cron',
    '/en/admin/logs/access-denied',
    '/en/admin/logs/404',
    '/en/admin/logs/activity',
];

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        checks: ['rate>0.95'],
    },
};

export default function () {
    for (const path of ADMIN_PATHS) {
        http.get(`${BASE_URL}${path}`, {
            tags: { name: 'GET admin (unauth)', phase: 'pre-block' },
            responseCallback: http.expectedStatuses(200, 302, 403),
        });
    }

    const verify = http.get(`${BASE_URL}/en`, {
        tags: { name: 'GET /en', phase: 'post-block' },
        responseCallback: http.expectedStatuses(200, 403),
    });

    const blocked = check(verify, {
        'post-script request returns 403': (r) => r.status === 403,
        'post-script body contains block marker': (r) => (r.body || '').includes(BLOCK_MARKER),
    });

    if (!blocked) {
        fail(`expected block page after ${ADMIN_PATHS.length} admin probes, got ${verify.status}`);
    }
}

export function handleSummary(data) {
    const expectations = {
        scenario: SCENARIO,
        expectations: [
            { kind: 'incidentCount', triggeredBy: 'access_denied', since: 'PT5M', expected: 1 },
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
