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
use App\Models\RichBuildAlert;
use CDash\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

/** BuildFailure */
class BuildFailure
{
    public $BuildId;
    public $Type;
    public $WorkingDirectory;
    public $Arguments;
    public $StdOutput;
    public $StdError;
    public $ExitCondition;
    public $Language;
    public $TargetName;
    public $SourceFile;
    public $OutputFile;
    public $OutputType;
    public $Labels;

    private $PDO;

    public function __construct()
    {
        $this->Arguments = [];
        $this->Labels = [];
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function AddLabel($label): void
    {
        $this->Labels[] = $label;
    }

    // Add an argument to the buildfailure
    public function AddArgument($argument): void
    {
        $this->Arguments[] = $argument;
    }

    protected function InsertLabelAssociations($id): void
    {
        if (empty($this->Labels)) {
            return;
        }

        if ($id) {
            foreach ($this->Labels as $label) {
                $label->BuildFailureId = $id;
                $label->Insert();
            }
        } else {
            Log::error('No BuildFailure id - cannot call $label->Insert...', [
                'function' => 'BuildFailure::InsertLabelAssociations',
                'buildid' => $this->BuildId,
            ]);
        }
    }

    // Insert in the database (no update possible)
    public function Insert(): bool
    {
        if (!$this->BuildId) {
            abort(500, 'BuildFailure::Insert(): BuildId not set.');
        }

        $workingDirectory = $this->WorkingDirectory ?? '';
        $stdOutput = $this->StdOutput ?? '';
        $stdError = $this->StdError ?? '';
        $exitCondition = $this->ExitCondition ?? '';
        $language = $this->Language ?? '';
        $targetName = $this->TargetName ?? '';
        $outputFile = $this->OutputFile ?? '';
        $outputType = $this->OutputType ?? '';
        $sourceFile = $this->SourceFile ?? '';

        $db = Database::getInstance();

        /** @var RichBuildAlert $failure */
        $failure = Build::findOrFail((int) $this->BuildId)->richAlerts()->create([
            'workingdirectory' => $workingDirectory,
            'sourcefile' => $sourceFile,
            'newstatus' => 0,
            'type' => (int) $this->Type,
            'stdoutput' => $stdOutput,
            'stderror' => $stdError,
            'exitcondition' => $exitCondition,
            'language' => $language,
            'targetname' => $targetName,
            'outputfile' => $outputFile,
            'outputtype' => $outputType,
        ]);

        // Insert the arguments
        $argumentids = [];

        foreach ($this->Arguments as $argument) {
            // Limit the argument to 255
            $argumentescaped = substr($argument, 0, 255);

            // Check if the argument exists
            $query = $db->executePreparedSingleRow('
                         SELECT id FROM buildfailureargument WHERE argument=?
                     ', [$argumentescaped]);
            if ($query === false) {
                return false;
            }

            if (!empty($query)) {
                $argumentids[] = (int) $query['id'];
            } else {
                $argumentids[] = DB::table('buildfailureargument')->insertGetId([
                    'argument' => $argumentescaped,
                ]);
            }
        }

        // Insert the argument
        $query = 'INSERT INTO buildfailure2argument (buildfailureid, argumentid, place) VALUES ';
        $params = [];
        $i = 0;
        foreach ($argumentids as $argumentid) {
            $query .= '(?, ?, ?),';
            $params[] = $failure->id;
            $params[] = (int) $argumentid;
            $params[] = $i;
            $i++;
        }
        $query = rtrim($query, ',');
        if (count($params) > 0 && $db->executePrepared($query, $params) === false) {
            return false;
        }

        $this->InsertLabelAssociations($failure->id);
        return true;
    }

    /** Returns all failures, including warnings, for current build */
    public function GetFailuresForBuild(int $fetchStyle = PDO::FETCH_ASSOC): array|false
    {
        if (!$this->BuildId) {
            Log::warning('BuildId not set', [
                'function' => 'BuildFailure::GetFailuresForBuild',
            ]);
            return false;
        }

        $sql = '
            SELECT
                bf.id,
                bf.buildid,
                bf.workingdirectory,
                bf.sourcefile,
                bf.newstatus,
                bf.stdoutput,
                bf.stderror,
                bf.type,
                bf.exitcondition,
                bf.language,
                bf.targetname,
                bf.outputfile,
                bf.outputtype
            FROM buildfailure AS bf
            WHERE bf.buildid=?
            ORDER BY bf.id
        ';
        $query = $this->PDO->prepare($sql);

        pdo_execute($query, [$this->BuildId]);

        return $query->fetchAll($fetchStyle);
    }

    /**
     * Retrieve the arguments from a build failure given its id.
     **/
    public function GetBuildFailureArguments($buildFailureId): array
    {
        $response = [
            'argumentfirst' => null,
            'arguments' => [],
        ];

        $sql = '
            SELECT bfa.argument
            FROM buildfailureargument AS bfa,
            buildfailure2argument AS bf2a
            WHERE bf2a.buildfailureid=:build_failure_id
            AND bf2a.argumentid=bfa.id
            ORDER BY bf2a.place ASC
        ';

        $stmt = $this->PDO->prepare($sql);
        $stmt->bindParam(':build_failure_id', $buildFailureId);
        pdo_execute($stmt);

        $i = 0;
        while ($argument_array = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($i == 0) {
                $response['argumentfirst'] = $argument_array['argument'];
            } else {
                $response['arguments'][] = $argument_array['argument'];
            }
            $i++;
        }

        return $response;
    }

    /** Returns a self referencing URI for a the current BuildFailure. */
    public function GetUrlForSelf(): string
    {
        return url('/viewBuildError.php') . "?type={$this->Type}&buildid={$this->BuildId}";
    }
}
