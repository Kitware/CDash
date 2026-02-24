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
use App\Models\Label;
use App\Models\RichBuildAlert;
use CDash\Database;
use Illuminate\Support\Facades\DB;
use PDO;

/** BuildFailure */
class BuildFailure
{
    public $BuildId;
    public $Type;
    public string $WorkingDirectory = '';
    /** @var array<string> */
    protected array $Arguments = [];
    public string $StdOutput = '';
    public string $StdError = '';
    public string $ExitCondition = '';
    public string $Language = '';
    public string $TargetName = '';
    public string $SourceFile = '';
    public string $OutputFile = '';
    public string $OutputType = '';
    public array $Labels = [];

    private $PDO;

    public function __construct()
    {
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function AddLabel($label): void
    {
        $this->Labels[] = $label;
    }

    public function AddArgument(string $argument): void
    {
        $this->Arguments[] = $argument;
    }

    // Insert in the database (no update possible)
    public function Insert(): bool
    {
        if (!$this->BuildId) {
            abort(500, 'BuildFailure::Insert(): BuildId not set.');
        }

        $db = Database::getInstance();

        /** @var RichBuildAlert $failure */
        $failure = Build::findOrFail((int) $this->BuildId)->richAlerts()->create([
            'workingdirectory' => $this->WorkingDirectory,
            'sourcefile' => $this->SourceFile,
            'newstatus' => 0,
            'type' => (int) $this->Type,
            'stdoutput' => $this->StdOutput,
            'stderror' => $this->StdError,
            'exitcondition' => $this->ExitCondition,
            'language' => $this->Language,
            'targetname' => $this->TargetName,
            'outputfile' => $this->OutputFile,
            'outputtype' => $this->OutputType,
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

        // Insert the labels
        foreach ($this->Labels as $label) {
            $label = Label::firstOrCreate(['text' => $label->Text ?? '']);
            $failure->labels()->syncWithoutDetaching([$label->id]);
        }
        return true;
    }

    /**
     * Retrieve the arguments from a build failure given its id.
     **/
    public function GetBuildFailureArguments(int $buildFailureId): array
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

    /** Returns a self referencing URI for the current BuildFailure. */
    public function GetUrlForSelf(): string
    {
        return url('/builds/' . $this->BuildId . '/errors');
    }
}
