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
namespace CDash\Model;

require_once 'include/cdashmail.php';

use CDash\Collection\SubscriberCollection;

use CDash\Config;
use CDash\Database;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\ServiceContainer;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Project as EloquentProject;
use App\Models\User;
use RuntimeException;

/** Main project class */
class Project
{
    public const PROJECT_ADMIN = 2;
    public const SITE_MAINTAINER = 1;
    public const PROJECT_USER = 0;

    public const ACCESS_PRIVATE = 0;
    public const ACCESS_PUBLIC = 1;
    public const ACCESS_PROTECTED = 2;

    public $Name;
    public $Id;
    public $Description;
    public $HomeUrl;
    public $CvsUrl;
    public $DocumentationUrl;
    public $BugTrackerUrl;
    public $BugTrackerFileUrl;
    public $BugTrackerNewIssueUrl;
    public $BugTrackerType;
    public $ImageId;
    public $Public;
    public $CoverageThreshold;
    public $TestingDataUrl;
    public $NightlyTime;
    public $NightlyDateTime;
    public $NightlyTimezone;
    public $GoogleTracker;
    public $EmailLowCoverage;
    public $EmailTestTimingChanged;
    public $EmailBrokenSubmission;
    public $EmailRedundantFailures;
    public $CvsViewerType;
    public $TestTimeStd;
    public $TestTimeStdThreshold;
    public $ShowTestTime;
    public $TestTimeMaxStatus;
    public $EmailMaxItems;
    public $EmailMaxChars;
    public $EmailAdministrator;
    public $ShowIPAddresses;
    public $DisplayLabels;
    public $ShareLabelFilters;
    public $ViewSubProjectsLink;
    public $AuthenticateSubmissions;
    public $ShowCoverageCode;
    public $AutoremoveTimeframe;
    public int $AutoremoveMaxBuilds;
    public $UploadQuota;
    public $WarningsFilter;
    public $ErrorsFilter;
    /** @var Database $PDO */
    private $PDO;

    /**
     * @var SubscriberCollection
     */
    private $SubscriberCollection;

    public $Filled;

    public function __construct()
    {
        $this->Initialize(); // why?
    }

    /** Initialize non defined variables */
    private function Initialize(): void
    {
        if (empty($this->EmailLowCoverage)) {
            $this->EmailLowCoverage = 0;
        }
        if (empty($this->EmailTestTimingChanged)) {
            $this->EmailTestTimingChanged = 0;
        }
        if (empty($this->EmailBrokenSubmission)) {
            $this->EmailBrokenSubmission = 0;
        }
        if (empty($this->EmailRedundantFailures)) {
            $this->EmailRedundantFailures = 0;
        }
        if (empty($this->EmailAdministrator)) {
            $this->EmailAdministrator = 0;
        }
        if (empty($this->ShowIPAddresses)) {
            $this->ShowIPAddresses = 0;
        }
        if (empty($this->ShowTestTime)) {
            $this->ShowTestTime = 0;
        }
        if (empty($this->DisplayLabels)) {
            $this->DisplayLabels = 0;
        }
        if (empty($this->ShareLabelFilters)) {
            $this->ShareLabelFilters = 0;
        }
        if (empty($this->ViewSubProjectsLink)) {
            $this->ViewSubProjectsLink = 0;
        }
        if (empty($this->AuthenticateSubmissions)) {
            $this->AuthenticateSubmissions = 0;
        }
        if (empty($this->ShowCoverageCode)) {
            $this->ShowCoverageCode = 0;
        }
        if (empty($this->AutoremoveTimeframe)) {
            $this->AutoremoveTimeframe = 0;
        }
        if (empty($this->AutoremoveMaxBuilds)) {
            $this->AutoremoveMaxBuilds = 300;
        }
        if (empty($this->UploadQuota)) {
            $this->UploadQuota = 0;
        }
        if (empty($this->EmailMaxItems)) {
            $this->EmailMaxItems = 5;
        }
        if (empty($this->EmailMaxChars)) {
            $this->EmailMaxChars = 255;
        }
        if (empty($this->WarningsFilter)) {
            $this->WarningsFilter = '';
        }
        if (empty($this->ErrorsFilter)) {
            $this->ErrorsFilter = '';
        }
        $this->PDO = Database::getInstance();

        $this->Filled = false;
    }

    /** Add a build group */
    public function AddBuildGroup(BuildGroup $buildgroup): void
    {
        $buildgroup->SetProjectId($this->Id);
        $buildgroup->Save();
    }

