<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
// It is assumed that appropriate headers should be included before including this file
include_once('models/dailyupdatefile.php');
include_once('models/buildgrouprule.php');
include_once('models/buildgroupposition.php');

/** Main project class */
class Project
{
  var $Name;
  var $Id; 
  var $Description;
  var $HomeUrl;
  var $CvsUrl;
  var $DocumentationUrl;
  var $BugTrackerUrl;
  var $BugTrackerFileUrl;
  var $ImageId;
  var $Public;
  var $CoverageThreshold;
  var $NightlyTime;
  var $GoogleTracker;
  var $EmailBuildMissing;
  var $EmailLowCoverage;
  var $EmailTestTimingChanged;
  var $EmailBrokenSubmission;
  var $EmailRedundantFailures;
  var $CvsViewerType;
  var $TestTimeStd;
  var $TestTimeStdThreshold;
  var $ShowTestTime;
  var $TestTimeMaxStatus;
  var $EmailMaxItems;
  var $EmailMaxChars;
  var $EmailAdministrator;
  var $ShowIPAddresses;
  var $DisplayLabels;
  var $AutoremoveTimeframe;
  var $AutoremoveMaxBuilds;

  function __construct()
    {
    $this->Initialize();
    }

  /** Initialize non defined variables */
  private function Initialize()
    {
    if(empty($this->EmailBuildMissing))
      { 
      $this->EmailBuildMissing=0;
      } 
    if(empty($this->EmailLowCoverage))
      { 
      $this->EmailLowCoverage=0;
      } 
    if(empty($this->EmailTestTimingChanged))
      { 
      $this->EmailTestTimingChanged=0;
      } 
    if(empty($this->EmailBrokenSubmission))
      { 
      $this->EmailBrokenSubmission=0;
      } 
    if(empty($this->EmailRedundantFailures))
      { 
      $this->EmailRedundantFailures=0;
      } 
    if(empty($this->EmailAdministrator))
      { 
      $this->EmailAdministrator=1;
      } 
    if(empty($this->ShowIPAddresses))
      { 
      $this->ShowIPAddresses=1;
      }
    if(empty($this->ShowTestTime))
      { 
      $this->ShowTestTime=1;
      }   
    if(empty($this->DisplayLabels))
      { 
      $this->DisplayLabels=1;
      }
    if(empty($this->AutoremoveTimeframe))
      { 
      $this->AutoremoveTimeframe=0;
      }
    if(empty($this->AutoremoveMaxBuilds))
      { 
      $this->AutoremoveMaxBuilds=300;
      }
    }  
    
  /** Add a build group */
  function AddBuildGroup($buildgroup)
    {
    $buildgroup->ProjectId = $this->Id;
    $buildgroup->Save();
    }

