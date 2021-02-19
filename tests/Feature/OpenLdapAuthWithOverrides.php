<?php

namespace Tests\Feature;

use Adldap\Laravel\Facades\Resolver;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\LdapTest;

class OpenLdapAuthWithOverrides extends LdapTest
{
    protected function setUp() : void
    {
        putenv('LDAP_PROVIDER=openldap');
        parent::setUp();
    }

    public function testLdapAuthenticationCustomLocater()
    {
        putenv('LDAP_LOCATE_USERS_BY=uid');
        putenv('LDAP_GUID=mail');
        Config::set('ldap.connections.default.auto_connect', false);

        $uid = 'ricky.bobby';
        $email = 'ricky.bobby@taladega.tld';
        $credentials = ['email' => $uid, 'password' => 'shake-n-bake'];

        $user = $this->makeLdapUser([
                'objectguid' => [$this->faker->uuid],
                'cn' => ['Ricky Bobby'],
                'uid' => [$uid],
                'mail' => $email,
                'sn' => 'Bobby',
                'givenName' => 'Ricky'
        ]);

        Resolver::shouldReceive('byCredentials')
            ->once()
            ->with($credentials)
            ->andReturn($user)
            ->shouldReceive('getDatabaseIdColumn')
            ->twice()
            ->andReturn('email')
            ->shouldReceive('getDatabaseUsernameColumn')
            ->once()
            ->andReturn('email')
            ->shouldReceive('getLdapDiscoveryAttribute')
            ->once()
            ->andReturn('uid')
            ->shouldReceive('authenticate')
            ->andReturn(true);

        $this->post(route('login'), $credentials)->assertRedirect('/');
        $this->assertInstanceOf(User::class, Auth::user());
        $this->assertDatabaseHas('user', ['email' => $email]);
    }

    public function testOpenLdapUnmodifiedGuid()
    {
        putenv('LDAP_LOCATE_USERS_BY=uid');
        putenv('LDAP_GUID=mail');
        Config::set('ldap.connections.default.auto_connect', false);

        $uid = 'ricky.bobby';
        $email = 'ricky.bobby@taladega.tld';

        $user = $this->makeLdapUser([
                'objectguid' => [$this->faker->uuid],
                'cn' => ['Ricky Bobby'],
                'uid' => [$uid],
                'mail' => $email,
                'sn' => 'Bobby',
                'givenName' => 'Ricky'
        ]);

        $this->assertEquals($email, $user->getConvertedGuid());
    }
}
