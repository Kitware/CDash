<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Configure;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;
use CDash\Model\Project;
use CDash\ServiceContainer;
use Illuminate\Support\Facades\Log;
use PDO;

class RepositoryUtils
{
    /** Return the source directory for a source file */
    public static function get_source_dir($projectid, $projecturl, $file_path)
    {
        if (!is_numeric($projectid)) {
            return;
        }

        $service = ServiceContainer::getInstance();
        $project = $service->get(Project::class);
        $project->Id = $projectid;
        $project->Fill();
        if (is_null($project->CvsViewerType)) {
            return;
        }
        $cvsviewertype = strtolower($project->CvsViewerType);

        $target_fn = $cvsviewertype . '_get_source_dir';

        if (method_exists(self::class, $target_fn)) {
            return self::$target_fn($projecturl, $file_path);
        }
    }

    /** Extract the source directory from a Github URL and a full path to
     * a source file.  This only works properly if the source dir's name matches
     * the repo's name, ie it was not renamed as it was cloned.
     **/
    public static function github_get_source_dir($projecturl, $file_path): string
    {
        $repo_name = basename($projecturl);
        $offset = stripos($file_path, $repo_name);
        if ($offset === false) {
            return '/.../';
        }
        $offset += strlen($repo_name);
        return substr($file_path, 0, $offset);
    }

    /** Return the GitHub diff URL */
    public static function get_github_diff_url($projecturl, $directory, $file, $revision)
    {
        if (empty($directory) && empty($file) && empty($revision)) {
            return;
        }

        // set a reasonable default revision if none was specified
        if (empty($revision)) {
            $revision = 'master';
        }

        $directory = trim($directory, '/');

        $diff_url = "$projecturl/blob/$revision/";
        $diff_url .= "$directory/$file";
        return make_cdash_url($diff_url);
    }

    /** Return the GitLab diff URL */
    public static function get_gitlab_diff_url($projecturl, $directory, $file, $revision): string
    {
        // Since GitLab supports arbitrarily nested groups, there is a `/-/`
        // component to start per-project resources.
        if ($revision !== '') {
            $diff_url = $projecturl . '/-/commit/' . $revision;
        } elseif ($file !== '') {
            $diff_url = $projecturl . '/-/blob/master/';
            if ($directory !== '') {
                $diff_url .= $directory . '/';
            }
            $diff_url .= $file;
        } else {
            return '';
        }
        return make_cdash_url($diff_url);
    }

    /** Get the diff url based on the type of viewer */
    public static function get_diff_url($projectid, $projecturl, $directory, $file, $revision = '')
    {
        if (!is_numeric($projectid)) {
            return;
        }

        $service = ServiceContainer::getInstance();
        $project = $service->get(Project::class);
        $project->Id = $projectid;
        $project->Fill();

        $cvsviewertype = strtolower($project->CvsViewerType ?? '');
        $difffunction = 'get_' . $cvsviewertype . '_diff_url';

        if (method_exists(self::class, $difffunction)) {
            return self::$difffunction($projecturl, $directory, $file, $revision);
        } else {
            // default is github
            return self::get_github_diff_url($projecturl, $directory, $file, $revision);
        }
    }

    /** Return the GitHub revision URL */
    public static function get_github_revision_url($projecturl, $revision, $priorrevision): string
    {
        if ($priorrevision) {
            $revision_url = "$projecturl/compare/$priorrevision...$revision";
        } else {
            $revision_url = "$projecturl/commit/$revision";
        }
        return make_cdash_url($revision_url);
    }

    /** Return the GitLab revision URL */
    public static function get_gitlab_revision_url($projecturl, $revision, $priorrevision): string
    {
        return self::get_github_revision_url($projecturl, $revision, $priorrevision);
    }

    /** Return the global revision URL (not file based) for a repository */
    public static function get_revision_url($projectid, $revision, $priorrevision)
    {
        if (!is_numeric($projectid)) {
            return;
        }

        $db = Database::getInstance();
        $project = $db->executePreparedSingleRow('SELECT cvsviewertype, cvsurl FROM project WHERE id=?', [intval($projectid)]);
        $projecturl = $project['cvsurl'];
        $cvsviewertype = strtolower($project['cvsviewertype'] ?? '');
        $cvsviewerurl = $project['cvsurl'] ?? '';

        if ($cvsviewertype === '' || $cvsviewerurl === '') {
            return '';
        }

        $revisionfunction = 'get_' . $cvsviewertype . '_revision_url';

        if (method_exists(self::class, $revisionfunction)) {
            return self::$revisionfunction($projecturl, $revision, $priorrevision);
        } else {
            // default is github
            return self::get_github_revision_url($projecturl, $revision, $priorrevision);
        }
    }

