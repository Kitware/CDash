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

include_once 'api.php';

class RepositoryAPI extends CDashAPI
{
    /** return the example URL  */
    private function ExampleURL()
    {
        include_once 'include/common.php';
        include_once 'include/repository.php';

        if (!isset($this->Parameters['url'])) {
            echo 'url parameter not set';
            return;
        }
        if (!isset($this->Parameters['type'])) {
            echo 'type parameter not set';
            return;
        }

        $url = $this->Parameters['url'];
        $functionname = 'get_' . strtolower($this->Parameters['type']) . '_diff_url';
        return $functionname($url, 'DIRECTORYNAME', 'FILENAME', 'REVISION');
    }

    /** Run function */
    public function Run()
    {
        switch ($this->Parameters['task']) {
            case 'exampleurl':
                return $this->ExampleURL();
        }
    }
}
