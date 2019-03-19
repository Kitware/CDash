<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Lib\Repository;

use Http\Adapter\Guzzle6\Client as GuzzleClient;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;

use CDash\Config;

require_once 'include/log.php';

/**
 * Class GitHub
 * @package CDash\Lib\Repository
 */
class GitHub implements RepositoryInterface
{
    const BASE_URI = 'https://api.github.com';

    /** @var string $installationId */
    private $installationId;

    /** @var string $owner */
    private $owner;

    /** @var string $repo */
    private $repo;

    /** @var string $hash */
    private $hash;

    private $apiClient;
    private $jwtBuilder;

    /**
     * GitHub constructor.
     * @param $installationId
     * @param $owner
     * @param $repo
     * @param $hash
     */
    public function __construct($installationId, $owner, $repo)
    {
        $this->installationId = $installationId;
        $this->owner = $owner;
        $this->repo = $repo;
    }

    public function setApiClient(\Github\Client $client)
    {
        $this->apiClient = $client;
    }

    public function setJwtBuilder(\Lcobucci\JWT\Builder $builder)
    {
        $this->jwtBuilder = $builder;
    }

    protected function initializeApiClient()
    {
        if (!$this->jwtBuilder) {
            $this->setJwtBuilder(new Builder());
        }

        $builder = new \Github\HttpClient\Builder(new GuzzleClient());
        $apiClient = new \Github\Client($builder, 'machine-man-preview');
        $this->setApiClient($apiClient);
    }

    public function authenticate()
    {
        $pem = Config::getInstance()->get('CDASH_GITHUB_PRIVATE_KEY');
        if (!file_exists($pem)) {
            add_log('No GitHub pem', 'GitHub::authenticate', LOG_INFO);
            return false;
        }

        $integrationId = Config::getInstance()->get('CDASH_GITHUB_APP_ID');

        $jwt = $this->jwtBuilder
            ->setIssuer($integrationId)
            ->setIssuedAt(time())
            ->setExpiration(time() + 60)
            ->sign(new Sha256(), new Key("file://{$pem}"))
            ->getToken();

        $this->apiClient->authenticate($jwt, null, \Github\Client::AUTH_JWT);

        $token = $this->apiClient->api('apps')->createInstallationToken($this->installationId);
        $this->apiClient->authenticate($token['token'], null, \Github\Client::AUTH_HTTP_TOKEN);
        return true;
    }

    /**
     * Sets the status of a commit. Possible status' are pending, success, error and
     * failure. The options argument MUST contain the following properties:
     *   - commit_hash
     *   - state
     *
     * The options argument MAY contain the following additional properties:
     *   - context (GitHub default is 'default')
     *   - target_url
     *   - description
     *
     * @see https://developer.github.com/v3/repos/statuses/ for property descriptions
     *
     * @param array $options
     */
    public function setStatus(array $options)
    {
        if (!$this->apiClient) {
            $this->initializeApiClient();
        }

        if (!$this->authenticate()) {
            return;
        }

        $commitHash = $options['commit_hash'];
        $params = array_filter($options, function ($key) {
            return in_array($key, ['state', 'context', 'description', 'target_url']);
        }, ARRAY_FILTER_USE_KEY);

        $statuses = $this->apiClient
            ->api('repo')
            ->statuses()
            ->create($this->owner, $this->repo, $commitHash, $params);
    }

    /**
     * @return string
     */
    public function getInstallationId()
    {
        return $this->installationId;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getRepository()
    {
        return $this->repo;
    }
}
