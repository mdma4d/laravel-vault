<?php

return [
    'address' => env('VAULT_ADDR', 'https://127.0.0.1:8200'),
    'ca_cert_path' => env('VAULT_CA_CERT_PATH'),

    // When no CA certificate is provided, set VAULT_VERIFY=true to enable
    // TLS verification. Defaults to false to preserve legacy behaviour.
    'verify' => env('VAULT_VERIFY', false),

    'token' => env('VAULT_TOKEN'),
    'role_id' => env('VAULT_ROLE_ID'),
    'secret_id' => env('VAULT_SECRET_ID'),
    'config' => env('VAULT_CONFIG'),
    'database' => env('VAULT_DATABASE'),

    'transit' => [
        'path' => env('VAULT_TRANSIT_PATH'),
        'key'  => env('VAULT_TRANSIT_KEY'),
    ],

    // Dedicated HMAC settings. When omitted, the transit settings above are
    // used as a fallback. The environment variable names are unchanged for
    // backward compatibility.
    'hmac' => [
        'path' => env('VAULT_HMAC_TRANSIT_PATH'),
        'key'  => env('VAULT_HMAC_KEY'),
    ],
];
