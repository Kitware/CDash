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

class ClientSite
{
    public $Id;
    public $Name;
    public $OsId;

    public $SystemName;
    public $Host;
    public $BaseDirectory;

    /** get name*/
    public function GetName()
    {
        if (!$this->Id) {
            add_log('ClientSite::GetName()', 'Id not set');
            return;
        }
        $name = pdo_query('SELECT name FROM client_site WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($name);
        return $row[0];
    }

    /** Return the last ping */
    public function GetLastPing()
    {
        if (!$this->Id) {
            add_log('ClientSite::GetLastPing()', 'Id not set');
            return false;
        }

        $lastping = pdo_query('SELECT lastping FROM client_site WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($lastping);
        return $row[0];
    }

    /** get name*/
    public function GetSystemName()
    {
        if (!$this->Id) {
            add_log('ClientSite::Name()', 'Id not set');
            return;
        }
        $name = pdo_query('SELECT systemname FROM client_site WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($name);
        return $row[0];
    }

    /** get the OS */
    public function GetOS()
    {
        if (!$this->Id) {
            add_log('ClientSite::GetOS()', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT osid FROM client_site WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** get host*/
    // commenting out until it's actually used
    /*
    function GetHost()
      {
      if(!$this->Id)
        {
        add_log("ClientSite::GetHost()","Id not set");
        return;
        }
      $sys = pdo_query("SELECT host FROM client_site WHERE id=".qnum($this->Id));
      $row = pdo_fetch_array($sys);
      return $row['host'];
      }
    */

    /** get base directory */
    public function GetBaseDirectory()
    {
        if (!$this->Id) {
            add_log('ClientSite::GetBaseDirectory()', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT basedirectory FROM client_site WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);

        // If we have a source we update and build
        $baseDir = $row[0];
        if ($baseDir[strlen($baseDir) - 1] == '/') {
            $baseDir = substr($baseDir, 0, strlen($baseDir) - 1);
        }
        return $baseDir;
    }

    /** Return a list of compiler ids */
    // commenting out until it's actually used
    /*
    function GetCompilerIds()
      {
      if(!$this->Id)
        {
        add_log("ClientSite::GetCompilerIds()","Id not set");
        return;
        }

      $ids = array();
      $query = pdo_query("SELECT compilerid FROM client_site2compiler WHERE siteid=".qnum($this->Id));
      while($query_array = pdo_fetch_array($query))
        {
        $ids[] = $query_array['compilerid'];
        }
      return $ids;
      }
    */

    /** get name*/
    public function GetCompilerGenerator($compilerid)
    {
        if (!$this->Id) {
            add_log('ClientSite::GetCompilerGenerator()', 'Id not set');
            return;
        }
        $name = pdo_query('SELECT generator FROM client_site2compiler WHERE siteid=' . qnum($this->Id) . " AND compilerid='" . $compilerid . "'");
        $row = pdo_fetch_array($name);
        return $row[0];
    }

    /** Return the CMake path */
    public function GetCMakePath($cmakeid)
    {
        if (!$this->Id) {
            add_log('ClientSite::GetCMakePath()', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT path FROM client_site2cmake WHERE siteid=' . qnum($this->Id) . ' AND cmakeid=' . qnum($cmakeid));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Library Source */
    // commenting out until it's actually used
    /*
    function GetLibrarySource($libraryid)
      {
      if(!$this->Id)
        {
        add_log("clientSite::GetLibrarySource()","Id not set");
        return;
        }
      $sys = pdo_query("SELECT source FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
      $row = pdo_fetch_array($sys);
      return $row[0];
      }
    */

    /** Get Library Repository */
    // commenting out until it's actually used
    /*
    function GetLibraryRepository($libraryid)
      {
      if(!$this->Id)
        {
        add_log("clientSite::GetLibraryRepository()","Id not set");
        return;
        }
      $sys = pdo_query("SELECT repository FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
      $row = pdo_fetch_array($sys);
      return $row[0];
      }
    */

    /** Get Library Path */
    // commenting out until it's actually used
    /*
    function GetLibraryPath($libraryid)
      {
      if(!$this->Id)
        {
        add_log("clientLibrary::GetLibraryPath()","Id not set");
        return;
        }
      $sys = pdo_query("SELECT path FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
      $row = pdo_fetch_array($sys);
      return $row[0];
      }
    */

    /** Get Library Module */
    // commenting out until it's actually used
    /*
    function GetLibraryModule($libraryid)
      {
      if(!$this->Id)
        {
        add_log("clientSite::GetLibraryModule()","Id not set");
        return;
        }
      $sys = pdo_query("SELECT module FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
      $row = pdo_fetch_array($sys);
      return $row[0];
      }
    */

    /** Return a list of cmake ids */
    // commenting out until it's actually used
    /*
    function GetCMakeIds()
      {
      if(!$this->Id)
        {
        add_log("ClientSite::GetCMakeIds()","Id not set");
        return;
        }

      $ids = array();
      $query = pdo_query("SELECT cmakeid FROM client_site2cmake WHERE siteid=".qnum($this->Id));
      while($query_array = pdo_fetch_array($query))
        {
        $ids[] = $query_array['cmakeid'];
        }
      return $ids;
      }
    */

    /** Return a list of cmake ids */
    // commenting out until it's actually used
    /*
    function GetLibraryIds()
      {
      if(!$this->Id)
        {
        add_log("ClientSite::GetLibraryIds()","Id not set");
        return;
        }

      $ids = array();
      $query = pdo_query("SELECT libraryid FROM client_site2library WHERE siteid=".qnum($this->Id));
      while($query_array = pdo_fetch_array($query))
        {
        $ids[] = $query_array['libraryid'];
        }
      return $ids;
      }
    */

    /** Get the id of a site from the sitename and systemname */
    public function GetId($sitename, $systemname)
    {
        $query = pdo_query("SELECT id FROM client_site WHERE name='" . $sitename . "' AND systemname='" . $systemname . "'");
        if (!$query) {
            add_last_sql_error('clientSite::GetId()');
            return 0;
        }

        if (pdo_num_rows($query) == 0) {
            return 0;
        }

        $row = pdo_fetch_array($query);
        return $row['0'];
    }

    /** Save a site */
    public function Save()
    {
        // Check if the name or system already exists
        $query = pdo_query("SELECT id FROM client_site WHERE name='" . $this->Name . "' AND systemname='" . $this->SystemName . "'");
        if (pdo_num_rows($query) == 0) {
            $sql = "INSERT INTO client_site (name,osid,systemname,host,basedirectory)
              VALUES ('" . $this->Name . "','" . $this->OsId . "','" . $this->SystemName . "','" . $this->Host . "','" . $this->BaseDirectory . "')";
            pdo_query($sql);
            $this->Id = pdo_insert_id('client_site');
            add_last_sql_error('clientSite::Save()');
        } else {
            // update

            $query_array = pdo_fetch_array($query);
            $this->Id = $query_array['id'];
            $sql = "UPDATE client_site SET osid='" . $this->OsId . "',host='" . $this->Host . "',basedirectory='" . $this->BaseDirectory . "' WHERE id=" . qnum($this->Id);
            pdo_query($sql);
            add_last_sql_error('clientSite::Save()');
        }
    }

    /** Get all the site */
    public function GetAll()
    {
        $ids = array();
        $sql = 'SELECT id FROM client_site ORDER BY lastping DESC, name ASC';
        $query = pdo_query($sql);
        while ($query_array = pdo_fetch_array($query)) {
            $ids[] = $query_array['id'];
        }
        return $ids;
    }

    public function GetAllForProject($projectid)
    {
        $ids = $this->GetAll();

        $matching = array();
        foreach ($ids as $id) {
            $result = pdo_query('SELECT projectid FROM client_site2project WHERE siteid=' . qnum($id));
            if (pdo_num_rows($result) == 0) {
                $matching[] = $id;
            } else {
                $result = pdo_query('SELECT projectid FROM client_site2project WHERE siteid=' . qnum($id) . ' AND projectid=' . qnum($projectid));
                if (pdo_num_rows($result) > 0) {
                    $matching[] = $id;
                }
            }
        }
        return $matching;
    }

    /** Return all the sites that match this os */
    // commenting out until it's actually used
    /*
    function GetAllByOS($osid)
      {
      $ids = array();
      $sql = "SELECT id FROM client_site WHERE osid=".qnum($osid);
      $query = pdo_query($sql);
      while($query_array = pdo_fetch_array($query))
        {
        $ids[] = $query_array['id'];
        }
      return $ids;
      }
    */

    /** Get the programs */
    public function GetPrograms()
    {
        if (!$this->Id) {
            add_log('ClientSite::GetPrograms()', 'Id not set');
            return;
        }

        $programs = array();
        $query = pdo_query('SELECT name,version,path FROM client_site2program WHERE siteid=' . qnum($this->Id) . ' ORDER BY NAME,VERSION DESC');
        while ($query_array = pdo_fetch_array($query)) {
            $programs[] = $query_array;
        }
        return $programs;
    }

    /** Update the list of program for a site */
    public function UpdatePrograms($programs)
    {
        foreach ($programs as $program) {
            $program_name = pdo_real_escape_string($program['name']);
            $program_version = pdo_real_escape_string($program['version']);
            $program_path = pdo_real_escape_string($program['path']);

            // Check if the name or system already exists
            $query = pdo_query("SELECT siteid FROM client_site2program
                WHERE name='" . $program_name . "' AND version='" . $program_version . "' AND siteid=" . qnum($this->Id));
            add_last_sql_error('clientSite::UpdatePrograms()');
            if (pdo_num_rows($query) == 0) {
                $sql = "INSERT INTO client_site2program (siteid,name,version,path)
                VALUES ('" . $this->Id . "','" . $program_name . "','" . $program_version . "','" . $program_path . "')";
                pdo_query($sql);
                add_last_sql_error('clientSite::UpdatePrograms()');
            } else {
                // update

                $sql = "UPDATE client_site2program SET path='" . $program_path .
                    "' WHERE name='" . $program_name . "' AND version='" . $program_version . "' AND siteid=" . qnum($this->Id);
                pdo_query($sql);
                add_last_sql_error('clientSite::UpdatePrograms()');
            }
        }

        // Delete the old programs
        $query = pdo_query('SELECT name,version FROM client_site2program WHERE siteid=' . qnum($this->Id));

        add_last_sql_error('clientSite::UpdatePrograms()');
        while ($query_array = pdo_fetch_array($query)) {
            $delete = 1;
            foreach ($programs as $program) {
                if ($program['name'] == $query_array['name'] && $program['version'] == $query_array['version']) {
                    $delete = 0;
                    break;
                }
            }
            if ($delete) {
                pdo_query("DELETE FROM client_site2program WHERE name='" . $query_array['name'] . "' AND version='" . $query_array['version'] . "' AND siteid=" . qnum($this->Id));
                add_last_sql_error('clientSite::UpdatePrograms()');
            }
        }
    }

    public function UpdateAllowedProjects($projectNames)
    {
        if (!$this->Id) {
            add_log('ClientSite::UpdateAllowedProjects()', 'Id not set');
            return;
        }

        pdo_query('DELETE FROM client_site2project WHERE siteid=' . qnum($this->Id));
        foreach ($projectNames as $projectName) {
            $projectid = 0;
            $projectName = pdo_real_escape_string($projectName);
            $project = pdo_query("SELECT id FROM project WHERE name='$projectName'");

            if (pdo_num_rows($project) > 0) {
                $project_array = pdo_fetch_array($project);
                $projectid = $project_array['id'];
            }

            if (!$projectid) {
                add_log('ClientSite::UpdateAllowedProjects()', "Invalid project name given: $projectName");
                continue;
            }

            $sql = "INSERT INTO client_site2project (siteid,projectid) VALUES ('" . $this->Id . "','" . $projectid . "')";
            pdo_query($sql);
            add_last_sql_error('clientSite::UpdateAllowedProjects()');
        }
    }
}