    /** Delete a project */
    public function Delete(): bool
    {
        if (!$this->Id || EloquentProject::find($this->Id) === null) {
            return false;
        }
        // Remove the project groups and rules
        // TODO: (williamjallen) This can be done with one delete statement for each table...
        $buildgroup = DB::select('SELECT * FROM buildgroup WHERE projectid=?', [intval($this->Id)]);
        foreach ($buildgroup as $buildgroup_array) {
            $groupid = intval($buildgroup_array->id);
            DB::delete('DELETE FROM buildgroupposition WHERE buildgroupid=?', [$groupid]);
            DB::delete('DELETE FROM build2grouprule WHERE groupid=?', [$groupid]);
            DB::delete('DELETE FROM build2group WHERE groupid=?', [$groupid]);
        }

        DB::delete('DELETE FROM buildgroup WHERE projectid=?', [intval($this->Id)]);
        DB::delete('DELETE FROM blockbuild WHERE projectid=?', [intval($this->Id)]);
        DB::delete('DELETE FROM user2project WHERE projectid=?', [intval($this->Id)]);
        DB::delete('DELETE FROM labelemail WHERE projectid=?', [intval($this->Id)]);
        DB::delete('DELETE FROM labelemail WHERE projectid=?', [intval($this->Id)]);
        DB::delete('DELETE FROM project2repositories WHERE projectid=?', [intval($this->Id)]);

        $dailyupdate = DB::select('SELECT id FROM dailyupdate WHERE projectid=?', [intval($this->Id)]);
        $dailyupdate_ids = [];
        foreach ($dailyupdate as $dailyupdate_array) {
            $dailyupdate_ids[] = (int) $dailyupdate_array->id;
        }
        DB::table('dailyupdatefile')->whereIn('dailyupdateid', $dailyupdate_ids)->delete();

        DB::delete('DELETE FROM dailyupdate WHERE projectid=?', [intval($this->Id)]);
        DB::delete('DELETE FROM build_filters WHERE projectid=?', [intval($this->Id)]);

        // Delete any repositories that aren't shared with other projects.
        // TODO: (williamjallen) rewrite this to use a single query...
        $repositories_query = DB::select('
                                  SELECT repositoryid
                                  FROM project2repositories
                                  WHERE projectid=?
                                  ORDER BY repositoryid
                              ', [(int) $this->Id]);
        foreach ($repositories_query as $repository_array) {
            $repoid = (int) $repository_array->repositoryid;
            $projects_query = DB::select('
                                  SELECT COUNT(projectid) AS c
                                  FROM project2repositories
                                  WHERE repositoryid=?
                              ', [$repoid]);
            if ($projects_query[0]->c > 1) {
                continue;
            }
            DB::delete('DELETE FROM repositories WHERE id=?', [$repoid]);
        }
        DB::delete('DELETE FROM project2repositories WHERE projectid=?', [intval($this->Id)]);

        EloquentProject::findOrFail((int) $this->Id)->delete();

        return true;
    }

    /** Return if a project exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if (!$this->Id) {
            return false;
        }
        return EloquentProject::find($this->Id) !== null;
    }

    /** Save the project in the database */
    public function Save(): bool
    {
        // Trim the name
        $this->Name = trim($this->Name);
        $this->Initialize();

        $project = EloquentProject::findOrNew($this->Id);
        $project->fill([
            'name' => $this->Name ?? '',
            'description' => $this->Description ?? '',
            'homeurl' => $this->HomeUrl ?? '',
            'cvsurl' => $this->CvsUrl ?? '',
            'documentationurl' => $this->DocumentationUrl ?? '',
            'bugtrackerurl' => $this->BugTrackerUrl ?? '',
            'bugtrackerfileurl' => $this->BugTrackerFileUrl ?? '',
            'bugtrackernewissueurl' => $this->BugTrackerNewIssueUrl ?? '',
            'bugtrackertype' => $this->BugTrackerType ?? '',
            'public' => (int) $this->Public,
            'coveragethreshold' => (int) $this->CoverageThreshold,
            'testingdataurl' => $this->TestingDataUrl ?? '',
            'nightlytime' => $this->NightlyTime ?? '',
            'googletracker' => $this->GoogleTracker ?? '',
            'emaillowcoverage' => (int) $this->EmailLowCoverage,
            'emailtesttimingchanged' => (int) $this->EmailTestTimingChanged,
            'emailbrokensubmission' => (int) $this->EmailBrokenSubmission,
            'emailredundantfailures' => (int) $this->EmailRedundantFailures,
            'emailadministrator' => (int) $this->EmailAdministrator,
            'showipaddresses' => (int) $this->ShowIPAddresses,
            'displaylabels' => (int) $this->DisplayLabels,
            'sharelabelfilters' => (int) $this->ShareLabelFilters,
            'viewsubprojectslink' => (int) $this->ViewSubProjectsLink,
            'authenticatesubmissions' => (int) $this->AuthenticateSubmissions,
            'showcoveragecode' => (int) $this->ShowCoverageCode,
            'autoremovetimeframe' => (int) $this->AutoremoveTimeframe,
            'autoremovemaxbuilds' => (int) $this->AutoremoveMaxBuilds,
            'uploadquota' => (int) $this->UploadQuota,
            'cvsviewertype' => $this->CvsViewerType ?? '',
            'testtimestd' => (int) $this->TestTimeStd,
            'testtimestdthreshold' => (int) $this->TestTimeStdThreshold,
            'showtesttime' => (int) $this->ShowTestTime,
            'testtimemaxstatus' => (int) $this->TestTimeMaxStatus,
            'emailmaxitems' => (int) $this->EmailMaxItems,
            'emailmaxchars' => (int) $this->EmailMaxChars,
            'imageid' => $this->ImageId ?? 0,
        ]);
        $project->save();
        $this->Id = $project->id;

        return $this->UpdateBuildFilters();
    }

    public function GetIdByName()
    {
        $this->Id = EloquentProject::where('name', $this->Name)->first()?->id;
        return $this->Id;
    }

    public function FindByName($name): bool
    {
        $this->Name = $name;
        $this->GetIdByName();
        if ($this->Id) {
            $this->Fill();
            return true;
        }
        return false;
    }

    /** Return true if the project exists */
    public function ExistsByName(string $name): bool
    {
        // TODO: (williamjallen) Side effects are almost always a bad thing.  Get rid of this...
        $this->Name = $name;

        return EloquentProject::where('name', $this->Name)->exists();
    }

    /** Get the logo id */
    private function GetLogoId(): int
    {
        if (!$this->Filled) {
            $this->Fill();
        }

        return $this->Id > 0 ? $this->ImageId : 0;
    }

    /** Fill in all the information from the database */
    public function Fill(): void
    {
        if ($this->Filled) {
            return;
        }

        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        $project = EloquentProject::find((int) $this->Id);
        if ($project !== null) {
            $this->Name = $project->name;
            $this->Description = $project->description;
            $this->HomeUrl = $project->homeurl;
            $this->CvsUrl = $project->cvsurl;
            $this->DocumentationUrl = $project->documentationurl;
            $this->BugTrackerUrl = $project->bugtrackerurl;
            $this->BugTrackerFileUrl = $project->bugtrackerfileurl;
            $this->BugTrackerNewIssueUrl = $project->bugtrackernewissueurl;
            $this->BugTrackerType = $project->bugtrackertype;
            $this->ImageId = $project->imageid;
            $this->Public = $project->public;
            $this->CoverageThreshold = $project->coveragethreshold;
            $this->TestingDataUrl = $project->testingdataurl;
            $this->SetNightlyTime($project->nightlytime);
            $this->GoogleTracker = $project->googletracker;
            $this->EmailLowCoverage = $project->emaillowcoverage;
            $this->EmailTestTimingChanged = $project->emailtesttimingchanged;
            $this->EmailBrokenSubmission = $project->emailbrokensubmission;
            $this->EmailRedundantFailures = $project->emailredundantfailures;
            $this->EmailAdministrator = $project->emailadministrator;
            $this->ShowIPAddresses = $project->showipaddresses;
            $this->DisplayLabels = $project->displaylabels;
            $this->ShareLabelFilters = $project->sharelabelfilters;
            $this->ViewSubProjectsLink = $project->viewsubprojectslink;
            $this->AuthenticateSubmissions = $project->authenticatesubmissions;
            $this->ShowCoverageCode = $project->showcoveragecode;
            $this->AutoremoveTimeframe = $project->autoremovetimeframe;
            $this->AutoremoveMaxBuilds = $project->autoremovemaxbuilds;
            $this->UploadQuota = $project->uploadquota;
            $this->CvsViewerType = $project->cvsviewertype;
            $this->TestTimeStd = $project->testtimestd;
            $this->TestTimeStdThreshold = $project->testtimestdthreshold;
            $this->ShowTestTime = $project->showtesttime;
            $this->TestTimeMaxStatus = $project->testtimemaxstatus;
            $this->EmailMaxItems = $project->emailmaxitems;
            $this->EmailMaxChars = $project->emailmaxchars;
        }

        // Check if we have filters
        $build_filters = DB::select('SELECT * FROM build_filters WHERE projectid=?', [(int) $this->Id]);

        if (count($build_filters) > 0) {
            $this->WarningsFilter = $build_filters[0]->warnings;
            $this->ErrorsFilter = $build_filters[0]->errors;
        }

        $this->Filled = true;
    }

    public function SetNightlyTime(string $nightly_time): void
    {
        $this->NightlyTime = $nightly_time;

        // Get the timezone for the project's nightly start time.
        try {
            $this->NightlyDateTime = new DateTime($this->NightlyTime);
            $this->NightlyTimezone = $this->NightlyDateTime->getTimezone();
        } catch (Exception) {
            // Bad timezone (probably) specified, try defaulting to UTC.
            $this->NightlyTimezone = new DateTimeZone('UTC');
            $parts = explode(' ', $nightly_time);
            $this->NightlyTime = $parts[0];
            try {
                $this->NightlyDateTime = new DateTime($this->NightlyTime, $this->NightlyTimezone);
            } catch (Exception) {
                Log::error("Could not parse $nightly_time");
                return;
            }
        }

        // Attempt to deal with the fact that tz->getName() doesn't necessarily return
        // a "valid timezone ID".
        $timezone_name = timezone_name_from_abbr($this->NightlyTimezone->getName());
        if ($timezone_name === false) {
            $timezone_name = $this->NightlyTimezone->getName();
        }

        // Use the project's timezone by default.
        date_default_timezone_set($timezone_name);
    }

    /**
     * Add a logo
     *
     * TODO: (williamjallen) This function is only ever used in the tests.  Remove it?
     */
    public function AddLogo($contents, string $filetype)
    {
        if (strlen($contents) === 0) {
            return;
        }

        $image = new Image();
        $image->Data = $contents;
        $image->Checksum = crc32($contents);
        $image->Extension = $filetype;

        $imgid = $this->GetLogoId();
        if ($imgid > 0) {
            $image->Id = $imgid;
        }

        if ($image->Save(true)) {
            $project = EloquentProject::findOrFail((int) $this->Id);
            $project->imageid = $image->Id;
            $project->save();
        }
        return $image->Id;
    }

    /** Add CVS/SVN repositories */
    public function AddRepositories($repositories, $usernames, $passwords, $branches)
    {
        // First we update/delete any registered repositories
        $currentRepository = 0;
        $repositories_query = $this->PDO->executePrepared('
                                  SELECT repositoryid
                                  FROM project2repositories
                                  WHERE projectid=?
                                  ORDER BY repositoryid
                              ', [intval($this->Id)]);

        foreach ($repositories_query as $repository_array) {
            $repositoryid = intval($repository_array['repositoryid']);
            if (!isset($repositories[$currentRepository]) || strlen($repositories[$currentRepository]) === 0) {
                // TODO: (wiliamjallen) This should be done with one query
                $query = DB::select('
                             SELECT COUNT(*) AS c
                             FROM project2repositories
                             WHERE repositoryid=?
                         ', [$repositoryid]);
                if ((int) $query[0]->c === 1) {
                    DB::delete('DELETE FROM repositories WHERE id=?', [$repositoryid]);
                }
                DB::delete('
                    DELETE FROM project2repositories
                    WHERE projectid=? AND repositoryid=?
                ', [(int) $this->Id, $repositoryid]);
            } else {
                // If the repository is not shared by any other project we update
                $count_array = DB::select('
                                   SELECT count(*) as c
                                   FROM project2repositories
                                   WHERE repositoryid=?
                               ', [$repositoryid]);
                if ((int) $count_array[0]->c === 1) {
                    DB::table('repositories')->where('id', $repositoryid)->update([
                        'url' => $repositories[$currentRepository],
                        'username' => $usernames[$currentRepository],
                        'password' => $passwords[$currentRepository],
                        'branch' => $branches[$currentRepository],
                    ]);
                } else {
                    // Otherwise we remove it from the current project and add it to the queue to be created
                    DB::delete('
                        DELETE FROM project2repositories
                        WHERE projectid=? AND repositoryid=?
                    ', [intval($this->Id), $repositoryid]);

                    $repositories[] = $repositories[$currentRepository];
                    $usernames[] = $usernames[$currentRepository];
                    $passwords[] = $passwords[$currentRepository];
                    $branches[] = $branches[$currentRepository];
                }
            }
            $currentRepository++;
        }

        //  Then we add new repositories
        for ($i = $currentRepository; $i < count($repositories); $i++) {
            $url = $repositories[$i];
            $username = $usernames[$i];
            $password = $passwords[$i];
            $branch = $branches[$i];
            if (strlen($url) === 0) {
                continue;
            }

            // Insert into repositories if not any
            $repositories_query = DB::select('SELECT id FROM repositories WHERE url=?', [$url]);

            if ($repositories_query === []) {
                $repositoryid = DB::table('repositories')->insertGetId([
                    'url' => $url,
                    'username' => $username,
                    'password' => $password,
                    'branch' => $branch,
                ]);
            } else {
                $repositoryid = intval($repositories['id']);
            }
            DB::table('project2repositories')->insert([
                'projectid' => (int) $this->Id,
                'repositoryid' => $repositoryid,
            ]);
        }
    }

    /** Get the repositories */
    public function GetRepositories(): array
    {
        $repository = DB::select('
                          SELECT
                              url,
                              username,
                              password,
                              branch
                          FROM repositories, project2repositories
                          WHERE
                              repositories.id=project2repositories.repositoryid
                              AND project2repositories.projectid=?
                      ', [(int) $this->Id]);

        $repositories = [];
        foreach ($repository as $repository_array) {
            $rep['url'] = $repository_array->url;
            $rep['username'] = $repository_array->username;
            $rep['password'] = $repository_array->password;
            $rep['branch'] = $repository_array->branch;
            $repositories[] = $rep;
        }
        return $repositories;
    }

    /** Get the build groups */
    public function GetBuildGroups(): array
    {
        $query = DB::select("
                     SELECT id, name
                     FROM buildgroup
                     WHERE projectid=? AND endtime='1980-01-01 00:00:00'
                 ", [(int) $this->Id]);

        $buildgroups = [];
        foreach ($query as $row) {
            $buildgroup = new BuildGroup();
            $buildgroup->SetId(intval($row->id));
            $buildgroup->SetName($row->name);
            $buildgroups[] = $buildgroup;
        }
        return $buildgroups;
    }

    /** Get the list of block builds */
    public function GetBlockedBuilds(): array
    {
        $site = DB::select('
                    SELECT
                        id,
                        buildname,
                        sitename,
                        ipaddress
                    FROM blockbuild
                    WHERE projectid=?
                ', [(int) $this->Id]);

        $sites = [];
        foreach ($site as $site_array) {
            $sites[] = (array) $site_array;
        }
        return $sites;
    }

    /** Get the Name of the project */
    public function GetName(): string|false
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        if (!$this->Filled) {
            $this->Fill();
        }

        return $this->Name;
    }

    /** Get the coveragethreshold */
    public function GetCoverageThreshold(): int|false
    {
        if (strlen($this->CoverageThreshold) > 0) {
            return (int) $this->CoverageThreshold;
        }

        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        if (!$this->Filled) {
            $this->Fill();
        }

        return (int) $this->CoverageThreshold;
    }

    /** Get the number of subproject */
    public function GetNumberOfSubProjects($date = null): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        if ($date !== null) {
            $date = Carbon::parse($date);
        }

        return EloquentProject::findOrFail((int) $this->Id)
            ->subprojects($date)
            ->count();
    }

    /**
     * Get the subproject ids
     *
     * @return Collection<int, \App\Models\SubProject>
     */
    public function GetSubProjects(): Collection
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return EloquentProject::findOrFail((int) $this->Id)
            ->subprojects()
            ->get();
    }

    /** Get the last submission of the subproject*/
    public function GetLastSubmission(): string|false
    {
        if (!config('cdash.show_last_submission')) {
            return false;
        }

        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        $starttime = EloquentProject::findOrFail((int) $this->Id)
            ->builds()
            ->max('starttime');

        if ($starttime === null) {
            return false;
        }

        return date(FMT_DATETIMESTD, strtotime($starttime . 'UTC'));
    }

    /** Get the number of builds given a date range */
    public function GetNumberOfBuilds(string|null $startUTCdate = null, string|null $endUTCdate = null): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        if ($startUTCdate !== null) {
            $startUTCdate = Carbon::parse($startUTCdate);
        }

        if ($endUTCdate !== null) {
            $endUTCdate = Carbon::parse($endUTCdate);
        }

        return EloquentProject::findOrFail((int) $this->Id)
            ->builds()
            ->betweenDates($startUTCdate, $endUTCdate)
            ->count();
    }

    /** Get the number of builds given per day */
    public function GetBuildsDailyAverage(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        $project = DB::select('
                       SELECT starttime
                       FROM build
                       WHERE
                           projectid=?
                           AND starttime>?
                           AND starttime<=?
                           AND parentid IN (-1, 0)
                       ORDER BY starttime ASC
                       LIMIT 1
                   ', [(int) $this->Id, $startUTCdate, $endUTCdate]);
        if ($project === []) {
            return 0;
        }
        $first_build = $project[0]->starttime;
        $nb_days = strtotime($endUTCdate) - strtotime($first_build);
        $nb_days = intval($nb_days / 86400) + 1;
        $nbuilds = $this->GetNumberOfBuilds($startUTCdate, $endUTCdate);
        return $nbuilds / $nb_days;
    }

    /** Get the number of warning builds given a date range */
    public function GetNumberOfWarningBuilds(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT count(*) AS c
            FROM build, build2group, buildgroup
            WHERE
                build.projectid = ?
                AND build.starttime > ?
                AND build.starttime <= ?
                AND build2group.buildid = build.id
                AND build2group.groupid = buildgroup.id
                AND buildgroup.includesubprojectotal = 1
                AND build.buildwarnings > 0
                AND build.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->c;
    }

    /** Get the number of error builds given a date range */
    public function GetNumberOfErrorBuilds(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT count(*) AS c
            FROM build, build2group, buildgroup
            WHERE
                build.projectid = ?
                AND build.starttime > ?
                AND build.starttime <= ?
                AND build2group.buildid = build.id
                AND build2group.groupid = buildgroup.id
                AND buildgroup.includesubprojectotal = 1
                AND build.builderrors > 0
                AND build.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->c;
    }

    /** Get the number of failing builds given a date range */
    public function GetNumberOfPassingBuilds(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT count(*) AS c
            FROM build b
            JOIN build2group b2g ON (b2g.buildid=b.id)
            JOIN buildgroup bg ON (bg.id=b2g.groupid)
            WHERE
                b.projectid=?
                AND b.starttime>?
                AND b.starttime<=?
                AND bg.includesubprojectotal=1
                AND b.builderrors=0
                AND b.buildwarnings=0
                AND b.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->c;
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfWarningConfigures(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT COUNT(*) AS c
            FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            WHERE
                b.projectid = ?
                AND b.starttime > ?
                AND b.starttime <= ?
                AND b.configurewarnings > 0
                AND bg.includesubprojectotal = 1
                AND b.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->c;
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfErrorConfigures(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT COUNT(*) AS c
            FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            WHERE
                b.projectid = ?
                AND b.starttime > ?
                AND b.starttime <= ?
                AND b.configureerrors > 0
                AND bg.includesubprojectotal = 1
                AND b.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->c;
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfPassingConfigures(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT COUNT(*) AS c
            FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            WHERE
                b.projectid = ?
                AND b.starttime > ?
                AND b.starttime <= ?
                AND b.configureerrors = 0
                AND b.configurewarnings = 0
                AND bg.includesubprojectotal = 1
                AND b.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->c;
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfPassingTests(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT COALESCE(SUM(build.testpassed), 0) AS s
            FROM build, build2group, buildgroup
            WHERE
                build.projectid = ?
                AND build2group.buildid = build.id
                AND build.testpassed >= 0
                AND build2group.groupid = buildgroup.id
                AND buildgroup.includesubprojectotal = 1
                AND build.starttime > ?
                AND build.starttime <= ?
                AND build.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->s;
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfFailingTests(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT COALESCE(SUM(build.testfailed), 0) AS s
            FROM build, build2group, buildgroup
            WHERE
                build.projectid = ?
                AND build2group.buildid = build.id
                AND build.testfailed >= 0
                AND build2group.groupid = buildgroup.id
                AND buildgroup.includesubprojectotal = 1
                AND build.starttime > ?
                AND build.starttime <= ?
                AND build.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->s;
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfNotRunTests(string $startUTCdate, string $endUTCdate): int
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        return (int) DB::select('
            SELECT COALESCE(SUM(build.testnotrun), 0) AS s
            FROM build, build2group, buildgroup
            WHERE
                build.projectid = ?
                AND build2group.buildid = build.id
                AND build.testnotrun >= 0
                AND build2group.groupid = buildgroup.id
                AND buildgroup.includesubprojectotal = 1
                AND build.starttime > ?
                AND build.starttime <= ?
                AND build.parentid IN (-1, 0)
        ', [(int) $this->Id, $startUTCdate, $endUTCdate])[0]->s;
    }

    /**
     * Get the labels ids for a given project
     *
     * @return array<int>|false
     */
    public function GetLabels($days): array|false
    {
        $todaytime = time();
        $todaytime -= 3600 * 24 * $days;
        $today = date(FMT_DATETIMESTD, $todaytime);

        $straightjoin = '';
        if (config('database.default') != 'pgsql') {
            $straightjoin = 'STRAIGHT_JOIN';
        }

        $labels = DB::select("
                      (
                          SELECT labelid AS id
                          FROM label2build, build
                          WHERE
                             label2build.buildid=build.id
                             AND build.projectid=?
                             AND build.starttime>?
                      ) UNION (
                          SELECT labelid AS id
                          FROM label2test, build
                          WHERE
                              label2test.buildid=build.id
                              AND build.projectid=?
                              AND build.starttime>?
                      ) UNION (
                          SELECT $straightjoin labelid AS id
                          FROM build, label2coveragefile
                          WHERE
                              label2coveragefile.buildid=build.id
                              AND build.projectid=?
                              AND build.starttime>?
                      ) UNION (
                          SELECT $straightjoin labelid AS id
                          FROM build, buildfailure, label2buildfailure
                          WHERE
                              label2buildfailure.buildfailureid=buildfailure.id
                              AND buildfailure.buildid=build.id
                              AND build.projectid=?
                              AND build.starttime>?
                      ) UNION (
                          SELECT $straightjoin labelid AS id
                          FROM build, dynamicanalysis, label2dynamicanalysis
                          WHERE
                              label2dynamicanalysis.dynamicanalysisid=dynamicanalysis.id
                              AND dynamicanalysis.buildid=build.id
                              AND build.projectid=?
                              AND build.starttime>?
                      )
                  ", [
                      intval($this->Id),
                      $today,
                      intval($this->Id),
                      $today,
                      intval($this->Id),
                      $today,
                      intval($this->Id),
                      $today,
                      intval($this->Id),
                      $today,
                  ]);

        $labelids = [];
        foreach ($labels as $label_array) {
            $labelids[] = (int) $label_array->id;
        }
        return array_unique($labelids);
    }

    /** Send an email to the administrator of the project */
    public function SendEmailToAdmin(string $subject, string $body): bool
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        if (!$this->Filled) {
            $this->Fill();
        }

        $config = Config::getInstance();

        if (intval($this->EmailAdministrator) === 0) {
            return true;
        }

        // Find the site maintainers
        $UserProject = new UserProject();
        $UserProject->ProjectId = $this->Id;

        $userids = $UserProject->GetUsers(2); // administrators
        $recipients = [];
        // TODO: Simplify this loop
        foreach ($userids as $userid) {
            $recipients[] = User::findOrFail($userid)->email;
        }

        if (!empty($recipients)) {
            $projectname = $this->Name;
            $emailtitle = 'CDash [' . $projectname . '] - Administration ';
            $emailbody = 'Object: ' . $subject . "\n";
            $emailbody .= $body . "\n";
            $serverName = $config->getServer();

            $emailbody .= "\n-CDash on " . $serverName . "\n";

            if (cdashmail($recipients, $emailtitle, $emailbody)) {
                add_log('email sent to: ' . implode(', ', $recipients), 'SendEmailToAdmin');
            } else {
                add_log('cannot send email to: ' . implode(', ', $recipients), 'SendEmailToAdmin', LOG_ERR, $this->Id);
            }
        }

        return true;
    }

    /** Returns the total size of all uploaded files for this project */
    public function GetUploadsTotalSize(): int|false
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }
        return DB::select('
                   SELECT COALESCE(SUM(query_result.size), 0) AS s
                   FROM (
                       SELECT DISTINCT uploadfile.id, uploadfile.filesize AS size
                       FROM build, build2uploadfile, uploadfile
                       WHERE
                           build.projectid=?
                           AND build.id=build2uploadfile.buildid
                           AND build2uploadfile.fileid=uploadfile.id
                   ) AS query_result;
               ', [(int) $this->Id])[0]->s;
    }

    /**
     * Checks whether this project has exceeded its upload size quota.  If so,
     * Removes the files (starting with the oldest builds) until the total upload size
     * is <= the upload quota.
     */
    public function CullUploadedFiles(): bool
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }
        $totalUploadSize = $this->GetUploadsTotalSize();

        if ($totalUploadSize > $this->UploadQuota) {
            Log::info("Upload quota exceeded, removing old files for project $this->Name...");

            $query = DB::select('
                         SELECT DISTINCT build.id AS id, build.starttime
                         FROM build, build2uploadfile, uploadfile
                         WHERE
                             build.projectid=?
                             AND build.id=build2uploadfile.buildid
                             AND build2uploadfile.fileid=uploadfile.id
                         ORDER BY build.starttime ASC
                     ', [intval($this->Id)]);

            foreach ($query as $builds_array) {
                // Delete the uploaded files
                $fileids = [];
                $build2uploadfiles = DB::select('
                                         SELECT fileid
                                         FROM build2uploadfile
                                         WHERE buildid = ?
                                     ', [(int) $builds_array->id]);
                foreach ($build2uploadfiles as $build2uploadfile_array) {
                    $fileid = (int) $build2uploadfile_array->fileid;
                    $fileids[] = $fileid;
                    $totalUploadSize -= unlink_uploaded_file($fileid);
                    Log::info("Removed file $fileid for project $this->Name.");
                }

                if (count($fileids) > 0) {
                    DB::table('uploadfile')->whereIn('id', $fileids)->delete();
                    DB::table('build2uploadfile')->whereIn('fileid', $fileids)->delete();
                }

                // Stop if we get below the quota
                if ($totalUploadSize <= $this->UploadQuota) {
                    break;
                }
            }
        }

        return true;
    }

    /**
     * Return the list of subproject groups that belong to this project.
     *
     * @return array<SubProjectGroup>
     */
    public function GetSubProjectGroups(): array
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        $query = DB::select("
                     SELECT id
                     FROM subprojectgroup
                     WHERE projectid=? AND endtime='1980-01-01 00:00:00'
                 ", [(int) $this->Id]);

        $subProjectGroups = [];
        foreach ($query as $result) {
            $subProjectGroup = new SubProjectGroup();
            // SetId automatically loads the rest of the group's data.
            $subProjectGroup->SetId((int) $result->id);
            $subProjectGroups[] = $subProjectGroup;
        }
        return $subProjectGroups;
    }

    /**
     * Return a JSON representation of this object.
     *
     * @return array<string,mixed>
     */
    public function ConvertToJSON(): array
    {
        $response = [];
        $clone = new \ReflectionObject($this);
        $properties = $clone->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $k = $property->getName();
            $v = $this->$k;
            $response[$k] = $v;
        }
        $response['name_encoded'] = urlencode($this->Name ?? '');

        $user = Auth::user();
        $includeQuota = !boolval(config('cdash.user_create_projects')) || ($user !== null && $user->admin);

        if ($includeQuota) {
            $uploadQuotaGB = 0;

            if ($this->UploadQuota > 0) {
                $uploadQuotaGB = $this->UploadQuota / (1024 * 1024 * 1024);
            }

            $max = config('cdash.max_upload_quota');
            $response['UploadQuota'] = min($uploadQuotaGB, $max);
            $response['MaxUploadQuota'] = $max;
        } else {
            unset($response['UploadQuota']);
        }
        return $response;
    }

    /**
     * Called once when the project is initially created.
     */
    public function InitialSetup(): bool
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        // Add the default groups.
        $BuildGroup = new BuildGroup();
        $BuildGroup->SetName('Nightly');
        $BuildGroup->SetDescription('Nightly builds');
        $BuildGroup->SetSummaryEmail(0);
        $this->AddBuildGroup($BuildGroup);

        $BuildGroup = new BuildGroup();
        $BuildGroup->SetName('Continuous');
        $BuildGroup->SetDescription('Continuous builds');
        $BuildGroup->SetSummaryEmail(0);
        $this->AddBuildGroup($BuildGroup);

        $BuildGroup = new BuildGroup();
        $BuildGroup->SetName('Experimental');
        $BuildGroup->SetDescription('Experimental builds');
        // default to "No Email" for the Experimental group
        $BuildGroup->SetSummaryEmail(2);
        $this->AddBuildGroup($BuildGroup);

        // Set up overview page to initially contain just the "Nightly" group.
        $groups = $this->GetBuildGroups();
        foreach ($groups as $group) {
            if ($group->GetName() == 'Nightly') {
                $buildgroupid = (int) $group->GetId();
                DB::table('overview_components')->insert([
                    'projectid' => $this->Id,
                    'buildgroupid' => $buildgroupid,
                    'position' => 1,
                    'type' => 'build',
                ]);
                break;
            }
        }

        // Add administrator to the project.
        $UserProject = new UserProject();
        $UserProject->Role = 2;
        $UserProject->EmailType = 3;// receive all emails
        $UserProject->ProjectId = $this->Id;
        $UserProject->UserId = 1; // administrator
        $UserProject->Save();

        return true;
    }

    public function AddBlockedBuild(string $buildname, string $sitename, string $ip): int
    {
        return EloquentProject::findOrFail((int) $this->Id)
            ->blockedbuilds()
            ->create([
                'buildname' => $buildname,
                'sitename' => $sitename,
                'ipaddress' => $ip,
            ])->id;
    }

    public function RemoveBlockedBuild(int $id): void
    {
        EloquentProject::findOrFail((int) $this->Id)
            ->blockedbuilds()
            ->findOrFail($id)
            ->delete();
    }

    /** Delete old builds if this project has too many. */
    public function CheckForTooManyBuilds(): bool
    {
        if (!$this->Id) {
            throw new RuntimeException('ID not set for project');
        }

        // Perform the "daily update" step asychronously here via cURL.
        if (config('cdash.daily_updates') === true) {
            $baseUrl = Config::getInstance()->getBaseUrl();
            self::curlRequest("{$baseUrl}/ajax/dailyupdatescurl.php?projectid={$this->Id}");
        }

        $max_builds = (int) config('cdash.builds_per_project');
        if ($max_builds === 0 || in_array($this->GetName(), config('cdash.unlimited_projects'))) {
            return false;
        }

        $num_builds = $this->GetNumberOfBuilds();

        // The +1 here is to account for the build we're currently inserting.
        if ($num_builds < ($max_builds + 1)) {
            return false;
        }

        // Remove old builds.
        $num_to_remove = $num_builds - $max_builds;
        require_once 'include/autoremove.php';
        removeFirstBuilds($this->Id, -1, $num_to_remove, true, false);

        Log::info("Too many builds for $this->Name");
        return true;
    }

    /**
     * Returns the Project's SubscriberCollection object. This method lazily loads the
     * SubscriberCollection if the object does not exist.
     */
    public function GetSubscriberCollection(): SubscriberCollection
    {
        if (!$this->SubscriberCollection) {
            $this->Fill();
            $this->SubscriberCollection = $this->GetProjectSubscribers();
        }

        return $this->SubscriberCollection;
    }

    /**
     * Sets the Project's SubscriberCollection property.
     */
    public function SetSubscriberCollection(SubscriberCollection $subscribers)
    {
        $this->SubscriberCollection = $subscribers;
    }

    /**
     * Returns a SubscriberCollection; a collection of all users and their subscription preferences.
     */
    public function GetProjectSubscribers(): SubscriberCollection
    {
        $service = ServiceContainer::getInstance()->getContainer();
        $collection = $service->make(SubscriberCollection::class);
        $userTable = qid('user');
        // TODO: works, but maybe find a better query
        $sql = "
            SELECT
               u2p.*,
               u.email email,
               labelid haslabels
            FROM user2project u2p
              JOIN $userTable u ON u.id = u2p.userid
              LEFT JOIN labelemail ON labelemail.userid = u2p.userid
            WHERE u2p.projectid = :id
            ORDER BY u.email;
        ";

        $user = $this->PDO->prepare($sql);
        $user->bindParam(':id', $this->Id, \PDO::PARAM_INT);
        $user->execute();

        foreach ($user->fetchAll(\PDO::FETCH_OBJ) as $row) {
            /** @var NotificationPreferences $preferences */
            $preferences = $service->make(
                BitmaskNotificationPreferences::class,
                ['mask' => $row->emailcategory]
            );
            $preferences->setPreferencesFromEmailTypeProperty($row->emailtype);
            if ($preferences->get(NotifyOn::NEVER)) {
                continue;
            }
            $preferences->set(NotifyOn::FIXED, $row->emailsuccess);
            $preferences->set(NotifyOn::SITE_MISSING, $row->emailmissingsites);
            $preferences->set(NotifyOn::REDUNDANT, $this->EmailRedundantFailures);
            $preferences->set(NotifyOn::LABELED, (bool)$row->haslabels);

            /** @var Subscriber $subscriber */
            $subscriber = $service->make(Subscriber::class, ['preferences' => $preferences]);
            $subscriber
                ->setAddress($row->email)
                ->setUserId($row->userid);

            $collection->add($subscriber);
        }

        return $collection;
    }

    /** Modify the build error/warning filters for this project if necessary. */
    public function UpdateBuildFilters(): bool
    {
        $buildErrorFilter = new BuildErrorFilter($this);
        if ($buildErrorFilter->GetErrorsFilter() != $this->ErrorsFilter ||
                $buildErrorFilter->GetWarningsFilter() != $this->WarningsFilter) {
            return $buildErrorFilter->AddOrUpdateFilters($this->WarningsFilter, $this->ErrorsFilter);
        }
        return true;
    }

    /**
     * Return the beginning and the end of the specified testing day
     * in DATETIME format.
     *
     * @return array<string>
     */
    public function ComputeTestingDayBounds($date): array
    {
        [$unused, $beginning_timestamp] = get_dates($date, $this->NightlyTime);

        $datetime = new \DateTime();
        $datetime->setTimeStamp($beginning_timestamp);
        $datetime->add(new \DateInterval('P1D'));
        $end_timestamp = $datetime->getTimestamp();

        $beginningOfDay = gmdate(FMT_DATETIME, $beginning_timestamp);
        $endOfDay = gmdate(FMT_DATETIME, $end_timestamp);
        return [$beginningOfDay, $endOfDay];
    }

    /**
     * Returns a boolean indicating whether the specified string could be a valid project name
     */
    public static function validateProjectName(string $projectname): bool
    {
        if (preg_match('/^[a-zA-Z0-9\ +.\-_]+$/', $projectname) !== 1) {
            return false;
        }
        if (str_contains($projectname, '_-_')) {
            return false;
        }

        return true;
    }

    private static function curlRequest(string $request) : void
    {
        $use_https = config('app.env') === 'production';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        if ($use_https) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_exec($ch);
        curl_close($ch);
    }
}
