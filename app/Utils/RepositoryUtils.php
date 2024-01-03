<?php

declare(strict_types=1);

namespace App\Utils;

use CDash\Database;
use CDash\Model\Project;
use CDash\ServiceContainer;
use Illuminate\Support\Facades\Log;

class RepositoryUtils
{
    public static function get_previous_revision($revision)
    {
        // Split revision into components based on any "." separators:
        $revcmps = explode('.', $revision);
        $n = count($revcmps);

        // svn style "single-component" revision number, just subtract one:
        //
        if ($n === 1) {
            return $revcmps[0] - 1;
        }

        // cvs style "multi-component" revision number, subtract one from last
        // component -- if result is 0, chop off last two components -- finally,
        // re-assemble $n components for previous_revision:
        //
        $revcmps[$n - 1] = $revcmps[$n - 1] - 1;
        if ($revcmps[$n - 1] === 0) {
            $n = $n - 2;
        }

        if ($n < 2) {
            // Can't reassemble less than 2 components; use original revision
            // as previous...
            //
            $previous_revision = $revision;
        } else {
            // Reassemble components into previous_revision:
            //
            $previous_revision = $revcmps[0];
            $i = 1;
            while ($i < $n) {
                $previous_revision = $previous_revision . '.' . $revcmps[$i];
                $i = $i + 1;
            }
        }
        return $previous_revision;
    }

