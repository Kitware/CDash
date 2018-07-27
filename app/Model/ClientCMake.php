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

class ClientCMake
{
    public $Id;
    public $SiteId;
    public $Version;
    public $Path;

    /** Get Version */
    public function GetVersion()
    {
        if (!$this->Id) {
            add_log('clientCMake::GetVersion()', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT version FROM client_cmake WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row['version'];
    }

    /** Get all the cmake */
    public function GetAll()
    {
        $ids = array();
        $sql = 'SELECT id FROM client_cmake ORDER BY version';
        $query = pdo_query($sql);
        while ($query_array = pdo_fetch_array($query)) {
            $ids[] = $query_array['id'];
        }
        return $ids;
    }

    /** Get cmake who have this version */
    public function GetIdFromVersion()
    {
        if (!$this->Version) {
            add_log('clientCMake::GetIdFromVersion()', 'Version not set');
            return;
        }
        $version = pdo_real_escape_string($this->Version);
        $sys = pdo_query("SELECT id FROM client_cmake WHERE version='" . $version . "'");
        $row = pdo_fetch_array($sys);
        return $row['id'];
    }

    /** Save */
    public function Save()
    {
        $version = pdo_real_escape_string($this->Version);
        $path = pdo_real_escape_string($this->Path);

        // Check if the version already exists
        $query = pdo_query("SELECT id FROM client_cmake WHERE version='" . $version . "'");
        if (pdo_num_rows($query) == 0) {
            $sql = "INSERT INTO client_cmake (version)
              VALUES ('" . $version . "')";
            pdo_query($sql);
            $this->Id = pdo_insert_id('client_cmake');
            add_last_sql_error('clientCMake::Save()');
        } else {
            // update

            $query_array = pdo_fetch_array($query);
            $this->Id = $query_array['id'];
            $sql = "UPDATE client_cmake SET version='" . $version . "' WHERE id=" . qnum($this->Id);
            pdo_query($sql);
            add_last_sql_error('clientCMake::Save()');
        }

        // Insert into the siteid
        $query = pdo_query('SELECT cmakeid FROM client_site2cmake WHERE cmakeid=' . qnum($this->Id) . ' AND siteid=' . qnum($this->SiteId));
        if (pdo_num_rows($query) == 0) {
            $sql = 'INSERT INTO client_site2cmake (siteid,cmakeid,path)
              VALUES (' . qnum($this->SiteId) . ',' . qnum($this->Id) . ",'" . $path . "')";
            pdo_query($sql);
            add_last_sql_error('clientCMake::Save()');
        } else {
            // update

            $sql = "UPDATE client_site2cmake SET path='" . $path . "' WHERE cmakeid=" . qnum($this->Id) . ' AND siteid=' . qnum($this->SiteId);
            pdo_query($sql);
            add_last_sql_error('clientCMake::Save()');
        }
    }

    /** Delete unused cmakes */
    public function DeleteUnused($cmakes)
    {
        if (!$this->SiteId) {
            add_log('clientCMake::DeleteUnused()', 'SiteId not set');
            return;
        }

        // Delete the old cmakes
        $query = pdo_query('SELECT path,version,cmakeid FROM client_cmake,client_site2cmake
              WHERE client_cmake.id=client_site2cmake.cmakeid
              AND siteid=' . qnum($this->SiteId));

        add_last_sql_error('clientCMake::DeleteUnused()');
        while ($query_array = pdo_fetch_array($query)) {
            $delete = 1;
            foreach ($cmakes as $cmake) {
                if ($cmake['path'] == $query_array['path'] && $cmake['version'] == $query_array['version']) {
                    $delete = 0;
                    break;
                }
            }
            if ($delete) {
                pdo_query("DELETE FROM client_site2cmake WHERE path='" . $query_array['path'] .
                    "' AND cmakeid='" . $query_array['cmakeid'] .
                    "' AND siteid=" . qnum($this->SiteId));
                add_last_sql_error('clientCMake::DeleteUnused()');
            }
        }

        // Delete the client_compiler not attached to anything
        pdo_query('DELETE FROM client_cmake WHERE id NOT IN(SELECT cmakeid AS id FROM client_site2cmake)');
    }
}
