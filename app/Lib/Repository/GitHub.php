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

use GuzzleHttp\ClientInterface;

/**
 * Class GitHub
 * @package CDash\Lib\Repository
 */
class GitHub implements RepositoryInterface
{
    const BASE_URI = 'https://api.github.com';

    /** @var string $token */
    private $token;

    /** @var string $owner */
    private $owner;

    /** @var string $repo */
    private $repo;

    /** @var string $hash */
    private $hash;

    /**
     * GitHub constructor.
     * @param $token
     * @param $owner
     * @param $repo
     * @param $hash
     */
    public function __construct($token, $owner, $repo)
    {
        $this->token = $token;
        $this->owner = $owner;
        $this->repo = $repo;
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
     * @param ClientInterface $client
     * @param array $options
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function setStatus(ClientInterface $client, array $options)
    {
        $path = "/repos/{$this->owner}/{$this->repo}/statuses/{$options['commit_hash']}";
        $uri  = self::BASE_URI . $path;

        $body = array_filter($options, function ($key) {
            return in_array($key, ['state', 'context', 'description', 'target_url']);
        }, ARRAY_FILTER_USE_KEY);

        $options = [
            'headers' => [
                'Authorization' => "token {$this->token}"
            ],
            'body' => json_encode($body),
        ];

        return $client->request('POST', $uri, $options);
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
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
