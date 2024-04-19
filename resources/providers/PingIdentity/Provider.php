<?php

namespace SocialiteProviders\PingIdentity;

use GuzzleHttp\RequestOptions;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'PINGIDENTITY';

    /**
     * {@inheritdoc}
     */
    protected $scopes = [
        'openid',
        'profile',
        'email'
    ];

    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = ' ';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
	$auth_url = $this->buildAuthUrlFromBase($this->getInstanceUri().'/as/authorization.oauth2', $state);
	$auth_url .= "&acr_values=Single_Factor&prompt=login";
	return $auth_url;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
	return $this->getInstanceUri() . '/as/token.oauth2?acr_values=Single_Factor&prompt=login';
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'nickname' => $user['name'],
            'name'     => $user['given_name'] . " " . $user['family_name'],
            'email'    => $user['email'],
        ]);
    }

    protected function getAppId(): string
    {
        return $this->getConfig('app_id');
    }

    protected function getEnvironmentId(): string
    {
        return $this->getConfig('environment_id');
    }

    protected function getInstanceUri(): string
    {
        return $this->getConfig('instance_uri', 'https://auth.pingone.com/');
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public static function additionalConfigKeys() : array
    {
        return ['instance_uri', 'environment_id', 'app_id'];
    }
}
