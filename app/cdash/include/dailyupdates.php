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

// get_related_dates takes a projectname and basedate as input
// and produces an array of related dates and times based on:
// the input, the project's nightly start time, now
//

require_once 'include/pdo.php';
include_once 'include/common.php';
require_once 'include/cdashmail.php';

use CDash\Config;
use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;
use CDash\Model\Project;
use CDash\Model\UserProject;

@set_time_limit(0);

function get_related_dates($projectnightlytime, $basedate)
{
    $dates = array();
    $nightlytime = $projectnightlytime;

    if (!isset($basedate) || strlen($basedate) == 0) {
        $basedate = gmdate(FMT_DATE);
    }

    // Convert the nightly time into GMT
    $nightlytime = gmdate(FMT_TIME, strtotime($nightlytime));

    $nightlyhour = time2hour($nightlytime);
    $nightlyminute = time2minute($nightlytime);
    $nightlysecond = time2second($nightlytime);
    $basemonth = date2month($basedate);
    $baseday = date2day($basedate);
    $baseyear = date2year($basedate);

    $dates['nightly+2'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
        $basemonth, $baseday + 2, $baseyear);
    $dates['nightly+1'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
        $basemonth, $baseday + 1, $baseyear);
    $dates['nightly-0'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
        $basemonth, $baseday, $baseyear);
    $dates['nightly-1'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
        $basemonth, $baseday - 1, $baseyear);
    $dates['nightly-2'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
        $basemonth, $baseday - 2, $baseyear);

    // Snapshot of "now"
    //
    $currentgmtime = time();
    $currentgmdate = gmdate(FMT_DATE, $currentgmtime);

    // Find the most recently past nightly time:
    //
    $todaymonth = date2month($currentgmdate);
    $todayday = date2day($currentgmdate);
    $todayyear = date2year($currentgmdate);
    $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
        $todaymonth, $todayday, $todayyear);
    while ($currentnightly > $currentgmtime) {
        $todayday = $todayday - 1;
        $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
            $todaymonth, $todayday, $todayyear);
    }

    $dates['now'] = $currentgmtime;
    $dates['most-recent-nightly'] = $currentnightly;
    $dates['today_utc'] = $currentgmdate;
    $dates['basedate'] = gmdate(FMT_DATE, $dates['nightly-0']);

    // CDash equivalent of DART1's "last rollup time"
    if ($dates['basedate'] === $dates['today_utc']) {
        // If it's today, it's now:
        $dates['last-rollup-time'] = $dates['now'];
    } else {
        // If it's not today, it's the nightly time on the basedate:
        $dates['last-rollup-time'] = $dates['nightly-0'];
    }
    return $dates;
}

/** */
function remove_directory_from_filename(&$filename)
{
    $npos = strrpos($filename, '/');

    if ($npos === false || $npos === 0) {
        $dir = '.';
    } else {
        $dir = substr($filename, 0, $npos);
        $filename = substr($filename, $npos + 1);
    }
    return $dir;
}

// If the string $root begins with one of the known cvs protocol
// indicators, then return true. Otherwise, return false.
//
function is_cvs_root($root)
{
    $npos = strpos($root, ':pserver:');
    if ($npos !== false && $npos === 0) {
        return true;
    }

    $npos = strpos($root, ':ext:');
    if ($npos !== false && $npos === 0) {
        return true;
    }
    return false;
}

