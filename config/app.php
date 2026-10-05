<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Set debug to true only on a local XAMPP machine when you need
 * detailed error output. Leave false for any shared or production host.
 */
return [
    'name' => 'StudentHub',
    'base_path' => '/student-management',
    'debug' => false,
    'session_idle_seconds' => 7200,
    'session_regenerate_seconds' => 900,
    'login_max_attempts' => 8,
    'login_lockout_seconds' => 900,

    /*
    |----------------------------------------------------------------------
    | Notifications (email / SMS)
    |----------------------------------------------------------------------
    | Both transports are DISABLED by default: until a real mailer and
    | gateway are configured, notify_student() is a silent no-op that
    | logs nothing and never blocks a request.
    |
    | No secrets belong in this file. For production, override
    | mail_from_address / sms_api_url / sms_api_key through
    | environment variables instead of committing them.
    */
    'mail_enabled' => false,
    'mail_from_address' => 'no-reply@localhost',
    'mail_from_name' => 'StudentHub',

    'sms_enabled' => false,
    'sms_api_url' => '',   // e.g. https://gateway.example.com/v1/sms/send
    'sms_api_key' => '',   // leave empty; never commit real keys

    /*
    |----------------------------------------------------------------------
    | Multi-factor authentication (reserved — not implemented)
    |----------------------------------------------------------------------
    | The centralized login (Auth::login()) has a single, well-defined
    | gate point after password verification and before the session is
    | established where a second factor for privileged roles would be
    | enforced. Keep this flag false until a COMPLETE flow exists
    | (enrollment, TOTP verification, recovery codes, session binding).
    | A partial MFA implementation must never ship.
    */
    'admin_mfa_enabled' => false,
];
