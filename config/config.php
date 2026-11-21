<?php
/**
 * SasthoBazar - main application config.
 * Database credentials live in config/database.php (written by the installer).
 */
return [
    'app' => [
        'name'      => 'SasthoBazar',
        'name_bn'   => 'স্বাস্থ্যবাজার',
        // Leave null to auto-detect (e.g. "/sasthobazar"). Set manually if auto-detect fails, e.g. '/sasthobazar'
        'base_path' => null,
        // Error details on screen. They are only ever shown on localhost / *.test, never on a live domain
        // (unless debug_anywhere is true - do not do that on a public site). Set false in production anyway.
        'debug'     => true,
        'debug_anywhere' => false,
        // Public address of the site, used in emails / SMS links when there is no web request (cron). e.g. 'https://sasthobazar.com'
        'url'       => '',
        // true only when the site is behind Cloudflare / a reverse proxy that sets X-Forwarded-Proto and X-Forwarded-For
        'trust_proxy' => false,
        'timezone'  => 'Asia/Dhaka',
        'locale'    => 'bn',          // default UI language: bn | en
        'locales'   => ['bn', 'en'],
        'currency'  => 'BDT',
        'currency_symbol' => '৳',
    ],
    'session' => [
        'name'     => 'SBSESSID',
        'lifetime' => 7200,           // seconds
        'secure'   => false,          // the cookie is HTTPS-only automatically on an HTTPS request; true forces it always
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
    ],
    'uploads' => [
        'public_dir'  => 'public/uploads',
        'private_dir' => 'storage/private',
        'max_size_mb' => 5,
    ],
];