/** Get the CVS repository commits */
function get_cvs_repository_commits($cvsroot, $dates)
{
    $commits = array();

    // Compute time stamp range expressed as $fromtime and $totime for cvs
    //
    $fromtime = gmdate(FMT_DATETIMESTD, $dates['nightly-1'] + 1) . ' GMT';
    $totime = gmdate(FMT_DATETIMESTD, $dates['nightly-0']) . ' GMT';

    $npos = strpos($cvsroot, '/');
    $npos2 = strlen($cvsroot);
    $module = substr($cvsroot, $npos + strlen('/cvsroot/'));
    $Idbase = substr($cvsroot, $npos, $npos2 - $npos) . '/'; // . $module . '/';

    // Do a shell_exec of a cvs rlog call to get the changes in the requested
    // date range:
    //
    $raw_output = `cvs -d $cvsroot rlog -S -N -d "$fromtime<$totime" $module 2>&1`;

    // Process as an array of lines:
    //
    $lines = explode("\n", $raw_output);

    // Compute summary data:
    //
    $current_author = '';
    $current_comment = '';
    $current_directory = '';
    $current_filename = '';
    $current_revision = '';
    $current_time = 0;

    $line_number = 0;
    $in_revision_chunk = 0;
    $in_revision_chunk_line_number = 0;
    $total_revisions = 0;

    foreach ($lines as $vv) {
        $num_revisions = 0;
        $line_number = $line_number + 1;

        $npos = strpos($vv, '--------------------');
        if ($npos !== false && $npos === 0) {
            if ($in_revision_chunk === 1) {
                $commit = array();
                $commit['directory'] = $current_directory;
                $commit['filename'] = $current_filename;
                $commit['revision'] = $current_revision;
                $commit['priorrevision'] = '';
                $commit['time'] = $current_time;
                $commit['author'] = $current_author;
                $commit['comment'] = $current_comment;
                $commits[$current_directory . '/' . $current_filename . ';' . $current_revision] = $commit;
            }

            $current_comment = '';
            $in_revision_chunk = 1;
            $in_revision_chunk_line_number = $line_number;

            $total_revisions = $total_revisions + 1;
        }

        if ($in_revision_chunk === 0) {
            $npos = strpos($vv, 'RCS file: ' . $Idbase);
            if ($npos !== false && $npos === 0) {
                $npos = strlen('RCS file: ' . $Idbase);
                $npos2 = strlen($vv) - 2; // 2 == strlen(",v") at the end of the "RCS file:" line

                $current_filename = substr($vv, $npos, $npos2 - $npos);

                // We need to remove the current directory
                // which is the directory of the project
                $p = strpos($current_filename, '/');
                if ($p !== false) {
                    $current_filename = substr($current_filename, $p + 1);
                }

                $current_directory = remove_directory_from_filename($current_filename);
            }
        }

        if ($in_revision_chunk === 1) {
            // $in_revision_chunk_line_number + 1
            $npos = strpos($vv, 'revision ');
            if ($npos !== false && $npos === 0 && $line_number === $in_revision_chunk_line_number + 1) {
                $npos = strlen('revision ');
                $npos2 = strlen($vv);
                $current_revision = substr($vv, $npos, $npos2 - $npos);
            }

            // $in_revision_chunk_line_number + 2
            $npos = strpos($vv, 'date: ');
            if ($npos !== false && $npos === 0 && $line_number === $in_revision_chunk_line_number + 2) {
                $npos2 = strpos($vv, '; ', $npos);
                $current_time = gmdate(FMT_DATETIME, strtotime(substr($vv, $npos + 6, $npos2 - ($npos + 6)))); // 6 == strlen("date: ")

                // Lines that begin with "date: " also contain "author: "
                //
                $npos = strpos($vv, 'author: ');
                if ($npos !== false) {
                    $npos2 = strpos($vv, '; ', $npos);
                    $current_author = substr($vv, $npos + 8, $npos2 - ($npos + 8)); // 8 == strlen("author: ")
                }
            }

            // still $in_revision_chunk?
            $npos = strpos($vv, '====================');
            if ($npos !== false && $npos === 0) {
                $commit = array();
                $commit['directory'] = $current_directory;
                $commit['filename'] = $current_filename;
                $commit['revision'] = $current_revision;
                $commit['priorrevision'] = '';
                $commit['time'] = $current_time;
                $commit['author'] = $current_author;
                $commit['comment'] = $current_comment;
                $commits[$current_directory . '/' . $current_filename . ';' . $current_revision] = $commit;

                // Switching out of revision chunk. Clear current_comment:
                //
                $current_comment = '';
                $in_revision_chunk = 0;
            }

            if ($in_revision_chunk === 1 && $line_number > $in_revision_chunk_line_number + 2) {
                if ($current_comment === '') {
                    $current_comment = $vv;
                } else {
                    $current_comment = $current_comment . "\n" . $vv;
                }
            }
        }
    }
    return $commits;
}

/** Get the Perforce repository commits */
function get_p4_repository_commits($root, $branch, $dates)
{
    $config = Config::getInstance();
    $commits = array();
    $users = array();

    // Add the command line specified by the user in the "Repository" field
    // of the project settings "Repository" tab and set the message language
    // to be English
    $p4command = '"' . $config->get('CDASH_P4_COMMAND') . '" ' . $root . ' -L en';

    // Perforce needs the dates separated with / and not with -
    $fromtime = str_replace('-', '/', gmdate(FMT_DATETIMESTD, $dates['nightly-1'] + 1));
    $totime = str_replace('-', '/', gmdate(FMT_DATETIMESTD, $dates['nightly-0']));

    // "Branch" is the file spec for the root directory of the P4 client
    // Example: //depot/myproject/...
    $raw_output = `$p4command changes $branch@"$fromtime","$totime" 2>&1`;
    $lines = explode("\n", $raw_output);

    // Enumerate the changelists between the two given dates and p4 describe
    // them to get a list of files commited
    $currentrevision = '';
    foreach (array_reverse($lines) as $line) {
        if (preg_match('/^Change ([0-9]+) on/', $line, $matches)) {
            $currentrevision = $matches[1];
            $raw_output = `$p4command describe -s $matches[1]`;
            $describe_lines = explode("\n", $raw_output);

            $commit = array();
            $comment = '';

            // Parse the changelist description and add each file modified to the
            // commits list
            foreach ($describe_lines as $dline) {
                // Commit header
                if (preg_match('/^Change ([0-9]+) by (.+)@(.+) on (.*)$/', $dline, $matches)) {
                    $commit['revision'] = $matches[1];
                    $commit['priorrevision'] = '';
                    $commit['comment'] = '';
                    $commit['time'] = gmdate(FMT_DATETIME, strtotime($matches[4]));

                    $user = $matches[2];
                    if (isset($users[$user]) && $users[$user] != '') {
                        $commit['author'] = $users[$user]['name'];
                        $commit['email'] = $users[$user]['email'];
                    } else {
                        $raw_output = `$p4command users -m 1 $user`;
                        if (preg_match("/^(.+) <(.*)> \((.*)\) accessed (.*)$/", $raw_output, $matches)) {
                            $newuser = array();
                            $newuser['username'] = $matches[1];
                            $newuser['email'] = $matches[2];
                            $newuser['name'] = $matches[3];
                            $newuser['time'] = $matches[4];
                            $users[$user] = $newuser;

                            $commit['author'] = $newuser['name'];
                            $commit['email'] = $newuser['email'];
                        }
                    }
                } // File specification
                elseif (preg_match('/^\\.\\.\\. (.*)#[0-9]+ ([^ ]+)$/', $dline, $matches)) {
                    $commit['filename'] = $matches[1];
                    $commit['directory'] = remove_directory_from_filename($commit['filename']);
                    $commits[$directory . '/' . $filename . ';' . $commit['revision']] = $commit;
                } // Anything else that begins with a tab is a comment line
                elseif (strlen($dline) > 0 && $dline[0] == "\t") {
                    $commit['comment'] = $commit['comment'] . trim(substr($dline, 1)) . "\n";
                }
            }
        }
    }

    $results['currentrevision'] = $currentrevision;
    $results['commits'] = $commits;
    return $results;
}

