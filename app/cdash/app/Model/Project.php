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

use App\Models\Project as EloquentProject;
use App\Models\SubProject;
use App\Utils\DatabaseCleanupUtils;
use CDash\Collection\SubscriberCollection;
use CDash\Database;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\ServiceContainer;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;

/** Main project class */
class Project
{
    public $Name;
    public $Id;
    public $Description;
    public $HomeUrl;
    public $CvsUrl;
    public $DocumentationUrl;
    public $BugTrackerUrl;
    public $BugTrackerNewIssueUrl;
    public $BugTrackerType;
    public $ImageId;
    public $Public;
    public $CoverageThreshold;
    public $TestingDataUrl;
    public $NightlyTime;
    public $NightlyDateTime;
    public $NightlyTimezone;
    public $EmailLowCoverage = 0;
    public $EmailTestTimingChanged = 0;
    public $EmailBrokenSubmission = 0;
    public $EmailRedundantFailures = 0;
    public $CvsViewerType;
    public $TestTimeStd;
    public $TestTimeStdThreshold;
    public $ShowTestTime = 0;
    public $TestTimeMaxStatus;
    public $EmailMaxItems = 5;
    public $EmailMaxChars = 255;
    public $ShowIPAddresses = 0;
    public $DisplayLabels = 0;
    public $ShareLabelFilters = 0;
    public $ViewSubProjectsLink = 0;
    public $AuthenticateSubmissions = 0;
    public $ShowCoverageCode = 0;
    public $AutoremoveTimeframe = 0;
    public int $AutoremoveMaxBuilds = 300;
    public $UploadQuota = 0;
    public $WarningsFilter = '';
    public $ErrorsFilter = '';
    public ?string $LdapFilter = null;
    public ?string $Banner = null;
    private Database $PDO;

    /**
     * @var SubscriberCollection
     */
    private $SubscriberCollection;

    public bool $Filled = false;

    public function __construct()
    {
        $this->PDO = Database::getInstance();
    }

    /** Delete a project */
    public function Delete(): bool
    {
        if (!$this->Id || EloquentProject::find($this->Id) === null) {
            return false;
        }

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
        $this->Filled = false; // TODO: Is this necessary?

        $project = EloquentProject::findOrNew($this->Id);
        $project->fill([
            'name' => $this->Name ?? '',
            'description' => $this->Description ?? '',
            'homeurl' => $this->HomeUrl ?? '',
            'cvsurl' => $this->CvsUrl ?? '',
            'documentationurl' => $this->DocumentationUrl ?? '',
            'bugtrackerurl' => $this->BugTrackerUrl ?? '',
            'bugtrackernewissueurl' => $this->BugTrackerNewIssueUrl ?? '',
            'bugtrackertype' => $this->BugTrackerType ?? '',
            'public' => (int) $this->Public,
            'coveragethreshold' => (int) $this->CoverageThreshold,
            'testingdataurl' => $this->TestingDataUrl ?? '',
            'nightlytime' => $this->NightlyTime ?? '',
            'emaillowcoverage' => (int) $this->EmailLowCoverage,
            'emailtesttimingchanged' => (int) $this->EmailTestTimingChanged,
            'emailbrokensubmission' => (int) $this->EmailBrokenSubmission,
            'emailredundantfailures' => (int) $this->EmailRedundantFailures,
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
            'ldapfilter' => $this->LdapFilter,
            'banner' => $this->Banner,
        ]);
        $project->save();
        $this->Id = $project->id;

        $buildErrorFilter = new BuildErrorFilter($this);
        $buildErrorFilter->Fill();
        if ($buildErrorFilter->GetErrorsFilter() != $this->ErrorsFilter
            || $buildErrorFilter->GetWarningsFilter() != $this->WarningsFilter) {
            return $buildErrorFilter->AddOrUpdateFilters($this->WarningsFilter, $this->ErrorsFilter);
        }
        return true;
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

        if (!isset($this->Id)) {
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
            $this->BugTrackerNewIssueUrl = $project->bugtrackernewissueurl;
            $this->BugTrackerType = $project->bugtrackertype;
            $this->ImageId = $project->imageid;
            $this->Public = $project->public;
            $this->CoverageThreshold = $project->coveragethreshold;
            $this->TestingDataUrl = $project->testingdataurl;
            $this->SetNightlyTime($project->nightlytime);
            $this->EmailLowCoverage = $project->emaillowcoverage;
            $this->EmailTestTimingChanged = $project->emailtesttimingchanged;
            $this->EmailBrokenSubmission = $project->emailbrokensubmission;
            $this->EmailRedundantFailures = $project->emailredundantfailures;
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
            $this->Banner = $project->banner;
            $this->LdapFilter = $project->ldapfilter;
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
     * Return a JSON representation of this object.
     *
     * @return array<string,mixed>
     */
    public function ConvertToJSON(): array
    {
        $response = [];
        $clone = new ReflectionObject($this);
        $properties = $clone->getProperties(ReflectionProperty::IS_PUBLIC);
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

        $max_builds = (int) config('cdash.builds_per_project');
        if ($max_builds === 0 || in_array($this->GetName(), config('cdash.unlimited_projects'))) {
            return false;
        }

        $project = EloquentProject::findOrFail((int) $this->Id);
        $num_builds = $project->builds()->count();

        // The +1 here is to account for the build we're currently inserting.
        if ($num_builds < ($max_builds + 1)) {
            return false;
        }

        // Remove old builds.
        $num_to_remove = $num_builds - $max_builds;
        DatabaseCleanupUtils::removeFirstBuilds($this->Id, -1, $num_to_remove, true);

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
    public function SetSubscriberCollection(SubscriberCollection $subscribers): void
    {
        $this->SubscriberCollection = $subscribers;
    }

    /**
     * Returns a SubscriberCollection; a collection of all users and their subscription preferences.
     */
    protected function GetProjectSubscribers(): SubscriberCollection
    {
        $service = ServiceContainer::getInstance()->getContainer();
        $collection = $service->make(SubscriberCollection::class);
        // TODO: works, but maybe find a better query
        $sql = '
            SELECT
               u2p.*,
               u.email email
            FROM user2project u2p
              JOIN users u ON u.id = u2p.userid
            WHERE u2p.projectid = :id
            ORDER BY u.email;
        ';

        $user = $this->PDO->prepare($sql);
        $user->bindParam(':id', $this->Id, PDO::PARAM_INT);
        $user->execute();

        foreach ($user->fetchAll(PDO::FETCH_OBJ) as $row) {
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

            /** @var Subscriber $subscriber */
            $subscriber = $service->make(Subscriber::class, ['preferences' => $preferences]);
            $subscriber
                ->setAddress($row->email)
                ->setUserId($row->userid);

            $collection->add($subscriber);
        }

        return $collection;
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

        $datetime = new DateTime();
        $datetime->setTimeStamp($beginning_timestamp);
        $datetime->add(new DateInterval('P1D'));
        $end_timestamp = $datetime->getTimestamp();

        $beginningOfDay = gmdate(FMT_DATETIME, $beginning_timestamp);
        $endOfDay = gmdate(FMT_DATETIME, $end_timestamp);
        return [$beginningOfDay, $endOfDay];
    }
}
