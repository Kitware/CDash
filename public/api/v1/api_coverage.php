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

class CoverageAPI extends CDashAPI
{
    /** Return the coverage per directory with the number of lines
     * covered and not covered */
    private function CoveragePerDirectory()
    {
        include_once 'include/common.php';
        if (!isset($this->Parameters['project'])) {
            echo 'Project not set';
            return;
        }

        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            echo 'Project not found';
            return;
        }

        // Select the last build that has coverage from the project
        $query = pdo_query("SELECT buildid FROM coveragesummary,build WHERE build.id=coveragesummary.buildid
                              AND build.projectid='$projectid' ORDER BY buildid DESC LIMIT 1");
        echo pdo_error();

        if (pdo_num_rows($query) == 0) {
            echo 'No coverage entries found for this project';
            return;
        }
        $query_array = pdo_fetch_array($query);
        $buildid = $query_array['buildid'];

        // Find the coverage files
        $query = pdo_query("SELECT cf.fullpath,c.loctested,c.locuntested FROM coverage as c,coveragefile as cf
                 WHERE c.fileid=cf.id AND c.buildid='" . $buildid . "' ORDER BY cf.fullpath ASC");
        echo pdo_error();
        $coveragearray = array();
        while ($query_array = pdo_fetch_array($query)) {
            $fullpath = $query_array['fullpath'];
            $paths = explode('/', $fullpath);
            $current = array();
            for ($i = 1; $i < count($paths) - 1; $i++) {
                if ($i == 1) {
                    if (!isset($coveragearray[$paths[$i]])) {
                        $coveragearray[$paths[$i]] = array();
                    }
                    $current = &$coveragearray[$paths[$i]];
                } else {
                    if ($i == count($paths) - 2) {
                        if (isset($current[$paths[$i]])) {
                            $v = $current[$paths[$i]]['locuntested'];
                            $current[$paths[$i]]['locuntested'] = (integer)$v + $query_array['locuntested'];
                            $v = $current[$paths[$i]]['loctested'];
                            $current[$paths[$i]]['loctested'] = (integer)$v + $query_array['loctested'];
                        } else {
                            @$current[$paths[$i]]['locuntested'] = $query_array['locuntested'];
                            @$current[$paths[$i]]['loctested'] = $query_array['loctested'];
                        }
                        unset($current);
                    } else {
                        $current[$paths[$i]] = array();
                        $current[$paths[$i]]['locuntested'] = 0;
                        $current[$paths[$i]]['loctested'] = 0;
                        $current = &$current[$paths[$i]];
                    }
                }
            }
        }
        return $coveragearray;
    }

    /** Run function */
    public function Run()
    {
        switch ($this->Parameters['task']) {
            case 'directory':
                return $this->CoveragePerDirectory();
        }
    }
}