  function AddDailyUpdate($dailyupdate)
    {
    $dailyupdate->ProjectId = $this->Id;
    $dailyupdate->Save();
    }
    
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "NAME": $this->Name = $value;break;
      case "DESCRIPTION": $this->Description = $value;break;
      case "HOMEURL": $this->HomeUrl = $value;break;
      case "CVSURL": $this->CvsUrl = $value;break;
      case "DOCUMENTATIONURL": $this->DocumentationUrl = $value;break;
      case "BUGTRACKERURL": $this->BugTrackerUrl = $value;break;
      case "BUGTRACKERFILEURL": $this->BugTrackerFileUrl = $value;break;
      case "IMAGEID": $this->ImageId = $value;break;
      case "PUBLIC": $this->Public = $value;break;
      case "COVERAGETHRESHOLD": $this->CoverageThreshold = $value;break;
      case "NIGHTLYTIME": $this->NightlyTime = $value;break;
      case "GOOGLETRACKER": $this->GoogleTracker = $value;break;
      case "EMAILBUILDMISSING": $this->EmailBuildMissing = $value;break;
      case "EMAILLOWCOVERAGE": $this->EmailLowCoverage = $value;break;
      case "EMAILTESTTIMINGCHANGED": $this->EmailTestTimingChanged = $value;break;
      case "EMAILBROKENSUBMISSION": $this->EmailBrokenSubmission = $value;break;
      case "EMAILREDUNDANTFAILURES": $this->EmailRedundantFailures = $value;break;
      case "EMAILADMINISTRATOR": $this->EmailAdministrator = $value;break;
      case "SHOWIPADDRESSES": $this->ShowIPAddresses = $value;break;
      case "DISPLAYLABELS": $this->DisplayLabels = $value;break;
      case "AUTOREMOVETIMEFRAME": $this->AutoremoveTimeframe = $value;break;
      case "AUTOREMOVEMAXBUILDS": $this->AutoremoveMaxBuilds = $value;break;
      case "CVSVIEWERTYPE": $this->CvsViewerType = $value;break;
      case "TESTTIMESTD": $this->TestTimeStd = $value;break;
      case "TESTTIMESTDTHRESHOLD": $this->TestTimeStdThreshold = $value;break;
      case "SHOWTESTTIME": $this->ShowTestTime = $value;break;
      case "TESTTIMEMAXSTATUS": $this->TestTimeMaxStatus = $value;break;
      case "EMAILMAXITEMS": $this->EmailMaxItems = $value;break;
      case "EMAILMAXCHARS": $this->EmailMaxChars = $value;break;
      }
    }    
  
  /** Delete a project */
  function Delete()
    {
    if(!$this->Id)
      {
      return false;    
      }
    // Remove the project groups and rules
    $buildgroup = pdo_query("SELECT * FROM buildgroup WHERE projectid=$this->Id");
    while($buildgroup_array = pdo_fetch_array($buildgroup))
      {
      $groupid = $buildgroup_array["id"];
      pdo_query("DELETE FROM buildgroupposition WHERE buildgroupid=$groupid");
      pdo_query("DELETE FROM build2grouprule WHERE groupid=$groupid");
      pdo_query("DELETE FROM build2group WHERE groupid=$groupid");
      }
   
    pdo_query("DELETE FROM buildgroup WHERE projectid=$this->Id");
    pdo_query("DELETE FROM user2project WHERE projectid=$this->Id");
    pdo_query("DELETE FROM labelemail WHERE projectid=$this->Id");
    pdo_query("DELETE FROM labelemail WHERE projectid=$this->Id");
    pdo_query("DELETE FROM project2repositories WHERE projectid=$this->Id");
    
    $dailyupdate = pdo_query("SELECT id FROM dailyupdate WHERE projectid=$this->Id");
    while($dailyupdate_array = pdo_fetch_array($dailyupdate))
      {
      $dailyupdateid = $dailyupdate_array['id'];
      pdo_query("DELETE FROM dailyupdatefile WHERE dailyupdateid='$dailyupdateid'");
      }
    
    pdo_query("DELETE FROM dailyupdate WHERE projectid=$this->Id");  
    pdo_query("DELETE FROM project WHERE id=$this->Id");
    }
      
  /** Return if a project exists */
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM project WHERE id='".$this->Id."'");
    $query_array = pdo_fetch_array($query);
    if($query_array[0]>0)
      {
      return true;
      }
    return false;
    }      
      
  // Save the project in the database
  function Save()
    {
    // Check if the project is already
    if($this->Exists())
      {
      // Trim the name
      $this->Name = trim($this->Name);
      $this->Initialize();
      // Update the project
      $query = "UPDATE project SET ";
      $query .= "description='".$this->Description."'";
      $query .= ",homeurl='".$this->HomeUrl."'";
      $query .= ",cvsurl='".$this->CvsUrl."'";
      $query .= ",documentationurl='".$this->DocumentationUrl."'";
      $query .= ",bugtrackerurl='".$this->BugTrackerUrl."'";
      $query .= ",bugtrackerfileurl='".$this->BugTrackerFileUrl."'";
      $query .= ",public=".qnum($this->Public);
      $query .= ",coveragethreshold=".qnum($this->CoverageThreshold);
      $query .= ",nightlytime='".$this->NightlyTime."'";
      $query .= ",googletracker='".$this->GoogleTracker."'";
      $query .= ",emailbuildmissing=".qnum($this->EmailBuildMissing);
      $query .= ",emaillowcoverage=".qnum($this->EmailLowCoverage);
      $query .= ",emailtesttimingchanged=".qnum($this->EmailTestTimingChanged);
      $query .= ",emailbrokensubmission=".qnum($this->EmailBrokenSubmission);
      $query .= ",emailredundantfailures=".qnum($this->EmailRedundantFailures);
      $query .= ",emailadministrator=".qnum($this->EmailAdministrator);
      $query .= ",showipaddresses=".qnum($this->ShowIPAddresses);
      $query .= ",displaylabels=".qnum($this->DisplayLabels);
      $query .= ",autoremovetimeframe=".qnum($this->AutoremoveTimeframe);
      $query .= ",autoremovemaxbuilds=".qnum($this->AutoremoveMaxBuilds);
      $query .= ",cvsviewertype='".$this->CvsViewerType."'";
      $query .= ",testtimestd=".qnum($this->TestTimeStd);
      $query .= ",testtimestdthreshold=".qnum($this->TestTimeStdThreshold);
      $query .= ",showtesttime=".qnum($this->ShowTestTime);
      $query .= ",testtimemaxstatus=".qnum($this->TestTimeMaxStatus);
      $query .= ",emailmaxitems=".qnum($this->EmailMaxItems);
      $query .= ",emailmaxchars=".qnum($this->EmailMaxChars);
      $query .= " WHERE id=".qnum($this->Id)."";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("Project Update");
        return false;
        }
      }
    else // insert the project
      {      
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }
      
      if(strlen($this->ImageId) == 0)
        {
        $this->ImageId = 0;
        }
      
      // Trim the name
      $this->Name = trim($this->Name);
      $this->Initialize();
      $query = "INSERT INTO project(".$id."name,description,homeurl,cvsurl,bugtrackerurl,bugtrackerfileurl,documentationurl,public,imageid,coveragethreshold,nightlytime,
                                    googletracker,emailbrokensubmission,emailredundantfailures,
                                    emailbuildmissing,emaillowcoverage,emailtesttimingchanged,cvsviewertype,
                                    testtimestd,testtimestdthreshold,testtimemaxstatus,emailmaxitems,emailmaxchars,showtesttime,emailadministrator,showipaddresses
                                    ,displaylabels,autoremovetimeframe,autoremovemaxbuilds)
                 VALUES (".$idvalue."'$this->Name','$this->Description','$this->HomeUrl','$this->CvsUrl','$this->BugTrackerUrl','$this->BugTrackerFileUrl','$this->DocumentationUrl',
                 ".qnum($this->Public).",".qnum($this->ImageId).",".qnum($this->CoverageThreshold).",'$this->NightlyTime',
                 '$this->GoogleTracker',".qnum($this->EmailBrokenSubmission).",".qnum($this->EmailRedundantFailures).",".qnum($this->EmailBuildMissing).","
                 .qnum($this->EmailLowCoverage).",".qnum($this->EmailTestTimingChanged).",'$this->CvsViewerType',".qnum($this->TestTimeStd)
                 .",".qnum($this->TestTimeStdThreshold).",".qnum($this->TestTimeMaxStatus).",".qnum($this->EmailMaxItems).",".qnum($this->EmailMaxChars).","
                 .qnum($this->ShowTestTime).",".qnum($this->EmailAdministrator).",".qnum($this->ShowIPAddresses).",".qnum($this->DisplayLabels)
                 .",".qnum($this->AutoremoveTimeframe).",".qnum($this->AutoremoveMaxBuilds).")";
                    
       if(pdo_query($query))
         {
         $this->Id = pdo_insert_id("project");
         }
       else
         {
         add_last_sql_error("Project Create");
         return false;
         }  
       }
      
    return true;
    }  
    
  /** Get the user's role */
  function GetUserRole($userid)
    {
    if(!$this->Id || !is_numeric($this->Id))
      {
      return -1;
      }
     
    $role = -1; 
      
    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='".$this->Id."'");
    if(pdo_num_rows($user2project)>0)
      {
      $user2project_array = pdo_fetch_array($user2project);
      $role = $user2project_array["role"];
      }
    
    return $role;
    }
  
  /** Return true if the project exists */
  function ExistsByName($name)
    {
    $project = pdo_query("SELECT id FROM project WHERE name='$name'");
    if(pdo_num_rows($project)>0)
      {
      return true;
      } 
    return false;    
    }
  
  /** Get the logo id */
  function GetLogoId()
    {
    $query = pdo_query("SELECT imageid FROM project WHERE id=".$this->Id);
    
    if(!$query)
      {
      add_last_sql_error("Project GetLogoId");
      return 0;
      }
    
    if($query_array = pdo_fetch_array($query))
      {
      return qnum($query_array["imageid"]);
      }
    return 0;  
    }
  
  /** Fill in all the information from the database */
  function Fill()
    {
    if(!$this->Id)
      {
      echo "Project Fill(): Id not set";
      }
  
    $project = pdo_query("SELECT * FROM project WHERE id=".$this->Id);
    if(!$project)
      {
      add_last_sql_error("Project Fill");
      return;
      }
    
    if($project_array = pdo_fetch_array($project))
      {
      $this->Name = $project_array['name'];
      $this->Description = $project_array['description'];
      $this->HomeUrl = $project_array['homeurl'];
      $this->CvsUrl = $project_array['cvsurl'];
      $this->DocumentationUrl = $project_array['documentationurl'];
      $this->BugTrackerUrl = $project_array['bugtrackerurl'];
      $this->BugTrackerFileUrl = $project_array['bugtrackerfileurl'];
      $this->ImageId = $project_array['imageid'];
      $this->Public = $project_array['public'];
      $this->CoverageThreshold = $project_array['coveragethreshold'];
      $this->NightlyTime = $project_array['nightlytime'];
      $this->GoogleTracker = $project_array['googletracker'];
      $this->EmailBuildMissing = $project_array['emailbuildmissing'];
      $this->EmailLowCoverage = $project_array['emaillowcoverage'];
      $this->EmailTestTimingChanged = $project_array['emailtesttimingchanged'];
      $this->EmailBrokenSubmission = $project_array['emailbrokensubmission'];
      $this->EmailRedundantFailures = $project_array['emailredundantfailures'];
      $this->EmailAdministrator = $project_array['emailadministrator'];
      $this->ShowIPAddresses = $project_array['showipaddresses'];
      $this->DisplayLabels = $project_array['displaylabels'];
      $this->AutoremoveTimeframe = $project_array['autoremovetimeframe'];
      $this->AutoremoveMaxBuilds = $project_array['autoremovemaxbuilds'];
      $this->CvsViewerType = $project_array['cvsviewertype'];
      $this->TestTimeStd = $project_array['testtimestd'];
      $this->TestTimeStdThreshold = $project_array['testtimestdthreshold'];
      $this->ShowTestTime = $project_array['showtesttime'];
      $this->TestTimeMaxStatus = $project_array['testtimemaxstatus'];
      $this->EmailMaxItems = $project_array['emailmaxitems'];
      $this->EmailMaxChars = $project_array['emailmaxchars'];
      }
    }  
    
  /** Add a logo */
  function AddLogo($contents,$filetype)
    {
    if(strlen($contents) == 0)
      {
      return; 
      }

    $imgid = $this->GetLogoId();
    $checksum = crc32($contents);
    
    
    
    //check if we already have a copy of this file in the database
    $sql = "SELECT id FROM image WHERE checksum = '$checksum'";
    $result = pdo_query("$sql");
    if($row = pdo_fetch_array($result))
      {
      $imgid = $row["id"];
      // Insert into the project
      pdo_query("UPDATE project SET imageid=".qnum($imgid)." WHERE id=".$this->Id);
      add_last_sql_error("Project AddLogo");
      }
    else if($imgid==0)
      {
      include("cdash/config.php");
      if($CDASH_DB_TYPE == "pgsql")
        {
        $contents = pg_escape_bytea($contents);
        }
      $sql = "INSERT INTO image(img, extension, checksum) VALUES ('$contents', '$filetype', '$checksum')";
      if(pdo_query("$sql"))
        {
        $imgid = pdo_insert_id("image");
        
        // Insert into the project
        pdo_query("UPDATE project SET imageid=".qnum($imgid)." WHERE id=".qnum($this->Id));
        add_last_sql_error("Project AddLogo");
        }
      }
     else // update the current image
       {
       include("cdash/config.php");
       if($CDASH_DB_TYPE == "pgsql")
         {
         $contents = pg_escape_bytea($contents);
         }
       pdo_query("UPDATE image SET img='$contents',extension='$filetype',checksum='$checksum' WHERE id=".qnum($imgid));
       add_last_sql_error("Project AddLogo");
       } 
    return $imgid;   
    }
  
  /** Add CVS/SVN repositories */
  function AddRepositories($repositories)
    {
    // First we update/delete any registered repositories
    $currentRepository = 0;
    $repositories_query = pdo_query("SELECT repositoryid from project2repositories WHERE projectid=".qnum($this->Id)." ORDER BY repositoryid");
    while($repository_array = pdo_fetch_array($repositories_query))
      {
      $repositoryid = $repository_array["repositoryid"];
      if(!isset($repositories[$currentRepository]) || strlen($repositories[$currentRepository])==0)
        {
        $query = pdo_query("SELECT * FROM project2repositories WHERE repositoryid=".qnum($repositoryid));
        if(pdo_num_rows($query)==1)
          {
          pdo_query("DELETE FROM repositories WHERE id='$repositoryid'");
          }
        pdo_query("DELETE FROM project2repositories WHERE projectid=".qnum($this->Id)." AND repositoryid=.".qnum($repositoryid));  
        }
      else
        {
        pdo_query("UPDATE repositories SET url='$repositories[$currentRepository]' WHERE id=".qnum($repositoryid));
        }  
      $currentRepository++;
      }
  
    //  Then we add new repositories
    for($i=$currentRepository;$i<count($repositories);$i++)
      {
      $url = $repositories[$i];
      if(strlen($url) == 0)
        {
        continue;
        }
    
      // Insert into repositories if not any
      $repositories_query = pdo_query("SELECT id FROM repositories WHERE url='$url'");
      if(pdo_num_rows($repositories_query) == 0)
        {
        pdo_query("INSERT INTO repositories (url) VALUES ('$url')");
        $repositoryid = pdo_insert_id("repositories");
        }
      else
        {
        $repositories_array = pdo_fetch_array($repositories_query);
        $repositoryid = $repositories_array["id"];
        } 
      pdo_query("INSERT INTO project2repositories (projectid,repositoryid) VALUES (".qnum($this->Id).",'$repositoryid')");
      echo pdo_error();   
      } // end add repository
    } // end function   AddRepositories
 
   /** Get the repositories */
   function GetRepositories()
     {
     $repositories = array();
     $repository = pdo_query("SELECT url from repositories,project2repositories
                               WHERE repositories.id=project2repositories.repositoryid
                               AND project2repositories.projectid=".qnum($this->Id));
     while($repository_array = pdo_fetch_array($repository))
       {
       $rep['url'] = $repository_array['url'];
       $repositories[] = $rep;
       }
     return $repositories;   
     } // end GetRepositories


  /** Get Ids of all the project registered
   *  Maybe this function should go somewhere else but for now here */
  function GetIds()
    {
    $ids = array();
    $query = pdo_query("SELECT id FROM project ORDER BY id");
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array["id"];
      }
    return $ids;    
    }
   
  /** Get the Name of the project */
  function GetName()
    {
    if(strlen($this->Name)>0)
      {
      return $this->Name;
      }
      
    if(!$this->Id)
      {
      echo "Project GetName(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT name FROM project WHERE id=".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project GetName");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $this->Name = $project_array['name'];
    
    return $this->Name;
    }   
 
  /** Get the coveragethreshold */
  function GetCoverageThreshold()
    {
    if(strlen($this->CoverageThreshold)>0)
      {
      return $this->CoverageThreshold;
      }
      
    if(!$this->Id)
      {
      echo "Project GetCoverageThreshold(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT coveragethreshold FROM project WHERE id=".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project GetCoverageThreshold");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $this->CoverageThreshold = $project_array['coveragethreshold'];
    
    return $this->CoverageThreshold;
    }   
 
  /** Get the number of subproject */
  function GetNumberOfSubProjects()
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfSubprojects(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT count(*) FROM subproject WHERE projectid=".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfSubprojects");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }   
 
  /** Get the subproject ids*/
  function GetSubProjects($date=NULL)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfSubprojects(): Id not set";
      return false;
      }
  
    // If not set, the date is now
    if($date == NULL)
      {
      $date = gmdate(FMT_DATETIME);
      }
      
    $project = pdo_query("SELECT id FROM subproject WHERE projectid=".qnum($this->Id)." AND 
                          starttime<='".$date."' AND (endtime>'".$date."' OR endtime='1980-01-01 00:00:00')");
    if(!$project)
      {
      add_last_sql_error("Project GetSubProjects");
      return false;
      }
    
    $ids = array();
    while($project_array = pdo_fetch_array($project))
      {
      $ids[] = $project_array['id'];
      }
    return $ids;
    }  
   
  /** Get the last submission of the subproject*/
  function GetLastSubmission()
    {
    if(!$this->Id)
      {
      echo "Project GetLastSubmission(): Id not set";
      return false;
      }
  
    $build = pdo_query("SELECT submittime FROM build WHERE projectid=".qnum($this->Id).
                         " ORDER BY submittime DESC LIMIT 1");
                          
    if(!$build)
      {
      add_last_sql_error("Project GetLastSubmission");
      return false;
      }
    $build_array = pdo_fetch_array($build);
    return date(FMT_DATETIMESTD,strtotime($build_array['submittime']. "UTC"));
    }   
 
  /** Get the total number of builds for a project*/
  function GetTotalNumberOfBuilds()
    {
    if(!$this->Id)
      {
      echo "Project GetTotalNumberOfBuilds(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT count(*) FROM build WHERE projectid=".qnum($this->Id));
                           
    if(!$project)
      {
      add_last_sql_error("Project GetTotalNumberOfBuilds");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }
    
  /** Get the number of builds given a date range */
  function GetNumberOfBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfBuilds(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT count(build.id) FROM build WHERE projectid=".qnum($this->Id).
                         " AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate'");
                           
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfBuilds");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }
  
  /** Get the number of builds given per day */
  function GetBuildsDailyAverage($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfBuilds(): Id not set";
      return false;
      }  
    $nbuilds=$this->GetNumberOfBuilds($startUTCdate,$endUTCdate);   
    $project = pdo_query("SELECT starttime FROM build WHERE projectid=".qnum($this->Id).
                           " AND build.starttime>'$startUTCdate' 
                             AND build.starttime<='$endUTCdate'
                             ORDER BY starttime ASC 
                             LIMIT 1");  
    $first_build=pdo_fetch_array($project);      
    $first_build=$first_build['starttime'];     
    $nb_days=strtotime($endUTCdate)-strtotime($first_build);
    $nb_days=intval($nb_days/86400)+1;
    if(!$project)
      {
      return 0;
      }
    
    return $nbuilds/$nb_days;
    }

  /** Get the number of warning builds given a date range */
  function GetNumberOfWarningBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfWarningBuilds(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT count(*) FROM build,build2group,buildgroup
                          WHERE build.projectid=".qnum($this->Id).
                          " AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.buildwarnings>0");
    
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfWarningBuilds");
      return false;
      }  
    $project_array = pdo_fetch_array($project);
    $count = $project_array[0];
    return $count;
    }
  
  /** Get the number of error builds given a date range */
  function GetNumberOfErrorBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfErrorBuilds(): Id not set";
      return false;
      }
    
    // build failures
    $project = pdo_query("SELECT count(*) FROM build,build2group,buildgroup
                          WHERE build.projectid=".qnum($this->Id).
                          " AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.builderrors>0");
  
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfErrorBuilds");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $count = $project_array[0];
    return $count;
    }
      
  /** Get the number of failing builds given a date range */
  function GetNumberOfPassingBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfPassingBuilds(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT count(*) FROM build,build2group,buildgroup
                          WHERE build.projectid=".qnum($this->Id).
                          " AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.builderrors=0
                           AND build.buildwarnings=0
                           ");
  
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfPassingBuilds");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }
  
  /** Get the number of configure given a date range */
  function GetNumberOfConfigures($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfConfigures(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT count(*) FROM configure,build,build2group,buildgroup WHERE projectid=".qnum($this->Id).
                         " AND configure.buildid=build.id AND build.starttime>'$startUTCdate' 
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1 
                           AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfConfigures");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    } 
  
  /** Get the number of failing configure given a date range */
  function GetNumberOfWarningConfigures($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfWarningConfigures(): Id not set";
      return false;
      }
      
    $project = pdo_query("SELECT count(*) FROM build,configure,build2group,buildgroup
                          WHERE  configure.buildid=build.id  AND build.projectid=".qnum($this->Id).
                         " AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1 
                           AND  configure.warnings>0 
                           ");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfWarningConfigures");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }  
  
  /** Get the number of failing configure given a date range */
  function GetNumberOfErrorConfigures($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfErrorConfigures(): Id not set";
      return false;
      }
      
    $project = pdo_query("SELECT count(*) FROM build,configure,buildgroup,build2group
                          WHERE  configure.buildid=build.id  AND build.projectid=".qnum($this->Id).
                         " AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate' 
                           AND configure.status='1'
                          AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                          AND buildgroup.includesubprojectotal=1");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfErrorConfigures");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }  
    
  /** Get the number of failing configure given a date range */
  function GetNumberOfPassingConfigures($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfPassingConfigures(): Id not set";
      return false;
      }
      
    $project = pdo_query("SELECT count(*) FROM configure,build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1 
                           AND configure.buildid=build.id AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate' AND configure.status='0'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfPassingConfigures");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }
    
  /** Get the number of tests given a date range */
  function GetNumberOfTests($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfTests(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT count(*) FROM build2test,build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build2test.buildid=build.id AND build.starttime>'$startUTCdate' 
                           AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfTests");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }
  
  /** Get the number of tests given a date range */
  function GetNumberOfPassingTests($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfPassingTests(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT SUM(build.testpassed) FROM build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                         AND buildgroup.includesubprojectotal=1 
                         AND build.starttime>'$startUTCdate' 
                         AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfPassingTests");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }
    
  /** Get the number of tests given a date range */
  function GetNumberOfFailingTests($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfFailingTests(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT SUM(build.testfailed) FROM build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                         AND buildgroup.includesubprojectotal=1 
                         AND build.starttime>'$startUTCdate' 
                         AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfFailingTests");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }
    
  /** Get the number of tests given a date range */
  function GetNumberOfNotRunTests($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfNotRunTests(): Id not set";
      return false;
      }
  
    $project = pdo_query("SELECT SUM(build.testnotrun) FROM build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                         AND buildgroup.includesubprojectotal=1 
                         AND build.starttime>'$startUTCdate' 
                         AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfNotRunTests");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }  // end GetNumberOfNotRunTests()
    
  /** Get the labels ids for a given project */
  function GetLabels($days)
    {
    $today = time();
    $today -= 3600*24*$days;
    
    $labelids = array();
    
    
    $labels = pdo_query("SELECT label.id as labelid FROM label WHERE 
                         label.id IN (SELECT labelid AS id FROM label2build,build 
                            WHERE label2build.buildid=build.id AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2test,build 
                            WHERE label2test.buildid=build.id AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2coveragefile,build 
                            WHERE label2coveragefile.buildid=build.id AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2buildfailure,buildfailure,build
                            WHERE label2buildfailure.buildfailureid=buildfailure.id AND buildfailure.buildid=build.id
                                  AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2dynamicanalysis,dynamicanalysis,build
                            WHERE label2dynamicanalysis.dynamicanalysisid=dynamicanalysis.id AND dynamicanalysis.buildid=build.id
                            AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         ");           
    if(!$labels)
      {
      add_last_sql_error("Project GetLabels");
      return false;
      }
    
    while($label_array = pdo_fetch_array($labels))
      {
      $labelids[] = $label_array['labelid'];
      }
   
    return array_unique($labelids);
    } // end GetLabels()  
   
  /** Send an email to the administrator of the project */
  function SendEmailToAdmin($subject,$body)
    {
    if(!$this->Id)
      {
      echo "Project SendEmailToAdmin(): Id not set";
      return false;
      }
    
    include('cdash/config.php');
          
    // Check if we should send emails
    $project = pdo_query("SELECT emailadministrator,name FROM project WHERE id =".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project SendEmailToAdmin");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    
    if($project_array['emailadministrator'] == 0)
      {
      return;
      }
    
    // Find the site maintainers
    include_once('models/userproject.php');
    include_once('models/user.php');
    $UserProject = new UserProject();
    $UserProject->ProjectId = $this->Id;
    
    $userids = $UserProject->GetUsers(2); // administrators
    $email = "";
    foreach($userids as $userid)
      {
      $User = new User;
      $User->Id = $userid;
      if($email != "")
        {
        $email .= ", ";
        }
      $email .= $User->GetEmail();
      }

    if($email!="")
      {
      $projectname = $project_array['name'];
      $emailtitle = "CDash [".$projectname."] - Administration "; 
      $emailbody = "Object: ".$subject."\n";
      $emailbody .= $body."\n";
      $serverName = $CDASH_SERVER_NAME;
      if(strlen($serverName) == 0)
        {
        $serverName = $_SERVER['SERVER_NAME'];
        }
      $emailbody .= "\n-CDash on ".$serverName."\n";
    
      if(mail("$email", $emailtitle, $emailbody,
       "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
        {
        add_log("email sent to: ".$email,"Project::SendEmailToAdmin");
        return;
        }
      else
        {
        add_log("cannot send email to: ".$email,"Project::SendEmailToAdmin");
        }
      } // end if email
    } // end SendEmailToAdmin
    
}  // end class Project

?>
