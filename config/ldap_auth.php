<?php

return [
    'connection' => 'default',
    'identifiers' => [
        'ldap' => [
            'locate_users_by' => env('LDAP_LOCATE_USERS_BY', 'mail'),
            'bind_users_by'   => env('LDAP_BIND_USERS_BY', 'distinguishedname'),
        ],
        'database' => [
            'guid_column' => 'email',
            'username_column' => 'email',
        ],
    ],
    'sync_attributes' => [
        'email' => env('LDAP_EMAIL_ATTRIBUTE', 'mail'),
        'firstname' => 'givenName',
        'lastname' => 'sn',
    ],
    'logging' => [
        'enabled' => env('LDAP_LOGGING', true),
        'events' => [
            \Adldap\Laravel\Events\Importing::class                 => \Adldap\Laravel\Listeners\LogImport::class,
            \Adldap\Laravel\Events\Synchronized::class              => \Adldap\Laravel\Listeners\LogSynchronized::class,
            \Adldap\Laravel\Events\Synchronizing::class             => \Adldap\Laravel\Listeners\LogSynchronizing::class,
            \Adldap\Laravel\Events\Authenticated::class             => \Adldap\Laravel\Listeners\LogAuthenticated::class,
            \Adldap\Laravel\Events\Authenticating::class            => \Adldap\Laravel\Listeners\LogAuthentication::class,
            \Adldap\Laravel\Events\AuthenticationFailed::class      => \Adldap\Laravel\Listeners\LogAuthenticationFailure::class,
            \Adldap\Laravel\Events\AuthenticationRejected::class    => \Adldap\Laravel\Listeners\LogAuthenticationRejection::class,
            \Adldap\Laravel\Events\AuthenticationSuccessful::class  => \Adldap\Laravel\Listeners\LogAuthenticationSuccess::class,
            \Adldap\Laravel\Events\DiscoveredWithCredentials::class => \Adldap\Laravel\Listeners\LogDiscovery::class,
            \Adldap\Laravel\Events\AuthenticatedWithWindows::class  => \Adldap\Laravel\Listeners\LogWindowsAuth::class,
            \Adldap\Laravel\Events\AuthenticatedModelTrashed::class => \Adldap\Laravel\Listeners\LogTrashedModel::class,
        ],
    ],
    'rules' => [
        App\Rules\LdapFilterRules::class,
    ],
];
