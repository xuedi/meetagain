// Login helper. Uses Symfony's standard form-login flow:
//   1) GET /en/login to get the CSRF token
//   2) POST /en/login with _username, _password, _csrf_token
// k6's default cookie jar carries the PHPSESSID across calls within the same VU.
//
// All requests carry the loadtest-bypass header so SecurityService and
// Symfony's perception of rapid login attempts don't immediately block the
// synthetic clients. Attack scripts must NOT use this helper.

import http from 'k6/http';
import { check } from 'k6';
import { withLoadtestHeaders } from './headers.js';

const CSRF_RE = /name="_csrf_token" value="([^"]+)"/;

export function login(baseUrl, user) {
    const get = http.get(`${baseUrl}/en/login`, withLoadtestHeaders());
    const csrfMatch = get.body.match(CSRF_RE);
    const csrf = csrfMatch !== null ? csrfMatch[1] : '';

    const res = http.post(
        `${baseUrl}/en/login`,
        {
            _username: user.email,
            _password: user.password,
            _csrf_token: csrf,
        },
        withLoadtestHeaders({
            redirects: 0,
            tags: { name: 'POST /en/login' },
            // Symfony's stateless CSRF (csrf.yaml) verifies via the Origin
            // header. Browsers send this automatically; k6 does not.
            headers: { Origin: baseUrl },
        }),
    );

    check(res, {
        'login returns 302': (r) => r.status === 302,
        'login redirect is not back to login': (r) => !(r.headers['Location'] || '').includes('/login'),
    });

    return res;
}
