<?php

return [
    'identifiers' => [
        'ldap' => [
            'locate_users_by' => 'mail',
        ],
        'database' => [
            'guid_column' => 'email',
            'username_column' => 'email',
        ]
    ],
    'sync_attributes' => [
        'email' => 'mail',
        'firstname' => 'givenName',
        'lastname' => 'sn'
    ]
];