    /** Return the ViewCVS URL */
    public static function get_viewcvs_diff_url($projecturl, $directory, $file, $revision)
    {
        // The project's viewcvs URL is expected to contain "?root=projectname"
        // Split it at the "?"
        //
        if (strlen($projecturl) == 0) {
            return '';
        }

        $cmps = explode('?', $projecturl);

        // If $cmps[1] starts with "root=" and the $directory value starts
        // with "whatever comes after that" then remove that bit from directory:
        //
        @$npos = strpos($cmps[1], 'root=');
        if ($npos !== false && $npos === 0) {
            $rootdir = substr($cmps[1], 5);

            $npos = strpos($directory, $rootdir);
            if ($npos !== false && $npos === 0) {
                $directory = substr($directory, strlen($rootdir));
                $npos = strpos($directory, '/');
                if ($npos !== false && $npos === 0) {
                    if (1 === strlen($directory)) {
                        $directory = '';
                    } else {
                        $directory = substr($directory, 1);
                    }
                }
            }
        }

        if (strlen($directory) > 0) {
            $dircmp = $directory . '/';
        } else {
            $dircmp = '';
        }

        // If we have a revision
        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if (0 === strcmp($revision, $prev_revision)) {
                $revcmp = '&rev=' . $revision . '&view=markup';
                $diff_url = $cmps[0] . $dircmp . $file . '?' . $cmps[1] . $revcmp;
            } else {
                // different : view the diff of r1 and r2:
                $revcmp = '&r1=' . $prev_revision . '&r2=' . $revision;
                $diff_url = $cmps[0] . $dircmp . $file . '.diff?' . $cmps[1] . $revcmp;
            }
        } else {
            @$diff_url = $cmps[0] . $dircmp . $file . '?' . $cmps[1];
        }
        return make_cdash_url($diff_url);
    }

    /** Return the Trac URL */
    public static function get_trac_diff_url($projecturl, $directory, $file, $revision)
    {
        $filename = $file;
        if ($directory != '') {
            $filename = $directory . '/' . $file;
        }

        if ($revision != '') {
            $diff_url = $projecturl . '/changeset/' . $revision . '/trunk/' . $filename;
        } else {
            // no revision

            $diff_url = $projecturl . '/browser/' . $filename;
        }
        return make_cdash_url($diff_url);
    }

    /** Return the Mercurial URL */
    public static function get_hgweb_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $diff_url = $projecturl . '/diff/' . $revision . '/' . ($directory ? ('/' . $directory) : '') . '/' . $file;
        } else {
            $diff_url = $projecturl . '/file/tip/' . ($directory ? ('/' . $directory) : '') . '/' . $file;
        }
        return make_cdash_url($diff_url);
    }

    /** Return the Fisheye URL */
    public static function get_fisheye_diff_url($projecturl, $directory, $file, $revision)
    {
        $diff_url = rtrim($projecturl, '/') . ($directory ? ('/' . $directory) : '') . '/' . $file;

        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if ($prev_revision != $revision) {
                $diff_url .= '?r1=' . $prev_revision . '&r2=' . $revision;
            } else {
                $diff_url .= '?r=' . $revision;
            }
        }
        return make_cdash_url($diff_url);
    }

    /** Return the P4Web URL */
    public static function get_p4web_diff_url($projecturl, $directory, $file, $revision)
    {
        $diff_url = rtrim($projecturl, '/') . ($directory ? ('/' . $directory) : '') . '/' . $file;

        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if ($prev_revision != $revision) {
                $diff_url .= '?ac=207&sr1=' . $prev_revision . '&sr2=' . $revision;
            } else {
                $diff_url .= '?ac=64&sr=' . $revision;
            }
        }
        return make_cdash_url($diff_url);
    }

    /** Return the CVSTrac URL */
    public static function get_cvstrac_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if ($prev_revision != $revision) {
                $diff_url = $projecturl . '/filediff?f=' . ($directory ? ($directory) : '') . '/' . $file;
                $diff_url .= '&v1=' . $prev_revision . '&v2=' . $revision;
            } else {
                $diff_url = $projecturl . '/fileview?f=' . ($directory ? ($directory) : '') . '/' . $file;
                $diff_url .= '&v=' . $revision;
            }
        } else {
            $diff_url = $projecturl . '/rlog?f=' . ($directory ? ($directory) : '') . '/' . $file;
        }
        return make_cdash_url($diff_url);
    }

    /** Return the ViewVC URL */
    public static function get_viewvc_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if ($prev_revision != $revision) {
                //diff

                $diff_url = $projecturl . '/?action=browse&path=' . ($directory ? ($directory) : '') . '/' . $file;
                $diff_url .= '&r1=' . $prev_revision . '&r2=' . $revision;
            } else {
                //view

                $diff_url = $projecturl . '/?action=browse&path=' . ($directory ? ($directory) : '') . '/' . $file;
                $diff_url .= '&revision=' . $revision . '&view=markup';
            }
        } else {
            //log

            $diff_url = $projecturl . '/?action=browse&path=' . ($directory ? ($directory) : '') . '/' . $file . '&view=log';
        }
        return make_cdash_url($diff_url);
    }

    /** Return the viewVC 1-1 url */
    public static function get_viewvc_1_1_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if ($prev_revision != $revision) {
                //diff

                $diff_url = $projecturl . '/' . ($directory ? ($directory) : '') . '/' . $file;
                $diff_url .= '?r1=' . $prev_revision . '&r2=' . $revision;
            } else {
                //view

                $diff_url = $projecturl . '/' . ($directory ? ($directory) : '') . '/' . $file;
                $diff_url .= '?revision=' . $revision . '&view=markup';
            }
        } else {
            //log

            $diff_url = $projecturl . '/' . ($directory ? ($directory) : '') . '/' . $file . '?view=log';
        }
        return make_cdash_url($diff_url);
    }

    /** Return the WebSVN URL */
    public static function get_websvn_diff_url($projecturl, $directory, $file, $revision)
    {
        $repname = '';
        $root = '';
        // find the repository name
        $pos_repname = strpos($projecturl, 'repname=');
        if ($pos_repname !== false) {
            $pos_repname_end = strpos($projecturl, '&', $pos_repname + 1);
            if ($pos_repname_end !== false) {
                $repname = substr($projecturl, $pos_repname, $pos_repname_end - $pos_repname);
            } else {
                $repname = substr($projecturl, $pos_repname);
            }
        }

        // find the root name
        $pos_root = strpos($projecturl, 'path=');
        if ($pos_root !== false) {
            $pos_root_end = strpos($projecturl, '&', $pos_root + 1);
            if ($pos_root_end !== false) {
                $root = substr($projecturl, $pos_root + 5, $pos_root_end - $pos_root - 5);
            } else {
                $root = substr($projecturl, $pos_root + 5);
            }
        }

        // find the project url
        $pos_dotphp = strpos($projecturl, '.php?');
        if ($pos_dotphp !== false) {
            $projecturl = substr($projecturl, 0, $pos_dotphp);
            $pos_slash = strrpos($projecturl, '/');
            $projecturl = substr($projecturl, 0, $pos_slash);
        }

        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if ($prev_revision != $revision) {
                //diff

                $diff_url = $projecturl . '/diff.php?' . $repname . '&path=' . $root . ($directory ? '/' . ($directory) : '') . '/' . $file;
                $diff_url .= '&rev=' . $revision . '&sc=1';
            } else {
                //view

                $diff_url = $projecturl . '/filedetails.php?' . $repname . '&path=' . $root . ($directory ? '/' . ($directory) : '') . '/' . $file;
                $diff_url .= '&rev=' . $revision;
            }
        } else {
            //log

            $diff_url = $projecturl . '/log.php?' . $repname . '&path=' . $root . ($directory ? '/' . ($directory) : '') . '/' . $file;
            $diff_url .= '&rev=0&sc=0&isdir=0';
        }
        return make_cdash_url($diff_url);
    }

    /** Return the SourceForge Allura URL */
    public static function get_allura_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $prev_revision = self::get_previous_revision($revision);
            if ($prev_revision != $revision) {
                //diff

                $diff_url = $projecturl . '/' . $revision . '/tree/trunk/' . $directory . '/' . $file . '?diff=' . $prev_revision;
            } else {
                //view

                $diff_url = $projecturl . '/' . $revision . '/tree/trunk/';
            }
        } else {
            //log

            $diff_url = $projecturl . '/' . $revision;
        }
        return make_cdash_url($diff_url);
    }

    /** Return the Loggerhead URL */
    public static function get_loggerhead_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $diff_url = $projecturl . '/revision/' . $revision . ($directory ? ('/' . $directory) : '') . '/' . $file;
        } else {
            $diff_url = $projecturl . '/changes/head:/' . ($directory ? ($directory) : '') . '/' . $file;
        }
        return make_cdash_url($diff_url);
    }

    /** Return the GitWeb diff URL */
    public static function get_gitweb_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $diff_url = $projecturl . ';a=commitdiff;h=' . $revision;
        } elseif ($file != '') {
            $diff_url = $projecturl . ';a=blob;f=';
            if ($directory != '') {
                $diff_url .= $directory . '/';
            }
            $diff_url .= $file;
        } else {
            return '';
        }
        return make_cdash_url($diff_url);
    }

    /** Return the GitWeb2 diff URL */
    public static function get_gitweb2_diff_url($projecturl, $directory, $file, $revision)
    {
        if ($revision != '') {
            $diff_url = $projecturl . '/commitdiff/' . $revision;
        } elseif ($file != '') {
            $diff_url = $projecturl . '/blob/';
            if ($directory != '') {
                $diff_url .= $directory . '/';
            }
            $diff_url .= $file;
        } else {
            return '';
        }
        return make_cdash_url($diff_url);
    }

    /** Return the Gitorious/GitHub diff URL */
    public static function get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, $blobs, $branch = 'master')
    {
        if ($revision != '') {
            $diff_url = $projecturl . '/commit/' . $revision;
        } elseif ($file != '') {
            $diff_url = $projecturl . '/' . $blobs . '/' . $branch . '/';
            if ($directory != '') {
                $diff_url .= $directory . '/';
            }
            $diff_url .= $file;
        } else {
            return '';
        }
        return make_cdash_url($diff_url);
    }

    /** Return the Stash diff URL */
    public static function get_stash_diff_url($projecturl, $directory, $file, $revision)
    {
        $diff_url = $projecturl . '/browse/';
        if ($directory) {
            $diff_url .= $directory . '/';
        }
        $diff_url .= $file . '?until=' . $revision;
        return make_cdash_url($diff_url);
    }

    /** Return the Gitorious diff URL */
    public static function get_gitorious_diff_url($projecturl, $directory, $file, $revision)
    {
        // Gitorious uses 'blobs' or 'trees' (plural)
        return self::get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blobs');
    }

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
    public static function github_get_source_dir($projecturl, $file_path)
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
    public static function get_gitlab_diff_url($projecturl, $directory, $file, $revision)
    {
        // GitLab uses 'blob' or 'tree' (singular, no s)
        return self::get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blob');
    }

    /** Return the cgit diff URL */
    public static function get_cgit_diff_url($projecturl, $directory, $file, $revision)
    {
        $diff_url = $projecturl . '/diff/';
        if ($directory) {
            $diff_url .= $directory . '/';
        }
        $diff_url .= $file . '?id=' . $revision;
        return make_cdash_url($diff_url);
    }

    /** Return the Redmine diff URL */
    public static function get_redmine_diff_url($projecturl, $directory, $file, $revision)
    {
        $diff_url = $projecturl . '/revisions/' . $revision . '/diff/';
        if ($directory) {
            $diff_url .= $directory . '/';
        }
        $diff_url .= $file;
        return make_cdash_url($diff_url);
    }

    /** Return the Phabricator diff URL */
    public static function get_phab_git_diff_url($projecturl, $directory, $file, $revision)
    {
        // "master" is misleading as the revision is the only relevant part.
        // Could be any string but even Phabricator uses "master" when
        // creating file URLs of revisions in other branches.
        $diff_url = $projecturl . '/browse/master/';

        if ($directory) {
            $diff_url .= $directory . '/';
        }

        $diff_url .= $file;

        if ($revision) {
            $diff_url .= ';' . $revision;
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
            // default is viewcvs
            return self::get_viewcvs_diff_url($projecturl, $directory, $file, $revision);
        }
    }

    /** Return the ViewCVS URL */
    public static function get_viewcvs_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '&rev=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the Trac URL */
    public static function get_trac_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/changeset/' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the Mercurial URL */
    public static function get_hgweb_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/rev/' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the Fisheye URL */
    public static function get_fisheye_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '?r=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the P4Web URL */
    public static function get_p4web_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '?ac=64&sr=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the CVSTrac URL */
    public static function get_cvstrac_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = ''; // not implemented
        return make_cdash_url($revision_url);
    }

    /** Return the Stash revision URL */
    public static function get_stash_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/commits/' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the ViewVC URL */
    public static function get_viewvc_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '?view=rev&revision=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the viewVC 1-1 url */
    public static function get_viewvc_1_1_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '?view=rev&revision=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the WebSVN URL */
    public static function get_websvn_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '?view=revision&revision=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the SourceForge Allura URL */
    public static function get_allura_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the Loggerhead URL */
    public static function get_loggerhead_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = ''; // not implemented
        return make_cdash_url($revision_url);
    }

    /** Return the GitWeb revision URL */
    public static function get_gitweb_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . ';a=shortlog;h=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the GitWeb revision URL */
    public static function get_gitweb2_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/shortlog/' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the Gitorious revision URL */
    public static function get_gitorious_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/commits/';
        if ($priorrevision) {
            $revision_url .= $priorrevision . '..';
        }
        $revision_url .= $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the GitHub revision URL */
    public static function get_github_revision_url($projecturl, $revision, $priorrevision)
    {
        if ($priorrevision) {
            $revision_url = "$projecturl/compare/$priorrevision...$revision";
        } else {
            $revision_url = "$projecturl/commit/$revision";
        }
        return make_cdash_url($revision_url);
    }

    /** Return the GitLab revision URL */
    public static function get_gitlab_revision_url($projecturl, $revision, $priorrevision)
    {
        return self::get_github_revision_url($projecturl, $revision, $priorrevision);
    }

    /** Return the cgit revision URL */
    public static function get_cgit_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/log/?id=' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the Redmine revision URL */
    public static function get_redmine_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/revisions/' . $revision;
        return make_cdash_url($revision_url);
    }

    /** Return the Phabricator revision URL */
    public static function get_phab_git_revision_url($projecturl, $revision, $priorrevision)
    {
        $revision_url = $projecturl . '/commit/' . $revision;
        return make_cdash_url($revision_url);
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

        if (strlen($projecturl) === 0) {
            return '';
        }

        $cvsviewertype = strtolower($project['cvsviewertype']);
        $revisionfunction = 'get_' . $cvsviewertype . '_revision_url';

        if (method_exists(self::class, $revisionfunction)) {
            return self::$revisionfunction($projecturl, $revision, $priorrevision);
        } else {
            // default is viewcvs
            return self::get_viewcvs_revision_url($projecturl, $revision, $priorrevision);
        }
    }

    public static function linkify_compiler_output($projecturl, $source_dir, $revision, $compiler_output)
    {
        // Escape HTML characters in compiler output first.  This allows us to properly
        // display characters such as angle brackets in compiler output.
        $compiler_output = htmlspecialchars($compiler_output, ENT_QUOTES, 'UTF-8', false);

        // set a reasonable default revision if none was specified
        if (empty($revision)) {
            $revision = 'master';
        }

        // Make sure we specify a protocol so this isn't interpreted as a relative path.
        if (strpos($projecturl, '//') === false) {
            $projecturl = '//' . $projecturl;
        }
        $repo_link = "<a href='$projecturl/blob/$revision";
        $pattern = "&$source_dir\/*([a-zA-Z0-9_\.\-\\/]+):(\d+)&";
        $replacement = "$repo_link/$1#L$2'>$1:$2</a>";

        // create links for source files
        $compiler_output = preg_replace($pattern, $replacement, $compiler_output);

        // remove base dir from other (binary) paths
        $base_dir = dirname($source_dir) . '/';
        if ($base_dir != '//') {
            return str_replace($base_dir, '', $compiler_output);
        }
        return $compiler_output;
    }

    /** Post a comment on a pull request */
    public static function post_pull_request_comment($projectid, $pull_request, $comment, $cdash_url)
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
            add_log("PR commenting not implemented for '$project->CvsViewerType'",
                'post_pull_request_comment()', LOG_WARNING);
        }
    }

    /** Convert GitHub repository viewer URL into corresponding API URL. */
    public static function get_github_api_url($github_url)
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

    public static function post_github_pull_request_comment(Project $project, $pull_request, $comment, $cdash_url)
    {
        $repo = null;
        $repositories = $project->GetRepositories();
        foreach ($repositories as $repository) {
            if (strpos($repository['url'], 'github.com') !== false) {
                $repo = $repository;
                break;
            }
        }

        if (is_null($repo) || !isset($repo['username'])
            || !isset($repo['password'])) {
            add_log("Missing repository info for project #$project->Id",
                'post_github_pull_request_comment()', LOG_WARNING);
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
            add_log(
                'cURL error: ' . curl_error($ch),
                'post_github_pull_request_comment',
                LOG_ERR, $project->Id);
        } elseif (config('app.debug')) {
            $matches = [];
            preg_match("#/comments/(\d+)#", $retval, $matches);
            add_log(
                'Just posted comment #' . $matches[1],
                'post_github_pull_request_comment',
                LOG_DEBUG, $project->Id);
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
        require_once 'include/sendemail.php';
        $errors = check_email_errors(intval($build->Id), false, 0, true);
        $emailtext = [];
        foreach ($errors as $errorkey => $nerrors) {
            if ($nerrors < 1) {
                continue;
            }
            $emailtext['nerror'] = 1;
            $emailtext['summary'][$errorkey] =
                get_email_summary(intval($build->Id), $errors, $errorkey, 1, 500, 0, false);
            $emailtext['category'][$errorkey] = $nerrors;
        }
        if (empty($emailtext)) {
            return false;
        }
        $msg_parts = generate_broken_build_message($emailtext, $build, $project);
        if (!is_array($msg_parts)) {
            return false;
        }
        $title = $msg_parts['title'];
        $body = $msg_parts['body'];

        $users = [];
        $subproject_name = $build->GetSubProjectName();
        if ($subproject_name) {
            // Get users to notify for this SubProject.
            $pdo = get_link_identifier()->getPdo();
            $user_table = qid('user');
            $stmt = $pdo->prepare(
                "SELECT email FROM $user_table
            JOIN labelemail ON labelemail.userid = $user_table.id
            JOIN label ON label.id = labelemail.labelid
            WHERE label.text = ?  AND labelemail.projectid = ?");
            pdo_execute($stmt, [$subproject_name, $project->Id]);
            while ($row = $stmt->fetch()) {
                $users[] = $row['email'];
            }
            // Sort alphabetically.
            sort($users);
        }

        // Escape text fields that will be passed in the query string.
        $title = urlencode($title);
        $body = urlencode($body);

        // Call the implementation specific to this bug tracker.
        return self::$function_name($project->BugTrackerNewIssueUrl, $title, $body, $users);
    }

    public static function generate_Buganizer_new_issue_link($baseurl, $title, $body, $users)
    {
        $url = "$baseurl&type=BUG&priority=P0&severity=S0&title=$title&description=$body";
        if (!empty($users)) {
            $cc = "&cc=";
            $cc .= implode(',', $users);
            $url .= $cc;
        }
        return $url;
    }

    public static function generate_JIRA_new_issue_link($baseurl, $title, $body, $users)
    {
        $url = "$baseurl&summary=$title";
        if (!empty($users)) {
            /* By default, JIRA doesn't have a "CC" field. So we will add mentions
             * to the description field instead.
             * It also only supports mentions by user name, not email.
             * So for first.last@domain.com, we will add [~first.last] to the body.
             **/
            foreach ($users as $user) {
                $parts = explode("@", $user, 2);
                $user_name = $parts[0];
                $body .= urlencode("[~$user_name] ");
            }
        }
        $url .= "&description=$body";
        return $url;
    }

    public static function generate_GitHub_new_issue_link($baseurl, $title, $body, $users)
    {
        $url = "{$baseurl}title=$title";
        if (!empty($users)) {
            /* Similar to JIRA (above), we need to put any mentioned users
             * in the body of the issue.
             **/
            foreach ($users as $user) {
                $parts = explode("@", $user, 2);
                $user_name = $parts[0];
                $body .= urlencode("@$user_name ");
            }
        }
        $url .= "&body=$body";
        return $url;
    }
}
