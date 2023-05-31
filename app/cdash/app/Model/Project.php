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

require_once  'include/common.php';
require_once 'include/cdashmail.php';

use CDash\Collection\SubscriberCollection;

use CDash\Config;
use CDash\Database;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferencesInterface;
use CDash\ServiceContainer;
use CDash\Model\Subscriber;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;

/** Main project class */
class Project
{
    const PROJECT_ADMIN = 2;
    const SITE_MAINTAINER = 1;
    const PROJECT_USER = 0;

    const ACCESS_PRIVATE = 0;
    const ACCESS_PUBLIC = 1;
    const ACCESS_PROTECTED = 2;

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
    public $AutoremoveMaxBuilds;
    public $UploadQuota;
    public $RobotName;
    public $RobotRegex;
    public $WebApiKey;
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
        if (empty($this->WebApiKey)) {
            $this->WebApiKey = '';
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
    public function AddBuildGroup($buildgroup): void
    {
        $buildgroup->SetProjectId($this->Id);
        $buildgroup->Save();
    }

    /** Delete a project */
    public function Delete(): bool
    {
        if (!$this->Id) {
            return false;
        }
        // Remove the project groups and rules
        $buildgroup = $this->PDO->executePrepared('SELECT * FROM buildgroup WHERE projectid=?', [intval($this->Id)]);
        foreach ($buildgroup as $buildgroup_array) {
            $groupid = intval($buildgroup_array['id']);
            $this->PDO->executePrepared('DELETE FROM buildgroupposition WHERE buildgroupid=?', [$groupid]);
            $this->PDO->executePrepared('DELETE FROM build2grouprule WHERE groupid=?', [$groupid]);
            $this->PDO->executePrepared('DELETE FROM build2group WHERE groupid=?', [$groupid]);
        }

        $this->PDO->executePrepared('DELETE FROM buildgroup WHERE projectid=?', [intval($this->Id)]);
        $this->PDO->executePrepared('DELETE FROM blockbuild WHERE projectid=?', [intval($this->Id)]);
        $this->PDO->executePrepared('DELETE FROM user2project WHERE projectid=?', [intval($this->Id)]);
        $this->PDO->executePrepared('DELETE FROM labelemail WHERE projectid=?', [intval($this->Id)]);
        $this->PDO->executePrepared('DELETE FROM labelemail WHERE projectid=?', [intval($this->Id)]);
        $this->PDO->executePrepared('DELETE FROM project2repositories WHERE projectid=?', [intval($this->Id)]);

        $dailyupdate = $this->PDO->executePrepared('SELECT id FROM dailyupdate WHERE projectid=?', [intval($this->Id)]);
        foreach ($dailyupdate as $dailyupdate_array) {
            $dailyupdateid = intval($dailyupdate_array['id']);
            $this->PDO->executePrepared('DELETE FROM dailyupdatefile WHERE dailyupdateid=?', [$dailyupdateid]);
        }

        $this->PDO->executePrepared('DELETE FROM dailyupdate WHERE projectid=?', [intval($this->Id)]);
        $this->PDO->executePrepared('DELETE FROM projectrobot WHERE projectid=?', [intval($this->Id)]);
        $this->PDO->executePrepared('DELETE FROM build_filters WHERE projectid=?', [intval($this->Id)]);

        // Delete any repositories that aren't shared with other projects.
        $repositories_query = $this->PDO->executePrepared('
                                  SELECT repositoryid
                                  FROM project2repositories
                                  WHERE projectid=?
                                  ORDER BY repositoryid
                              ', [intval($this->Id)]);
        add_last_sql_error('Project DeleteRepositories1', $this->Id);
        foreach ($repositories_query as $repository_array) {
            $repoid = intval($repository_array['repositoryid']);
            $projects_query = $this->PDO->executePreparedSingleRow('
                                  SELECT COUNT(projectid) AS c
                                  FROM project2repositories
                                  WHERE repositoryid=?
                              ', [$repoid]);
            add_last_sql_error('Project DeleteRepositories1', $this->Id);
            if ($projects_query['c'] > 1) {
                continue;
            }
            $this->PDO->executePrepared('DELETE FROM repositories WHERE id=?', [$repoid]);
        }
        $this->PDO->executePrepared('DELETE FROM project2repositories WHERE projectid=?', [intval($this->Id)]);

        $this->PDO->executePrepared('DELETE FROM project WHERE id=?', [intval($this->Id)]);

        return true;
    }

    /** Return if a project exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if (!$this->Id) {
            return false;
        }
        /** @var \PDOStatement $stmt */
        $stmt = $this->PDO->prepare("SELECT count(*) FROM project WHERE id=:id");
        $stmt->bindParam(':id', $this->Id);
        $stmt->execute();
        $query_array = pdo_fetch_array($stmt);
        if ($query_array[0] > 0) {
            return true;
        }
        return false;
    }

    // Save the project in the database
    public function Save(): bool
    {
        // Escape the values
        $Description = $this->Description ?? '';
        $HomeUrl = $this->HomeUrl ?? '';
        $CvsUrl = $this->CvsUrl ?? '';
        $DocumentationUrl = $this->DocumentationUrl ?? '';
        $BugTrackerUrl = $this->BugTrackerUrl ?? '';
        $BugTrackerFileUrl = $this->BugTrackerFileUrl ?? '';
        $BugTrackerNewIssueUrl = $this->BugTrackerNewIssueUrl ?? '';
        $BugTrackerType = $this->BugTrackerType ?? '';
        $TestingDataUrl = $this->TestingDataUrl ?? '';
        $NightlyTime = $this->NightlyTime ?? '';
        $GoogleTracker = $this->GoogleTracker ?? '';
        $RobotName = $this->RobotName ?? '';
        $RobotRegex = $this->RobotRegex ?? '';
        $Name = $this->Name ?? '';
        $CvsViewerType = $this->CvsViewerType ?? '';

        // Check if the project is already
        if ($this->Exists()) {
            // Trim the name
            $this->Name = trim($this->Name);
            $this->Initialize();

            $query = $this->PDO->executePrepared('
                         UPDATE project
                         SET
                             description=?,
                             homeurl=?,
                             cvsurl=?,
                             documentationurl=?,
                             bugtrackerurl=?,
                             bugtrackerfileurl=?,
                             bugtrackernewissueurl=?,
                             bugtrackertype=?,
                             public=?,
                             coveragethreshold=?,
                             testingdataurl=?,
                             nightlytime=?,
                             googletracker=?,
                             emaillowcoverage=?,
                             emailtesttimingchanged=?,
                             emailbrokensubmission=?,
                             emailredundantfailures=?,
                             emailadministrator=?,
                             showipaddresses=?,
                             displaylabels=?,
                             sharelabelfilters=?,
                             viewsubprojectslink=?,
                             authenticatesubmissions=?,
                             showcoveragecode=?,
                             autoremovetimeframe=?,
                             autoremovemaxbuilds=?,
                             uploadquota=?,
                             cvsviewertype=?,
                             testtimestd=?,
                             testtimestdthreshold=?,
                             showtesttime=?,
                             testtimemaxstatus=?,
                             emailmaxitems=?,
                             emailmaxchars=?
                         WHERE id=?
                     ', [
                         $Description,
                         $HomeUrl,
                         $CvsUrl,
                         $DocumentationUrl,
                         $BugTrackerUrl,
                         $BugTrackerFileUrl,
                         $BugTrackerNewIssueUrl,
                         $BugTrackerType,
                         intval($this->Public),
                         intval($this->CoverageThreshold),
                         $TestingDataUrl,
                         $NightlyTime,
                         $GoogleTracker,
                         intval($this->EmailLowCoverage),
                         intval($this->EmailTestTimingChanged),
                         intval($this->EmailBrokenSubmission),
                         intval($this->EmailRedundantFailures),
                         intval($this->EmailAdministrator),
                         intval($this->ShowIPAddresses),
                         intval($this->DisplayLabels),
                         intval($this->ShareLabelFilters),
                         intval($this->ViewSubProjectsLink),
                         intval($this->AuthenticateSubmissions),
                         intval($this->ShowCoverageCode),
                         intval($this->AutoremoveTimeframe),
                         intval($this->AutoremoveMaxBuilds),
                         intval($this->UploadQuota),
                         $CvsViewerType,
                         intval($this->TestTimeStd),
                         intval($this->TestTimeStdThreshold),
                         intval($this->ShowTestTime),
                         intval($this->TestTimeMaxStatus),
                         intval($this->EmailMaxItems),
                         intval($this->EmailMaxChars),
                         intval($this->Id)
                     ]);

            if ($query === false) {
                add_last_sql_error('Project Update', $this->Id);
                return false;
            }

            if ($this->RobotName != '') {
                // Check if it exists
                $robot = $this->PDO->executePreparedSingleRow('
                             SELECT projectid
                             FROM projectrobot
                             WHERE projectid=?
                         ', [intval($this->Id)]);
                if (!empty($robot)) {
                    $query = $this->PDO->executePrepared('
                                 UPDATE projectrobot
                                 SET
                                     robotname=?,
                                     authorregex=?
                                 WHERE projectid=?
                             ', [$RobotName, $RobotRegex, intval($this->Id)]);
                    if ($query === false) {
                        add_last_sql_error('Project Update', $this->Id);
                        return false;
                    }
                } else {
                    $query = $this->PDO->executePrepared('
                                 INSERT INTO projectrobot (
                                     projectid,
                                     robotname,
                                     authorregex
                                 )
                                 VALUES (?, ?, ?)
                             ', [intval($this->Id), $RobotName, $RobotRegex]);
                    if ($query === false) {
                        add_last_sql_error('Project Update', $this->Id);
                        return false;
                    }
                }
            }

            if (!$this->UpdateBuildFilters()) {
                return false;
            }
        } else {
            // insert the project

            $id = '';
            $idvalue = [];
            $prepared_array = $this->PDO->createPreparedArray(37);
            if ($this->Id) {
                $id = 'id, ';
                $idvalue[] = intval($this->Id);
                $prepared_array = $this->PDO->createPreparedArray(38);
            }

            if (strlen($this->ImageId) === 0) {
                $this->ImageId = 0;
            }

            // Trim the name
            $this->Name = trim($this->Name);
            $this->Initialize();

            $query = $this->PDO->executePrepared("
                         INSERT INTO project(
                             $id
                             name,
                             description,
                             homeurl,
                             cvsurl,
                             bugtrackerurl,
                             bugtrackerfileurl,
                             bugtrackernewissueurl,
                             bugtrackertype,
                             documentationurl,
                             public,
                             imageid,
                             coveragethreshold,
                             testingdataurl,
                             nightlytime,
                             googletracker,
                             emailbrokensubmission,
                             emailredundantfailures,
                             emaillowcoverage,
                             emailtesttimingchanged,
                             cvsviewertype,
                             testtimestd,
                             testtimestdthreshold,
                             testtimemaxstatus,
                             emailmaxitems,
                             emailmaxchars,
                             showtesttime,
                             emailadministrator,
                             showipaddresses,
                             displaylabels,
                             sharelabelfilters,
                             viewsubprojectslink,
                             authenticatesubmissions,
                             showcoveragecode,
                             autoremovetimeframe,
                             autoremovemaxbuilds,
                             uploadquota,
                             webapikey
                         )
                     VALUES $prepared_array
                 ", array_merge($idvalue, [
                     $Name,
                     $Description,
                     $HomeUrl,
                     $CvsUrl,
                     $BugTrackerUrl,
                     $BugTrackerFileUrl,
                     $BugTrackerNewIssueUrl,
                     $BugTrackerType,
                     $DocumentationUrl,
                     intval($this->Public),
                     intval($this->ImageId),
                     intval($this->CoverageThreshold),
                     $TestingDataUrl,
                     $NightlyTime,
                     $GoogleTracker,
                     intval($this->EmailBrokenSubmission),
                     intval($this->EmailRedundantFailures),
                     intval($this->EmailLowCoverage),
                     intval($this->EmailTestTimingChanged),
                     $CvsViewerType,
                     intval($this->TestTimeStd),
                     intval($this->TestTimeStdThreshold),
                     intval($this->TestTimeMaxStatus),
                     intval($this->EmailMaxItems),
                     intval($this->EmailMaxChars),
                     intval($this->ShowTestTime),
                     intval($this->EmailAdministrator),
                     intval($this->ShowIPAddresses),
                     intval($this->DisplayLabels),
                     intval($this->ShareLabelFilters),
                     intval($this->ViewSubProjectsLink),
                     intval($this->AuthenticateSubmissions),
                     intval($this->ShowCoverageCode),
                     intval($this->AutoremoveTimeframe),
                     intval($this->AutoremoveMaxBuilds),
                     intval($this->UploadQuota),
                     $this->WebApiKey
                 ]));

            if ($query === false) {
                add_last_sql_error('Project Create');
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id('project');
            }

            if ($this->RobotName != '') {
                $query = $this->PDO->executePrepared('
                             INSERT INTO projectrobot (
                                 projectid,
                                 robotname,
                                 authorregex
                             )
                             VALUES (?, ?, ?)
                         ', [intval($this->Id), $RobotName, $RobotRegex]);
                if ($query === false) {
                    return false;
                }
            }

            if (!$this->UpdateBuildFilters()) {
                return false;
            }
        }
        return true;
    }

    public function GetIdByName()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('SELECT id FROM project WHERE name = :name');
        $stmt->bindParam(':name', $this->Name);
        pdo_execute($stmt);
        $stmt->bindColumn('id', $this->Id);
        $stmt->fetch(\PDO::FETCH_BOUND);
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
    public function ExistsByName($name): bool
    {
        $this->Name = $name;
        if ($this->GetIdByName()) {
            return true;
        }
        return false;
    }

    /** Get the logo id */
    public function GetLogoId(): int
    {
        $query = $this->PDO->executePreparedSingleRow('
                     SELECT imageid FROM project WHERE id=?
                 ', [intval($this->Id)]);

        if ($query === false) {
            add_last_sql_error('Project GetLogoId', $this->Id);
            return 0;
        }

        if (!empty($query)) {
            return intval($query['imageid']);
        }
        return 0;
    }

    /** Fill in all the information from the database */
    public function Fill(): void
    {
        if ($this->Filled) {
            return;
        }

        if (!$this->Id) {
            echo 'Project Fill(): Id not set';
        }


        $project_array = $this->PDO->executePreparedSingleRow('
                             SELECT * FROM project WHERE id=?
                         ', [intval($this->Id)]);
        if ($project_array === false) {
            add_last_sql_error('Project Fill', $this->Id);
            return;
        }
        if ($project_array) {
            $this->Name = $project_array['name'];
            $this->Description = $project_array['description'];
            $this->HomeUrl = $project_array['homeurl'];
            $this->CvsUrl = $project_array['cvsurl'];
            $this->DocumentationUrl = $project_array['documentationurl'];
            $this->BugTrackerUrl = $project_array['bugtrackerurl'];
            $this->BugTrackerFileUrl = $project_array['bugtrackerfileurl'];
            $this->BugTrackerNewIssueUrl = $project_array['bugtrackernewissueurl'];
            $this->BugTrackerType = $project_array['bugtrackertype'];
            $this->ImageId = $project_array['imageid'];
            $this->Public = $project_array['public'];
            $this->CoverageThreshold = $project_array['coveragethreshold'];
            $this->TestingDataUrl = $project_array['testingdataurl'];
            $this->SetNightlyTime($project_array['nightlytime']);
            $this->GoogleTracker = $project_array['googletracker'];
            $this->EmailLowCoverage = $project_array['emaillowcoverage'];
            $this->EmailTestTimingChanged = $project_array['emailtesttimingchanged'];
            $this->EmailBrokenSubmission = $project_array['emailbrokensubmission'];
            $this->EmailRedundantFailures = $project_array['emailredundantfailures'];
            $this->EmailAdministrator = $project_array['emailadministrator'];
            $this->ShowIPAddresses = $project_array['showipaddresses'];
            $this->DisplayLabels = $project_array['displaylabels'];
            $this->ShareLabelFilters = $project_array['sharelabelfilters'];
            $this->ViewSubProjectsLink = $project_array['viewsubprojectslink'];
            $this->AuthenticateSubmissions = $project_array['authenticatesubmissions'];
            $this->ShowCoverageCode = $project_array['showcoveragecode'];
            $this->AutoremoveTimeframe = $project_array['autoremovetimeframe'];
            $this->AutoremoveMaxBuilds = $project_array['autoremovemaxbuilds'];
            $this->UploadQuota = $project_array['uploadquota'];
            $this->CvsViewerType = $project_array['cvsviewertype'];
            $this->TestTimeStd = $project_array['testtimestd'];
            $this->TestTimeStdThreshold = $project_array['testtimestdthreshold'];
            $this->ShowTestTime = $project_array['showtesttime'];
            $this->TestTimeMaxStatus = $project_array['testtimemaxstatus'];
            $this->EmailMaxItems = $project_array['emailmaxitems'];
            $this->EmailMaxChars = $project_array['emailmaxchars'];
            $this->WebApiKey = $project_array['webapikey'];
            if ($this->WebApiKey == '') {
                // If no web API key exists, we add one
                include_once 'include/common.php';
                $newKey = generate_password(40);
                $this->PDO->executePrepared('
                    UPDATE project SET webapikey=? WHERE id=?
                ', [$newKey, intval($this->Id)]);
                $this->WebApiKey = $newKey;
            }
        }

        // Check if we have a robot
        $robot = $this->PDO->executePreparedSingleRow('
                     SELECT * FROM projectrobot WHERE projectid=?
                 ', [intval($this->Id)]);
        if ($robot === false) {
            add_last_sql_error('Project Fill', $this->Id);
            return;
        }

        if (!empty($robot)) {
            $this->RobotName = $robot['robotname'];
            $this->RobotRegex = $robot['authorregex'];
        }

        // Check if we have filters
        $build_filters = $this->PDO->executePreparedSingleRow('
                             SELECT * FROM build_filters WHERE projectid=?
                         ', [intval($this->Id)]);
        if ($build_filters === false) {
            add_last_sql_error('Project Fill', $this->Id);
            throw new Exception(var_export($this->Id, true));
            return;
        }

        if (!empty($build_filters)) {
            $this->WarningsFilter = $build_filters['warnings'];
            $this->ErrorsFilter = $build_filters['errors'];
        }

        $this->Filled = true;
    }

    public function SetNightlyTime($nightly_time): void
    {
        $this->NightlyTime = $nightly_time;

        // Get the timezone for the project's nightly start time.
        try {
            $this->NightlyDateTime = new DateTime($this->NightlyTime);
            $this->NightlyTimezone = $this->NightlyDateTime->getTimezone();
        } catch (Exception) {
            // Bad timezone (probably) specified, try defaulting to UTC.
            $this->NightlyTimezone = new \DateTimeZone('UTC');
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

    /** Add a logo */
    public function AddLogo($contents, $filetype)
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
            $this->PDO->executePrepared('
                UPDATE project SET imageid=? WHERE id=?
            ', [$image->Id, intval($this->Id)]);
            add_last_sql_error('Project AddLogo', $this->Id);
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

        add_last_sql_error('Project AddRepositories', $this->Id);
        foreach ($repositories_query as $repository_array) {
            $repositoryid = intval($repository_array['repositoryid']);
            if (!isset($repositories[$currentRepository]) || strlen($repositories[$currentRepository]) === 0) {
                $query = $this->PDO->executePrepared('
                             SELECT COUNT(*) AS c
                             FROM project2repositories
                             WHERE repositoryid=?
                         ', [$repositoryid]);
                add_last_sql_error('Project AddRepositories', $this->Id);
                if (intval($query['c']) === 1) {
                    $this->PDO->executePrepared('DELETE FROM repositories WHERE id=?', [$repositoryid]);
                    add_last_sql_error('Project AddRepositories', $this->Id);
                }
                $this->PDO->executePrepared('
                    DELETE FROM project2repositories
                    WHERE projectid=? AND repositoryid=?
                ', [intval($this->Id), $repositoryid]);
                add_last_sql_error('Project AddRepositories', $this->Id);
            } else {
                // If the repository is not shared by any other project we update
                $count_array = $this->PDO->executePreparedSingleRow('
                                   SELECT count(*) as c
                                   FROM project2repositories
                                   WHERE repositoryid=?
                               ', [$repositoryid]);
                if (intval($count_array['c']) === 1) {
                    $this->PDO->executePrepared('
                        UPDATE repositories
                        SET
                            url=?,
                            username=?,
                            password=?,
                            branch=?
                        WHERE id=?
                    ', [
                        $repositories[$currentRepository],
                        $usernames[$currentRepository],
                        $passwords[$currentRepository],
                        $branches[$currentRepository],
                        $repositoryid]
                    );
                    add_last_sql_error('Project AddRepositories', $this->Id);
                } else {
                    // Otherwise we remove it from the current project and add it to the queue to be created
                    $this->PDO->executePrepared('
                        DELETE FROM project2repositories
                        WHERE projectid=? AND repositoryid=?
                    ', [intval($this->Id), $repositoryid]);

                    add_last_sql_error('Project AddRepositories', $this->Id);
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
            $repositories_query = $this->PDO->executePreparedSingleRow('
                                      SELECT id
                                      FROM repositories
                                      WHERE url=?
                                  ', [$url]);

            if (empty($repositories_query)) {
                $this->PDO->executePrepared('
                    INSERT INTO repositories (
                        url,
                        username,
                        password,
                        branch
                    ) VALUES (?, ?, ?, ?)
                ', [$url, $username, $password, $branch]);
                add_last_sql_error('Project AddRepositories', $this->Id);
                $repositoryid = intval(pdo_insert_id('repositories'));
            } else {
                $repositoryid = intval($repositories['id']);
            }
            $this->PDO->executePrepared('
                INSERT INTO project2repositories (
                    projectid,
                    repositoryid
                ) VALUES (?, ?)', [intval($this->Id), $repositoryid]);
            add_last_sql_error('Project AddRepositories', $this->Id);
        }
    }

    /** Get the repositories */
    public function GetRepositories(): array
    {
        $repositories = array();
        $repository = $this->PDO->executePrepared('
                          SELECT
                              url,
                              username,
                              password,
                              branch
                          FROM repositories, project2repositories
                          WHERE
                              repositories.id=project2repositories.repositoryid
                              AND project2repositories.projectid=?
                      ', [intval($this->Id)]);
        add_last_sql_error('Project GetRepositories', $this->Id);
        foreach ($repository as $repository_array) {
            $rep['url'] = $repository_array['url'];
            $rep['username'] = $repository_array['username'];
            $rep['password'] = $repository_array['password'];
            $rep['branch'] = $repository_array['branch'];
            $repositories[] = $rep;
        }
        return $repositories;
    }

    /** Get the build groups */
    public function GetBuildGroups(): array
    {
        $buildgroups = array();
        $query = $this->PDO->executePrepared("
                     SELECT id
                     FROM buildgroup
                     WHERE projectid=? AND endtime='1980-01-01 00:00:00'
                 ", [intval($this->Id)]);

        add_last_sql_error('Project GetBuildGroups', $this->Id);
        foreach ($query as $row) {
            $buildgroup = new BuildGroup();
            $buildgroup->SetId(intval($row['id']));
            $buildgroups[] = $buildgroup;
        }
        return $buildgroups;
    }

    /** Get the list of block builds */
    public function GetBlockedBuilds(): array
    {
        $sites = array();
        $site = $this->PDO->executePrepared('
                    SELECT
                        id,
                        buildname,
                        sitename,
                        ipaddress
                    FROM blockbuild
                    WHERE projectid=?
                ', [intval($this->Id)]);
        add_last_sql_error('Project GetBlockedBuilds', $this->Id);
        foreach ($site as $site_array) {
            $sites[] = $site_array;
        }
        return $sites;
    }

    /**
     * Get Ids of all the project registered
     * Maybe this function should go somewhere else but for now here
     *
     * @return array<int>
     */
    public function GetIds(): array
    {
        $ids = array();
        $query = $this->PDO->executePrepared('SELECT id FROM project ORDER BY id');
        add_last_sql_error('Project GetIds', $this->Id);
        foreach ($query as $query_array) {
            $ids[] = intval($query_array['id']);
        }
        return $ids;
    }

    /** Get the Name of the project */
    public function GetName()
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if (!$this->Id) {
            echo 'Project GetName(): Id not set';
            return false;
        }

        $project = $this->PDO->executePreparedSingleRow('
                       SELECT name FROM project WHERE id=?
                   ', [intval($this->Id)]);
        if ($project === false) {
            add_last_sql_error('Project GetName', $this->Id);
            return false;
        }
        $this->Name = $project['name'];
        return $this->Name;
    }

    /** Get the coveragethreshold */
    public function GetCoverageThreshold()
    {
        if (strlen($this->CoverageThreshold) > 0) {
            return $this->CoverageThreshold;
        }

        if (!$this->Id) {
            echo 'Project GetCoverageThreshold(): Id not set';
            return false;
        }

        $project = $this->PDO->executePreparedSingleRow('
                       SELECT coveragethreshold FROM project WHERE id=?
                   ', [intval($this->Id)]);
        if ($project === false) {
            add_last_sql_error('Project GetCoverageThreshold', $this->Id);
            return false;
        }
        $this->CoverageThreshold = intval($project['coveragethreshold']);
        return $this->CoverageThreshold;
    }

    /** Get the number of subproject */
    public function GetNumberOfSubProjects($date = null): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfSubProjects(): Id not set';
            return false;
        }

        if ($date == null) {
            $date = gmdate(FMT_DATETIME);
        }

        $project = $this->PDO->executePreparedSingleRow("
                       SELECT count(*) AS c
                       FROM subproject
                       WHERE
                           projectid=?
                           AND (
                               endtime='1980-01-01 00:00:00'
                               OR endtime>?
                           )
                   ", [intval($this->Id), $date]);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfSubProjects', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /**
     * Get the subproject ids
     *
     * @return array<int>|false
     */
    public function GetSubProjects(): array|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfSubProjects(): Id not set';
            return false;
        }

        $date = gmdate(FMT_DATETIME);

        $project = $this->PDO->executePrepared("
                       SELECT id
                       FROM subproject
                       WHERE
                           projectid=?
                           AND starttime<=?
                           AND (endtime>? OR endtime='1980-01-01 00:00:00')
                   ", [intval($this->Id), $date, $date]);
        if ($project === false) {
            add_last_sql_error('Project GetSubProjects', $this->Id);
            return false;
        }

        $ids = array();
        foreach ($project as $project_array) {
            $ids[] = intval($project_array['id']);
        }
        return $ids;
    }

    /** Get the last submission of the subproject*/
    public function GetLastSubmission(): string|false
    {
        if (!config('cdash.show_last_submission')) {
            return false;
        }

        if (!$this->Id) {
            echo 'Project GetLastSubmission(): Id not set';
            return false;
        }

        $build = $this->PDO->executePreparedSingleRow('
                     SELECT starttime
                     FROM build
                     WHERE projectid=?
                     ORDER BY starttime DESC
                     LIMIT 1
                 ', [intval($this->Id)]);

        if ($build === false) {
            add_last_sql_error('Project GetLastSubmission', $this->Id);
            return false;
        }

        if (!is_array($build) || !array_key_exists('starttime', $build)) {
            return false;
        }

        return date(FMT_DATETIMESTD, strtotime($build['starttime'] . 'UTC'));
    }

    /** Get the total number of builds for a project*/
    public function GetTotalNumberOfBuilds(): int|false
    {
        if (!$this->Id) {
            echo 'Project GetTotalNumberOfBuilds(): Id not set';
            return false;
        }

        $project = $this->PDO->executePreparedSingleRow('
                       SELECT count(*) AS c
                       FROM build
                       WHERE
                           parentid IN (-1, 0)
                           AND projectid=?
                   ', [intval($this->Id)]);

        if ($project === false) {
            add_last_sql_error('Project GetTotalNumberOfBuilds', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /** Get the number of builds given a date range */
    public function GetNumberOfBuilds($startUTCdate = null, $endUTCdate = null): int|false
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::GetNumberOfBuilds', LOG_ERR,
                $this->Id);
            return false;
        }

        // Construct our query given the optional parameters of this function.
        $sql = 'SELECT COUNT(build.id) FROM build
                WHERE projectid = :projectid AND parentid IN (-1, 0)';
        if (!is_null($startUTCdate)) {
            $sql .= ' AND build.starttime > :start';
        }
        if (!is_null($endUTCdate)) {
            $sql .= ' AND build.starttime <= :end';
        }

        $stmt = $this->PDO->prepare($sql);
        $stmt->bindParam(':projectid', $this->Id);
        if (!is_null($startUTCdate)) {
            $stmt->bindParam(':start', $startUTCdate);
        }
        if (!is_null($endUTCdate)) {
            $stmt->bindParam(':end', $endUTCdate);
        }

        if (!pdo_execute($stmt)) {
            return false;
        }

        return intval($stmt->fetchColumn());
    }

    /** Get the number of builds given per day */
    public function GetBuildsDailyAverage($startUTCdate, $endUTCdate): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfBuilds(): Id not set';
            return false;
        }
        $nbuilds = $this->GetNumberOfBuilds($startUTCdate, $endUTCdate);
        $project = $this->PDO->executePreparedSingleRow('
                       SELECT starttime
                       FROM build
                       WHERE
                           projectid=?
                           AND starttime>?
                           AND starttime<=?
                           AND parentid IN (-1, 0)
                       ORDER BY starttime ASC
                       LIMIT 1
                   ', [intval($this->Id), $startUTCdate, $endUTCdate]);
        if (empty($project)) {
            return 0;
        }
        $first_build = $project['starttime'];
        $nb_days = strtotime($endUTCdate) - strtotime($first_build);
        $nb_days = intval($nb_days / 86400) + 1;
        return $nbuilds / $nb_days;
    }

    /** Get the number of warning builds given a date range */
    public function GetNumberOfWarningBuilds($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfWarningBuilds(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT count(*) AS c
                  FROM build, build2group, buildgroup
                  WHERE
                      build.projectid=?
                      AND build.starttime>?
                      AND build.starttime<=?
                      AND build2group.buildid=build.id
                      AND build2group.groupid=buildgroup.id
                      AND buildgroup.includesubprojectotal=1
                      AND build.buildwarnings>0';
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfWarningBuilds', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /** Get the number of error builds given a date range */
    public function GetNumberOfErrorBuilds($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfErrorBuilds(): Id not set';
            return false;
        }

        // build failures
        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT count(*) AS c
                  FROM build, build2group, buildgroup
                  WHERE
                      build.projectid=?
                      AND build.starttime>?
                      AND build.starttime<=?
                      AND build2group.buildid=build.id
                      AND build2group.groupid=buildgroup.id
                      AND buildgroup.includesubprojectotal=1
                      AND build.builderrors>0';
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfErrorBuilds', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /** Get the number of failing builds given a date range */
    public function GetNumberOfPassingBuilds($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfPassingBuilds(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT count(*) AS c
                  FROM build b
                  JOIN build2group b2g ON (b2g.buildid=b.id)
                  JOIN buildgroup bg ON (bg.id=b2g.groupid)
                  WHERE
                      b.projectid=?
                      AND b.starttime>?
                      AND b.starttime<=?
                      AND bg.includesubprojectotal=1
                      AND b.builderrors=0
                      AND b.buildwarnings=0';
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfPassingBuilds', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfWarningConfigures($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfWarningConfigures(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT COUNT(*) AS c
                  FROM build b
                  JOIN build2group b2g ON (b2g.buildid = b.id)
                  JOIN buildgroup bg ON (bg.id = b2g.groupid)
                  WHERE
                      b.projectid = ?
                      AND b.starttime > ?
                      AND b.starttime <= ?
                      AND b.configurewarnings > 0
                      AND bg.includesubprojectotal = 1';
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfWarningConfigures', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfErrorConfigures($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfErrorConfigures(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT COUNT(*) AS c
                  FROM build b
                  JOIN build2group b2g ON (b2g.buildid = b.id)
                  JOIN buildgroup bg ON (bg.id = b2g.groupid)
                  WHERE
                      b.projectid = ?
                      AND b.starttime > ?
                      AND b.starttime <= ?
                      AND b.configureerrors > 0
                      AND bg.includesubprojectotal = 1';
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfErrorConfigures', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfPassingConfigures($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfPassingConfigures(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT COUNT(*) AS c
                  FROM build b
                  JOIN build2group b2g ON (b2g.buildid = b.id)
                  JOIN buildgroup bg ON (bg.id = b2g.groupid)
                  WHERE
                      b.projectid = ?
                      AND b.starttime > ?
                      AND b.starttime <= ?
                      AND b.configureerrors = 0
                      AND b.configurewarnings = 0
                      AND bg.includesubprojectotal = 1';
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfPassingConfigures', $this->Id);
            return false;
        }
        return intval($project['c']);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfPassingTests($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfPassingTests(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT SUM(build.testpassed) AS s
                  FROM build, build2group, buildgroup
                  WHERE
                      build.projectid=?
                      AND build2group.buildid=build.id
                      AND build.testpassed>=0
                      AND build2group.groupid=buildgroup.id
                      AND buildgroup.includesubprojectotal=1
                      AND build.starttime>?
                      AND build.starttime<=?';
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfPassingTests', $this->Id);
            return false;
        }
        return intval($project['s']);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfFailingTests($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfFailingTests(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT SUM(build.testfailed) AS s
                  FROM build, build2group, buildgroup
                  WHERE
                      build.projectid=?
                      AND build2group.buildid=build.id
                      AND build.testfailed>=0
                      AND build2group.groupid=buildgroup.id
                      AND buildgroup.includesubprojectotal=1
                      AND build.starttime>?
                      AND build.starttime<=?';
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfFailingTests', $this->Id);
            return false;
        }
        return intval($project['s']);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfNotRunTests($startUTCdate, $endUTCdate,
        $childrenOnly = false): int|false
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfNotRunTests(): Id not set';
            return false;
        }

        $params = [intval($this->Id), $startUTCdate, $endUTCdate];
        $query = 'SELECT SUM(build.testnotrun) AS s
                  FROM build, build2group, buildgroup
                  WHERE
                      build.projectid=?
                      AND build2group.buildid=build.id
                      AND build.testnotrun>=0
                      AND build2group.groupid=buildgroup.id
                      AND buildgroup.includesubprojectotal=1
                      AND build.starttime>?
                      AND build.starttime<=?';
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = $this->PDO->executePreparedSingleRow($query, $params);
        if ($project === false) {
            add_last_sql_error('Project GetNumberOfNotRunTests', $this->Id);
            return false;
        }
        return intval($project['s']);
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

        $labelids = array();
        $labels = $this->PDO->executePrepared("
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
                      $today
                  ]);

        if ($labels === false) {
            add_last_sql_error('Project GetLabels', $this->Id);
            return false;
        }

        foreach ($labels as $label_array) {
            $labelids[] = intval($label_array['id']);
        }
        return array_unique($labelids);
    }

    /** Send an email to the administrator of the project */
    public function SendEmailToAdmin(string $subject, string $body): bool
    {
        if (!$this->Id) {
            echo 'Project SendEmailToAdmin(): Id not set';
            return false;
        }
        $config = Config::getInstance();
        // Check if we should send emails
        $project = $this->PDO->executePreparedSingleRow('
                       SELECT emailadministrator, name
                       FROM project
                       WHERE id = ?
                   ', [intval($this->Id)]);
        if ($project === false) {
            add_last_sql_error('Project SendEmailToAdmin', $this->Id);
            return false;
        }

        if (intval($project['emailadministrator']) === 0) {
            return true;
        }

        // Find the site maintainers
        $UserProject = new UserProject();
        $UserProject->ProjectId = $this->Id;

        $userids = $UserProject->GetUsers(2); // administrators
        $recipients = [];
        foreach ($userids as $userid) {
            $User = new User;
            $User->Id = $userid;
            $recipients[] = $User->GetEmail();
        }

        if (!empty($recipients)) {
            $projectname = $project['name'];
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
            add_log('Id not set', 'Project::GetUploadsTotalSize', LOG_ERR);
            return false;
        }
        $totalSizeQuery = $this->PDO->executePrepared('
                              SELECT DISTINCT uploadfile.id, uploadfile.filesize AS size
                              FROM build, build2uploadfile, uploadfile
                              WHERE
                                  build.projectid=?
                                  AND build.id=build2uploadfile.buildid
                                  AND build2uploadfile.fileid=uploadfile.id
                          ', [intval($this->Id)]);
        if ($totalSizeQuery === false) {
            add_last_sql_error('Project::GetUploadsTotalSize', $this->Id);
            return false;
        }

        // TODO: (williamjallen) This should be done in SQL
        $totalSize = 0;
        foreach ($totalSizeQuery as $result) {
            $totalSize += intval($result['size']);
        }
        return $totalSize;
    }

    /**
     * Checks whether this project has exceeded its upload size quota.  If so,
     * Removes the files (starting with the oldest builds) until the total upload size
     * is <= the upload quota.
     */
    public function CullUploadedFiles(): bool
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::CullUploadedFiles', LOG_ERR);
            return false;
        }
        $totalUploadSize = $this->GetUploadsTotalSize();

        if ($totalUploadSize > $this->UploadQuota) {
            require_once 'include/common.php';
            add_log('Upload quota exceeded, removing old files', 'Project::CullUploadedFiles',
                LOG_INFO, $this->Id);

            $query = $this->PDO->executePrepared('
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
                $build2uploadfiles = $this->PDO->executePrepared('
                                         SELECT fileid
                                         FROM build2uploadfile
                                         WHERE buildid = ?
                                     ', [intval($builds_array['id'])]);
                foreach ($build2uploadfiles as $build2uploadfile_array) {
                    $fileid = intval($build2uploadfile_array['fileid']);
                    $fileids[] = $fileid;
                    $totalUploadSize -= unlink_uploaded_file($fileid);
                    add_log("Removed file $fileid", 'Project::CullUploadedFiles', LOG_INFO, $this->Id);
                }

                if (count($fileids) > 0) {
                    $prepared_array = $this->PDO->createPreparedArray(count($fileids));
                    $this->PDO->executePrepared("DELETE FROM uploadfile WHERE id IN $prepared_array", $fileids);
                    $this->PDO->executePrepared("DELETE FROM build2uploadfile WHERE fileid IN $prepared_array", $fileids);
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
     * @return array<SubProjectGroup>|false
     */
    public function GetSubProjectGroups(): array|false
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::GetSubProjectGroups', LOG_ERR);
            return false;
        }

        $query = $this->PDO->executePrepared("
                     SELECT id
                     FROM subprojectgroup
                     WHERE projectid=? AND endtime='1980-01-01 00:00:00'
                 ", [intval($this->Id)]);
        if ($query === false) {
            add_last_sql_error('Project::GetSubProjectGroups', $this->Id);
            return false;
        }

        $subProjectGroups = array();
        foreach ($query as $result) {
            $subProjectGroup = new SubProjectGroup();
            // SetId automatically loads the rest of the group's data.
            $subProjectGroup->SetId(intval($result['id']));
            $subProjectGroups[] = $subProjectGroup;
        }
        return $subProjectGroups;
    }

    /**
     * Return a JSON representation of this object.
     */
    public function ConvertToJSON(\App\Models\User $user): array
    {
        $config = Config::getInstance();
        $response = [];
        $clone = new \ReflectionObject($this);
        $properties = $clone->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $k = $property->getName();
            $v = $this->$k;
            $response[$k] = $v;
        }
        $response['name_encoded'] = urlencode($this->Name ?? '');

        $includeQuota = !$config->get('CDASH_USER_CREATE_PROJECTS') || $user->IsAdmin();

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
            return false;
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
                $buildgroupid = $group->GetId();
                $this->PDO->executePrepared("
                    INSERT INTO overview_components (
                        projectid,
                        buildgroupid,
                        position,
                        type
                    )
                    VALUES (?, ?, '1', 'build')", [intval($this->Id), intval($buildgroupid)]);
                add_last_sql_error('CreateProject :: DefaultOverview', $this->Id);
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

    public function AddBlockedBuild(string $buildname, string $sitename, string $ip)
    {
        $stmt = $this->PDO->prepare(
            'INSERT INTO blockbuild (projectid,buildname,sitename,ipaddress)
                VALUES (:projectid, :buildname, :sitename, :ip)');
        $stmt->bindParam(':projectid', $this->Id);
        $stmt->bindParam(':buildname', $buildname);
        $stmt->bindParam(':sitename', $sitename);
        $stmt->bindParam(':ip', $ip);
        pdo_execute($stmt);
        $blocked_id = pdo_insert_id('blockbuild');
        return $blocked_id;
    }

    public function RemoveBlockedBuild($id): void
    {
        $stmt = $this->PDO->prepare('DELETE FROM blockbuild WHERE id=?');
        pdo_execute($stmt, [$id]);
    }

    // Delete old builds if this project has too many.
    public function CheckForTooManyBuilds(): bool
    {
        if (!$this->Id) {
            return false;
        }
        $max_builds = config('cdash.builds_per_project');
        if ($max_builds == 0 ||
                in_array($this->GetName(), config('cdash.unlimited_projects'))) {
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

        add_log("Too many builds for $this->Name", 'project_has_too_many_builds',
            LOG_INFO, $this->Id);
        return true;
    }

    /**
     * Returns the Project's SubscriberCollection object. This method lazily loads the
     * SubscriberCollection if the object does not exist.
     *
     * @returns \CDash\Collection\SubscriberCollection
     */
    public function GetSubscriberCollection()
    {
        if (!$this->SubscriberCollection) {
            $this->Fill();
            $this->SubscriberCollection = $this->GetProjectSubscribers();
        }

        return $this->SubscriberCollection;
    }

    /**
     * Sets the Project's SubscriberCollection property.
     *
     * @param SubscriberCollection $subscribers
     */
    public function SetSubscriberCollection(SubscriberCollection $subscribers)
    {
        $this->SubscriberCollection = $subscribers;
    }

    /**
     * Returns a SubscriberCollection; a collection of all users and their subscription preferences.
     *
     * @return SubscriberCollection
     */
    public function GetProjectSubscribers()
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

    // Modify the build error/warning filters for this project if necessary.
    public function UpdateBuildFilters(): bool
    {
        $buildErrorFilter = new BuildErrorFilter($this);
        if ($buildErrorFilter->GetErrorsFilter() != $this->ErrorsFilter ||
                $buildErrorFilter->GetWarningsFilter() != $this->WarningsFilter) {
            return $buildErrorFilter->AddOrUpdateFilters(
                $this->WarningsFilter, $this->ErrorsFilter);
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
        list($unused, $beginning_timestamp) =
            get_dates($date, $this->NightlyTime);

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
}
