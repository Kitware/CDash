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
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use CDash\Model\BuildUpdateFile;
use CDash\Model\Project;

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

    public function authenticate($required = true)
    {
        if (!$this->apiClient) {
            $this->initializeApiClient();
        }

        if (empty($this->installationId)) {
            if ($required) {
                throw new \Exception('Unable to find installation ID for repository');
            }
            return false;
        }

        $pem = Config::getInstance()->get('CDASH_GITHUB_PRIVATE_KEY');
        if (!file_exists($pem)) {
            if ($required) {
                throw new \Exception('Could not find GitHub private key');
            }
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
     * Record what changed between two commits.
     **/
    public function compareCommits(BuildUpdate $update, Project $project)
    {
        // Get current revision (head).
        if (empty($update->Revision)) {
            return;
        }
        $head = $update->Revision;

        // Get the previous revision (base).
        $build = new Build();
        $build->Id = $update->BuildId;
        $previous_buildid = $build->GetPreviousBuildId();
        if ($previous_buildid < 1) {
            return;
        }
        $previous_update = new BuildUpdate();
        $previous_update->BuildId = $previous_buildid;
        $previous_update->FillFromBuildId();
        if (!$previous_update->Revision) {
            return;
        }
        $base = $previous_update->Revision;

        // Record the previous revision in the buildupdate table.
        $db = Database::getInstance();
        $stmt = $db->prepare(
                'UPDATE buildupdate SET priorrevision = ? WHERE id = ?');
        $db->execute($stmt, [$base, $update->UpdateId]);

        // Attempt to authenticate with the GitHub API.
        // We do not check the return value of authenticate() here because
        // it is possible to compare revisions anonymously for open-source
        // projects. Authenticating when possible makes it much less likely
        // that we will get rate-limited by the API.
        $this->authenticate(false);

        // Connect to memcache.
        require_once 'include/memcache_functions.php';
        $config = Config::getInstance();
        $memcache_enabled = $config->get('CDASH_MEMECACHE_ENABLED');
        $memcache_prefix = $config->get('CDASH_MEMCACHE_PREFIX');
        if ($memcache_enabled) {
            list($server, $port) = $config->get('CDASH_MEMCACHE_SERVER');
            $memcache = cdash_memcache_connect($server, $port);
            // Disable memcache for this request if it fails to connect.
            if ($memcache === false) {
                $config->set('CDASH_MEMCACHE_ENABLED', false);
            }
        }

        // Check if we've memcached the difference between these two revisions.
        $commits = null;
        $diff_key = "$memcache_prefix:$project->Name:$base:$head";
        if ($memcache_enabled) {
            $cached_response = cdash_memcache_get($memcache, $diff_key);
            if ($cached_response !== false) {
                $commits = json_decode($cached_response, true);
            }
        }

        if (is_null($commits)) {
            // Use the GitHub API to find what changed between these two revisions.
            $commits = $this->apiClient
                ->api('repo')
                ->commits()
                ->compare($this->owner, $this->repo, $base, $head);
        }

        // To do anything meaningful here our response needs to tell us about commits
        // and the files that changed.  Abort early if either of these pieces of
        // information are missing.
        if (!is_array($commits) ||
                !array_key_exists('commits', $commits) ||
                !array_key_exists('files', $commits)) {
            return;
        }

        // Discard merge commits.  We want to assign credit to the author who did
        // the actual work, not the approver who clicked the merge button.
        foreach ($commits['commits'] as $idx => $commit) {
            if (strpos($commit['commit']['message'], 'Merge pull request')
                    !== false) {
                unset($commits['commits'][$idx]);
            }
        }

        // If we still have more than one commit, we'll need to perform follow-up
        // API calls to figure out which commit was likely responsible for each
        // changed file.
        $multiple_commits = false;
        if (count($commits['commits']) > 1) {
            $multiple_commits = true;
            // Generate list of commits contained by this changeset in reverse order
            // (most recent first).
            $list_of_commits = array_reverse($commits['commits']);

            // Also maintain a local cache of what files were changed by each commit.
            // This prevents us from hitting the GitHub API more than necessary.
            $cached_commits = [];
        }

        // Find the commit that changed each file.
        foreach ($commits['files'] as $modified_file) {
            if ($multiple_commits) {
                // Find the most recent commit that changed this file.
                $commit = null;

                // First check our local cache.
                foreach ($cached_commits as $sha => $files) {
                    if (in_array($modified_file['filename'], $files)) {
                        $idx = array_search($sha, array_column($list_of_commits, 'sha'));
                        $commit = $list_of_commits[$idx];
                        break;
                    }
                }

                if (is_null($commit)) {
                    // Next, check the database.
                    $stmt = $db->prepare(
                            'SELECT DISTINCT revision FROM updatefile
                            WHERE filename = ?');
                    $db->execute($stmt, [$modified_file['filename']]);
                    while ($row = $stmt->fetch()) {
                        foreach ($list_of_commits as $c) {
                            if ($row['revision'] == $c['sha']) {
                                $commit = $c;
                                break;
                            }
                        }
                        if (!is_null($commit)) {
                            break;
                        }
                    }
                }

                if (is_null($commit)) {
                    // Lastly, use the Github API to find what files this commit changed.
                    // To avoid being rate-limited, we only perform this lookup once
                    // per commit, caching the results as we go.
                    foreach ($list_of_commits as $c) {
                        $sha = $c['sha'];

                        if (array_key_exists($sha, $cached_commits)) {
                            // We already looked up this commit.
                            // Apparently it didn't modify the file we're looking for.
                            continue;
                        }

                        $commit_array = null;
                        $commit_key = "$memcache_prefix:$project->Name:$sha";
                        if ($memcache_enabled) {
                            // Check memcache if it is enabled before hitting
                            // the GitHub API.
                            $cached_response = cdash_memcache_get($memcache, $commit_key);
                            if ($cached_response !== false) {
                                $commit_array = json_decode($cached_response, true);
                            }
                        }

                        if (is_null($commit_array)) {
                            $commit_array = $this->apiClient
                                ->api('repo')
                                ->commits()
                                ->show($this->owner, $this->repo, $sha);

                            if ($memcache_enabled) {
                                // Cache this response for 24 hours.
                                cdash_memcache_set($memcache, $commit_key, json_encode($commit_array), 60 * 60 * 24);
                            }
                        }

                        if (!is_array($commit_array) ||
                                !array_key_exists('files', $commit_array)) {
                            // Skip to the next commit if no list of files was returned.
                            $cached_commits[$sha] = array();
                            continue;
                        }

                        // Locally cache what files this commit changed.
                        $cached_commits[$sha] =
                            array_column($commit_array['files'], 'filename');

                        // Check if this commit modified the file in question.
                        foreach ($commit_array['files'] as $file) {
                            if ($file['filename'] === $modified_file['filename']) {
                                $commit = $c;
                                break;
                            }
                        }
                        if (!is_null($commit)) {
                            // Stop examining commits once we find one that matches.
                            break;
                        }
                    }
                }

                if (is_null($commit)) {
                    // Skip this file if we couldn't find a commit that modified it.
                    continue;
                }
            } else {
                $commit = $commits['commits'][0];
            }

            // Record this modified file as part of the changeset.
            $updateFile = new BuildUpdateFile();
            $updateFile->Filename = $modified_file['filename'];
            $updateFile->CheckinDate = $commit['commit']['author']['date'];
            $updateFile->Author = $commit['commit']['author']['name'];
            $updateFile->Email = $commit['commit']['author']['email'];
            $updateFile->Committer = $commit['commit']['committer']['name'];
            $updateFile->CommitterEmail = $commit['commit']['committer']['email'];
            $updateFile->Log = $commit['commit']['message'];
            $updateFile->Revision = $commit['sha'];
            $updateFile->PriorRevision = $base;
            $updateFile->Status = 'UPDATED';
            $update->AddFile($updateFile);
        }

        $update->Append = true;
        $update->Insert();
        return true;
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
