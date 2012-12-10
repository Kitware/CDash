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

/** Main subproject class */
class SubProject
{
  var $Name;
  var $Id;
  var $ProjectId;

  function __construct()
    {
    }

  /** Function to set the project id */
  function SetProjectId($projectid)
    {
    if(is_numeric($projectid))
      {
      $this->ProjectId = $projectid;
      return true;
      }
    return false;
    }

  /** Delete a project */
  function Delete($keephistory=true)
    {
    if(!$this->Id)
      {
      return false;
      }

    // If there is no build in the subproject we remove
    $query = pdo_query("SELECT count(*) FROM subproject2build WHERE subprojectid=".qnum($this->Id));
    if(!$query)
      {
      add_last_sql_error("SubProject Delete");
      return false;
      }
    $query_array = pdo_fetch_array($query);
    if($query_array[0]==0)
      {
      $keephistory = false;
      }

    if(!$keephistory)
      {
      pdo_query("DELETE FROM subproject2build WHERE subprojectid=".qnum($this->Id));
      pdo_query("DELETE FROM subproject2subproject WHERE subprojectid=".qnum($this->Id));
      pdo_query("DELETE FROM subproject2subproject WHERE dependsonid=".qnum($this->Id));
      pdo_query("DELETE FROM subproject WHERE id=".qnum($this->Id));
      }
    else
      {
      $endtime = gmdate(FMT_DATETIME);
      $query = "UPDATE subproject SET ";
      $query .= "endtime='".$endtime."'";
      $query .= " WHERE id=".qnum($this->Id)."";
      if(!pdo_query($query))
        {
        add_last_sql_error("SubProject Delete");
        return false;
        }
      }
    }

