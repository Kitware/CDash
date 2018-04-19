<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

require_once 'config/config.php';
require_once 'include/log.php';

use CDash\Model\Build;
use CDash\Model\BuildUpdateFile;
use CDash\Model\Project;

function get_previous_revision($revision)
{
    // Split revision into components based on any "." separators:
    //
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
function get_viewcvs_diff_url($projecturl, $directory, $file, $revision)
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
        $prev_revision = get_previous_revision($revision);
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
function get_trac_diff_url($projecturl, $directory, $file, $revision)
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
function get_hgweb_diff_url($projecturl, $directory, $file, $revision)
{
    if ($revision != '') {
        $diff_url = $projecturl . '/diff/' . $revision . '/' . ($directory ? ('/' . $directory) : '') . '/' . $file;
    } else {
        $diff_url = $projecturl . '/file/tip/' . ($directory ? ('/' . $directory) : '') . '/' . $file;
    }
    return make_cdash_url($diff_url);
}

/** Return the Fisheye URL */
function get_fisheye_diff_url($projecturl, $directory, $file, $revision)
{
    $diff_url = rtrim($projecturl, '/') . ($directory ? ('/' . $directory) : '') . '/' . $file;

    if ($revision != '') {
        $prev_revision = get_previous_revision($revision);
        if ($prev_revision != $revision) {
            $diff_url .= '?r1=' . $prev_revision . '&r2=' . $revision;
        } else {
            $diff_url .= '?r=' . $revision;
        }
    }
    return make_cdash_url($diff_url);
}

/** Return the P4Web URL */
function get_p4web_diff_url($projecturl, $directory, $file, $revision)
{
    $diff_url = rtrim($projecturl, '/') . ($directory ? ('/' . $directory) : '') . '/' . $file;

    if ($revision != '') {
        $prev_revision = get_previous_revision($revision);
        if ($prev_revision != $revision) {
            $diff_url .= '?ac=207&sr1=' . $prev_revision . '&sr2=' . $revision;
        } else {
            $diff_url .= '?ac=64&sr=' . $revision;
        }
    }
    return make_cdash_url($diff_url);
}

/** Return the CVSTrac URL */
function get_cvstrac_diff_url($projecturl, $directory, $file, $revision)
{
    if ($revision != '') {
        $prev_revision = get_previous_revision($revision);
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
function get_viewvc_diff_url($projecturl, $directory, $file, $revision)
{
    if ($revision != '') {
        $prev_revision = get_previous_revision($revision);
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
function get_viewvc_1_1_diff_url($projecturl, $directory, $file, $revision)
{
    if ($revision != '') {
        $prev_revision = get_previous_revision($revision);
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
function get_websvn_diff_url($projecturl, $directory, $file, $revision)
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
        $prev_revision = get_previous_revision($revision);
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
function get_allura_diff_url($projecturl, $directory, $file, $revision)
{
    if ($revision != '') {
        $prev_revision = get_previous_revision($revision);
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
function get_loggerhead_diff_url($projecturl, $directory, $file, $revision)
{
    if ($revision != '') {
        $diff_url = $projecturl . '/revision/' . $revision . ($directory ? ('/' . $directory) : '') . '/' . $file;
    } else {
        $diff_url = $projecturl . '/changes/head:/' . ($directory ? ($directory) : '') . '/' . $file;
    }
    return make_cdash_url($diff_url);
}

/** Return the GitWeb diff URL */
function get_gitweb_diff_url($projecturl, $directory, $file, $revision)
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
function get_gitweb2_diff_url($projecturl, $directory, $file, $revision)
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
function get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, $blobs, $branch = 'master')
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
function get_stash_diff_url($projecturl, $directory, $file, $revision)
{
    $diff_url = $projecturl . '/browse/';
    if ($directory) {
        $diff_url .= $directory . '/';
    }
    $diff_url .= $file . '?until=' . $revision;
    return make_cdash_url($diff_url);
}

/** Return the Gitorious diff URL */
function get_gitorious_diff_url($projecturl, $directory, $file, $revision)
{
    // Gitorious uses 'blobs' or 'trees' (plural)
    return get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blobs');
}

/** Return the source directory for a source file */
function get_source_dir($projectid, $projecturl, $file_path)
{
    if (!is_numeric($projectid)) {
        return;
    }

    $project = pdo_query("SELECT cvsviewertype FROM project WHERE id='$projectid'");
    $project_array = pdo_fetch_array($project);
    $cvsviewertype = strtolower($project_array['cvsviewertype']);

    $target_fn = $cvsviewertype . '_get_source_dir';

    if (function_exists($target_fn)) {
        return $target_fn($projecturl, $file_path);
    }
}

/** Extract the source directory from a Github URL and a full path to
 * a source file.  This only works properly if the source dir's name matches
 * the repo's name, ie it was not renamed as it was cloned.
 **/
function github_get_source_dir($projecturl, $file_path)
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
function get_github_diff_url($projecturl, $directory, $file, $revision)
{
    if (empty($directory) && empty($file) && empty($revision)) {
        return;
    }

    // set a reasonable default revision if none was specified
    if (empty($revision)) {
        $revision = 'master';
    }
    // get the source dir
    $source_dir = github_get_source_dir($projecturl, $directory);

    // remove it from the beginning of our path if it is found
    if (substr($directory, 0, strlen($source_dir)) == $source_dir) {
        $directory = substr($directory, strlen($source_dir));
    }
    $directory = trim($directory, '/');

    $diff_url = "$projecturl/blob/$revision/";
    $diff_url .= "$directory/$file";
    return make_cdash_url($diff_url);
}

/** Return the GitLab diff URL */
function get_gitlab_diff_url($projecturl, $directory, $file, $revision)
{
    // GitLab uses 'blob' or 'tree' (singular, no s)
    return get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blob');
}

/** Return the cgit diff URL */
function get_cgit_diff_url($projecturl, $directory, $file, $revision)
{
    $diff_url = $projecturl . '/diff/';
    if ($directory) {
        $diff_url .= $directory . '/';
    }
    $diff_url .= $file . '?id=' . $revision;
    return make_cdash_url($diff_url);
}

/** Return the Redmine diff URL */
function get_redmine_diff_url($projecturl, $directory, $file, $revision)
{
    $diff_url = $projecturl . '/revisions/' . $revision . '/diff/';
    if ($directory) {
        $diff_url .= $directory . '/';
    }
    $diff_url .= $file;
    return make_cdash_url($diff_url);
}

/** Return the Phabricator diff URL */
function get_phab_git_diff_url($projecturl, $directory, $file, $revision)
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
function get_diff_url($projectid, $projecturl, $directory, $file, $revision = '')
{
    if (!is_numeric($projectid)) {
        return;
    }

    $project = pdo_query("SELECT cvsviewertype FROM project WHERE id='$projectid'");
    $project_array = pdo_fetch_array($project);

    $cvsviewertype = strtolower($project_array['cvsviewertype']);
    $difffonction = 'get_' . $cvsviewertype . '_diff_url';

    if (function_exists($difffonction)) {
        return $difffonction($projecturl, $directory, $file, $revision);
    } else {
        // default is viewcvs
        return get_viewcvs_diff_url($projecturl, $directory, $file, $revision);
    }
}

/** Return the ViewCVS URL */
function get_viewcvs_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '&rev=' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the Trac URL */
function get_trac_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/changeset/' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the Mercurial URL */
function get_hgweb_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/rev/' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the Fisheye URL */
function get_fisheye_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '?r=' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the P4Web URL */
function get_p4web_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $project_url . '?ac=64&sr=' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the CVSTrac URL */
function get_cvstrac_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = ''; // not implemented
    return make_cdash_url($revision_url);
}

/** Return the Stash revision URL */
function get_stash_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/commits/' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the ViewVC URL */
function get_viewvc_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '?view=rev&revision=' . $revision;
    return make_cdash_url($diff_url);
}

/** Return the viewVC 1-1 url */
function get_viewvc_1_1_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '?view=rev&revision=' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the WebSVN URL */
function get_websvn_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '?view=revision&revision=' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the SourceForge Allura URL */
function get_allura_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the Loggerhead URL */
function get_loggerhead_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = ''; // not implemented
    return make_cdash_url($revision_url);
}

/** Return the GitWeb revision URL */
function get_gitweb_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . ';a=shortlog;h=' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the GitWeb revision URL */
function get_gitweb2_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/shortlog/' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the Gitorious revision URL */
function get_gitorious_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/commits/';
    if ($priorrevision) {
        $revision_url .= $priorrevision . '..';
    }
    $revision_url .= $revision;
    return make_cdash_url($revision_url);
}

/** Return the GitHub revision URL */
function get_github_revision_url($projecturl, $revision, $priorrevision)
{
    if ($priorrevision) {
        $revision_url = "$projecturl/compare/$priorrevision...$revision";
    } else {
        $revision_url = "$projecturl/commit/$revision";
    }
    return make_cdash_url($revision_url);
}

/** Return the GitLab revision URL */
function get_gitlab_revision_url($projecturl, $revision, $priorrevision)
{
    return get_github_revision_url($projecturl, $revision, $priorrevision);
}

/** Return the cgit revision URL */
function get_cgit_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/log/?id=' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the Redmine revision URL */
function get_redmine_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/revisions/' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the Phabricator revision URL */
function get_phab_git_revision_url($projecturl, $revision, $priorrevision)
{
    $revision_url = $projecturl . '/commit/' . $revision;
    return make_cdash_url($revision_url);
}

/** Return the global revision URL (not file based) for a repository */
function get_revision_url($projectid, $revision, $priorrevision)
{
    if (!is_numeric($projectid)) {
        return;
    }

    $project = pdo_query("SELECT cvsviewertype,cvsurl FROM project WHERE id='$projectid'");
    $project_array = pdo_fetch_array($project);
    $projecturl = $project_array['cvsurl'];

    $cvsviewertype = strtolower($project_array['cvsviewertype']);
    $revisionfonction = 'get_' . $cvsviewertype . '_revision_url';

    if (function_exists($revisionfonction)) {
        return $revisionfonction($projecturl, $revision, $priorrevision);
    } else {
        // default is viewcvs
        return get_viewcvs_revision_url($projecturl, $revision, $priorrevision);
    }
}

function linkify_compiler_output($projecturl, $source_dir, $revision, $compiler_output)
{
    // set a reasonable default revision if none was specified
    if (empty($revision)) {
        $revision = 'master';
    }

    $repo_link = "<a href='$projecturl/blob/$revision";
    $pattern = "&$source_dir/([a-zA-Z0-9_\.\-\\/]+):(\d+)&";
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
function post_pull_request_comment($projectid, $pull_request, $comment, $cdash_url)
{
    if (!is_numeric($projectid)) {
        return;
    }

    $project = pdo_query("SELECT cvsviewertype,cvsurl FROM project WHERE id='$projectid'");
    $project_array = pdo_fetch_array($project);
    $projecturl = $project_array['cvsurl'];

    $cvsviewertype = strtolower($project_array['cvsviewertype']);
    $PR_func = 'post_' . $cvsviewertype . '_pull_request_comment';

    if (function_exists($PR_func)) {
        $PR_func($projectid, $pull_request, $comment, $cdash_url);
        return;
    } else {
        add_log("PR commenting not implemented for '$cvsviewertype'",
            'post_pull_request_comment()', LOG_WARNING);
    }
}

/** Convert GitHub repository viewer URL into corresponding API URL. */
function get_github_api_url($github_url)
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

function post_github_pull_request_comment($projectid, $pull_request, $comment, $cdash_url)
{
    $row = pdo_single_row_query(
        "SELECT url, username, password FROM repositories
    LEFT JOIN project2repositories AS p2r ON (p2r.repositoryid=repositories.id)
    WHERE p2r.projectid='$projectid'");

    if (empty($row) || !isset($row['url']) || !isset($row['username']) ||
        !isset($row['password'])
    ) {
        add_log("Missing repository info for project #$projectid",
            'post_github_pull_request_comment()', LOG_WARNING);
        return;
    }

    /* Massage our github url into the API endpoint that we need to POST to:
     * .../repos/:owner/:repo/issues/:number/comments
     */
    $post_url = get_github_api_url($row['url']);
    $post_url .= "/issues/$pull_request/comments";

    // Format our comment using Github's comment syntax.
    $message = "[$comment]($cdash_url)";

    $data = array('body' => $message);
    $data_string = json_encode($data);

    $ch = curl_init($post_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
    );
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $userpwd = $row['username'] . ':' . $row['password'];
    curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');

    global $CDASH_TESTING_MODE;
    $retval = curl_exec($ch);
    if ($retval === false) {
        add_log(
            'cURL error: ' . curl_error($ch),
            'post_github_pull_request_comment',
            LOG_ERR, $projectid);
    } elseif ($CDASH_TESTING_MODE) {
        $matches = array();
        preg_match("#/comments/(\d+)#", $retval, $matches);
        add_log(
            'Just posted comment #' . $matches[1],
            'post_github_pull_request_comment',
            LOG_DEBUG, $projectid);
    }

    curl_close($ch);
}

/** Find changes for a "version only" update. */
function perform_version_only_diff($update, $projectid)
{
    // Return early if we don't have a current revision.
    if (empty($update->Revision)) {
        return;
    }

    // Return early if this project doesn't have a remote repository viewer.
    $project = new Project();
    $project->Id = $projectid;
    $project->Fill();
    if (strlen($project->CvsUrl) === 0 || strlen($project->CvsViewerType) === 0) {
        return;
    }

    // Return early if we don't have an implementation for this repository viewer.
    $viewertype = strtolower($project->CvsViewerType);
    $function_name = 'perform_' . $viewertype . '_version_only_diff';
    if (!function_exists($function_name)) {
        return;
    }

    // Return early if we don't have a previous build to compare against.

    $build = new Build();
    $build->Id = $update->BuildId;
    $previous_buildid = $build->GetPreviousBuildId();
    if ($previous_buildid < 1) {
        return;
    }

    // Get the revision for the previous build.
    $pdo = get_link_identifier()->getPdo();
    $stmt = $pdo->prepare(
            'SELECT revision FROM buildupdate AS bu
            INNER JOIN build2update AS b2u ON (b2u.updateid=bu.id)
            WHERE b2u.buildid=?');
    pdo_execute($stmt, [$previous_buildid]);
    $row = $stmt->fetch();
    if (empty($row) || !isset($row['revision'])) {
        return;
    }
    $previous_revision = $row['revision'];
    if (empty($previous_revision)) {
        return;
    }

    // Record the previous revision in the buildupdate table.
    $stmt = $pdo->prepare(
        'UPDATE buildupdate SET priorrevision=? WHERE id=?');
    pdo_execute($stmt, [$previous_revision, $update->UpdateId]);

    // Call the implementation specific to this repository viewer.
    $update->Append = true;
    return $function_name($project, $update, $previous_revision);
}

function perform_github_version_only_diff($project, $update, $previous_revision)
{
    require_once 'include/memcache_functions.php';
    global $CDASH_MEMCACHE_ENABLED, $CDASH_MEMCACHE_PREFIX, $CDASH_MEMCACHE_SERVER;

    $current_revision = $update->Revision;

    // Check if we have a Github account associated with this project.
    // If so, we are much less likely to get rate-limited by the API.
    $auth = array();
    $repositories = $project->GetRepositories();
    foreach ($repositories as $repo) {
        if (strlen($repo['username']) > 0 && strlen($repo['password']) > 0) {
            $auth = ['auth' => [$repo['username'], $repo['password']]];
            break;
        }
    }

    // Connect to memcache.
    if ($CDASH_MEMCACHE_ENABLED) {
        list($server, $port) = $CDASH_MEMCACHE_SERVER;
        $memcache = cdash_memcache_connect($server, $port);
        // Disable memcache for this request if it fails to connect.
        if ($memcache === false) {
            $CDASH_MEMCACHE_ENABLED = false;
        }
    }

    // Check if we've memcached the difference between these two revisions.
    $diff_response = null;
    $diff_key = "$CDASH_MEMCACHE_PREFIX:$project->Name:$current_revision:$previous_revision";
    if ($CDASH_MEMCACHE_ENABLED) {
        $cached_response = cdash_memcache_get($memcache, $diff_key);
        if ($cached_response !== false) {
            $diff_response = $cached_response;
        }
    }

    if (is_null($diff_response)) {
        // Use the GitHub API to find what changed between these two revisions.
        // This API endpoint takes the following form:
        // GET /repos/:owner/:repo/compare/:base...:head
        $base_api_url = get_github_api_url($project->CvsUrl);
        $client = new GuzzleHttp\Client();
        $api_url = "$base_api_url/compare/$previous_revision...$current_revision";
        try {
            $response = $client->request('GET', $api_url, $auth);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            // Typically this occurs due to a local commit that GitHub does not
            // know about.
            add_log($e->getMessage(),
                    "perform_github_version_only_diff", LOG_WARNING,
                    $project->Id);
            return;
        }
        $diff_response = strval($response->getBody());

        // Cache the response from the GitHub API for 24 hours.
        if ($CDASH_MEMCACHE_ENABLED) {
            cdash_memcache_set($memcache, $diff_key, $diff_response, 60 * 60 * 24);
        }
    }

    $response_array = json_decode($diff_response, true);

    // To do anything meaningful here our response needs to tell us about commits
    // and the files that changed.  Abort early if either of these pieces of
    // information are missing.
    if (!is_array($response_array) ||
            !array_key_exists('commits', $response_array) ||
            !array_key_exists('files', $response_array)) {
        return;
    }

    // Discard merge commits.  We want to assign credit to the author who did
    // the actual work, not the approver who clicked the merge button.
    foreach ($response_array['commits'] as $idx => $commit) {
        if (strpos($commit['commit']['message'], 'Merge pull request')
                !== false) {
            unset($response_array['commits'][$idx]);
        }
    }

    // If we still have more than one commit, we'll need to perform follow-up
    // API calls to figure out which commit was likely responsible for each
    // changed file.
    $multiple_commits = false;
    if (count($response_array['commits']) > 1) {
        $multiple_commits = true;
        // Generate list of commits contained by this changeset in reverse order
        // (most recent first).
        $list_of_commits = array_reverse($response_array['commits']);

        // Also maintain a local cache of what files were changed by each commit.
        // This prevents us from hitting the GitHub API more than necessary.
        $cached_commits = array();
    }

    $pdo = get_link_identifier()->getPdo();

    // Find the commit that changed each file.
    foreach ($response_array['files'] as $modified_file) {
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
                $stmt = $pdo->prepare(
                        'SELECT DISTINCT revision FROM updatefile
                        WHERE filename=?');
                pdo_execute($stmt, [$modified_file['filename']]);
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

                    $commit_response = null;
                    $commit_key = "$CDASH_MEMCACHE_PREFIX:$project->Name:$sha";
                    if ($CDASH_MEMCACHE_ENABLED) {
                        // Check memcache if it is enabled before hitting
                        // the GitHub API.
                        $cached_response = cdash_memcache_get($memcache, $commit_key);
                        if ($cached_response !== false) {
                            $commit_response = $cached_response;
                        }
                    }

                    if (is_null($commit_response)) {
                        $api_url = "$base_api_url/commits/$sha";
                        try {
                            $r = $client->request('GET', $api_url, $auth);
                        } catch (GuzzleHttp\Exception\ClientException $e) {
                            add_log($e->getMessage(),
                                    "perform_github_version_only_diff", LOG_ERROR,
                                    $project->Id);
                            break;
                        }
                        $commit_response = strval($r->getBody());

                        if ($CDASH_MEMCACHE_ENABLED) {
                            // Cache this response for 24 hours.
                            cdash_memcache_set($memcache, $commit_key, $commit_response, 60 * 60 * 24);
                        }
                    }

                    $commit_array = json_decode($commit_response, true);

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
            $commit = $response_array['commits'][0];
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
        $updateFile->PriorRevision = $previous_revision;
        $updateFile->Status = 'MODIFIED';
        $update->AddFile($updateFile);
    }

    $update->Insert();
    return true;
}

/** Create a bugtracker issue for a broken build. */
function generate_bugtracker_new_issue_link($build, $project)
{
    // Make sure that we have a valid build.
    if (!$build->Filled && !$build->Exists()) {
        return false;
    }

    // Return early if we don't have an implementation for this type
    // of bug tracker.
    $project->Fill();
    $function_name = "generate_{$project->BugTrackerType}_new_issue_link";
    if (!function_exists($function_name)) {
        return false;
    }

    // Use our email functions to generate a message body and title for this build.
    require_once('include/sendemail.php');
    $errors = check_email_errors($build->Id, false, 0, true);
    $emailtext = [];
    foreach ($errors as $errorkey => $nerrors) {
        if ($nerrors < 1) {
            continue;
        }
        $emailtext['nerror'] = 1;
        $emailtext['summary'][$errorkey] =
            get_email_summary($build->Id, $errors, $errorkey, 1, 500, 0, false);
        $emailtext['category'][$errorkey] = $nerrors;
    }
    $msg_parts = generate_broken_build_message($emailtext, $build, $project);
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
    return $function_name($project->BugTrackerNewIssueUrl, $title, $body, $users);
}

function generate_Buganizer_new_issue_link($baseurl, $title, $body, $users)
{
    $url = "$baseurl&type=BUG&priority=P0&severity=S0&title=$title&description=$body";
    if (!empty($users)) {
        $cc = "&cc=";
        $cc .= implode(',', $users);
        $url .= $cc;
    }
    return $url;
}

function generate_JIRA_new_issue_link($baseurl, $title, $body, $users)
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

function generate_GitHub_new_issue_link($baseurl, $title, $body, $users)
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