/** Get the GIT repository commits */
function get_git_repository_commits($gitroot, $dates, $branch, $previousrevision)
{
    $config = Config::getInstance();
    $commits = array();

    $gitcommand = $config->get('CDASH_GIT_COMMAND');
    $gitlocaldirectory = $config->get('CDASH_DEFAULT_GIT_DIRECTORY');

    // Check that the default git directory exists and is writable
    if (empty($gitlocaldirectory) || !is_writable($gitlocaldirectory)) {
        add_log('CDASH_DEFAULT_GIT_DIRECTORY is not set in config or not writable.', 'get_git_repository_commits');
        $results['commits'] = $commits;
        return $results;
    }

    $pos = strrpos($gitroot, '/');
    $gitdirectory = substr($gitroot, $pos + 1);
    $gitdir = $gitlocaldirectory . '/' . $gitdirectory;

    // If the current directory doesn't exist we create it
    if (!file_exists($gitdir)) {
        // If the bare repository doesn't exist we clone it
        $command = 'cd "' . $gitlocaldirectory . '" && "' . $gitcommand . '" clone --bare ' . $gitroot . ' ' . $gitdirectory;
        $raw_output = `$command`;
    }

    // Update the current bare repository
    $command = '"' . $gitcommand . '" --git-dir="' . $gitdir . '" fetch ' . $gitroot;
    if ($branch != '') {
        $command .= ' +' . $branch . ':' . $branch;
    }

    $raw_output = `$command`;

    // Get what changed during that time
    if ($branch == '') {
        $branch = 'FETCH_HEAD';
    }

    $command = '"' . $gitcommand . '" --git-dir="' . $gitdir . '" rev-parse ' . $branch;
    $currentrevision = `$command`;
    $results['currentrevision'] = trim($currentrevision);

    // Find the previous day version
    if ($previousrevision != '') {
        // Compare with the fetch head for now
        $command = '"' . $gitcommand . '" --git-dir="' . $gitdir . '" whatchanged ' . $previousrevision . '..' . $currentrevision . ' --pretty=medium ' . $branch;
    } else {
        $fromtime = gmdate(FMT_DATETIMESTD, $dates['nightly-1'] + 1) . ' GMT';
        $totime = gmdate(FMT_DATETIMESTD, $dates['nightly-0']) . ' GMT';

        // Compare with the fetch head for now
        $command = '"' . $gitcommand . '" --git-dir="' . $gitdir . '" whatchanged --since="' . $fromtime . '" --until="' . $totime . '" --pretty=medium ' . $branch;
    }

    $raw_output = `$command`;

    $lines = explode("\n", $raw_output);

    foreach ($lines as $line) {
        if (substr($line, 0, 6) == 'commit') {
            $commit = array();
            $commit['revision'] = substr($line, 7);
            $commit['priorrevision'] = '';
            $commit['comment'] = '';
        } elseif (substr($line, 0, 7) == 'Author:') {
            $pos = strpos($line, '<');
            $pos2 = strpos($line, '>', $pos);
            $commit['author'] = trim(substr($line, 7, $pos - 7));
            $commit['email'] = substr($line, $pos + 1, $pos2 - $pos - 1);
        } elseif (substr($line, 0, 5) == 'Date:') {
            $commit['time'] = gmdate(FMT_DATETIME, strtotime(substr($line, 7)));
        } elseif (strlen($line) > 0 && $line[0] == ':') {
            $pos = strrpos($line, "\t");
            $filename = substr($line, $pos + 1);
            $posdir = strrpos($filename, '/');
            $commit['directory'] = '';
            $commit['filename'] = $filename;
            if ($posdir !== false) {
                $commit['directory'] = substr($filename, 0, $posdir);
                $commit['filename'] = substr($filename, $posdir + 1);
            }
            // add the current commit
            if (isset($commit['filename']) && $commit['filename'] != '') {
                $commits[$commit['directory'] . '/' . $commit['filename'] . ';' . $commit['revision']] = $commit;
            }
        } elseif (strlen($line) > 0 && $line[0] == ' ') {
            $commit['comment'] .= trim($line) . "\n";
        }
    }

    $results['commits'] = $commits;
    return $results;
}

