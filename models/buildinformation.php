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
    private $PDO;

    public function __construct()
    {
        $this->BuildId = 0;
        $this->OSName = '';
        $this->OSPlatform = '';
        $this->OSRelease = '';
        $this->OSVersion = '';
        $this->CompilerName = 'unknown';
        $this->CompilerVersion = 'unknown';
        $this->PDO = get_link_identifier()->getPdo();
    }

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
        }
    }

    /** Save the site information */
    public function Save()
    {
        if ($this->BuildId < 1) {
            return false;
        }
        if ($this->OSName == '' && $this->OSPlatform == '' &&
                $this->OSRelease == '' && $this->OSVersion == '') {
            return false;
        }

        // Check if we already have a buildinformation for this build.
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM buildinformation WHERE buildid = ?');
        pdo_execute($stmt, [$this->BuildId]);
        if ($stmt->fetchColumn() > 0) {
            // If so we just skip it.
            return true;
        }

        $stmt = $this->PDO->prepare(
            'INSERT INTO buildinformation
            (buildid, osname, osrelease, osversion, osplatform, compilername,
             compilerversion)
            VALUES
            (:buildid, :osname, :osrelease, :osversion, :osplatform, :compilername,
             :compilerversion)');
        $stmt->bindValue(':buildid', $this->BuildId);
        $stmt->bindValue(':osname', $this->OSName);
        $stmt->bindValue(':osrelease', $this->OSRelease);
        $stmt->bindValue(':osversion', $this->OSVersion);
        $stmt->bindValue(':osplatform', $this->OSPlatform);
        $stmt->bindValue(':compilername', $this->CompilerName);
        $stmt->bindValue(':compilerversion', $this->CompilerVersion);
        return pdo_execute($stmt);
    }
}
