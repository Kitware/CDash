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

use App\Utils\RepositoryUtils;
use CDash\Database;
use Illuminate\Support\Facades\DB;
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

    public function InsertLabelAssociations($id): void
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
            add_log('No BuildFailure id - cannot call $label->Insert...',
                'BuildFailure::InsertLabelAssociations', LOG_ERR, 0, $this->BuildId);
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

        // Compute the crc32.
        $crc32 = crc32($outputFile . $stdOutput . $stdError . $sourceFile);

        $db = Database::getInstance();

        // Get details ID if it already exists, otherwise insert a new row.
        $detailsResult = $db->executePreparedSingleRow('
                             SELECT id FROM buildfailuredetails WHERE crc32=?
                         ', [$crc32]);
        if ($detailsResult && array_key_exists('id', $detailsResult)) {
            $detailsId = intval($detailsResult['id']);
        } else {
            $detailsId = DB::table('buildfailuredetails')
                ->insertGetId([
                    'type' => intval($this->Type),
                    'stdoutput' => $stdOutput,
                    'stderror' => $stdError,
                    'exitcondition' => $exitCondition,
                    'language' => $language,
                    'targetname' => $targetName,
                    'outputfile' => $outputFile,
                    'outputtype' => $outputType,
                    'crc32' => $crc32,
                ]);
        }

        $id = DB::table('buildfailure')
            ->insertGetId([
                'buildid' => intval($this->BuildId),
                'detailsid' => $detailsId,
                'workingdirectory' => $workingDirectory,
                'sourcefile' => $sourceFile,
                'newstatus' => 0,
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
                add_last_sql_error('BuildFailure Insert', 0, $this->BuildId);
                return false;
            }

            if (!empty($query)) {
                $argumentids[] = intval($query['id']);
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
            $params[] = intval($id);
            $params[] = intval($argumentid);
            $params[] = $i;
            $i++;
        }
        $query = rtrim($query, ',');
        if (count($params) > 0 && $db->executePrepared($query, $params) === false) {
            add_last_sql_error('BuildFailure Insert', 0, $this->BuildId);
            return false;
        }

        $this->InsertLabelAssociations($id);
        return true;
    }

    /** Returns all failures, including warnings, for current build */
    public function GetFailuresForBuild(int $fetchStyle = PDO::FETCH_ASSOC): array|false
    {
        if (!$this->BuildId) {
            add_log('BuildId not set', 'BuildFailure::GetFailuresForBuild', LOG_WARNING);
            return false;
        }

        $sql = "
            SELECT
                bf.id,
                bf.buildid,
                bf.workingdirectory,
                bf.sourcefile,
                bf.newstatus,
                bfd.stdoutput,
                bfd.stderror,
                bfd.type,
                bfd.exitcondition,
                bfd.language,
                bfd.targetname,
                bfd.outputfile,
                bfd.outputtype
            FROM buildfailuredetails AS bfd
            LEFT JOIN buildfailure AS bf
                ON (bf.detailsid = bfd.id)
            WHERE bf.buildid=?
            ORDER BY bf.id
        ";
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

        $sql = "
            SELECT bfa.argument
            FROM buildfailureargument AS bfa,
            buildfailure2argument AS bf2a
            WHERE bf2a.buildfailureid=:build_failure_id
            AND bf2a.argumentid=bfa.id
            ORDER BY bf2a.place ASC
        ";

        $stmt = $this->PDO->prepare($sql);
        $stmt->bindParam(':build_failure_id', $buildFailureId);
        pdo_execute($stmt);

        $i = 0;
        while ($argument_array = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($i == 0) {
                $response['argumentfirst'] = $argument_array['argument'];
            } else {
                $response['arguments'][] = $argument_array['argument'];
            }
            $i++;
        }

        return $response;
    }

    /**
     * Marshal a build failure, this includes the build failure arguments.
     **/
    public static function marshal($data, Project $project, $revision, $linkifyOutput, $buildfailure): array
    {
        deepEncodeHTMLEntities($data);

        $marshaled = array_merge([
            'language' => $data['language'],
            'sourcefile' => $data['sourcefile'],
            'targetname' => $data['targetname'],
            'outputfile' => $data['outputfile'],
            'outputtype' => $data['outputtype'],
            'workingdirectory' => $data['workingdirectory'],
            'exitcondition' => $data['exitcondition'],
        ], $buildfailure->GetBuildFailureArguments($data['id']));

        $marshaled['stderror'] = $data['stderror'];
        $marshaled['stdoutput'] = $data['stdoutput'];

        if (isset($data['sourcefile'])) {
            $file = basename($data['sourcefile']);
            $directory = dirname($data['sourcefile']);

            $source_dir = RepositoryUtils::get_source_dir($project->Id, $project->CvsUrl, $directory);
            if (str_starts_with($directory, $source_dir)) {
                $directory = substr($directory, strlen($source_dir));
            }

            $marshaled['cvsurl'] = RepositoryUtils::get_diff_url($project->Id,
                $project->CvsUrl,
                $directory,
                $file,
                $revision);

            if ($source_dir !== null && $linkifyOutput) {
                $marshaled['stderror'] = RepositoryUtils::linkify_compiler_output($project->CvsUrl, $source_dir,
                    $revision, $data['stderror']);
                $marshaled['stdoutput'] = RepositoryUtils::linkify_compiler_output($project->CvsUrl, $source_dir,
                    $revision, $data['stdoutput']);
            }
        }

        if (isset($data['subprojectid'])) {
            $marshaled['subprojectid'] = $data['subprojectid'];
            $marshaled['subprojectname'] = $data['subprojectname'];
        }

        return $marshaled;
    }

    /** Returns a self referencing URI for a the current BuildFailure. */
    public function GetUrlForSelf(): string
    {
        return url('/viewBuildError.php') . "?type={$this->Type}&buildid={$this->BuildId}";
    }
}