/** Get the SVN repository commits */
function get_svn_repository_commits($svnroot, $dates, $username = '', $password = '')
{
    $commits = array();

    // To pick up all possible changes, the svn log query has to go back
    // *2* days -- svn log (for date queries) spits out all changes since
    // the beginning of the date, there is no syntax for passing time
    // stamps. Then, we have to filter the results to include only those
    // changes that fall in the $fromtime, $totime range...
    //
    // So call get_dates twice to get yesterday ($fromdate) and again to
    // get the low end of the svn log date range ($daybefore)...
    //
    $svnrevision = '{' . gmdate(FMT_DATE, $dates['nightly-2']) . '}:{' .
        gmdate(FMT_DATE, $dates['nightly+1']) . '}';

    $fromtime = gmdate(FMT_DATETIMESTD, $dates['nightly-1'] + 1) . ' GMT';
    $totime = gmdate(FMT_DATETIMESTD, $dates['nightly-0']) . ' GMT';

    $ustring = (isset($username) && strlen($username) != 0) ? "--username $username" : '';
    $pstring = (isset($password) && strlen($password) != 0) ? "--password $password" : '';

    $raw_output = `svn log --trust-server-cert --non-interactive $ustring $pstring $svnroot -r $svnrevision -v 2>&1`;
    //$raw_output = `svn help log`;

    $lines = explode("\n", $raw_output);

    $gathered_file_lines = array();
    $current_author = '';
    $current_comment = '';
    $current_directory = '';
    $current_filename = '';
    $current_revision = '';
    $current_time = 0;

    $line_number = 0;
    $last_chunk_line_number = 0;
    $in_list_of_filenames = 0;
    foreach ($lines as $vv) {
        $line_number = $line_number + 1;

        $npos = strpos($vv, '--------------------');
        if ($npos !== false && $npos === 0) {
            if ($line_number > 1) {
                if ($current_time > $fromtime && $current_time <= $totime) {
                    foreach ($gathered_file_lines as $ff) {
                        $previous_revision = '';

                        // Look if we have a A or a M
                        if (strpos(substr($ff, 0, 5), 'A') !== false) {
                            $previous_revision = '-1'; // newly added file so we marked it as no prior revision
                        }

                        // Skip the '   M ' at the beginning of the filename output lines:
                        //
                        $current_filename = substr($ff, 5);

                        // If there is " (from /blah/blah.h:42)" notation at end of filename,
                        // strip it off:
                        //
                        $npos = strpos($current_filename, ' (from ');
                        if ($npos !== false && $npos !== 0) {
                            $current_filename = substr($current_filename, 0, $npos);
                        }

                        // Remove the first directory
                        $npos = strpos($current_filename, '/', 2);
                        if ($npos !== false && $npos !== 0) {
                            $current_filename = substr($current_filename, $npos + 1);
                        }

                        $current_directory = remove_directory_from_filename($current_filename);

                        $commit = array();
                        $commit['directory'] = $current_directory;
                        $commit['filename'] = $current_filename;
                        $commit['revision'] = $current_revision;
                        $commit['priorrevision'] = $previous_revision;
                        $commit['time'] = $current_time;
                        $commit['author'] = $current_author;
                        $commit['comment'] = $current_comment;
                        $commits[$current_directory . '/' . $current_filename . ';' . $current_revision] = $commit;
                    }
                } else {
                    //echo "excluding: '" . $current_time . "' (" . gmdate(FMT_DATETIMEMS, $current_time) . ")<br/>";
                }
                $gathered_file_lines = array();
            }
            $current_comment = '';
            $last_chunk_line_number = $line_number;
            //echo "<br/>";
        }

        if ($line_number === $last_chunk_line_number + 1) {
            $npos = strpos($vv, ' | ');
            if ($npos !== false) {
                $current_revision = substr($vv, 1, $npos - 1); // 1 == skip the 'r' at the beginning...
                //echo "current_revision: '" . $current_revision . "'<br/>";

                $npos2 = strpos($vv, ' | ', $npos + 3);
                if ($npos2 !== false) {
                    $current_author = substr($vv, $npos + 3, $npos2 - ($npos + 3));
                    //echo "current_author: '" . $current_author . "'<br/>";
                    $npos = $npos2;

                    $npos2 = strpos($vv, ' (', $npos + 3);
                    if ($npos2 !== false) {
                        $current_date = substr($vv, $npos + 3, $npos2 - ($npos + 3));
                        //echo "current_date: '" . $current_date . "'<br/>";

                        $current_time = gmdate(FMT_DATETIME, strtotime($current_date));
                        //echo "date: '" . $current_time . "' (" . date(FMT_DATETIMEMS, $current_time) . ")<br/>";
                        //echo "gmdate: '" . $current_time . "' (" . gmdate(FMT_DATETIMEMS, $current_time) . ")<br/>";

                        $npos2 = strpos($vv, ' | ', $npos + 3);
                        $npos = $npos2;
                        if ($npos2 !== false) {
                            $current_line_count = substr($vv, $npos + 3);
                            $npos2 = strpos($current_line_count, ' line');
                            $current_line_count = substr($current_line_count, 0, $npos2);
                            //echo "current_line_count: '" . $current_line_count . "'<br/>";
                        }
                    }
                }
            }
        }

        if ($in_list_of_filenames === 0 && $line_number > $last_chunk_line_number + 2) {
            $in_comment = 1;

            //echo "gather comment line: '" . $vv . "'<br/>";
            if ($current_comment === '') {
                $current_comment = $vv;
            } else {
                $current_comment = $current_comment . "\n" . $vv;
            }
        }

        if ($in_list_of_filenames === 1) {
            if (strlen($vv) === 0) {
                // Empty line signals the end of the list of filenames:
                //
                $in_list_of_filenames = 0;
            } else {
                $gathered_file_lines[] = $vv;
            }
        }

        if ($line_number === $last_chunk_line_number + 2) {
            $in_list_of_filenames = 1;
        }
    }
    return $commits;
}

