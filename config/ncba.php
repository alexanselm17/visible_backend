<?php

return [
    // Enable receipt ingestion only after NCBA has registered the HTTPS callback.
    'enabled' => env('NCBA_ENABLED', false),
    'auto_credit' => env('NCBA_AUTO_CREDIT_ENABLED', false),
    // Set only after bank UAT confirms hashing, receipt IDs, payer phone and field mapping.
    'contract_verified' => env('NCBA_CONTRACT_VERIFIED', false),
    'require_https' => env('NCBA_REQUIRE_HTTPS', true),
    'alias' => env('NCBA_TILL_ALIAS', '906854'),
    'claim_wait_hours' => (int) env('NCBA_CLAIM_WAIT_HOURS', 48),
    'username' => env('NCBA_NOTIFICATION_USERNAME', ''),
    'password' => env('NCBA_NOTIFICATION_PASSWORD', ''),
    'secret' => env('NCBA_NOTIFICATION_SECRET', ''),
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', env('NCBA_ALLOWED_IPS', ''))))),
    'max_body_bytes' => 16384,
    'account_max_length' => (int) env('NCBA_ACCOUNT_MAX_LENGTH', 20),
    // The guide's Java example: SHA-256 hex text, then Base64 (not Base64 of raw digest).
    // Preserve original strings, spelling, spaces and decimal formatting before hashing.
    'hash_fields' => ['TransType', 'TransID', 'TransTime', 'TransAmount', 'BusinessShortCode', 'BillRefNumber', 'Mobile', 'name'],
];
