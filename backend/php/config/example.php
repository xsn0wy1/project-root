<?php
// Copy to local.php (ignored by Git), or use HOSPITAL_* environment variables.
return [
    'app_key' => 'REPLACE_WITH_AT_LEAST_32_RANDOM_CHARACTERS',
    'base_path' => '', // For example '/hospital' when mounted under a subdirectory.
    'secure_cookies' => false, // true with HTTPS; false only for local HTTP development.
    'session_idle_seconds' => 1800,
    'session_absolute_seconds' => 28800,
    'bcrypt_cost' => 12,
    'app_url' => 'http://127.0.0.1:8091/', // Application root, including /hospital/ if mounted there. Never inferred from Host.
    'brevo_api_key' => '', // Private Brevo API key; HOSPITAL_BREVO_API_KEY also supported.
    'mail_from_email' => '', // Mailbox verified as a sender in Brevo; HOSPITAL_MAIL_FROM_EMAIL.
    'mail_from_name' => 'Hospital — Proyecto educativo',
    'mail_api_url' => 'https://api.brevo.com/v3/smtp/email', // HTTPS provider/relay; loopback HTTP is available for offline tests.
    'storage_path' => dirname(__DIR__, 3) . '/storage', // Private writable directory, outside public/.
    'document_scanner' => '', // Optional absolute path to local clamscan; configured scans fail closed.
    'route_mapping_enabled' => false, // Addresses stay local unless explicitly enabled with trusted/self-hosted services.
    'geocoder_url' => '', // For example a private Photon deployment: https://geocoder.example/api/
    'router_url' => '', // Private OSRM deployment: https://router.example/route/v1/driving
    'unmapped_duration_seconds' => 3600, // Conservative booking interval only; not a displayed ETA.
    'map_country' => 'UY',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'socket' => '',
        'name' => 'hospital_refactor',
        'user' => 'hospital_refactor_app',
        'password' => 'REPLACE_WITH_YOUR_LOCAL_DATABASE_PASSWORD',
    ],
];