    public static function linkify_compiler_output($projecturl, $source_dir, $revision, $compiler_output): string
    {
        // Escape HTML characters in compiler output first.  This allows us to properly
        // display characters such as angle brackets in compiler output.
        $compiler_output = htmlspecialchars($compiler_output, ENT_QUOTES, 'UTF-8', false);

        // set a reasonable default revision if none was specified
        if (empty($revision)) {
            $revision = 'master';
        }

        // Make sure we specify a protocol so this isn't interpreted as a relative path.
        if (!str_contains($projecturl, '//')) {
            $projecturl = '//' . $projecturl;
        }
        $repo_link = "<a class='cdash-link' href='$projecturl/blob/$revision";
        $pattern = "&$source_dir\/*([a-zA-Z0-9_\.\-\\/]+):(\d+)&";
        $replacement = "$repo_link/$1#L$2'>$1:$2</a>";

        // create links for source files
        $compiler_output = preg_replace($pattern, $replacement, $compiler_output);

        // remove base dir from other (binary) paths
        $base_dir = dirname($source_dir) . '/';
        if ($base_dir !== '//') {
            return str_replace($base_dir, '', $compiler_output);
        }
        return $compiler_output;
    }

    /** Post a comment on a pull request */
    public static function post_pull_request_comment($projectid, $pull_request, $comment, $cdash_url): void
    {
        if (!is_numeric($projectid)) {
            return;
        }

        if (!config('cdash.notify_pull_request') || !config('cdash.use_vcs_api')) {
            if (config('app.debug')) {
                Log::info('pull request commenting is disabled');
            }
            return;
        }

        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();
        $PR_func = 'post_' . $project->CvsViewerType . '_pull_request_comment';

        if (method_exists(self::class, $PR_func)) {
            self::$PR_func($project, $pull_request, $comment, $cdash_url);
        } else {
            Log::warning("PR commenting not implemented for '$project->CvsViewerType'");
        }
    }

    /** Convert GitHub repository viewer URL into corresponding API URL. */
    public static function get_github_api_url($github_url): string
    {
        /*
         * For a URL of the form:
         * ...://github.com/<user>/<repo>
         * We return:
         * ...://api.github.com/repos/<user>/<repo>
         */
        $idx1 = strpos($github_url, 'github.com');
        $idx2 = $idx1 + strlen('github.com/');
        $api_url = substr($github_url, 0, $idx2);
        $api_url = str_replace('github.com', 'api.github.com', $api_url);
        $api_url .= 'repos/';
        $api_url .= substr($github_url, $idx2);
        return $api_url;
    }

