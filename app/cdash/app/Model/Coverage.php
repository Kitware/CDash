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

/** Coverage class. Used by CoverageSummary */
class Coverage
{
    public $BuildId;
    public $Covered;
    public $LocTested = 0;
    public $LocUntested = 0;
    public $BranchesTested = 0;
    public $BranchesUntested = 0;
    public $FunctionsTested = 0;
    public $FunctionsUntested = 0;
    public $CoverageFile;
    public $Labels;

    // Purposely no Insert function. Everything is done from the coverage summary
    public function AddLabel($label)
    {
        if (!isset($this->Labels)) {
            $this->Labels = array();
        }

        $label->CoverageFileId = $this->CoverageFile->Id;
        $label->CoverageFileBuildId = $this->BuildId;
        $this->Labels[] = $label;
    }

    /** Put labels for coverage */
    public function InsertLabelAssociations($buildid)
    {
        if ($buildid &&
            isset($this->CoverageFile) &&
            $this->CoverageFile->Id
        ) {
            if (empty($this->Labels)) {
                return;
            }

            foreach ($this->Labels as $label) {
                $label->CoverageFileId = $this->CoverageFile->Id;
                $label->CoverageFileBuildId = $buildid;
                $label->Insert();
            }
        } else {
            add_log('No buildid or coveragefile',
                'Coverage::InsertLabelAssociations', LOG_ERR,
                0, $buildid,
                ModelType::COVERAGE, $this->CoverageFile->Id);
        }
    }

    /** Return the name of a build */
    public function GetFiles()
    {
        if (!$this->BuildId) {
            echo 'Coverage GetFiles(): BuildId not set';
            return false;
        }

        $fileids = array();

        $db = Database::getInstance();
        $coverage = $db->executePrepared('SELECT fileid FROM coverage WHERE buildid=?', [$this->BuildId]);
        if (!$coverage) {
            add_last_sql_error('Coverage GetFiles');
            return false;
        }

        foreach ($coverage as $coverage_array) {
            $fileids[] = $coverage_array['fileid'];
        }
        return $fileids;
    }

    /** Return true if this build already has coverage for this file,
      * false otherwise.
      **/
    public function Exists()
    {
        if (!$this->BuildId || !$this->CoverageFile || !$this->CoverageFile->Id) {
            return false;
        }

        $db = Database::getInstance();
        $result = $db->executePreparedSingleRow('
                      SELECT COUNT(*) AS c
                      FROM coverage
                      WHERE
                          buildid=?
                          AND fileid=?
                  ', [$this->BuildId, $this->CoverageFile->Id]);
        if ($result['c'] > 0) {
            return true;
        }
        return false;
    }
}
