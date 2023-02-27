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
use PDO;

/** Label */
class Label
{
    public $Id;
    public $Text;

    public $BuildId;
    public $BuildFailureId;
    public $CoverageFileId;
    public $CoverageFileBuildId;
    public $DynamicAnalysisId;
    public $TestId;
    public $TestBuildId;

    private $PDO;


    public function __construct()
    {
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function SetText($text): void
    {
        $this->Text = $text ?? '';
    }

    /** Get the text of a label */
    public function GetText(): string
    {
        $db = Database::getInstance();
        return $db->executePreparedSingleRow('
                   SELECT text FROM label WHERE id=?
               ', [intval($this->Id)])['text'] ?? '';
    }

    /** Get the id from a label */
    public function GetIdFromText()
    {
        $db = Database::getInstance();
        return intval($db->executePreparedSingleRow('
                   SELECT id FROM label WHERE text=?
               ', [$this->Text ?? ''])['id'] ?? 0);
    }

    public function GetTextFromBuildFailure($fetchType = PDO::FETCH_ASSOC): array|false
    {
        if (!$this->BuildFailureId) {
            add_log('BuildFailureId not set', 'Label::GetTestFromBuildFailure', LOG_WARNING);
            return false;
        }

        $sql = '
            SELECT text FROM label, label2buildfailure
            WHERE label.id=label2buildfailure.labelid AND
            label2buildfailure.buildfailureid=?
            ORDER BY text ASC
        ';

        $query = $this->PDO->prepare($sql);
        pdo_execute($query, [$this->BuildFailureId]);

        return $query->fetchAll($fetchType);
    }

    public function InsertAssociation(string $table, string $field1, ?int $value1 = null, ?string $field2 = null, ?int $value2 = null): void
    {
        $duplicate_sql = '';
        if (config('database.default') !== 'pgsql') {
            $duplicate_sql = 'ON DUPLICATE KEY UPDATE labelid=labelid';
        }
        if (!empty($value1)) {
            $db = Database::getInstance();

            if (!empty($value2)) {
                $query = $db->executePreparedSingleRow("
                             SELECT $field1
                             FROM $table
                             WHERE
                                 labelid=?
                                 AND $field1=?
                                 AND $field2=?
                         ", [intval($this->Id), $value1, $value2]);
                $v = intval($query[$field1] ?? 0);

                // Only do the INSERT if it's not already there:
                if ($v === 0) {
                    $query = $db->executePrepared("
                                 INSERT INTO $table (labelid, $field1, $field2)
                                 VALUES (?, ?, ?)
                                 $duplicate_sql
                             ", [intval($this->Id), $value1, $value2]);

                    if ($query === false) {
                        add_last_sql_error('Label::InsertAssociation');
                    }
                }
            } else {
                $query = $db->executePreparedSingleRow("
                             SELECT $field1
                             FROM $table
                             WHERE
                                 labelid=?
                                 AND $field1=?
                         ", [intval($this->Id), $value1]);

                $v = intval($query[$field1] ?? 0);

                // Only do the INSERT if it's not already there:
                if ($v === 0) {
                    $query = $db->executePrepared("
                                 INSERT INTO $table (labelid, $field1)
                                 VALUES (?, ?)
                                 $duplicate_sql
                             ", [intval($this->Id), $value1]);

                    if ($query === false) {
                        add_last_sql_error('Label::InsertAssociation');
                    }
                }
            }
        }
    }

    // Save in the database
    public function Insert()
    {
        $text = $this->Text ?? '';

        $db = Database::getInstance();

        // Get this->Id from the database if text is already in the label table:
        $query = $db->executePreparedSingleRow('SELECT id FROM label WHERE text=?', [$text]);
        $this->Id = intval($query['id'] ?? 0);

        // Or, if necessary, insert a new row, then get the id of the inserted row:
        if ($this->Id === 0) {
            $query = $db->executePrepared("INSERT INTO label (text) VALUES (?)", [$text]);
            if ($query === false) {
                // This insert might have failed due to a race condition
                // during parallel processing.
                // Query again to see if it exists before throwing an error.
                $query = $db->executePreparedSingleRow('SELECT id FROM label WHERE text=?', [$text]);
                $this->Id = intval($query['id'] ?? 0);
                if ($this->Id === 0) {
                    add_last_sql_error('Label::Insert');
                    return false;
                }
            } else {
                $this->Id = intval(pdo_insert_id('label'));
            }
        }

        // Insert relationship records, too, but only for those relationships
        // established by callers. (If coming from test.php, for example, TestId
        // will be set, but none of the others will. Similarly for other callers.)
        $this->InsertAssociation('label2build', 'buildid',
            intval($this->BuildId));

        $this->InsertAssociation('label2buildfailure', 'buildfailureid',
            intval($this->BuildFailureId));

        $this->InsertAssociation('label2coveragefile', 'buildid',
            intval($this->CoverageFileBuildId),
            'coveragefileid', intval($this->CoverageFileId));

        $this->InsertAssociation('label2dynamicanalysis', 'dynamicanalysisid',
            intval($this->DynamicAnalysisId));

        $this->InsertAssociation('label2test', 'buildid',
            intval($this->TestBuildId), 'outputid', intval($this->TestId));

        // TODO: Implement this:
        //
        //$this->InsertAssociation($this->UpdateFileKey,
        //  'label2updatefile', 'updatefilekey');
        return true;
    }
}