    public static function post_github_pull_request_comment(Project $project, $pull_request, $comment, $cdash_url): void
    {
        $repo = null;
        $repositories = $project->GetRepositories();
        foreach ($repositories as $repository) {
            if (str_contains($repository['url'], 'github.com')) {
                $repo = $repository;
                break;
            }
        }

        if (is_null($repo) || !isset($repo['username'])
            || !isset($repo['password'])) {
            Log::warning("Missing repository info for project #$project->Id");
            return;
        }

        /* Massage our github url into the API endpoint that we need to POST to:
         * .../repos/:owner/:repo/issues/:number/comments
         */
        $post_url = self::get_github_api_url($repo['url']);
        $post_url .= "/issues/$pull_request/comments";

        // Format our comment using Github's comment syntax.
        $message = "[$comment]($cdash_url)";

        $data = ['body' => $message];
        $data_string = json_encode($data);

        $ch = curl_init($post_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)]
        );
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $userpwd = $repo['username'] . ':' . $repo['password'];
        curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');

        $retval = curl_exec($ch);
        if ($retval === false) {
            Log::error('cURL error: ' . curl_error($ch), ['projectid' => $project->Id]);
        } elseif (config('app.debug')) {
            $matches = [];
            preg_match("#/comments/(\d+)#", $retval, $matches);
            Log::debug('Just posted comment #' . $matches[1], ['projectid' => $project->Id]);
        }

        curl_close($ch);
    }

    /** Create a bugtracker issue for a broken build. */
    public static function generate_bugtracker_new_issue_link($build, $project)
    {
        // Make sure that we have a valid build.
        if (!$build->Filled && !$build->Exists()) {
            return false;
        }

        // Return early if we don't have an implementation for this type
        // of bug tracker.
        $project->Fill();
        $function_name = "generate_{$project->BugTrackerType}_new_issue_link";
        if (!method_exists(self::class, $function_name)) {
            return false;
        }

        // Use our email functions to generate a message body and title for this build.
        $errors = self::check_email_errors(intval($build->Id), false, 0, true);
        $emailtext = [];
        foreach ($errors as $errorkey => $nerrors) {
            if ($nerrors < 1) {
                continue;
            }
            $emailtext['nerror'] = 1;
            $emailtext['summary'][$errorkey] =
                self::get_email_summary(intval($build->Id), $errors, $errorkey, 1, 500, 0, false);
            $emailtext['category'][$errorkey] = $nerrors;
        }
        if (empty($emailtext)) {
            return false;
        }
        $msg_parts = self::generate_broken_build_message($emailtext, $build, $project);
        if (!is_array($msg_parts)) {
            return false;
        }
        $title = $msg_parts['title'];
        $body = $msg_parts['body'];

        // Escape text fields that will be passed in the query string.
        $title = urlencode($title);
        $body = urlencode($body);

        // Call the implementation specific to this bug tracker.
        return self::$function_name($project->BugTrackerNewIssueUrl, $title, $body);
    }

    public static function generate_Buganizer_new_issue_link($baseurl, $title, $body): string
    {
        return "$baseurl&type=BUG&priority=P0&severity=S0&title=$title&description=$body";
    }

    public static function generate_JIRA_new_issue_link($baseurl, $title, $body): string
    {
        return "$baseurl&summary=$title&description=$body";
    }

    public static function generate_GitHub_new_issue_link($baseurl, $title, $body): string
    {
        return "{$baseurl}title=$title&body=$body";
    }

    /** Generate the title and body for a broken build. */
    private static function generate_broken_build_message(array $emailtext, $Build, $Project): array|false
    {
        $preamble = 'A submission to CDash for the project ' . $Project->Name . ' has ';
        $titleerrors = '(';

        $i = 0;
        foreach ($emailtext['category'] as $key => $value) {
            if ($key !== 'update_errors'
                && $key !== 'configure_errors'
                && $key !== 'build_warnings'
                && $key !== 'build_errors'
                && $key !== 'test_errors'
                && $key !== 'dynamicanalysis_errors'
                && $key !== 'missing_tests'
            ) {
                continue;
            }

            if ($i > 0) {
                $preamble .= ' and ';
                $titleerrors .= ', ';
            }

            switch ($key) {
                case 'update_errors':
                    $preamble .= 'update errors';
                    $titleerrors .= 'u=' . $value;
                    break;
                case 'configure_errors':
                    $preamble .= 'configure errors';
                    $titleerrors .= 'c=' . $value;
                    break;
                case 'build_warnings':
                    $preamble .= 'build warnings';
                    $titleerrors .= 'w=' . $value;
                    break;
                case 'build_errors':
                    $preamble .= 'build errors';
                    $titleerrors .= 'b=' . $value;
                    break;
                case 'test_errors':
                    $preamble .= 'failing tests';
                    $titleerrors .= 't=' . $value;
                    break;
                case 'dynamicanalysis_errors':
                    $preamble .= 'failing dynamic analysis tests';
                    $titleerrors .= 'd=' . $value;
                    break;
                case 'missing_tests':
                    $missing = $value['count'];
                    if ($missing) {
                        $preamble .= 'missing tests';
                        $titleerrors .= 'm=' . $missing;
                    }
                    break;
            }
            $i++;
        }

        // Nothing to send so we stop.
        if ($i == 0) {
            return false;
        }

        // Title
        $titleerrors .= '):';
        $title = 'FAILED ' . $titleerrors . ' ' . $Project->Name;
        // In the sendmail.php file, in the sendmail function configure errors are now handled
        // with their own logic, and the sendmail logic removes the 'configure_error' key therefore
        // we should be able to verify that the following is a configure category by checking to see
        // if the 'configure_error' key exists in the category array of keys.
        $categories = array_keys($emailtext['category']);

        $useSubProjectName = $Build->GetSubProjectName()
            && !in_array('configure_errors', $categories);

        // Because a configure error is not subproject specific, remove this from the output
        // if this is a configure_error.
        if ($useSubProjectName) {
            $title .= '/' . $Build->GetSubProjectName();
        }
        $title .= ' - ' . $Build->Name . ' - ' . $Build->Type;

        $preamble .= ".\n";
        $preamble .= 'You have been identified as one of the authors who ';
        $preamble .= 'have checked in changes that are part of this submission ';
        $preamble .= "or you are listed in the default contact list.\n\n";

        $body = 'Details on the submission can be found at ';

        $body .= url("/build/{$Build->Id}");
        $body .= "\n\n";

        $body .= 'Project: ' . $Project->Name . "\n";

        // Because a configure error is not subproject specific, remove this from the output
        // if this is a configure_error.
        if ($useSubProjectName) {
            $body .= 'SubProject: ' . $Build->GetSubProjectName() . "\n";
        }

        $Site = $Build->GetSite();

        $body .= 'Site: ' . $Site->name . "\n";
        $body .= 'Build Name: ' . $Build->Name . "\n";
        $body .= 'Build Time: ' . date(FMT_DATETIMETZ, strtotime($Build->StartTime . ' UTC')) . "\n";
        $body .= 'Type: ' . $Build->Type . "\n";

        foreach ($emailtext['category'] as $key => $value) {
            switch ($key) {
                case 'update_errors':
                    $body .= "Update errors: $value\n";
                    break;
                case 'configure_errors':
                    $body .= "Configure errors: $value\n";
                    break;
                case 'build_warnings':
                    $body .= "Warnings: $value\n";
                    break;
                case 'build_errors':
                    $body .= "Errors: $value\n";
                    break;
                case 'test_errors':
                    $body .= "Tests not passing: $value\n";
                    break;
                case 'dynamicanalysis_errors':
                    $body .= "Dynamic analysis tests failing: $value\n";
                    break;
                case 'missing_tests':
                    $missing = $value['count'];
                    if ($missing) {
                        $body .= "Missing tests: {$missing}\n";
                    }
            }
        }

        foreach ($emailtext['summary'] as $summary) {
            $body .= $summary;
        }

        $footer = "\n-CDash\n";
        return ['title' => $title, 'preamble' => $preamble, 'body' => $body,
            'footer' => $footer];
    }

    /** Return a summary for a category of error */
    private static function get_email_summary(int $buildid, array $errors, $errorkey, int $maxitems, int $maxchars, int $testtimemaxstatus, bool $emailtesttimingchanged): string
    {
        $build = new Build();
        $build->Id = $buildid;

        $eloquentBuild = \App\Models\Build::findOrFail($buildid);

        $serverURI = url('/');
        $information = '';

        // Update information
        if ($errorkey === 'update_errors') {
            $information = "\n\n*Update*\n";

            $update = $eloquentBuild->updates()->firstOrFail();
            $information .= "Status: {$update->status} ({$serverURI}/build/{$buildid}/update)\n";
            $information .= 'Command: ';
            $information .= substr($update->command, 0, $maxchars);
            $information .= "\n";
        } elseif ($errorkey === 'configure_errors') {
            // Configure information

            $information = "\n\n*Configure*\n";

            /** @var Configure $configure */
            $configure = \App\Models\Build::findOrFail($buildid)->configure()->first();

            // If this is false pdo_execute called in BuildConfigure will
            // have already logged the error.
            if ($configure !== null) {
                $information .= "Status: {$configure->status} ({$serverURI}/build/{$buildid})\n/configure";
                $information .= 'Output: ';
                $information .= substr($configure->log, 0, $maxchars);
                $information .= "\n";
            }
        } elseif ($errorkey === 'build_errors') {
            $information .= "\n\n*Error*";

            // type 0 = error
            // type 1 = warning
            // filter out errors of type error
            $errors = $build->GetErrors(['type' => Build::TYPE_ERROR], PDO::FETCH_OBJ);

            if (count($errors) > $maxitems) {
                $errors = array_slice($errors, 0, $maxitems);
                $information .= ' (first ' . $maxitems . ')';
            }

            $information .= "\n";

            foreach ($errors as $error) {
                $info = '';
                if (strlen($error->sourcefile) > 0) {
                    $info .= "{$error->sourcefile} line {$error->sourceline} ({$serverURI}/viewBuildError.php?buildid={$buildid})";
                    $info .= "{$error->text}\n";
                } else {
                    $info .= "{$error->text}\n{$error->postcontext}\n";
                }
                $information .= mb_substr($info, 0, $maxchars);
            }

            // filter out just failures of type error
            $failures = $build->GetFailures(['type' => Build::TYPE_ERROR], PDO::FETCH_OBJ);

            // not yet accounted for in integration tests
            if (count($failures) > $maxitems) {
                $failures = array_slice($failures, 0, $maxitems);
                $information .= " (first {$maxitems})";
            }

            foreach ($failures as $fail) {
                $info = '';
                if (strlen($fail->sourcefile) > 0) {
                    $info .= "{$fail->sourcefile} ({$serverURI}/viewBuildError.php?type=0&buildid={$buildid})\n";
                }
                if (strlen($fail->stdoutput) > 0) {
                    $info .= "{$fail->stdoutput}\n";
                }
                if (strlen($fail->stderror) > 0) {
                    $info .= "{$fail->stderror}\n";
                }
                $information .= mb_substr($info, 0, $maxchars);
            }
            $information .= "\n";
        } elseif ($errorkey === 'build_warnings') {
            $information .= "\n\n*Warnings*";

            $warnings = $build->GetErrors(['type' => Build::TYPE_WARN], PDO::FETCH_OBJ);

            if (count($warnings) > $maxitems) {
                $information .= ' (first ' . $maxitems . ')';
                $warnings = array_slice($warnings, 0, $maxitems);
            }

            if (!empty($warnings)) {
                $information .= "\n";
            }

            foreach ($warnings as $warning) {
                $info = '';
                if (strlen($warning->sourcefile) > 0) {
                    $info .= "{$warning->sourcefile} line {$warning->sourceline} ({$serverURI}/viewBuildError.php?type=1&buildid={$buildid})\n";
                    $info .= "{$warning->text}\n";
                } else {
                    $info .= "{$warning->text}\n{$warning->postcontext}\n";
                }
                $information .= substr($info, 0, $maxchars);
            }

            $failures = $build->GetFailures(['type' => Build::TYPE_WARN], PDO::FETCH_OBJ);

            if (count($failures) > $maxitems) {
                $information .= ' (first ' . $maxitems . ')';
                $failures = array_slice($failures, 0, $maxitems);
            }

            if (!empty($failures)) {
                $information .= "\n";
            }

            foreach ($failures as $fail) {
                $info = '';
                if (strlen($fail->sourcefile) > 0) {
                    $info .= "{$fail->sourcefile} ({$serverURI}/viewBuildError.php?type=1&buildid={$buildid})\n";
                }
                if (strlen($fail->stdoutput) > 0) {
                    $info .= "{$fail->stdoutput}\n";
                }
                if (strlen($fail->stderror) > 0) {
                    $info .= "{$fail->stderror}\n";
                }
                $information .= substr($info, 0, $maxchars) . "\n";
            }
            $information .= "\n";
        } elseif ($errorkey === 'test_errors') {
            // Local function to add a set of tests to our email message body.
            // This reduces copied & pasted code below.
            $AddTestsToEmail = function ($tests, $section_title) use ($maxchars, $maxitems, $serverURI) {
                $num_tests = count($tests);
                if ($num_tests < 1) {
                    return '';
                }

                $information = "\n\n*$section_title*";
                if ($num_tests == $maxitems) {
                    $information .= " (first $maxitems)";
                }
                $information .= "\n";

                foreach ($tests as $test) {
                    $info = "{$test['name']} | {$test['details']} | ({$serverURI}/test/{$test['buildtestid']})\n";
                    $information .= substr($info, 0, $maxchars);
                }
                $information .= "\n";
                return $information;
            };

            $information .= $AddTestsToEmail($build->GetFailedTests($maxitems), 'Tests failing');
            if ($emailtesttimingchanged) {
                $information .= $AddTestsToEmail($build->GetFailedTimeStatusTests($maxitems, $testtimemaxstatus), 'Tests failing time status');
            }
            $information .= $AddTestsToEmail($build->GetNotRunTests($maxitems), 'Tests not run');
        } elseif ($errorkey === 'dynamicanalysis_errors') {
            $db = Database::getInstance();
            $da_query = $db->executePrepared("
                        SELECT name, id
                        FROM dynamicanalysis
                        WHERE
                            status IN ('failed', 'notrun')
                            AND buildid=?
                        ORDER BY name
                        LIMIT $maxitems
                    ", [$buildid]);
            add_last_sql_error('sendmail');
            $numrows = count($da_query);

            if ($numrows > 0) {
                $information .= "\n\n*Dynamic analysis tests failing or not run*";
                if ($numrows === $maxitems) {
                    $information .= ' (first ' . $maxitems . ')';
                }
                $information .= "\n";

                foreach ($da_query as $test_array) {
                    $info = $test_array['name'] . ' (' . $serverURI . '/viewDynamicAnalysisFile.php?id=' . $test_array['id'] . ")\n";
                    $information .= substr($info, 0, $maxchars);
                }
                $information .= "\n";
            }
        } elseif ($errorkey === 'missing_tests') {
            // sanity check
            $missing = $errors['missing_tests']['count'] ?? 0;

            if ($missing) {
                $information .= "\n\n*Missing tests*";
                if ($errors['missing_tests']['count'] > $maxitems) {
                    $information .= " (first {$maxitems})";
                }

                $list = array_slice($errors['missing_tests']['list'], 0, $maxitems);
                $information .= PHP_EOL;
                $url = "({$serverURI}/viewTest.php?buildid={$buildid})";
                $information .= implode(" {$url}\n", array_values($list));
                $information .= $url;
                $information .= PHP_EOL;
            }
        }

        return $information;
    }

    /** Check for errors for a given build. Return false if no errors */
    private static function check_email_errors(int $buildid, bool $checktesttimeingchanged, int $testtimemaxstatus, bool $checkpreviouserrors): array
    {
        $errors = [];
        $errors['errors'] = true;
        $errors['hasfixes'] = false;

        // Configure errors
        /** @var Configure $BuildConfigure */
        $BuildConfigure = \App\Models\Build::findOrFail($buildid)->configure()->first();
        $errors['configure_errors'] = $BuildConfigure->status ?? 0;

        // Build errors and warnings
        $Build = new Build();
        $Build->Id = $buildid;
        $Build->FillFromId($buildid);
        $errors['build_errors'] = $Build->GetNumberOfErrors();
        $errors['build_warnings'] = $Build->GetNumberOfWarnings();

        // Test errors
        $errors['test_errors'] = $Build->GetNumberOfFailedTests();
        $errors['test_errors'] += $Build->GetNumberOfNotRunTests();
        if ($checktesttimeingchanged) {
            $errors['test_errors'] += count($Build->GetFailedTimeStatusTests(0, $testtimemaxstatus));
        }

        // Dynamic analysis errors
        $DynamicAnalysis = new DynamicAnalysis();
        $DynamicAnalysis->BuildId = $buildid;
        $errors['dynamicanalysis_errors'] = $DynamicAnalysis->GetNumberOfErrors();

        // Check if this is a clean build.
        if ($errors['configure_errors'] == 0
            && $errors['build_errors'] == 0
            && $errors['build_warnings'] == 0
            && $errors['test_errors'] == 0
            && $errors['dynamicanalysis_errors'] == 0
        ) {
            $errors['errors'] = false;
        }

        // look for the previous build
        $previousbuildid = $Build->GetPreviousBuildId();
        if ($previousbuildid > 0) {
            $error_differences = $Build->GetErrorDifferences($buildid);
            if ($errors['errors'] && $checkpreviouserrors && $errors['dynamicanalysis_errors'] == 0) {
                // If the builderroddiff positive and configureerrordiff and testdiff positive are zero we don't send an email
                // we don't send any emails
                if ($error_differences['buildwarningspositive'] <= 0
                    && $error_differences['builderrorspositive'] <= 0
                    && $error_differences['configurewarnings'] <= 0
                    && $error_differences['configureerrors'] <= 0
                    && $error_differences['testfailedpositive'] <= 0
                    && $error_differences['testnotrunpositive'] <= 0
                ) {
                    $errors['errors'] = false;
                }
            }

            if ($error_differences['buildwarningsnegative'] > 0
                || $error_differences['builderrorsnegative'] > 0
                || $error_differences['configurewarnings'] < 0
                || $error_differences['configureerrors'] < 0
                || $error_differences['testfailednegative'] > 0
                || $error_differences['testnotrunnegative'] > 0
            ) {
                $errors['hasfixes'] = true;
                $errors['fixes']['configure_fixes'] = $error_differences['configurewarnings'] + $error_differences['configureerrors'];
                $errors['fixes']['builderror_fixes'] = $error_differences['builderrorsnegative'];
                $errors['fixes']['buildwarning_fixes'] = $error_differences['buildwarningsnegative'];
                $errors['fixes']['test_fixes'] = $error_differences['testfailednegative'] + $error_differences['testnotrunnegative'];
            }
        }
        return $errors;
    }
}
