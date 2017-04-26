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

class BuildInformation
{
    public $BuildId;
    public $OSName;
    public $OSPlatform;
    public $OSRelease;
    public $OSVersion;
    public $CompilerName = 'unknown';
    public $CompilerVersion = 'unknown';
    public $BuildName;
    public $BuildStamp;
    public $Generator;
    public $PullRequest;

    public function SetValue($tag, $value)
    {
        switch ($tag) {
            case 'OSNAME':
                $this->OSName = $value;
                break;
            case 'OSRELEASE':
                $this->OSRelease = $value;
                break;
            case 'OSVERSION':
                $this->OSVersion = $value;
                break;
            case 'OSPLATFORM':
                $this->OSPlatform = $value;
                break;
            case 'COMPILERNAME':
                $this->CompilerName = $value;
                break;
            case 'COMPILERVERSION':
                $this->CompilerVersion = $value;
                break;
            case 'BUILDNAME':
              $this->BuildName = $value;
              if (empty($this->BuildName)) {
                  $this->BuildName = '(empty)';
              }
              break;
            case 'BUILDSTAMP':
                $this->BuildStamp = $value;
                break;
            case 'GENERATOR':
                $this->Generator = $value;
                break;
            case 'CHANGEID':
                $this->PullRequest = $value;
                break;
        }
    }

    /** Save the site information */
    public function Save()
    {
        if ($this->OSName != '' || $this->OSPlatform != '' || $this->OSRelease != '' || $this->OSVersion != '') {
            if (empty($this->BuildId)) {
                return false;
            }

            // Check if we already have a buildinformation for that build. If yes we just skip it
            $query = pdo_query('SELECT buildid FROM buildinformation WHERE buildid=' . qnum($this->BuildId));
            add_last_sql_error('BuildInformation Insert', 0, $this->BuildId);
            if (pdo_num_rows($query) == 0) {
                pdo_query('INSERT INTO buildinformation (buildid,osname,osrelease,osversion,osplatform,compilername,compilerversion)
                    VALUES (' . qnum($this->BuildId) . ",'$this->OSName','$this->OSRelease',
                            '$this->OSVersion','$this->OSPlatform','$this->CompilerName','$this->CompilerVersion')");
                add_last_sql_error('BuildInformation Insert', 0, $this->BuildId);
            }
            return true;
        }
    }
}
