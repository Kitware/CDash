<?php

namespace SocialiteProviders\PingIdentity;

use GuzzleHttp\RequestOptions;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'PINGIDENTITY';

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [
        'openid',
        'profile',
        'email',
    ];

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     */
    protected function getAuthUrl($state): string
    {
        $auth_url = $this->buildAuthUrlFromBase($this->getInstanceUri().'/as/authorization.oauth2', $state);
        $auth_url .= "&acr_values=Single_Factor&prompt=login";
        return $auth_url;
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl(): string
    {
        return $this->getInstanceUri() . '/as/token.oauth2?acr_values=Single_Factor&prompt=login';
    }

    /**
     * Map the raw user array to a Socialite User instance.
     */
    protected function mapUserToObject(array $user): \Laravel\Socialite\Two\User
    {
        return (new User())->setRaw($user)->map([
            'nickname' => $user['name'],
            'name'     => $user['given_name'] . " " . $user['family_name'],
            'email'    => $user['email'],
        ]);
    }

    /**
     * Get the Instance URL for the provider.
     */
    protected function getInstanceUri(): string
    {
        return $this->getConfig('instance_uri', 'https://auth.pingone.com/');
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string $token
     * @return array<string>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->getInstanceUri() . '/idp/userinfo.openid', [
            RequestOptions::HEADERS => [
                'Authorization' => "Bearer $token",
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Additional configuration key values that may be set
     * @return array<string>
     */
    public static function additionalConfigKeys(): array
    {
        return ['instance_uri'];
    }
}
