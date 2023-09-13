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
use Illuminate\Support\Facades\Log;
use App\Models\Label as EloquentLabel;

/** Label */
class Label
{
    public $Id;
    public string $Text = '';

    public $BuildId;
    public $BuildFailureId;
    public $CoverageFileId;
    public int $CoverageFileBuildId = 0;
    public $DynamicAnalysisId;
    public $TestId;
    public int $TestBuildId = 0;

    public function SetText(?string $text): void
    {
        $this->Text = $text ?? '';
    }

    /** Get the text of a label */
    public function GetText(): string
    {
        $model = EloquentLabel::find((int) $this->Id);
        return $model === null ? '' : $model->text;
    }

    /** Get the id from a label */
    public function GetIdFromText(): int
    {
        $model = EloquentLabel::where('text', $this->Text);
        return $model->count() > 0 ? $model->first()->id : 0;
    }

    public function GetTextFromBuildFailure(): array|false
    {
        if (!$this->BuildFailureId) {
            Log::warning('Label::GetTestFromBuildFailure(): BuildFailureId not set');
            return false;
        }

        return DB::select('
            SELECT text FROM label, label2buildfailure
            WHERE label.id=label2buildfailure.labelid AND
            label2buildfailure.buildfailureid=?
            ORDER BY text ASC
        ', [$this->BuildFailureId]);
    }

    private function InsertAssociation(string $table, string $field1, ?int $value1 = null, ?string $field2 = null, ?int $value2 = null): void
    {
        $duplicate_sql = '';
        if (config('database.default') !== 'pgsql') {
            $duplicate_sql = 'ON DUPLICATE KEY UPDATE labelid=labelid';
        }
        if (!empty($value1)) {
            if (!empty($value2)) {
                $query = DB::select("
                             SELECT $field1
                             FROM $table
                             WHERE
                                 labelid=?
                                 AND $field1=?
                                 AND $field2=?
                         ", [intval($this->Id), $value1, $value2])[0] ?? [];
                $v = intval($query->$field1 ?? 0);

                // Only do the INSERT if it's not already there:
                if ($v === 0) {
                    DB::insert("
                        INSERT INTO $table (labelid, $field1, $field2)
                        VALUES (?, ?, ?)
                        $duplicate_sql
                    ", [intval($this->Id), $value1, $value2]);
                }
            } else {
                $query = DB::select("
                             SELECT $field1
                             FROM $table
                             WHERE
                                 labelid=?
                                 AND $field1=?
                         ", [intval($this->Id), $value1])[0] ?? [];

                $v = intval($query->$field1 ?? 0);

                // Only do the INSERT if it's not already there:
                if ($v === 0) {
                    DB::insert("
                        INSERT INTO $table (labelid, $field1)
                        VALUES (?, ?)
                        $duplicate_sql
                    ", [intval($this->Id), $value1]);
                }
            }
        }
    }

    /**
     * Save in the database
     */
    public function Insert()
    {
        $this->Id = EloquentLabel::firstOrCreate(['text' => $this->Text ?? ''])->id;

        // Insert relationship records, too, but only for those relationships
        // established by callers. (If coming from test.php, for example, TestId
        // will be set, but none of the others will. Similarly for other callers.)
        $this->InsertAssociation('label2build', 'buildid', intval($this->BuildId));

        $this->InsertAssociation('label2buildfailure', 'buildfailureid', intval($this->BuildFailureId));

        $this->InsertAssociation('label2coveragefile', 'buildid',
            $this->CoverageFileBuildId, 'coveragefileid', intval($this->CoverageFileId));

        $this->InsertAssociation('label2dynamicanalysis', 'dynamicanalysisid', intval($this->DynamicAnalysisId));

        $this->InsertAssociation('label2test', 'buildid', $this->TestBuildId, 'outputid', intval($this->TestId));

        // TODO: Implement this:
        //
        //$this->InsertAssociation($this->UpdateFileKey,
        //  'label2updatefile', 'updatefilekey');
        return true;
    }
}
