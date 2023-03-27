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

use CDash\Database;

class BuildInformation
{
    public $BuildId;
    public $OSName;
    public $OSPlatform;
    public $OSRelease;
    public $OSVersion;
    public $CompilerName = 'unknown';
    public $CompilerVersion = 'unknown';
    private $Filled;
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
        $this->Filled = false;
        $this->PDO = Database::getInstance()->getPdo();
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

        return \DB::transaction(function () {
            \DB::table('buildinformation')->insertOrIgnore([
                [
                    'buildid' => $this->BuildId,
                    'osname' => $this->OSName,
                    'osrelease' => $this->OSRelease,
                    'osversion' => $this->OSVersion,
                    'osplatform' => $this->OSPlatform,
                    'compilername' => $this->CompilerName,
                    'compilerversion' => $this->CompilerVersion,
                ],
            ]);
        });
    }

    /** Load information from the database */
    public function Fill()
    {
        if ($this->Filled) {
            return true;
        }
        if ($this->BuildId < 1) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT * FROM buildinformation WHERE buildid = ?');
        if (!pdo_execute($stmt, [$this->BuildId])) {
            return false;
        }
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $this->OSName = $row['osname'];
        $this->OSPlatform = $row['osplatform'];
        $this->OSRelease = $row['osrelease'];
        $this->OSVersion = $row['osversion'];
        $this->CompilerName = $row['compilername'];
        $this->CompilerVersion = $row['compilerversion'];
        return true;
    }
}
