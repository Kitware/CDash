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
    protected $scopes = ['email', 'family_name', 'given_name'];

    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = ' ';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->getInstanceUri().'/as/authorization.oauth2/'.$this->buildAuthArguments(), $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->buildAuthUrlFromBase($this->getInstanceUri().'/as/token.oauth2/'.$this->buildTokenArguments(), "");
    }


    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['id'],
            'nickname' => $user['nickname'],
            'name'     => $user['name'],
            'email'    => $user['email'],
        ]);
    }

    protected function buildAuthArguments(): string
    {
        return "?response_type=code&client_id=".$this->getAppId()."&redirect_uri=".$this->getConfig('redirect')."&scope=".join(" ", $this->scopes)."&acr_values=Single_Factor&prompt=login";
    }

    protected function buildTokenArguments(): string
    {
        return "?response_type=code&client_id=".$this->getAppId()."&redirect_uri=".$this->getConfig('redirect')."&scope=".join(" ", $this->scopes)."&acr_values=Single_Factor&prompt=login";
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
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$token,
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