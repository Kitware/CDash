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

    protected $scopeSeparator = ' ';

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     */
    protected function getAuthUrl($state): string
    {
        $auth_url = $this->buildAuthUrlFromBase($this->getInstanceUri().$this->getAuthEndpoint(), $state);
        $auth_url .= "&acr_values=Single_Factor&prompt=login";
        return $auth_url;
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl(): string
    {
        return $this->getInstanceUri() . $this->getTokenEndpoint() . '?acr_values=Single_Factor&prompt=login';
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
     * Get the URL fragment that represents the auth endpoint for the provider.
     */
    protected function getAuthEndpoint(): string
    {
        return $this->getConfig('auth_endpoint');
    }


    /**
     * GGet the URL fragment that represents the token endpoint for the provider.
     */
    protected function getTokenEndpoint(): string
    {
        return $this->getConfig('token_endpoint');
    }


    /**
     * Get the URL fragment that represents the user endpoint for the provider.
     */
    protected function getUserEndpoint(): string
    {
        return $this->getConfig('user_endpoint');
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
        $response = $this->getHttpClient()->get($this->getInstanceUri() . $this->getUserEndpoint(), [
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
        return ['instance_uri', 'auth_endpoint', 'token_endpoint', 'user_endpoint'];
    }
}
