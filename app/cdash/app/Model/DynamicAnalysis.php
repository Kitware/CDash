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

use App\Models\Build;
use App\Models\DynamicAnalysis as EloquentDynamicAnalysis;
use App\Models\DynamicAnalysisDefect;
use App\Models\Label;
use CDash\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicAnalysis
{
    public const PASSED = 'passed';
    public const FAILED = 'failed';
    public const NOTRUN = 'notrun';

    public $Id;
    public string $Status = ''; // TODO: use an enum for this
    public string $Checker = '';
    public string $Name = '';
    public string $Path = '';
    public string $FullCommandLine = '';
    public string $Log = '';
    /** @var array<DynamicAnalysisDefect> */
    private array $Defects = [];
    public int $BuildId = -1;
    /** @var array<\CDash\Model\Label> */
    public array $Labels = [];
    public string $LogCompression = '';
    public string $LogEncoding = '';

    /** Add a defect */
    public function AddDefect(DynamicAnalysisDefect $defect): void
    {
        $this->Defects[] = $defect;
    }

    public function GetDefects(): array
    {
        return $this->Defects;
    }

    public function AddLabel(\CDash\Model\Label $label): void
    {
        $this->Labels[] = $label;
    }

    /** Insert labels */
    protected function InsertLabelAssociations(): void
    {
        if (empty($this->Labels)) {
            return;
        }

        if ($this->Id) {
            $dynamicAnalysis = EloquentDynamicAnalysis::findOrFail((int) $this->Id);
            foreach ($this->Labels as $label) {
                $dynamicAnalysis->labels()->attach(
                    Label::firstOrCreate(['text' => $label->Text ?? ''])->id
                );
            }
        } else {
            Log::error('No DynamicAnalysis::Id - cannot call $label->Insert...', [
                'function' => 'DynamicAnalysis::InsertLabelAssociations',
                'buildid' => $this->BuildId,
            ]);
        }
    }

    /** Insert the DynamicAnalysis */
    public function Insert()
    {
        if ($this->BuildId === -1) {
            abort(500, 'DynamicAnalysis::Insert BuildId not set');
        }

        $max_log_length = 1024 * 1024;

        $log = $this->Log;
        // Handle log decoding/decompression
        if (strtolower($this->LogEncoding) === 'base64') {
            $log = str_replace(["\r\n", "\n", "\r"], '', $log);
            $log = base64_decode($log);
        }
        if (strtolower($this->LogCompression) === 'gzip') {
            // Avoid memory exhaustion errors by buffering data as we
            // decompress the gzipped log.
            $uncompressed_log = '';
            $inflate_context = inflate_init(ZLIB_ENCODING_DEFLATE);
            foreach (str_split($log, 1024) as $chunk) {
                $uncompressed_log .= inflate_add($inflate_context, $chunk, ZLIB_NO_FLUSH);
                if (strlen($uncompressed_log) >= $max_log_length) {
                    break;
                }
            }
            $uncompressed_log .= inflate_add($inflate_context, null, ZLIB_FINISH);
            $log = $uncompressed_log;
        }

        if ($log === false) {
            Log::error('Unable to decompress dynamic analysis log', [
                'function' => 'DynamicAnalysis::Insert',
                'buildid' => $this->BuildId,
                'dynamicanalysisid' => $this->Id,
            ]);
            $log = '';
        }
        $this->Log = $log;

        // Only store 1MB of log.
        if (strlen($this->Log) > $max_log_length) {
            $truncated_msg = "\n(truncated)\n";
            $keep_length = $max_log_length - strlen($truncated_msg);
            $this->Log = substr($this->Log, 0, $keep_length);
            $this->Log .= $truncated_msg;
        }

        $path = substr($this->Path, 0, 255);
        $fullCommandLine = substr($this->FullCommandLine, 0, 255);

        $eloquent_da = Build::findOrFail($this->BuildId)->dynamicAnalyses()->create([
            'status' => $this->Status,
            'checker' => $this->Checker,
            'name' => $this->Name,
            'path' => $path,
            'fullcommandline' => $fullCommandLine,
            'log' => $this->Log,
        ]);

        $this->Id = $eloquent_da->id;

        // Add the defects
        foreach ($this->Defects as $defect) {
            $eloquent_da->defects()->save($defect);
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

        $model = EloquentDynamicAnalysis::find((int) $this->Id);
        if ($model === null) {
            return false;
        }

        $this->BuildId = $model->buildid;
        $this->Status = $model->status;
        $this->Checker = $model->checker;
        $this->Name = $model->name;
        $this->Path = $model->path;
        $this->FullCommandLine = $model->fullcommandline;
        $this->Log = $model->log;

        return true;
    }

    /** Encapsulate common bits of functions below. */
    private function GetRelatedId($build, $order, $time_clause = null): int
    {
        $params = [
            'siteid' => $build->SiteId,
            'buildtype' => $build->Type,
            'buildname' => $build->Name,
            'projectid' => $build->ProjectId,
            'filename' => $this->Name,
        ];

        if ($time_clause !== null) {
            $params['starttime'] = $build->StartTime;
        }

        $query = DB::select("
            SELECT dynamicanalysis.id
            FROM dynamicanalysis
            JOIN build ON (dynamicanalysis.buildid = build.id)
            WHERE build.siteid = :siteid AND
                build.type = :buildtype AND
                build.name = :buildname AND
                build.projectid = :projectid AND
                $time_clause
                dynamicanalysis.name = :filename
            ORDER BY build.starttime $order
            LIMIT 1
        ", $params);

        if ($query === []) {
            return 0;
        }
        return intval($query[0]->id);
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
        return url('/viewDynamicAnalysisFile.php') . "?id={$this->Id}";
    }
}
