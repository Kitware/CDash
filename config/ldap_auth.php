<?php

return [
    'connection' => 'default',
    'identifiers' => [
        'ldap' => [
            'locate_users_by' => env('LDAP_EMAIL_ATTRIBUTE', 'mail'),
            'bind_users_by'   => env('LDAP_BIND_USERS_BY', 'distinguishedname'),
        ],
        'database' => [
            'guid_column' => 'email',
            'username_column' => 'email',
        ]
    ],
    'sync_attributes' => [
        'email' => env('LDAP_EMAIL_ATTRIBUTE', 'mail'),
        'firstname' => 'givenName',
        'lastname' => 'sn'
    ],
    'rules' => [
        App\Rules\LdapFilterRules::class
    ]
];
