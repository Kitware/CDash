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

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use CDash\Model\BuildUpdateFile;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use DateTime;
use DateTimeImmutable;
use Exception;
use Github\Api\Repository\Checks\CheckRuns;
use Github\AuthMethod;
use Github\Client as GitHubClient;
use Github\Exception\RuntimeException;
use Github\HttpClient\Builder as GitHubBuilder;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use PDO;

/**
 * Class GitHub
 */
class GitHub implements RepositoryInterface
{
    public const BASE_URI = 'https://api.github.com';

    private string $installationId = '';
    private string $owner = '';
    private string $repo = '';
    private $apiClient;
    private string $baseUrl;
    private $db;
    private bool $foundConfigureErrors = false;
    private bool $foundBuildErrors = false;
    private bool $foundTestFailures = false;
    private int $numPassed = 0;
    private int $numFailed = 0;
    private int $numPending = 0;
    private Project $project;

    private $check;

    /**
     * GitHub constructor.
     */
    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->baseUrl = config('app.url');

        $this->db = Database::getInstance();

        $this->getRepositoryInformation();

        $repositories = $this->project->GetRepositories();
        foreach ($repositories as $repo) {
            if (str_contains($repo['url'], 'github.com')) {
                $this->installationId = $repo['username'];
                break;
            }
        }

