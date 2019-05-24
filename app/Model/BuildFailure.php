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

require_once 'include/common.php';
require_once 'include/repository.php';

use CDash\Config;
use CDash\Database;

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

    public function AddLabel($label)
    {
        $this->Labels[] = $label;
    }

    // Add an argument to the buildfailure
    public function AddArgument($argument)
    {
        $this->Arguments[] = $argument;
    }

    public function InsertLabelAssociations($id)
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
    public function Insert()
    {
        if (!$this->BuildId) {
            echo 'BuildFailure::Insert(): BuildId not set<br>';
            return false;
        }

        $workingDirectory = pdo_real_escape_string($this->WorkingDirectory);
        $stdOutput = pdo_real_escape_string($this->StdOutput);
        $stdError = pdo_real_escape_string($this->StdError);
        $exitCondition = pdo_real_escape_string($this->ExitCondition);
        $language = pdo_real_escape_string($this->Language);
        $targetName = pdo_real_escape_string($this->TargetName);
        $outputFile = pdo_real_escape_string($this->OutputFile);
        $outputType = pdo_real_escape_string($this->OutputType);
        $sourceFile = pdo_real_escape_string($this->SourceFile);

        // Compute the crc32.
        $crc32 = crc32($outputFile . $stdOutput . $stdError . $sourceFile);

        // Get details ID if it already exists, otherwise insert a new row.
        $detailsResult = pdo_single_row_query(
            'SELECT id FROM buildfailuredetails WHERE crc32=' . qnum($crc32));
        if ($detailsResult && array_key_exists('id', $detailsResult)) {
            $detailsId = $detailsResult['id'];
        } else {
            $query =
                'INSERT INTO buildfailuredetails
          (type, stdoutput, stderror, exitcondition, language, targetname,
           outputfile, outputtype, crc32)
         VALUES
          (' . qnum($this->Type) . ", '$stdOutput', '$stdError', '$exitCondition',
           '$language', '$targetName', '$outputFile', '$outputType'," . qnum($crc32) . ')';
            if (!pdo_query($query)) {
                add_last_sql_error('BuildFailure InsertDetails', 0, $this->BuildId);
            }
            $detailsId = pdo_insert_id('buildfailuredetails');
        }

        // Insert the buildfailure.
        $query =
            'INSERT INTO buildfailure
         (buildid, detailsid, workingdirectory, sourcefile, newstatus)
       VALUES
         (' . qnum($this->BuildId) . ', ' . qnum($detailsId) . ", '$workingDirectory',
          '$sourceFile', 0)";
        if (!pdo_query($query)) {
            add_last_sql_error('BuildFailure Insert', 0, $this->BuildId);
            return false;
        }

        $id = pdo_insert_id('buildfailure');

        // Insert the arguments
        $argumentids = array();

        foreach ($this->Arguments as $argument) {
            // Limit the argument to 255
            $argumentescaped = pdo_real_escape_string(substr($argument, 0, 255));

            // Check if the argument exists
            $query = pdo_query("SELECT id FROM buildfailureargument WHERE argument='" . $argumentescaped . "'");
            if (!$query) {
                add_last_sql_error('BuildFailure Insert', 0, $this->BuildId);
                return false;
            }

            if (pdo_num_rows($query) > 0) {
                $argumentarray = pdo_fetch_array($query);
                $argumentids[] = $argumentarray['id'];
            } else {
                // insert the argument

                $query = "INSERT INTO buildfailureargument (argument) VALUES ('" . $argumentescaped . "')";
                if (!pdo_query($query)) {
                    add_last_sql_error('BuildFailure Insert', 0, $this->BuildId);
                    return false;
                }

                $argumentids[] = pdo_insert_id('buildfailureargument');
            }
        }

        // Insert the argument
        $query = 'INSERT INTO buildfailure2argument (buildfailureid,argumentid,place) VALUES ';
        $i = 0;
        foreach ($argumentids as $argumentid) {
            if ($i > 0) {
                $query .= ',';
            }
            $query .= '(' . qnum($id) . ',' . qnum($argumentid) . ',' . qnum($i) . ')';
            $i++;
        }
        if ($i > 0) {
            if (!pdo_query($query)) {
                add_last_sql_error('BuildFailure Insert', 0, $this->BuildId);
                return false;
            }
        }

        $this->InsertLabelAssociations($id);
        return true;
    }

    /**
     * Returns all failures, including warnings, for current build
     *
     * @param int $fetchStyle
     * @return array|bool
     */
    public function GetFailuresForBuild($fetchStyle = PDO::FETCH_ASSOC)
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
    public function GetBuildFailureArguments($buildFailureId)
    {
        $response = [
            'argumentfirst' => null,
            'arguments' => []
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
    public static function marshal($data, Project $project, $revision, $linkifyOutput, $buildfailure)
    {
        deepEncodeHTMLEntities($data);

        $marshaled = array_merge(array(
            'language' => $data['language'],
            'sourcefile' => $data['sourcefile'],
            'targetname' => $data['targetname'],
            'outputfile' => $data['outputfile'],
            'outputtype' => $data['outputtype'],
            'workingdirectory' => $data['workingdirectory'],
            'exitcondition' => $data['exitcondition']
        ), $buildfailure->GetBuildFailureArguments($data['id']));

        $marshaled['stderror'] = $data['stderror'];
        $marshaled['stdoutput'] = $data['stdoutput'];

        if (isset($data['sourcefile'])) {
            $file = basename($data['sourcefile']);
            $directory = dirname($data['sourcefile']);

            $source_dir = \get_source_dir($project->Id, $project->CvsUrl, $directory);
            if (substr($directory, 0, strlen($source_dir)) == $source_dir) {
                $directory = substr($directory, strlen($source_dir));
            }

            $marshaled['cvsurl'] = \get_diff_url($project->Id,
                                                $project->CvsUrl,
                                                $directory,
                                                $file,
                                                $revision);

            if ($source_dir !== null && $linkifyOutput) {
                $marshaled['stderror'] = linkify_compiler_output($project->CvsUrl, $source_dir,
                                                                 $revision, $data['stderror']);
                $marshaled['stdoutput'] = linkify_compiler_output($project->CvsUrl, $source_dir,
                                                                  $revision, $data['stdoutput']);
            }
        }

        if (isset($data['subprojectid'])) {
            $marshaled['subprojectid'] = $data['subprojectid'];
            $marshaled['subprojectname'] = $data['subprojectname'];
        }

        return $marshaled;
    }

    /**
     * Returns a self referencing URI for a the current BuildFailure.
     *
     * @return string
     */
    public function GetUrlForSelf()
    {
        $config = Config::getInstance();
        $url = $config->getBaseUrl();
        return "{$url}/viewBuildError.php?type={$this->Type}&buildid={$this->BuildId}";
    }
}
