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

use CDash\Config;
use CDash\Database;

class DynamicAnalysis
{
    const PASSED = 'passed';
    const FAILED = 'failed';
    const NOTRUN = 'notrun';

    public $Id;
    public $Status;
    public $Checker;
    public $Name;
    public $Path;
    public $FullCommandLine;
    public $Log;
    private $Defects;
    public $BuildId;
    public $Labels;
    public $LogCompression;
    public $LogEncoding;
    private $Filled;
    private $PDO;

    public function __construct()
    {
        $this->Id = null;
        $this->Filled = false;
        $this->PDO = Database::getInstance()->getPdo();
        $this->Defects = [];
    }

    /** Add a defect */
    public function AddDefect($defect): void
    {
        $defect->DynamicAnalysisId = $this->Id;
        $this->Defects[] = $defect;
    }

    public function GetDefects(): array
    {
        return $this->Defects;
    }

    public function AddLabel($label): void
    {
        $label->DynamicAnalysisId = $this->Id;
        $this->Labels[] = $label;
    }

    /** Find how many dynamic analysis tests were failed or notrun status */
    public function GetNumberOfErrors(): int|false
    {
        if (strlen($this->BuildId) == 0) {
            echo 'DynamicAnalysis::GetNumberOfErrors BuildId not set';
            return false;
        }

        $db = Database::getInstance();

        $query = $db->executePreparedSingleRow("
                     SELECT count(*) AS c
                     FROM dynamicanalysis
                     WHERE buildid=? AND status IN ('notrun', 'failed')
                 ", [$this->BuildId]);

        return intval($query['c']);
    }

    /** Remove all the dynamic analysis associated with a buildid */
    public function RemoveAll(): bool
    {
        if (strlen($this->BuildId) == 0) {
            echo 'DynamicAnalysis::RemoveAll BuildId not set';
            return false;
        }

        $this->BuildId = intval($this->BuildId);

        $db = Database::getInstance();

        if (config('database.default') == 'pgsql') {
            // postgresql doesn't support multiple delete
            //
            // TODO: (williamjallen) Figure out what to do with the potential race condition caused here.
            //       Ideally this should be done via a cascading delete, or at least in a single transaction.
            $query = $db->executePrepared('
                         DELETE FROM dynamicanalysisdefect
                         USING dynamicanalysis
                         WHERE
                             dynamicanalysis.buildid=?
                             AND dynamicanalysis.id=dynamicanalysisdefect.dynamicanalysisid
                     ', [$this->BuildId]);

            if ($query === false) {
                add_last_sql_error('DynamicAnalysis RemoveAll', 0, $this->BuildId);
                return false;
            }

            $query = $db->executePrepared('DELETE FROM dynamicanalysis WHERE dynamicanalysis.buildid=?', [$this->BuildId]);
        } else {
            $query = $db->executePrepared('
                         DELETE dynamicanalysisdefect, dynamicanalysis
                         FROM dynamicanalysisdefect, dynamicanalysis
                         WHERE
                             dynamicanalysis.buildid=?
                             AND dynamicanalysis.id=dynamicanalysisdefect.dynamicanalysisid
                     ', [$this->BuildId]);
        }

        if ($query === false) {
            add_last_sql_error('DynamicAnalysis RemoveAll', 0, $this->BuildId);
            return false;
        }

        $query = $db->executePrepared('DELETE FROM dynamicanalysis WHERE buildid=?', [$this->BuildId]);
        if ($query === false) {
            add_last_sql_error('DynamicAnalysis RemoveAll', 0, $this->BuildId);
            return false;
        }

        return true;
    }

    /** Insert labels */
    public function InsertLabelAssociations(): void
    {
        if (empty($this->Labels)) {
            return;
        }

        if ($this->Id) {
            foreach ($this->Labels as $label) {
                $label->DynamicAnalysisId = $this->Id;
                $label->Insert();
            }
        } else {
            add_log('No DynamicAnalysis::Id - cannot call $label->Insert...',
                'DynamicAnalysis::InsertLabelAssociations', LOG_ERR,
                0, $this->BuildId, ModelType::DYNAMICANALYSIS, $this->Id);
        }
    }

    /** Insert the DynamicAnalysis */
    public function Insert()
    {
        if (strlen($this->BuildId) == 0) {
            echo 'DynamicAnalysis::Insert BuildId not set';
            return false;
        }

        $max_log_length = 1024 * 1024;

        // Handle log decoding/decompression
        if (strtolower($this->LogEncoding ?? '') == 'base64') {
            $this->Log = str_replace(array("\r\n", "\n", "\r"), '', $this->Log);
            $this->Log = base64_decode($this->Log);
        }
        if (strtolower($this->LogCompression ?? '') == 'gzip') {
            // Avoid memory exhaustion errors by buffering data as we
            // decompress the gzipped log.
            $uncompressed_log = '';
            $inflate_context = inflate_init(ZLIB_ENCODING_DEFLATE);
            foreach (str_split($this->Log, 1024) as $chunk) {
                $uncompressed_log .= inflate_add($inflate_context, $chunk, ZLIB_NO_FLUSH);
                if (strlen($uncompressed_log) >= $max_log_length) {
                    break;
                }
            }
            $uncompressed_log .= inflate_add($inflate_context, null, ZLIB_FINISH);
            $this->Log = $uncompressed_log;
        }

        if ($this->Log === false) {
            add_log('Unable to decompress dynamic analysis log',
                'DynamicAnalysis::Insert', LOG_ERR, 0, $this->BuildId, ModelType::DYNAMICANALYSIS, $this->Id);
            $this->Log = '';
        }

        // Only store 1MB of log.
        if (strlen($this->Log) > $max_log_length) {
            $truncated_msg = "\n(truncated)\n";
            $keep_length = $max_log_length - strlen($truncated_msg);
            $this->Log = substr($this->Log, 0, $keep_length);
            $this->Log .= $truncated_msg;
        }

        $this->Status =  $this->Status ?? '';
        $this->Checker = $this->Checker ?? '';
        $this->Name = $this->Name ?? '';
        $path = substr($this->Path, 0, 255);
        $fullCommandLine = substr($this->FullCommandLine, 0, 255);
        $this->Log = $this->Log ?? '';
        $this->BuildId = intval($this->BuildId);

        $db = Database::getInstance();

        $id = '';
        $idvalue = [];
        $prepared_array = $db->createPreparedArray(7);
        if ($this->Id) {
            $id = 'id, ';
            $idvalue = [$this->Id];
            $prepared_array = $db->createPreparedArray(8);
        }

        $query = $db->executePrepared("
                     INSERT INTO dynamicanalysis (
                         $id
                         buildid,
                         status,
                         checker,
                         name,
                         path,
                         fullcommandline,
                         log
                     )
                     VALUES $prepared_array
                 ", array_merge($idvalue, [
                     $this->BuildId,
                     $this->Status,
                     $this->Checker,
                     $this->Name,
                     $path,
                     $fullCommandLine,
                     $this->Log
                 ]));

        if ($query === false) {
            add_last_sql_error('DynamicAnalysis Insert', 0, $this->BuildId);
            return false;
        }

        if (!$this->Id) {
            $this->Id = intval(pdo_insert_id('dynamicanalysis'));
        }

        // Add the defects
        if (!empty($this->Defects)) {
            foreach ($this->Defects as $defect) {
                $defect->DynamicAnalysisId = $this->Id;
                $defect->Insert();
            }
        }

        // Log won't be re-used, clear it here to save memory.
        $this->Log = '';

        // Add the labels
        $this->InsertLabelAssociations();
        return true;
    }

    /** Populate $this from the database based on $Id. */
    public function Fill(): bool
    {
        if (!$this->Id) {
            return false;
        }
        if ($this->Filled) {
            return true;
        }

        $stmt = $this->PDO->prepare(
            'SELECT * FROM dynamicanalysis WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }

        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $this->BuildId = $row['buildid'];
        $this->Status = $row['status'];
        $this->Checker = $row['checker'];
        $this->Name = $row['name'];
        $this->Path = $row['path'];
        $this->FullCommandLine = $row['fullcommandline'];
        $this->Log = $row['log'];

        return true;
    }

    /** Encapsulate common bits of functions below. */
    private function GetRelatedId($build, $order, $time_clause = null): int
    {
        $stmt = $this->PDO->prepare(
            "SELECT dynamicanalysis.id FROM dynamicanalysis
        JOIN build ON (dynamicanalysis.buildid = build.id)
        WHERE build.siteid = :siteid AND
              build.type = :buildtype AND
              build.name = :buildname AND
              build.projectid = :projectid AND
              $time_clause
              dynamicanalysis.name = :filename
        ORDER BY build.starttime $order LIMIT 1");

        $stmt->bindParam(':siteid', $build->SiteId);
        $stmt->bindParam(':buildtype', $build->Type);
        $stmt->bindParam(':buildname', $build->Name);
        $stmt->bindParam(':projectid', $build->ProjectId);
        if ($time_clause) {
            $stmt->bindParam(':starttime', $build->StartTime);
        }
        $stmt->bindParam(':filename', $this->Name);
        if (!pdo_execute($stmt)) {
            return 0;
        }
        $row = $stmt->fetch();
        if (!$row) {
            return 0;
        }
        return intval($row['id']);
    }

    /** Get the previous id for this DA */
    public function GetPreviousId($build): int
    {
        $time_clause = 'build.starttime < :starttime AND';
        return $this->GetRelatedId($build, 'DESC', $time_clause);
    }

    /** Get the next id for this DA */
    public function GetNextId($build): int
    {
        $time_clause = 'build.starttime > :starttime AND';
        return $this->GetRelatedId($build, 'ASC', $time_clause);
    }

    /** Get the most recent id for this DA */
    public function GetLastId($build): int
    {
        return $this->GetRelatedId($build, 'DESC');
    }

    /** Returns a self referencing URI for the current DynamicAnalysis. */
    public function GetUrlForSelf(): string
    {
        $config = Config::getInstance();
        $base_url = $config->getBaseUrl();

        return "{$base_url}/viewDynamicAnalysisFile.php?id={$this->Id}";
    }
}