/** Get BZR repository commits */
function get_bzr_repository_commits($bzrroot, $dates)
{
    $commits = array();

    $fromtime = gmdate(FMT_DATETIMESTD, $dates['nightly-1'] + 1) . ' GMT';
    $totime = gmdate(FMT_DATETIMESTD, $dates['nightly-0']) . ' GMT';

    $raw_output = `bzr log -v --xml -r date:"$fromtime"..date:"$totime" $bzrroot 2>&1`;

    $doc = new DomDocument;
    $doc->loadXML($raw_output);
    $logs = $doc->getElementsByTagName('log');

    foreach ($logs as $log) {
        $current_author = $log->getElementsByTagName('committer')->item(0)->nodeValue;
        // remove email from author and strip result
        $current_author = trim(substr($current_author, 0, strpos($current_author, '<')));

        $current_comment = $log->getElementsByTagName('message')->item(0)->nodeValue;
        $current_time = gmdate(FMT_DATETIMEMS, strtotime($log->getElementsByTagName('timestamp')->item(0)->nodeValue));
        $current_revision = $log->getElementsByTagName('revno')->item(0)->nodeValue;

        $files = $log->getElementsByTagName('file');
        foreach ($files as $file) {
            $current_filename = $file->nodeValue;
            $current_directory = remove_directory_from_filename($current_filename);
            $commit = array();
            $commit['directory'] = $current_directory;
            $commit['filename'] = $current_filename;
            $commit['revision'] = $current_revision;
            $commit['priorrevision'] = '';
            $commit['time'] = $current_time;
            $commit['author'] = $current_author;
            $commit['comment'] = $current_comment;
            $commits[$current_directory . '/' . $current_filename . ';' . $current_revision] = $commit;
        }
    }
    return $commits;
}

