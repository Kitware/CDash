<?php

namespace App\Jobs;

use App\Models\Project;
use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;
use DateInterval;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * All functionality is deprecated and should eventually be moved to separate dedicated worker tasks.
 */
class PerformLegacyDailyUpdates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        foreach (Project::all() as $project) {
            $this->addDailyChanges($project->id);
        }
    }

    /** Send email if expected build from last day have not been submitting */
    private function sendEmailExpectedBuilds($projectid, $currentstarttime): void
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
            $siteid = (int) $build2grouprule_array['siteid'];
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

                Mail::raw($missingSummary, function ($message) use ($missingTitle, $recipients): void {
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
                Mail::raw($summary, function ($message) use ($title, $recipients): void {
                    $message->subject($title)
                        ->to($recipients);
                });
            }
        }
    }

    /** Clean the usertemp table if more than 24hrs */
    private function cleanUserTemp(): void
    {
        $now = date(FMT_DATETIME, time() - 3600 * 24);
        DB::delete('DELETE FROM usertemp WHERE registrationdate < ?', [$now]);
    }

    /** Add daily changes if necessary */
    private function addDailyChanges(int $projectid): void
    {
        $project = new \CDash\Model\Project();
        $project->Id = $projectid;
        $project->Fill();
        [$previousdate, $currentstarttime, $nextdate] = get_dates('now', $project->NightlyTime);

        $db = Database::getInstance();

        // Send an email if some expected builds have not been submitting
        $this->sendEmailExpectedBuilds($projectid, $currentstarttime);

        $this->cleanUserTemp();

        // Delete expired buildgroups and rules.
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
    }
}
