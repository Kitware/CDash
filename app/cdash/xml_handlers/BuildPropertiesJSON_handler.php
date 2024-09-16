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

use CDash\Model\Build;
use CDash\Model\BuildProperties;

class BuildPropertiesJSONHandler extends AbstractSubmissionHandler
{
    public function __construct($buildid)
    {
        $this->Build = new Build();
        $this->Build->Id = $buildid;
        $this->Build->FillFromId($this->Build->Id);
    }

    /**
     * Parse the build properties file.
     **/
    public function Parse($filename)
    {
        // Test that this file contains valid JSON that PHP can decode.
        $json_obj = json_decode(file_get_contents($filename), true);
        if ($json_obj === null) {
            $err = json_last_error_msg();
            add_log("Failed to parse $filename: $err", 'BuildPropertiesJSONHandler::Parse', LOG_ERR);
            return false;
        }

        // Record the properties for this build.
        $buildProperties = new BuildProperties($this->Build);
        $buildProperties->Properties = $json_obj;
        return $buildProperties->Save();
    }
}
