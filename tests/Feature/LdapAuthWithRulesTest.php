<?php

namespace Tests\Feature;

use Adldap\Connections\ConnectionInterface;
use Adldap\Connections\Ldap;
use Adldap\Laravel\Facades\Adldap;
use Adldap\Laravel\Facades\Resolver;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LdapAuthWithRulesTest extends TestCase
{
    use WithFaker, DatabaseTransactions;

    protected function setUp()
    {
        // ensure that we're using LDAP, overriding whatever may be set in .env
        putenv('CDASH_AUTHENTICATION_PROVIDER=ldap');
        putenv('LDAP_PROVIDER=activedirectory');
        parent::setUp();
    }

    protected function makeLdapUser(array $attributes = [])
    {
        $mock_ldap = $this->getMockBuilder(ConnectionInterface::class)
            ->getMockForAbstractClass();

        $provider = config('ldap_auth.connection');
        $user = Adldap::getProvider($provider)->make()->user($attributes);
        $user->getQuery()->setConnection($mock_ldap);
        return $user;
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
            ->andReturn('userprinciplename')
            ->shouldReceive('authenticate')
            ->once()
            ->andReturn(true);

        $this->post(route('login'), $credentials)->assertRedirect('/');
        $this->assertInstanceOf(User::class, Auth::user());
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
            'givenName' => 'Ricky'
        ]);

        $mock_ldap_resource = fopen(__FILE__, 'r');
        $mock_ldap = $user->getQuery()->getConnection();

        $mock_ldap->expects($this->once())
            ->method('search')
            ->willReturn($mock_ldap_resource);

        $mock_ldap->expects($this->once())
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
        $this->assertNull(Auth::user());
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

        $mock_ldap->expects($this->once())
            ->method('search')
            ->willReturn($mock_ldap_resource);

        $mock_ldap->expects($this->once())
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
        $this->assertInstanceOf(User::class, Auth::user());
        $this->assertDatabaseHas('user', ['email' => $email]);
    }
}