        $this->check = null;
    }

    public function setApiClient(GitHubClient $client): void
    {
        $this->apiClient = $client;
    }

    protected function initializeApiClient(): void
    {
        $builder = new GitHubBuilder();
        $apiClient = new GitHubClient($builder, 'machine-man-preview');
        $this->setApiClient($apiClient);
    }

    /**
     * Attempt to log in to the GitHub API
     */
    public function authenticate(bool $required = true): bool
    {
        if (!config('cdash.use_vcs_api')) {
            return false;
        }

        if (!$this->apiClient) {
            $this->initializeApiClient();
        }

        if ($this->installationId === '') {
            if ($required) {
                throw new Exception('Unable to find installation ID for repository');
            }
            return false;
        }

        $pem = config('cdash.github_private_key');
        if (!file_exists($pem)) {
            if ($required) {
                throw new Exception('Could not find GitHub private key');
            }
            return false;
        }
        $pem = 'file://' . $pem;

        $integrationId = config('cdash.github_app_id');
        if (is_null($integrationId)) {
            if ($required) {
                throw new Exception('GITHUB_APP_ID is not set');
            }
            return false;
        }

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::file($pem)
        );

        $now = new DateTimeImmutable();
        $jwt = $config->builder(ChainedFormatter::withUnixTimestampDates())
            ->issuedBy($integrationId)
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 minute'))
            ->getToken($config->signer(), $config->signingKey())
        ;

        $this->apiClient->authenticate($jwt->toString(), null, AuthMethod::JWT);

        try {
            $token = $this->apiClient->api('apps')->createInstallationToken($this->installationId);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
        if ($token) {
            $this->apiClient->authenticate($token['token'], null, AuthMethod::ACCESS_TOKEN);
        }
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
     * @param array<string, string> $options
     */
    public function setStatus($options): void
    {
        if (!$this->authenticate()) {
            return;
        }

        $commitHash = $options['commit_hash'];
        $params = array_filter($options, fn ($key) => in_array($key, ['state', 'context', 'description', 'target_url'], true), ARRAY_FILTER_USE_KEY);

        $statuses = $this->apiClient
            ->api('repo')
            ->statuses()
            ->create($this->owner, $this->repo, $commitHash, $params);
    }

    /**
     * Post a check to GitHub for the given commit.
     *
     * @see https://developer.github.com/v3/checks/runs/#create-a-check-run
     */
    public function createCheck(string $head_sha): void
    {
        if (!$this->authenticate()) {
            return;
        }

        $build_rows = $this->getBuildRowsForCheck($head_sha);
        $payload = $this->generateCheckPayloadFromBuildRows($build_rows, $head_sha);

        if (!$this->check) {
            $this->check = new CheckRuns($this->apiClient);
        }
        try {
            $this->check->create($this->owner, $this->repo, $payload);
        } catch (RuntimeException $e) {
            Log::warning("RunTimeException while trying to create the check.\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        }
    }

    /**
     * Query the database for information needed to generate a check
     * for a commit.
     *
     * @return array<int, array<string, int|string>>
     */
    public function getBuildRowsForCheck(string $head_sha): array
    {
        $stmt = $this->db->prepare('
            SELECT b.id, b.name, b.builderrors, b.configureerrors, b.testfailed,
                   b.done, b.starttime, bp.properties
            FROM build b
            JOIN build2update b2u ON b2u.buildid = b.id
            JOIN buildupdate bu ON bu.id = b2u.updateid
            LEFT JOIN buildproperties bp ON bp.buildid = b.id
            WHERE bu.revision = :sha');
        $this->db->execute($stmt, [':sha' => $head_sha]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->dedupeAndSortBuildRows($rows);
    }

    /**
     * Include only one row per build name: the one with the most recent start time.
     * Also sort the rows by the build name (in alphabetical order).
     *
     * @param array<int, array<string, int|string>> $rows
     *
     * @return array<int, array<string, int|string>>
     */
    public function dedupeAndSortBuildRows($rows): array
    {
        // Gather up all the rows that have non-unique build names.
        $build_names = [];
        foreach ($rows as $row) {
            $build_name = $row['name'];
            if (!array_key_exists($build_name, $build_names)) {
                $build_names[$build_name] = [];
            }
            $build_names[$build_name][] = $row;
        }
        $build_names = array_filter($build_names, fn ($k, $v) => count($k) > 1, ARRAY_FILTER_USE_BOTH);

        // Find the ids of all the older builds that should not be included
        // in our report.
        $buildids_to_remove = [];
        foreach ($build_names as $name => $builds) {
            // Find the newest build with this name.
            // This is the one we will include in the report.
            $newest_starttime = -1;
            $buildid_to_keep = -1;
            foreach ($builds as $build) {
                $starttime = strtotime((string) $build['starttime']);
                if ($starttime > $newest_starttime) {
                    $newest_starttime = $starttime;
                    $buildid_to_keep = $build['id'];
                }
            }
            // Record all the old builds that should not be included.
            foreach ($builds as $build) {
                if ($build['id'] !== $buildid_to_keep) {
                    $buildids_to_remove[] = $build['id'];
                }
            }
        }

        // Make a new array of rows that only contains the builds that will be
        // included in our report.
        $output_rows = [];
        foreach ($rows as $row) {
            if (!in_array($row['id'], $buildids_to_remove, true)) {
                $output_rows[] = $row;
            }
        }

        // Alphabetize this array by build name.
        usort($output_rows, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $output_rows;
    }

    /**
     * Create a payload to make a check from an array of build data.
     *
     * @param array<int, array<string, int|string>> $build_rows
     *
     * @return array<string, array<string, string>|string>
     */
    public function generateCheckPayloadFromBuildRows($build_rows, string $head_sha): array
    {
        // Get information about each build performed for this commit.
        $this->numPassed = 0;
        $this->numFailed = 0;
        $this->numPending = 0;
        $this->foundConfigureErrors = false;
        $this->foundBuildErrors = false;
        $this->foundTestFailures = false;
        $build_summaries = [];
        foreach ($build_rows as $row) {
            $build_summary = $this->getCheckSummaryForBuildRow($row);
            if (!is_null($build_summary)) {
                $build_summaries[] = $build_summary;
            }
        }

        // Initialize our payload with default values.
        $summary_url = "$this->baseUrl/index.php?project={$this->project->Name}&filtercount=1&showfilters=1&field1=revision&compare1=61&value1=$head_sha";

        $datetime = new DateTime();
        $now = $datetime->format(DateTime::ATOM);
        $params = [
            'name' => 'CDash',
            'head_sha' => $head_sha,
            'details_url' => $summary_url,
            'started_at' => $now,
            'status' => 'in_progress',
        ];

        // Populate payload with build results.
        $output = [];
        if ($this->numPending + $this->numFailed + $this->numPassed === 0) {
            // No builds yet.
            $output['title'] = 'Awaiting results';
            $summary = 'CDash has not parsed any results for this check yet.';
        } else {
            $text = "Build Name | Status | Details\n";
            $text .= ":-: | :-: | :-:\n";
            $text .= implode("\n", $build_summaries);
            $output['text'] = $text;
            if ($this->numPending > 0) {
                // Some builds haven't finished yet.
                $output['title'] = 'Pending';
                $summary = 'Some builds have not yet finished submitting their results to CDash.';
            } else {
                $params['status'] = 'completed';
                $params['completed_at'] = $now;
                if ($this->numFailed > 0) {
                    $params['conclusion'] = 'failure';
                    $output['title'] = 'Failure';
                    // Describe the types of problems that CDash found.
                    $types_of_errors = [];
                    if ($this->foundConfigureErrors) {
                        $types_of_errors[] = 'configure errors';
                    }
                    if ($this->foundBuildErrors) {
                        $types_of_errors[] = 'build errors';
                    }
                    if ($this->foundTestFailures) {
                        $types_of_errors[] = 'failed tests';
                    }
                    $summary = 'CDash detected ';
                    $count = count($types_of_errors);
                    if ($count === 1) {
                        $summary .= $types_of_errors[0];
                    } else {
                        $summary .= implode(', ', array_slice($types_of_errors, 0, -1));
                        $summary .= ' and ' . end($types_of_errors) . '.';
                    }
                } else {
                    $params['conclusion'] = 'success';
                    $output['title'] = 'Success';
                    $summary = 'All builds completed successfully :shipit:';
                }
            }
        }
        $output['summary'] = "[$summary]($summary_url)";
        $params['output'] = $output;

        if ((bool) config('cdash.github_always_pass')) {
            $params['completed_at'] = $now;
            $params['status'] = 'completed';
            $params['conclusion'] = 'success';
        }

        return $params;
    }

    /**
     * Generate a check summary for a given row of build data.
     *
     * @param array<string, int|string> $row
     */
    public function getCheckSummaryForBuildRow(array $row): ?string
    {
        // Check properties to see if this build should be excluded
        // from the check.
        $properties = json_decode((string) $row['properties'], true);
        if (is_array($properties) && array_key_exists('skip checks', $properties)) {
            return null;
        }

        $build_name = $row['name'];
        $build_url = "$this->baseUrl/build/{$row['id']}";
        $details_url = $build_url;
        if ($row['configureerrors'] > 0) {
            // Build with configure errors.
            $msg = "{$row['configureerrors']} configure error";
            if ($row['configureerrors'] > 1) {
                // Pluralize.
                $msg .= 's';
            }
            $details_url = "$this->baseUrl/build/{$row['id']}/configure";
            $icon = ':x:';
            $this->numFailed++;
            $this->foundConfigureErrors = true;
        } elseif ($row['builderrors'] > 0) {
            // Build with build errors.
            $msg = "{$row['builderrors']} build error";
            if ($row['builderrors'] > 1) {
                // Pluralize.
                $msg .= 's';
            }
            $details_url = "$this->baseUrl/viewBuildError.php?buildid={$row['id']}";
            $icon = ':x:';
            $this->numFailed++;
            $this->foundBuildErrors = true;
        } elseif ($row['testfailed'] > 0) {
            // Build with test failures.
            $msg = "{$row['testfailed']} failed test";
            if ($row['testfailed'] > 1) {
                // Pluralize.
                $msg .= 's';
            }
            $details_url = "$this->baseUrl/viewTest.php?buildid={$row['id']}";
            $icon = ':x:';
            $this->numFailed++;
            $this->foundTestFailures = true;
        } else {
            if ((int) $row['done'] === 1) {
                // Build completed without problems.
                $icon = ':white_check_mark:';
                $msg = 'success';
                $this->numPassed++;
            } else {
                // Build hasn't finished reporting yet.
                $icon = ':hourglass_flowing_sand:';
                $msg = 'pending';
                $this->numPending++;
                // Schedule this check to re-run when the build
                // is finished.
                PendingSubmissions::RecheckForBuildId($row['id']);
            }
        }
        $build_summary = "[$build_name]($build_url) | $icon | [$msg]($details_url)";
        return $build_summary;
    }

    /**
     * Record what changed between two commits.
     **/
    public function compareCommits(BuildUpdate $update): bool
    {
        // Get current revision (head).
        if ($update->Revision === '') {
            return false;
        }
        $head = $update->Revision;

        // Get the previous revision (base).
        $build = new Build();
        $build->Id = $update->BuildId;
        $previous_buildid = $build->GetPreviousBuildId();
        if ($previous_buildid < 1) {
            return false;
        }
        $previous_update = new BuildUpdate();
        $previous_update->BuildId = $previous_buildid;
        $previous_update->FillFromBuildId();
        if (!$previous_update->Revision) {
            return false;
        }
        $base = $previous_update->Revision;

        // Record the previous revision in the buildupdate table.
        $stmt = $this->db->prepare(
            'UPDATE buildupdate SET priorrevision = ? WHERE id = ?');
        $this->db->execute($stmt, [$base, $update->UpdateId]);

        // Return early if we are configured to not use the GitHub API.
        if (!config('cdash.use_vcs_api')) {
            return false;
        }

        // Attempt to authenticate with the GitHub API.
        // We do not check the return value of authenticate() here because
        // it is possible to compare revisions anonymously for open-source
        // projects. Authenticating when possible makes it much less likely
        // that we will get rate-limited by the API.
        $this->authenticate(false);

        // Use the GitHub API to find what changed between these two revisions.
        $commits = $this->apiClient
            ->api('repo')
            ->commits()
            ->compare($this->owner, $this->repo, $base, $head);

        // To do anything meaningful here our response needs to tell us about commits
        // and the files that changed.  Abort early if either of these pieces of
        // information are missing.
        if (!is_array($commits)
                || !array_key_exists('commits', $commits)
                || !array_key_exists('files', $commits)) {
            return false;
        }

        // Discard merge commits.  We want to assign credit to the author who did
        // the actual work, not the approver who clicked the merge button.
        foreach ($commits['commits'] as $idx => $commit) {
            if (str_contains($commit['commit']['message'], 'Merge pull request')) {
                unset($commits['commits'][$idx]);
            }
        }

        // If we still have more than one commit, we'll need to perform follow-up
        // API calls to figure out which commit was likely responsible for each
        // changed file.
        $list_of_commits = [];
        $multiple_commits = false;
        if (count($commits['commits']) > 1) {
            $multiple_commits = true;
            // Generate list of commits contained by this changeset in reverse order
            // (most recent first).
            $list_of_commits = array_reverse($commits['commits']);
        }

        // Maintain a local cache of what files were changed by each commit.
        // This prevents us from hitting the GitHub API more than necessary.
        $cached_commits = [];

        // Find the commit that changed each file.
        foreach ($commits['files'] as $modified_file) {
            if ($multiple_commits) {
                // Find the most recent commit that changed this file.
                $commit = null;

                // First check our local cache.
                foreach ($cached_commits as $sha => $files) {
                    if (in_array($modified_file['filename'], $files, true)) {
                        $idx = array_search($sha, array_column($list_of_commits, 'sha'), true);
                        $commit = $list_of_commits[$idx];
                        break;
                    }
                }

                if (is_null($commit)) {
                    // Next, check the database.
                    $stmt = $this->db->prepare(
                        'SELECT DISTINCT revision FROM updatefile
                            WHERE filename = ?');
                    $this->db->execute($stmt, [$modified_file['filename']]);
                    while ($row = $stmt->fetch()) {
                        foreach ($list_of_commits as $c) {
                            if ($row['revision'] === $c['sha']) {
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

                        $commit_array = $this->apiClient
                            ->api('repo')
                            ->commits()
                            ->show($this->owner, $this->repo, $sha);

                        if (!is_array($commit_array)
                                || !array_key_exists('files', $commit_array)) {
                            // Skip to the next commit if no list of files was returned.
                            $cached_commits[$sha] = [];
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

    public function getInstallationId(): string
    {
        return $this->installationId;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepository(): string
    {
        return $this->repo;
    }

    protected function getRepositoryInformation(): void
    {
        $url = str_replace('//', '', $this->project->CvsUrl);
        $parts = explode('/', $url);
        if (isset($parts[1])) {
            $this->owner = $parts[1];
        }
        if (isset($parts[2])) {
            $this->repo = $parts[2];
        }
    }
}
