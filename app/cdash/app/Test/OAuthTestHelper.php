<?php

namespace CDash\Test;

use CDash\Middleware\OAuth2;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;

trait OAuthTestHelper
{
    protected $accessToken;

    public function setUp() : void
    {
        parent::setUp();

        // TODO: there is almost certainly a more Laravel-esque way of doing this
        $this->withSession([]);
        request()->setLaravelSession($this->app['session']);

        $this->accessToken = new AccessToken(['access_token' => uniqid()]);
    }

    /**
     * @return MockObject|OAuth2
     */
    protected function getSut()
    {
        $provider = $this->getProvider();

        /** @var MockObject|OAuth2 $sut */
        $sut = $this->getMockForAbstractClass(
            OAuth2::class,
            [],
            '',
            true,
            true,
            true,
            ['getProvider', 'getAccessToken']
        );

        $sut->expects($this->any())
            ->method('getProvider')
            ->willReturn($provider);

        $sut->expects($this->any())
            ->method('getAccessToken')
            ->willReturn($this->accessToken);

        $sut->setProvider($provider);

        return $sut;
    }

    /**
     * @param array $methods
     * @return MockObject|AbstractProvider
     */
    protected function getProvider(array $methods = [])
    {
        $methods = array_merge(['getResourceOwner', 'getAccessToken'], $methods);

        $resource_owner = $this->getResourceOwner();

        /** @var MockObject|AbstractProvider $provider */
        $provider = $this->getMockForAbstractClass(
            AbstractProvider::class,
            [],
            '',
            true,
            true,
            true,
            $methods
        );

        $provider->expects($this->any())
            ->method('getResourceOwner')
            ->with($this->accessToken)
            ->willReturn($resource_owner);

        $provider->expects($this->any())
            ->method('getAccessToken')
            ->willReturn($this->accessToken);

        return $provider;
    }

    /**
     * @return MockObject|ResourceOwnerInterface
     */
    protected function getResourceOwner()
    {
        /** @var MockObject|ResourceOwnerInterface $resource_owner */
        $resource_owner = $this->getMockForAbstractClass(
            ResourceOwnerInterface::class,
            [],
            '',
            true,
            true,
            true,
            ['getName']
        );

        $resource_owner->expects($this->any())
            ->method('getName')
            ->willReturn('Ricky Bobby');

        return $resource_owner;
    }
}
