<?php

use CDash\Test\OAuthTestHelper;
use Illuminate\Http\RedirectResponse;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Tests\TestCase;

class OAuth2Test extends TestCase
{
    use OAuthTestHelper;



    /**
     * @throws IdentityProviderException
     */
    public function testSetEmail()
    {
        $sut = $this->getSut();

        $collection = collect([]);
        $actual = $sut->setEmail($collection);

        $this->assertSame($sut, $actual);
        $this->assertSame($collection, $sut->getEmail());
    }


    /**
     * @throws IdentityProviderException
     */
    public function testGetPrimaryEmail()
    {
        $sut = $sut = $this->getSut();
        $collection = collect([]);
        $sut->setEmail($collection);

        $this->assertEmpty($sut->getPrimaryEmail());

        $collection->push((object)['email' => 'ricky.bobby@taladega.tld']);

        $expected = 'ricky.bobby@taladega.tld';
        $actual = $sut->getPrimaryEmail();
        $this->assertEquals($expected, $actual);

        $collection->push((object)['email' => 'shake.n.bake@taladega.tld']);
        $actual = $sut->getPrimaryEmail();
        $this->assertEquals($expected, $actual);

        $email = $collection->get(1);
        $email->primary = true;

        $expected = 'shake.n.bake@taladega.tld';
        $actual = $sut->getPrimaryEmail();
        $this->assertEquals($expected, $actual);
    }

    public function testCheckState()
    {
        $sut = $this->getSut();
        $provider = $sut->getProvider();

        $expected = uniqid();
        $options = ['state' => $expected];

        $provider->getAuthorizationUrl($options);

        $actual = $sut->getState();
        $this->assertEquals($expected, $actual);
    }

    public function testAuthorization()
    {
        $sut = $this->getSut();
        $provider = $sut->getProvider();

        $response = $sut->authorization();

        $expected = $provider->getState();
        $actual = session('auth.oauth.state');
        $this->assertEquals($expected, $actual);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $headers = $response->headers;

        $expected = 'must-revalidate, no-cache, private';
        $actual = $headers->get('cache-control');
        $this->assertEquals($expected, $actual);

        $expected = 'Sat, 26 Jul 1997 05:00:00GMT';
        $actual = $headers->get('expires');
        $this->assertEquals($expected, $actual);
    }

    public function testGetOwnerName()
    {
        $sut = $this->getSut();

        $expected = 'Ricky Bobby';
        $actual = $sut->getOwnerName();
        $this->assertEquals($expected, $actual);
    }
}
