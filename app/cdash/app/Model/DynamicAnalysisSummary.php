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

use Illuminate\Support\Facades\DB;

class DynamicAnalysisSummary
{
    public int $BuildId = 0;
    public string $Checker = '';
    private int $NumDefects = 0;

    /** Add defects to the summary */
    public function AddDefects(int $defects): void
    {
        $this->NumDefects += $defects;
    }

    /** Check if a summary already exists for this build. */
    protected function Exists(): bool
    {
        if ($this->BuildId < 1) {
            return false;
        }

        return \App\Models\DynamicAnalysisSummary::where('buildid', $this->BuildId)->exists();
    }

    /** Remove the dynamic analysis summary for this build. */
    protected function Remove(): void
    {
        if ($this->BuildId < 1) {
            abort(500, 'Invalid BuildId');
        }
        if (!$this->Exists()) {
            abort(500, 'Dynamic Analysis does not exist.');
        }

        \App\Models\DynamicAnalysisSummary::where('buildid', $this->BuildId)->delete();
    }

    public function Insert($append = false)
    {
        if ($this->BuildId < 1) {
            return false;
        }

        DB::beginTransaction();

        if ($this->Exists()) {
            if ($append) {
                \App\Models\DynamicAnalysisSummary::where('buildid', $this->BuildId)
                    ->increment('numdefects', $this->NumDefects);
                $model = \App\Models\DynamicAnalysisSummary::where('buildid', $this->BuildId)->firstOrFail();
                $this->Checker = $model->checker;
                $this->NumDefects = $model->numdefects;
            } else {
                \App\Models\DynamicAnalysisSummary::where('buildid', $this->BuildId)
                    ->update([
                        'checker' => $this->Checker,
                        'numdefects' => $this->NumDefects,
                    ]);
            }
        } else {
            \App\Models\DynamicAnalysisSummary::create([
                'buildid' => $this->BuildId,
                'checker' => $this->Checker,
                'numdefects' => $this->NumDefects,
            ]);
        }

        DB::commit();
        return true;
    }
}
