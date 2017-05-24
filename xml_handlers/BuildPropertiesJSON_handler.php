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

require_once('include/pdo.php');

class BuildPropertiesJSONHandler
{
    private $BuildId;
    private $PDO;

    public function __construct($buildid)
    {
        $this->BuildId = $buildid;
        $this->PDO = get_link_identifier()->getPdo();
    }

    /**
     * Parse the build properties file.
     **/
    public function Parse($filename)
    {
        // Test that this file contains valid JSON that PHP can decode.
        $json_obj = json_decode(file_get_contents($filename));
        if ($json_obj === null) {
            $err = json_last_error_msg();
            add_log("Failed to parse $filename: $err", 'BuildPropertiesJSONHandler::Parse', LOG_ERR);
            return false;
        }

        // Convert back to JSON.  This can result in a more compact storage
        // for the database as opposed to storing the original contents as is.
        $json_str = json_encode($json_obj);
        if ($json_str === false) {
            $err = json_last_error_msg();
            add_log("Failed to encode JSON: $err", 'BuildPropertiesJSONHandler::Parse', LOG_ERR);
            return false;
        }

        // Store this in the database, deleting any previously existing results.
        $stmt = $this->PDO->prepare('DELETE FROM buildproperties WHERE buildid = ?');
        pdo_execute($stmt, [$this->BuildId]);
        $stmt = $this->PDO->prepare(
            'INSERT INTO buildproperties (buildid, properties) VALUES (?, ?)');
        return pdo_execute($stmt, [$this->BuildId, $json_str]);
    }
}
