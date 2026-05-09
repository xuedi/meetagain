// Per-VU user journey, weighted by user type. Imported by every load scenario.
// All requests carry the loadtest-bypass header (see lib/headers.js).

import http from 'k6/http';
import { check, sleep } from 'k6';
import { login } from '../lib/auth.js';
import { withLoadtestHeaders } from '../lib/headers.js';
import { ADMIN, pickMember } from '../lib/users.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

const ANON_PUBLIC_PATHS = [
    '/en/',
    '/en/events',
    '/en/members',
];

function thinkTime() {
    sleep(0.5 + Math.random() * 1.5);
}

function anonBrowse() {
    for (const path of ANON_PUBLIC_PATHS) {
        const res = http.get(`${BASE_URL}${path}`, withLoadtestHeaders({
            tags: { name: `GET ${path}`, journey: 'anon' },
        }));
        check(res, {
            [`anon ${path} 2xx`]: (r) => r.status >= 200 && r.status < 300,
        });
        thinkTime();
    }
}

// Per-VU login state. k6 isolates VUs so module-level let is per-VU.
let memberLoggedIn = false;
let adminLoggedIn = false;

function memberBrowse() {
    if (!memberLoggedIn) {
        const member = pickMember(__VU);
        login(BASE_URL, member);
        memberLoggedIn = true;
    }
    thinkTime();

    for (const path of ANON_PUBLIC_PATHS) {
        const res = http.get(`${BASE_URL}${path}`, withLoadtestHeaders({
            tags: { name: `GET ${path}`, journey: 'member' },
        }));
        check(res, {
            [`member ${path} 2xx`]: (r) => r.status >= 200 && r.status < 300,
        });
        thinkTime();
    }

    const profile = http.get(`${BASE_URL}/en/profile`, withLoadtestHeaders({
        tags: { name: 'GET /en/profile', journey: 'member' },
    }));
    check(profile, {
        'profile reachable': (r) => r.status >= 200 && r.status < 400,
    });
}

function adminOps() {
    if (!adminLoggedIn) {
        login(BASE_URL, ADMIN);
        adminLoggedIn = true;
    }
    thinkTime();

    const adminPaths = ['/en/admin/dashboard', '/en/admin/member', '/en/admin/events'];
    for (const path of adminPaths) {
        const res = http.get(`${BASE_URL}${path}`, withLoadtestHeaders({
            tags: { name: `GET ${path}`, journey: 'admin' },
        }));
        check(res, {
            [`admin ${path} reachable`]: (r) => r.status >= 200 && r.status < 400,
        });
        thinkTime();
    }
}

export function defaultJourney() {
    const r = Math.random();
    if (r < 0.7) {
        anonBrowse();
    } else if (r < 0.95) {
        memberBrowse();
    } else {
        adminOps();
    }
}
