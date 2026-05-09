// Headers used by load scripts to bypass everything that would otherwise
// throttle synthetic traffic from a single IP:
//   - SecurityService (live blocks, fuse, per-session detection)
//   - Symfony login_throttling (security.yaml login_throttling)
//   - Symfony form rate limiters (limiter.password_reset, .registration, .support)
//
// All four are wired through src/Service/Security/LoadtestBypass.php and
// honored only in non-prod environments. Attack scripts deliberately do NOT
// include this header - they need the providers and limiters to fire.

export const LOADTEST_HEADERS = {
    'X-Loadtest-Bypass': '1',
};

export function withLoadtestHeaders(params = {}) {
    return {
        ...params,
        headers: {
            ...(params.headers || {}),
            ...LOADTEST_HEADERS,
        },
    };
}
