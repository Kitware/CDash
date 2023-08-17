<?php

namespace Tests\Feature;

use Adldap\Laravel\Facades\Resolver;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\LdapTest;

class LdapAuthWithRulesTest extends LdapTest
{
    protected function setUp() : void
    {
        putenv('LDAP_PROVIDER=activedirectory');
        parent::setUp();
    }

    public function testLdapAuthentication()
    {
        Config::set('ldap.connections.default.auto_connect', false);
        $email = 'ricky.bobby@taladega.tld';

        $credentials = ['email' => $email, 'password' => 'shake-n-bake'];

        $user = $this->makeLdapUser([
            'objectguid' => [$this->faker->uuid],
            'cn' => ['Ricky Bobby'],
            'userprinciplename' => [$email],
            'mail' => $email,
            'sn' => 'Bobby',
            'givenName' => 'Ricky',
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
            ->andReturn('userprinciplename')
            ->shouldReceive('authenticate')
            ->once()
            ->andReturn(true);

        $this->post(route('login'), $credentials)->assertRedirect('/');
        $this::assertInstanceOf(User::class, Auth::user());
        $this->assertDatabaseHas('user', ['email' => $email]);
    }

    public function testLdapAuthenticationRulesGivenDnDoesNotMatch()
    {
        putenv('LDAP_FILTERS_ON=CN=Administrators');
        Config::set('ldap.connections.default.auto_connect', false);

        $email = 'ricky.bobby@taladega.tld';

        $credentials = ['email' => $email, 'password' => 'shake-n-bake'];

        $user = $this->makeLdapUser([
            'objectguid' => [$this->faker->uuid],
            'cn' => ['Ricky Bobby'],
            'userprinciplename' => [$email],
            'mail' => $email,
            'sn' => 'Bobby',
            'givenName' => 'Ricky',
        ]);

        $mock_ldap_resource = fopen(__FILE__, 'r');
        $mock_ldap = $user->getQuery()->getConnection();

        $mock_ldap->expects($this::once())
            ->method('search')
            ->willReturn($mock_ldap_resource);

        $mock_ldap->expects($this::once())
            ->method('countEntries')
            ->with($mock_ldap_resource)
            ->willReturn(0);

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
            ->andReturn('userprinciplename')
            ->shouldReceive('authenticate')
            ->once()
            ->andReturn(true);

        $this->post(route('login'), $credentials)
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this::assertNull(Auth::user());
        $this->assertDatabaseMissing('user', ['email' => $email]);
    }

    public function testLdapAuthenticationRulesGivenDnMatch()
    {
        putenv('LDAP_FILTERS_ON=CN=Administrators');
        Config::set('ldap.connections.default.auto_connect', false);
        $email = 'ricky.bobby@taladega.tld';

        $credentials = ['email' => $email, 'password' => 'shake-n-bake'];

        $user = $this->makeLdapUser([
            'objectguid' => [$this->faker->uuid],
            'cn' => ['Ricky Bobby'],
            'userprinciplename' => [$email],
            'mail' => $email,
            'sn' => 'Bobby',
            'givenName' => 'Ricky',
            'dn' => 'CN=Ricky Bobby,CN=Administrators,DC=hq,DC=taladega,DC=tld',
        ]);

        $mock_ldap_resource = fopen(__FILE__, 'r');
        $mock_ldap = $user->getQuery()->getConnection();

        $mock_ldap->expects($this::once())
            ->method('search')
            ->willReturn($mock_ldap_resource);

        $mock_ldap->expects($this::once())
            ->method('countEntries')
            ->with($mock_ldap_resource)
            ->willReturn(1);

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
            ->andReturn('userprinciplename')
            ->shouldReceive('authenticate')
            ->once()
            ->andReturn(true);

        $this->post(route('login'), $credentials)
            ->assertStatus(Response::HTTP_FOUND);
        $this::assertInstanceOf(User::class, Auth::user());
        $this->assertDatabaseHas('user', ['email' => $email]);
    }
}
