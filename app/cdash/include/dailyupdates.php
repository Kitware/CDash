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

use App\Utils\DatabaseCleanupUtils;
use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteFile;

@set_time_limit(0);

/** Send email if expected build from last day have not been submitting */
function sendEmailExpectedBuilds($projectid, $currentstarttime): void
{
    $db = Database::getInstance();

    $currentEndUTCTime = gmdate(FMT_DATETIME, $currentstarttime);
    $currentBeginUTCTime = gmdate(FMT_DATETIME, $currentstarttime - 3600 * 24);
    $build2grouprule = $db->executePrepared("
                           SELECT
                               buildtype,
                               buildname,
                               siteid,
                               groupid,
                               site.name
                           FROM (
                               SELECT
                                   g.siteid,
                                   g.buildtype,
                                   g.buildname,
                                   g.groupid
                               FROM build2grouprule as g
                               LEFT JOIN build as b ON (
                                   g.expected='1'
                                   AND b.type=g.buildtype
                                   AND b.name=g.buildname
                                   AND b.siteid=g.siteid
                                   AND b.projectid=?
                                   AND b.starttime>?
                                   AND b.starttime<?
                               )
                               WHERE
                                   b.type IS NULL
                                   AND b.name IS NULL
                                   AND b.siteid IS NULL
                                   AND g.expected='1'
                                   AND g.starttime<?
                                   AND (
                                       g.endtime>?
                                       OR g.endtime='1980-01-01 00:00:00'
                                   )
                           ) as t1,
                           buildgroup as bg,
                           site
                           WHERE
                               t1.groupid=bg.id
                               AND bg.projectid=?
                               AND bg.starttime<?
                               AND (
                                   bg.endtime>?
                                   OR bg.endtime='1980-01-01 00:00:00'
                               )
                               AND site.id=t1.siteid
                       ", [
        $projectid,
        $currentBeginUTCTime,
        $currentEndUTCTime,
        $currentBeginUTCTime,
        $currentEndUTCTime,
        $projectid,
        $currentBeginUTCTime,
        $currentEndUTCTime,
    ]);

    $projectname = get_project_name($projectid);
    $summary = 'The following expected build(s) for the project *' . $projectname . "* didn't submit yesterday:\n";
    $missingbuilds = 0;

    foreach ($build2grouprule as $build2grouprule_array) {
        $builtype = $build2grouprule_array['buildtype'];
        $buildname = $build2grouprule_array['buildname'];
        $sitename = $build2grouprule_array['name'];
        $siteid = intval($build2grouprule_array['siteid']);
        $summary .= '* ' . $sitename . ' - ' . $buildname . ' (' . $builtype . ")\n";

        // Find the site maintainers
        $recipients = [];
        $emails = $db->executePrepared('
                      SELECT email
                      FROM
                          users AS u,
                          site2user
                      WHERE
                          u.id=site2user.userid
                          AND site2user.siteid=?
                  ', [$siteid]);
        foreach ($emails as $emails_array) {
            $recipients[] = $emails_array['email'];
        }

        if (!empty($recipients)) {
            $missingTitle = 'CDash [' . $projectname . '] - Missing Build for ' . $sitename;
            $missingSummary = 'The following expected build(s) for the project ' . $projectname . " didn't submit yesterday:\n";
            $missingSummary .= '* ' . $sitename . ' - ' . $buildname . ' (' . $builtype . ")\n";
            $missingSummary .= "\n" . url('/index.php') . '?project=' . urlencode($projectname) . "\n";
            $missingSummary .= "\n-CDash\n";

            Mail::raw($missingSummary, function ($message) use ($missingTitle, $recipients) {
                $message->subject($missingTitle)
                    ->to($recipients);
            });
        }
        $missingbuilds = 1;
    }

    // Send a summary email to the project administrator or users who want to receive notification
    // of missing builds
    if ($missingbuilds == 1) {
        $summary .= "\n" . url('/index.php') . '?project=' . urlencode($projectname) . "\n";
        $summary .= "\n-CDash\n";

        $title = 'CDash [' . $projectname . '] - Missing Builds';

        // Find the site administrators or users who want to receive the builds
        $recipients = [];
        $emails = $db->executePrepared('
                      SELECT email
                      FROM
                          users AS u,
                          user2project
                      WHERE
                          u.id=user2project.userid
                          AND user2project.projectid=?
                          AND (
                              user2project.role=2
                              OR user2project.emailmissingsites=1
                          )
                  ', [$projectid]);

        foreach ($emails as $emails_array) {
            $recipients[] = $emails_array['email'];
        }

        // Send the email
        if (!empty($recipients)) {
            Mail::raw($summary, function ($message) use ($title, $recipients) {
                $message->subject($title)
                    ->to($recipients);
            });
        }
    }
}

/** Remove the buildemail that have been there from more than 48h */
function cleanBuildEmail(): void
{
    $now = date(FMT_DATETIME, time() - 3600 * 48);

    DB::delete('DELETE FROM buildemail WHERE time<?', [$now]);
}

/** Clean the usertemp table if more than 24hrs */
function cleanUserTemp(): void
{
    $now = date(FMT_DATETIME, time() - 3600 * 24);
    DB::delete('DELETE FROM usertemp WHERE registrationdate < ?', [$now]);
}

/** Add daily changes if necessary */
function addDailyChanges(int $projectid): void
{
    $project = new Project();
    $project->Id = $projectid;
    $project->Fill();
    [$previousdate, $currentstarttime, $nextdate] = get_dates('now', $project->NightlyTime);
    $date = gmdate(FMT_DATE, $currentstarttime);

    $db = Database::getInstance();

    // Check if we already have it somwhere
    $query = $db->executePreparedSingleRow('
                 SELECT COUNT(*) AS c
                 FROM dailyupdate
                 WHERE
                     projectid=?
                     AND date=?
             ', [$projectid, $date]);
    if (intval($query['c']) === 0) {
        $updateid = DB::table('dailyupdate')
            ->insertGetId([
                'projectid' => $projectid,
                'date' => $date,
                'command' => 'NA',
                'type' => 'NA',
                'status' => '0',
            ]);

        // Send an email if some expected builds have not been submitting
        sendEmailExpectedBuilds($projectid, $currentstarttime);

        // cleanBuildEmail
        cleanBuildEmail();
        cleanUserTemp();

        // If the status of daily update is set to 2 that means we should send an email
        $dailyupdate_array = $db->executePreparedSingleRow('
                                 SELECT status
                                 FROM dailyupdate
                                 WHERE
                                     projectid=?
                                     AND date=?
                             ', [$projectid, $date]);
        $dailyupdate_status = intval($dailyupdate_array['status']);
        if ($dailyupdate_status === 2) {
            // Find the groupid
            $group_query = $db->executePrepared('
                               SELECT buildid, groupid
                               FROM summaryemail
                               WHERE date=?
                           ', [$date]);
            foreach ($group_query as $group_array) {
                $groupid = intval($group_array['groupid']);
                $buildid = intval($group_array['buildid']);

                // Find if the build has any errors
                $builderror = $db->executePreparedSingleRow('
                                  SELECT count(buildid) AS c
                                  FROM builderror
                                  WHERE
                                      buildid=?
                                      AND type=0
                              ', [$buildid]);
                $nbuilderrors = intval($builderror['c']);

                // Find if the build has any warnings
                $buildwarning = $db->executePreparedSingleRow('
                                    SELECT count(buildid) AS c
                                    FROM builderror
                                    WHERE
                                        buildid=?
                                        AND type=1
                                ', [$buildid]);
                $nbuildwarnings = intval($buildwarning['c']);

                // Find if the build has any test failings
                if ($project->EmailTestTimingChanged) {
                    $sql = "SELECT count(1) AS c
                            FROM build2test
                            WHERE
                                buildid=?
                                AND (
                                    status='failed'
                                    OR timestatus>?
                                )";
                    $params = [$buildid, intval($project->TestTimeMaxStatus)];
                } else {
                    $sql = "SELECT count(1) AS c
                            FROM build2test
                            WHERE
                                buildid=?
                                AND status='failed'";
                    $params = [$buildid];
                }

                $nfail_array = $db->executePreparedSingleRow($sql, $params);
                $nfailingtests = intval($nfail_array['c']);
            }
        }

        $db->executePrepared('
            UPDATE dailyupdate
            SET status=1
            WHERE
                projectid=?
                AND date=?
        ', [$projectid, $date]);

        // Clean the backup directories.
        $deletion_time_threshold = time() - (int) config('cdash.backup_timeframe') * 3600;
        $dirs_to_clean = ['parsed', 'failed', 'inprogress'];
        foreach ($dirs_to_clean as $dir_to_clean) {
            $files = Storage::allFiles($dir_to_clean);
            foreach ($files as $file) {
                if (Storage::lastModified($file) < $deletion_time_threshold) {
                    try {
                        Storage::delete($file);
                    } catch (UnableToDeleteFile $e) {
                        continue;
                    }
                }
            }
        }

        // Delete expired buildgroups and rules.
        $current_date = gmdate(FMT_DATETIME);
        $datetime = new DateTime();
        $datetime->sub(new DateInterval("P{$project->AutoremoveTimeframe}D"));
        $cutoff_date = gmdate(FMT_DATETIME, $datetime->getTimestamp());
        BuildGroupRule::DeleteExpiredRulesForProject($project->Id, $cutoff_date);

        $stmt = $db->prepare(
            "SELECT id FROM buildgroup
            WHERE projectid = :projectid AND
                  endtime != '1980-01-01 00:00:00' AND
                  endtime < :endtime");
        $query_params = [
            ':projectid' => $project->Id,
            ':endtime' => $cutoff_date,
        ];
        $db->execute($stmt, $query_params);
        while ($row = $stmt->fetch()) {
            $buildgroup = new BuildGroup();
            $buildgroup->SetId($row['id']);
            $buildgroup->Delete();
        }

        // Remove the first builds of the project
        DatabaseCleanupUtils::removeFirstBuilds($projectid, $project->AutoremoveTimeframe, $project->AutoremoveMaxBuilds);
        DatabaseCleanupUtils::removeBuildsGroupwise($projectid, $project->AutoremoveMaxBuilds);
    }
}
