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

class ClientLibrary
{
    public $Id;
    public $Name;
    public $Version;
    public $SiteId;
    public $Path;
    public $Include;

    /** Get Name */
    public function GetName()
    {
        if (!$this->Id) {
            add_log('ClientLibrary::GetName()', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT name FROM client_library WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Version */
    public function GetVersion()
    {
        if (!$this->Id) {
            add_log('ClientLibrary::GetVersion()', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT version FROM client_library WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get all  */
    public function GetAll()
    {
        $ids = array();
        $sql = 'SELECT id FROM client_library ORDER BY name';
        $query = pdo_query($sql);
        while ($query_array = pdo_fetch_array($query)) {
            $ids[] = $query_array['id'];
        }
        return $ids;
    }

    /** Get the library id from the description */
    public function GetLibrary($name, $version = '')
    {
        $sql = 'SELECT id FROM client_library WHERE ';
        $ids = array();

        $firstarg = true;
        if ($name != '') {
            $name = pdo_real_escape_string($name);
            $sql .= " name='" . $name . "'";
            $firstarg = false;
        }

        if ($version != '') {
            if (!$firstarg) {
                $sql .= ' AND ';
            }
            $version = pdo_real_escape_string($version);
            $sql .= " version='" . $version . "'";
            $firstarg = false;
        }

        $query = pdo_query($sql);
        while ($query_array = pdo_fetch_array($query)) {
            $ids[] = $query_array['id'];
        }
        return $ids;
    }

    /** Save */
    public function Save()
    {
        // Check if the name/version already exists
        $query = pdo_query("SELECT id FROM client_library WHERE name='" . $this->Name . "' AND version='" . $this->Version . "'");
        if (pdo_num_rows($query) == 0) {
            $sql = "INSERT INTO client_library (name,version)
              VALUES ('" . $this->Name . "','" . $this->Version . "')";
            pdo_query($sql);
            $this->Id = pdo_insert_id('client_library');
            add_last_sql_error('ClientLibrary::Save()');
        } else {
            // update

            $query_array = pdo_fetch_array($query);
            $this->Id = $query_array['id'];
            $sql = "UPDATE client_library SET version='" . $this->Version . "' WHERE id=" . qnum($this->Id);
            pdo_query($sql);
            add_last_sql_error('ClientLibrary::Save()');
        }

        // Insert into the siteid
        $query = pdo_query('SELECT libraryid FROM client_site2library WHERE libraryid=' . qnum($this->Id) . ' AND siteid=' . qnum($this->SiteId));
        if (pdo_num_rows($query) == 0) {
            $sql = 'INSERT INTO client_site2library (siteid,libraryid,path,include)
              VALUES (' . qnum($this->SiteId) . ',' . qnum($this->Id) . ",'" . $this->Path . "','" . $this->Include . "')";
            pdo_query($sql);
            add_last_sql_error('ClientLibrary::Save()');
        } else {
            // update

            $sql = "UPDATE client_site2library SET path='" . $this->Path . "',include='" . $this->Include . "' WHERE libraryid=" . qnum($this->Id) . ' AND siteid=' . qnum($this->SiteId);
            pdo_query($sql);
            add_last_sql_error('ClientLibrary::Save()');
        }
    }

    /** Delete unused libraries */
    public function DeleteUnused($libraries)
    {
        if (!$this->SiteId) {
            add_log('ClientLibrary::DeleteUnused()', 'SiteId not set');
            return;
        }

        // Delete the old libraries
        $query = pdo_query('SELECT name,path,version,include,libraryid FROM client_library,client_site2library
              WHERE client_library.id=client_site2library.libraryid
              AND siteid=' . qnum($this->SiteId));

        add_last_sql_error('ClientLibrary::DeleteUnused()');
        while ($query_array = pdo_fetch_array($query)) {
            $delete = 1;
            foreach ($libraries as $library) {
                if ($library['name'] == $query_array['name'] && $library['version'] == $query_array['version']
                    && $library['path'] == $query_array['path'] && $library['include'] == $query_array['include']
                ) {
                    $delete = 0;
                    break;
                }
            }
            if ($delete) {
                pdo_query("DELETE FROM client_site2library WHERE libraryid='" . $query_array['libraryid'] .
                    "' AND path='" . $query_array['path'] .
                    "' AND include='" . $query_array['include'] .
                    "' AND siteid=" . qnum($this->SiteId));
                add_last_sql_error('ClientLibrary::DeleteUnused()');
            }
        }

        // Delete the client_compiler not attached to anything
        pdo_query('DELETE FROM client_library WHERE id NOT IN(SELECT libraryid AS id FROM client_site2library)');
    }
}
