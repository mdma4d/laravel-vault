<?php

return [
    'address' => ENV('VAULT_ADDR', 'https://127.0.0.1:8200'),
    'ca_cert_path' => ENV('VAULT_CA_CERT_PATH'),
    'token' => ENV('VAULT_TOKEN'),
    'role_id' => ENV('VAULT_ROLE_ID'),
    'secret_id' => ENV('VAULT_SECRET_ID'),
    'config' => ENV('VAULT_CONFIG'),
    'database' => ENV('VAULT_DATABASE'),
    'transit' => [
        'path' => ENV('VAULT_TRANSIT_PATH'),
        'key'  => ENV('VAULT_TRANSIT_KEY'),
     ],   
    'hmacTransit' => ENV('VAULT_HMAC_TRANSIT_PATH'),
    'hmacKeyRing' => ENV('VAULT_HMAC_KEY'),
];
