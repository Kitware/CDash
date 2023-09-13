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

use App\Models\Coverage as EloquentCoverage;

/**
 * Coverage class. Used by CoverageSummary
 *
 * This class is deprecated and serves as an adapter for the replacement App\Models\Coverage
 * model to support legacy code and should ultimately be removed.
 */
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
            $this->Labels = [];
        }

        $label->CoverageFileId = $this->CoverageFile->Id;
        $label->CoverageFileBuildId = (int) $this->BuildId;
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
                $label->CoverageFileBuildId = (int) $buildid;
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
    public function GetFiles(): array|false
    {
        if (!$this->BuildId) {
            abort(500, 'Coverage GetFiles(): BuildId not set');
        }

        return EloquentCoverage::where('buildid', $this->BuildId)->pluck('fileid')->toArray();
    }

    /** Return true if this build already has coverage for this file,
      * false otherwise.
      **/
    public function Exists(): bool
    {
        if (!$this->BuildId || !$this->CoverageFile || !$this->CoverageFile->Id) {
            return false;
        }

        return EloquentCoverage::where([
            ['buildid', $this->BuildId],
            ['fileid', $this->CoverageFile->Id],
        ])->exists();
    }
}