  /** Return if a project exists */
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id)
      {
      return false;
      }

    $query = pdo_query("SELECT count(*) FROM subproject WHERE id='".$this->Id."' AND endtime='1980-01-01 00:00:00'");
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

      // Update the subproject
      $query = "UPDATE subproject SET ";
      $query .= "name='".$this->Name."'";
      $query .= ",projectid=".qnum($this->ProjectId);
      $query .= " WHERE id=".qnum($this->Id)."";

      if(!pdo_query($query))
        {
        add_last_sql_error("SubProject Update");
        return false;
        }
      }
    else // insert the subproject
      {
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }

      // Trim the name
      $this->Name = trim($this->Name);

      // Make sure it's no already in the database
      $query = pdo_query("SELECT id FROM subproject WHERE name='".$this->Name."' AND projectid=".qnum($this->ProjectId)
                         ." AND endtime='1980-01-01 00:00:00'");
      if(!$query)
        {
        add_last_sql_error("SubProject Update");
        return false;
        }

      if(pdo_num_rows($query)>0)
        {
        $query_array = pdo_fetch_array($query);
        $this->Id = $query_array['id'];
        return true;
        }

      $starttime = gmdate(FMT_DATETIME);
      $endtime = "1980-01-01 00:00:00";
      $query = "INSERT INTO subproject(".$id."name,projectid,starttime,endtime)
                 VALUES (".$idvalue."'$this->Name',".qnum($this->ProjectId)
                 .",'".$starttime."','".$endtime."')";

      if(!pdo_query($query))
        {
        add_last_sql_error("SubProject Create");
        return false;
        }

      if(!$this->Id)
        {
        $this->Id = pdo_insert_id("subproject");
        }
      }

    return true;
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
      echo "SubProject GetName(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT name FROM subproject WHERE id=".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("SubProject GetName");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $this->Name = $project_array['name'];

    return $this->Name;
    }

  /** Get the last submission of the subproject*/
  function GetLastSubmission()
    {
    if(!$this->Id)
      {
      echo "SubProject GetLastSubmission(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT submittime FROM build,subproject2build,build2group,buildgroup WHERE subprojectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND subproject2build.buildid=build.id ORDER BY submittime DESC LIMIT 1");
    if(!$project)
      {
      add_last_sql_error("SubProject GetLastSubmission");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return date(FMT_DATETIMESTD,strtotime($project_array['submittime']. "UTC"));
    }

  /** Get the number of warning builds given a date range */
  function GetNumberOfWarningBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "SubProject GetNumberOfWarningBuilds(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(build.id) FROM build,subproject2build,build2group,buildgroup
                          WHERE subprojectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate' AND build.buildwarnings>0");

    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfWarningBuilds");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of error builds given a date range */
  function GetNumberOfErrorBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "SubProject GetNumberOfErrorBuilds(): Id not set";
      return false;
      }


    $project = pdo_query("SELECT count(build.id) FROM build,subproject2build,build2group,buildgroup
                          WHERE subprojectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate' AND build.builderrors>0");

    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfErrorBuilds");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of failing builds given a date range */
  function GetNumberOfPassingBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "SubProject GetNumberOfPassingBuilds(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(build.id) FROM build,subproject2build,build2group,buildgroup
                          WHERE subprojectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate' AND build.builderrors=0 AND build.buildwarnings=0");

    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfPassingBuilds");
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
      echo "SubProject GetNumberOfWarningConfigures(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(build.id) FROM build,subproject2build,configure,build2group,buildgroup
                          WHERE  configure.buildid=build.id  AND subprojectid=".qnum($this->Id).
                         " AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND configure.warnings>0");
    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfWarningConfigures");
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
      echo "SubProject GetNumberOfErrorConfigures(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM configure,build,subproject2build,build2group,buildgroup WHERE subprojectid=".qnum($this->Id).
                         " AND configure.buildid=build.id AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate' AND configure.status='1'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                            AND buildgroup.includesubprojectotal=1
                           ");
    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfErrorConfigures");
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
      echo "SubProject GetNumberOfPassingConfigures(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM configure,build,subproject2build,build2group,buildgroup WHERE subprojectid=".qnum($this->Id).
                         " AND configure.buildid=build.id AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate' AND configure.status='0'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                            AND buildgroup.includesubprojectotal=1");
    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfPassingConfigures");
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
      echo "SubProject GetNumberOfPassingTests(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT SUM(build.testpassed) FROM build,subproject2build,build2group,buildgroup WHERE subprojectid=".qnum($this->Id).
                         " AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build.testpassed>=0
                           AND build2group.buildid=build.id
                           AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1");
    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfPassingTests");
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
      echo "SubProject GetNumberOfFailingTests(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT SUM(build.testfailed) FROM build,subproject2build,build2group,buildgroup WHERE subprojectid=".qnum($this->Id).
                         " AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build.testfailed>=0
                           AND build2group.buildid=build.id
                           AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1");
    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfFailingTests");
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
      echo "SubProject GetNumberOfNotRunTests(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT SUM(build.testnotrun) FROM build,subproject2build,build2group,buildgroup WHERE subprojectid=".qnum($this->Id).
                         " AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND build.testnotrun>=0
                           AND buildgroup.includesubprojectotal=1");
    if(!$project)
      {
      add_last_sql_error("SubProject GetNumberOfNotRunTests");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the id of the subproject from the name */
  function GetIdFromName()
    {
    if(!$this->Name || !$this->ProjectId)
      {
      add_log(
        "Name='".$this->Name."' or ProjectId='".$this->ProjectId."' not set",
        "SubProject::GetIdFromName",
        LOG_WARNING);
      return false;
      }

    $project = pdo_query("SELECT id FROM subproject WHERE projectid=".qnum($this->ProjectId).
                         " AND name='".$this->Name."' AND endtime='1980-01-01 00:00:00'");

    if(!$project)
      {
      add_last_sql_error("SubProject GetIdFromName");
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $this->Id = $project_array['id'];
    return $this->Id;
    }

  /** Get the subprojectids of the subprojects depending on this one */
  function GetDependencies($date=NULL)
    {
    if(!$this->Id)
      {
      add_log(
        "Id='".$this->Id."' not set",
        "SubProject::GetDependencies",
        LOG_WARNING);
      return false;
      }

    // If not set, the date is now
    if($date == NULL)
      {
      $date = gmdate(FMT_DATETIME);
      }

    $project = pdo_query("SELECT dependsonid FROM subproject2subproject
                          WHERE subprojectid=".qnum($this->Id)." AND
                          starttime<='".$date."' AND (endtime>'".$date."' OR endtime='1980-01-01 00:00:00')"
                          );
    if(!$project)
      {
      add_last_sql_error("SubProject GetDependencies");
      return false;
      }
    $ids = array();
    while($project_array = pdo_fetch_array($project))
      {
      $ids[] = $project_array['dependsonid'];
      }
    return $ids;
    } // end GetDependencies

  /** Add a dependency */
  function AddDependency($subprojectid)
    {
    if(!$this->Id)
      {
      echo "SubProject AddDependency(): Id not set";
      return false;
      }

    if(!isset($subprojectid) || !is_numeric($subprojectid))
      {
      echo "SubProject AddDependency(): subproject not set or invalid";
      return false;
      }

    // Check that the dependency doesn't exist
    $project = pdo_query("SELECT count(*) FROM subproject2subproject WHERE subprojectid=".qnum($this->Id).
                         " AND dependsonid=".qnum($subprojectid)." AND endtime='1980-01-01 00:00:00'"
                         );
    if(!$project)
      {
      add_last_sql_error("SubProject AddDependency");
      return false;
      }

    $project_array = pdo_fetch_array($project);
    if($project_array[0]>0)
      {
      //echo "Dependency already exists";
      return false;
      }

    // Add the dependency
    $starttime = gmdate(FMT_DATETIME);
    $endtime = "1980-01-01 00:00:00";
    $project = pdo_query("INSERT INTO subproject2subproject (subprojectid,dependsonid,starttime,endtime)
                         VALUES (".qnum($this->Id).
                         ",".qnum($subprojectid).",'".$starttime."','".$endtime."')");
    if(!$project)
      {
      add_last_sql_error("SubProject AddDependency");
      return false;
      }

    return true;
    } // end AddDependency

  /** Remove a dependency */
  function RemoveDependency($subprojectid)
    {
    if(!$this->Id)
      {
      echo "SubProject RemoveDependency(): Id not set";
      return false;
      }

    if(!isset($subprojectid) || !is_numeric($subprojectid))
      {
      echo "SubProject RemoveDependency(): subproject not set or invalid";
      return false;
      }

    // Set the date of the dependency to be now
    $now = gmdate(FMT_DATETIME);
    $project = pdo_query("UPDATE subproject2subproject SET endtime='".$now."'
                          WHERE subprojectid=".qnum($this->Id).
                         " AND dependsonid=".qnum($subprojectid)." AND endtime='1980-01-01 00:00:00'");
    if(!$project)
      {
      add_last_sql_error("SubProject RemoveDependency");
      return false;
      }
    return true;
    } // end RemoveDependency

}  // end class Project



?>