// Return an array of arrays. Each entry in the returned array will
// have the following named elements:
//   directory, filename, revision, time, author, comment
//
function get_repository_commits($projectid, $dates)
{
    global $xml;
    $roots = array();

    // Find the repository
    $repositories = pdo_query("SELECT repositories.url,repositories.username,repositories.password,repositories.branch
                        FROM repositories,project2repositories
                        WHERE repositories.id=project2repositories.repositoryid
                        AND project2repositories.projectid='$projectid'");

    $cvsviewers = pdo_query("SELECT cvsviewertype FROM project
                        WHERE id='$projectid'");

    $cvsviewers_array = pdo_fetch_array($cvsviewers);
    $cvsviewer = $cvsviewers_array[0];

    // Start with an empty array:
    $commits = array();

    while ($repositories_array = pdo_fetch_array($repositories)) {
        $root = $repositories_array['url'];
        $username = $repositories_array['username'];
        $password = $repositories_array['password'];

        if (is_cvs_root($root)) {
            $new_commits = get_cvs_repository_commits($root, $dates);
        } else {
            if ($cvsviewer == 'loggerhead') {
                $new_commits = get_bzr_repository_commits($root, $dates);
            } elseif ($cvsviewer == 'p4web') {
                $branch = $repositories_array['branch'];
                $results = get_p4_repository_commits($root, $branch, $dates);

                // Update the current revision
                if (isset($results['currentrevision'])) {
                    $currentdate = gmdate(FMT_DATE, $dates['nightly-0']);
                    $prevrev = pdo_query("UPDATE dailyupdate SET revision='" . $results['currentrevision'] . "'
                                WHERE projectid='$projectid' AND date='" . $currentdate . "'");
                    add_last_sql_error('get_repository_commits');
                }
                $new_commits = $results['commits'];
            } elseif ($cvsviewer == 'gitweb' || $cvsviewer == 'gitorious' || $cvsviewer == 'github') {
                $branch = $repositories_array['branch'];

                // Find the prior revision
                $previousdate = gmdate(FMT_DATE, $dates['nightly-1']);
                $prevrev = pdo_query("SELECT revision FROM dailyupdate
                              WHERE projectid='$projectid' AND date='" . $previousdate . "'");

                $previousrevision = '';
                if (pdo_num_rows($prevrev) > 0) {
                    $prevrev_array = pdo_fetch_array($prevrev);
                    $previousrevision = $prevrev_array[0];
                }

                $results = get_git_repository_commits($root, $dates, $branch, $previousrevision);

                // Update the current revision
                if (isset($results['currentrevision'])) {
                    $currentdate = gmdate(FMT_DATE, $dates['nightly-0']);
                    $prevrev = pdo_query("UPDATE dailyupdate SET revision='" . $results['currentrevision'] . "'
                                WHERE projectid='$projectid' AND date='" . $currentdate . "'");
                    add_last_sql_error('get_repository_commits');
                }
                $new_commits = $results['commits'];
            } else {
                $new_commits = get_svn_repository_commits($root, $dates, $username, $password);
            }
        }

        if (count($new_commits) > 0) {
            $commits = array_merge(array_values($commits), array_values($new_commits));
        }
    }
    return $commits;
}

/** Send email if expected build from last day have not been submitting */
function sendEmailExpectedBuilds($projectid, $currentstarttime)
{
    $config = Config::getInstance();
    $currentURI = get_server_URI();

    $currentEndUTCTime = gmdate(FMT_DATETIME, $currentstarttime);
    $currentBeginUTCTime = gmdate(FMT_DATETIME, $currentstarttime - 3600 * 24);
    $sql = "SELECT buildtype,buildname,siteid,groupid,site.name FROM (SELECT g.siteid,g.buildtype,g.buildname,g.groupid FROM build2grouprule as g  LEFT JOIN build as b ON(
          g.expected='1' AND (b.type=g.buildtype AND b.name=g.buildname AND b.siteid=g.siteid)
          AND b.projectid='$projectid' AND b.starttime>'$currentBeginUTCTime' AND b.starttime<'$currentEndUTCTime')
          WHERE (b.type is null AND b.name is null AND b.siteid is null)
          AND g.expected='1'
          AND g.starttime<'$currentBeginUTCTime' AND (g.endtime>'$currentEndUTCTime' OR g.endtime='1980-01-01 00:00:00')) as t1, buildgroup as bg, site
          WHERE t1.groupid=bg.id AND bg.projectid='$projectid' AND bg.starttime<'$currentBeginUTCTime' AND (bg.endtime>'$currentEndUTCTime' OR bg.endtime='1980-01-01 00:00:00')
          AND site.id=t1.siteid
          ";
    $build2grouprule = pdo_query($sql);
    $projectname = get_project_name($projectid);
    $summary = 'The following expected build(s) for the project *' . $projectname . "* didn't submit yesterday:\n";
    $missingbuilds = 0;

    $serverName = $config->getServer();

    while ($build2grouprule_array = pdo_fetch_array($build2grouprule)) {
        $builtype = $build2grouprule_array['buildtype'];
        $buildname = $build2grouprule_array['buildname'];
        $sitename = $build2grouprule_array['name'];
        $siteid = $build2grouprule_array['siteid'];
        $summary .= '* ' . $sitename . ' - ' . $buildname . ' (' . $builtype . ")\n";

        // Find the site maintainers
        $recipients = [];
        $emails = pdo_query('SELECT email FROM ' . qid('user') . ',site2user WHERE ' . qid('user') . ".id=site2user.userid AND site2user.siteid='$siteid'");
        while ($emails_array = pdo_fetch_array($emails)) {
            $recipients[] = $emails_array['email'];
        }

        if (!empty($recipients)) {
            $missingTitle = 'CDash [' . $projectname . '] - Missing Build for ' . $sitename;
            $missingSummary = 'The following expected build(s) for the project ' . $projectname . " didn't submit yesterday:\n";
            $missingSummary .= '* ' . $sitename . ' - ' . $buildname . ' (' . $builtype . ")\n";
            $missingSummary .= "\n" . $currentURI . '/index.php?project=' . urlencode($projectname) . "\n";
            $missingSummary .= "\n-CDash on " . $serverName . "\n";

            if (cdashmail($recipients, $missingTitle, $missingSummary)) {
                add_log('email sent to: ' . implode(', ', $recipients), 'sendEmailExpectedBuilds');
                return;
            } else {
                add_log('cannot send email to: ' . implode(', ', $recipients), 'sendEmailExpectedBuilds');
            }
        }
        $missingbuilds = 1;
    }

    // Send a summary email to the project administrator or users who want to receive notification
    // of missing builds
    if ($missingbuilds == 1) {
        $summary .= "\n" . $currentURI . '/index.php?project=' . urlencode($projectname) . "\n";
        $summary .= "\n-CDash on " . $serverName . "\n";

        $title = 'CDash [' . $projectname . '] - Missing Builds';

        // Find the site administrators or users who want to receive the builds
        $recipients = [];
        $emails = pdo_query('SELECT email FROM ' . qid('user') . ',user2project WHERE ' . qid('user') . ".id=user2project.userid
                         AND user2project.projectid='$projectid' AND (user2project.role='2' OR user2project.emailmissingsites=1)");
        while ($emails_array = pdo_fetch_array($emails)) {
            $recipients[] = $emails_array['email'];
        }

        // Send the email
        if (!empty($recipients)) {
            if (cdashmail($recipients, $title, $summary)) {
                add_log('email sent to: ' . implode(', ', $recipients), 'sendEmailExpectedBuilds');
                return;
            } else {
                add_log('cannot send email to: ' . implode(', ', $recipients), 'sendEmailExpectedBuilds');
            }
        }
    }
}

/** Remove the buildemail that have been there from more than 48h */
function cleanBuildEmail()
{
    include_once 'include/common.php';
    $now = date(FMT_DATETIME, time() - 3600 * 48);
    pdo_query("DELETE FROM buildemail WHERE time<'$now'");
}

/** Clean the usertemp table if more than 24hrs */
function cleanUserTemp()
{
    include_once 'include/common.php';
    $now = date(FMT_DATETIME, time() - 3600 * 24);
    pdo_query("DELETE FROM usertemp WHERE registrationdate<'$now'");
}

/** Send an email to administrator of the project for users who are not registered */
function sendEmailUnregisteredUsers($projectid, $cvsauthors)
{
    include_once 'include/common.php';
    $config = Config::getInstance();
    $unregisteredusers = array();
    foreach ($cvsauthors as $author) {
        if ($author == 'Local User') {
            continue;
        }

        $UserProject = new UserProject();
        $UserProject->RepositoryCredential = $author;
        $UserProject->ProjectId = $projectid;

        if (!$UserProject->FillFromRepositoryCredential()) {
            $unregisteredusers[] = $author;
        }
    }

    // Send the email if any
    if (count($unregisteredusers) > 0) {
        // Find the project administrators
        $recipients = [];
        $emails = pdo_query('SELECT email FROM ' . qid('user') . ',user2project WHERE ' . qid('user') . '.id=user2project.userid
                         AND user2project.projectid=' . qnum($projectid) . " AND user2project.role='2'");
        while ($emails_array = pdo_fetch_array($emails)) {
            $recipients[] = $emails_array['email'];
        }

        // Send the email
        if (!empty($recipients)) {
            $projectname = get_project_name($projectid);
            $serverName = $config->getServer();

            $title = 'CDash [' . $projectname . '] - Unregistered users';
            $body = 'The following users are checking in code but are not registered for the project ' . $projectname . ":\n";

            foreach ($unregisteredusers as $unreg) {
                $body .= '* ' . $unreg . "\n";
            }
            $body .= "\n You should register these users to your project. They are currently not receiving any emails from CDash.\n";
            $body .= "\n-CDash on " . $serverName . "\n";

            add_log($title . ' : ' . $body . ' : ' . implode(', ', $recipients), 'sendEmailUnregisteredUsers');

            if (cdashmail($recipients, $title, $body)) {
                add_log('email sent to: ' . implode(', ', $recipients), 'sendEmailUnregisteredUsers');
                return;
            } else {
                add_log('cannot send email to: ' . implode(', ', $recipients), 'sendEmailUnregisteredUsers');
            }
        }
    }
}

/** Add daily changes if necessary */
function addDailyChanges($projectid)
{
    include_once 'include/common.php';
    include_once 'include/sendemail.php';

    $project = new Project();
    $project->Id = $projectid;
    $project->Fill();
    list($previousdate, $currentstarttime, $nextdate) = get_dates('now', $project->NightlyTime);
    $date = gmdate(FMT_DATE, $currentstarttime);

    // Check if we already have it somwhere
    $query = pdo_query("SELECT id FROM dailyupdate WHERE projectid='$projectid' AND date='$date'");
    if (pdo_num_rows($query) == 0) {
        $cvsauthors = array();

        pdo_query("INSERT INTO dailyupdate (projectid,date,command,type,status)
               VALUES ($projectid,'$date','NA','NA','0')");
        $updateid = pdo_insert_id('dailyupdate');
        $dates = get_related_dates($project->NightlyTime, $date);
        $commits = get_repository_commits($projectid, $dates);

        // Insert the commits
        foreach ($commits as $commit) {
            $filename = $commit['directory'] . '/' . $commit['filename'];
            $checkindate = $commit['time'];
            $author = addslashes($commit['author']);
            $email = '';
            if (isset($commit['email'])) {
                $email = addslashes($commit['email']);
            }
            $log = addslashes($commit['comment']);
            $revision = $commit['revision'];
            $priorrevision = $commit['priorrevision'];

            // Check if we have a robot file for this build
            $robot = pdo_query('SELECT authorregex FROM projectrobot
                  WHERE projectid=' . qnum($projectid) . " AND robotname='" . $author . "'");

            if (pdo_num_rows($robot) > 0) {
                $robot_array = pdo_fetch_array($robot);
                $regex = $robot_array['authorregex'];
                preg_match($regex, $commit['comment'], $matches);
                if (isset($matches[1])) {
                    $author = addslashes($matches[1]);
                }
            }

            if (!in_array(stripslashes($author), $cvsauthors)) {
                $cvsauthors[] = stripslashes($author);
            }

            pdo_query("INSERT INTO dailyupdatefile (dailyupdateid,filename,checkindate,author,email,log,revision,priorrevision)
                   VALUES ($updateid,'$filename','$checkindate','$author','$email','$log','$revision','$priorrevision')");
            add_last_sql_error('addDailyChanges', $projectid);
        }

        // If the project has the option to send an email to the author
        if ($project->EmailAdministrator) {
            sendEmailUnregisteredUsers($projectid, $cvsauthors);
        }

        // Send an email if some expected builds have not been submitting
        sendEmailExpectedBuilds($projectid, $currentstarttime);

        // cleanBuildEmail
        cleanBuildEmail();
        cleanUserTemp();

        // Delete old records from the failed jobs database table.
        $dt = new \DateTime();
        $dt->setTimestamp(time() - (config('cdash.backup_timeframe') * 3600));
        \DB::table('failed_jobs')
            ->where('failed_at', '<', $dt)
            ->delete();

        // If the status of daily update is set to 2 that means we should send an email
        $query = pdo_query("SELECT status FROM dailyupdate WHERE projectid='$projectid' AND date='$date'");
        $dailyupdate_array = pdo_fetch_array($query);
        $dailyupdate_status = $dailyupdate_array['status'];
        if ($dailyupdate_status == 2) {
            // Find the groupid
            $group_query = pdo_query("SELECT buildid,groupid FROM summaryemail WHERE date='$date'");
            while ($group_array = pdo_fetch_array($group_query)) {
                $groupid = $group_array['groupid'];
                $buildid = $group_array['buildid'];

                // Find if the build has any errors
                $builderror = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='0'");
                $builderror_array = pdo_fetch_array($builderror);
                $nbuilderrors = $builderror_array[0];

                // Find if the build has any warnings
                $buildwarning = pdo_query("SELECT count(buildid) FROM builderror WHERE buildid='$buildid' AND type='1'");
                $buildwarning_array = pdo_fetch_array($buildwarning);
                $nbuildwarnings = $buildwarning_array[0];

                // Find if the build has any test failings
                if ($project->EmailTestTimingChanged) {
                    $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND (status='failed' OR timestatus>" . qnum($project->TestTimeMaxStatus) . ')';
                } else {
                    $sql = "SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'";
                }

                $nfail_array = pdo_fetch_array(pdo_query($sql));
                $nfailingtests = $nfail_array[0];

                sendsummaryemail($projectid, $groupid, $nbuildwarnings, $nbuilderrors, $nfailingtests);
            }
        }

        pdo_query("UPDATE dailyupdate SET status='1' WHERE projectid='$projectid' AND date='$date'");

        // Clean the backup directories.
        $timeframe = config('cdash.backup_timeframe');
        $dirs_to_clean = ['parsed', 'failed'];
        foreach ($dirs_to_clean as $dir_to_clean) {
            $files = Storage::allFiles($dir_to_clean);
            foreach ($files as $filename) {
                $filepath = Storage::path($filename);
                if (file_exists($filepath) && is_file($filepath) &&
                        time() - filemtime($filepath) > $timeframe * 3600) {
                    cdash_unlink($filepath);
                }
            }
        }

        // Delete expired authentication tokens.
        pdo_query('DELETE FROM authtoken WHERE expires < NOW()');

        // Delete expired buildgroups and rules.
        $current_date = gmdate(FMT_DATETIME);
        $datetime = new \DateTime();
        $datetime->sub(new \DateInterval("P{$project->AutoremoveTimeframe}D"));
        $cutoff_date = gmdate(FMT_DATETIME, $datetime->getTimestamp());
        BuildGroupRule::DeleteExpiredRulesForProject($project->Id, $cutoff_date);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id FROM buildgroup
            WHERE projectid = :projectid AND
                  endtime != '1980-01-01 00:00:00' AND
                  endtime < :endtime");
        $query_params = [
            ':projectid' => $project->Id,
            ':endtime' => $cutoff_date
        ];
        $db->execute($stmt, $query_params);
        while ($row = $stmt->fetch()) {
            $buildgroup = new BuildGroup();
            $buildgroup->SetId($row['id']);
            $buildgroup->Delete();
        }

        // Remove the first builds of the project
        include_once 'include/autoremove.php';
        removeFirstBuilds($projectid, $project->AutoremoveTimeframe, $project->AutoremoveMaxBuilds);
        removeBuildsGroupwise($projectid, $project->AutoremoveMaxBuilds);
    }
}
