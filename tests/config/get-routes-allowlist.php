<?php declare(strict_types=1);

return [
    'App\\Controller\\SecurityController::verifyUserEmail' => [
        'route' => 'app_register_confirm_email',
        'reason' => 'Email-link account activation. 256-bit token (bin2hex(random_bytes(32))), 24h TTL via regcodeExpiresAt, single-use (setRegcode(null) on success), entity-bound. See architecture/security/get-routes.md exception #1.',
    ],
    'Plugin\\Multisite\\Controller\\NonLocale\\JumpLandingController::land' => [
        'route' => 'app_jump_landing',
        'reason' => 'Cross-domain SSO handoff. UriSigner HMAC-SHA256 + payload exp + jti cache-key (single-use) + target_domain check + rate limiter + audit log. See architecture/security/get-routes.md exception #1.',
    ],
];
