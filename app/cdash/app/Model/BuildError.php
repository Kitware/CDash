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

require_once 'include/repository.php';

use PDO;
use CDash\Database;

/** BuildError */
class BuildError
{
    public $Type;
    public $LogLine;
    public $Text;
    public $SourceFile;
    public $SourceLine;
    public $PreContext;
    public $PostContext;
    public $RepeatCount;
    public $BuildId;

    private $PDO;

    public function __construct()
    {
        $this->PDO = Database::getInstance();
    }

    // Insert in the database (no update possible)
    public function Insert(): bool
    {
        if (!$this->BuildId) {
            abort(500, 'BuildError::Insert(): BuildId not set.');
        }

        if (empty($this->SourceLine)) {
            $this->SourceLine = 0;
        }
        if (empty($this->RepeatCount)) {
            $this->RepeatCount = 0;
        }

        // Compute the crc32
        if ($this->SourceLine === 0) {
            $crc32 = crc32($this->Text); // no need for precontext or postcontext, this doesn't work for parallel build
        } else {
            $crc32 = crc32($this->Text . $this->SourceFile . $this->SourceLine); // some warning can be on the same line
        }

        $query = $this->PDO->executePrepared('
                     INSERT INTO builderror (
                         buildid,
                         type,
                         logline,
                         text,
                         sourcefile,
                         sourceline,
                         precontext,
                         postcontext,
                         repeatcount,
                         newstatus,
                         crc32
                     )
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
                 ', [
                     intval($this->BuildId),
                     $this->Type,
                     $this->LogLine,
                     $this->Text,
                     $this->SourceFile ?? '',
                     intval($this->SourceLine),
                     $this->PreContext,
                     $this->PostContext,
                     intval($this->RepeatCount),
                     $crc32,
                 ]);
        if ($query === false) {
            add_last_sql_error('BuildError Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }

    /**
     * Returns all errors from builderror for current build
     */
    public function GetErrorsForBuild(int $fetchStyle = PDO::FETCH_ASSOC): array|bool
    {
        if (!$this->BuildId) {
            add_log('BuildId not set', 'BuildError::GetErrorsForBuild', LOG_WARNING);
            return false;
        }

        $sql = "SELECT * FROM builderror WHERE buildid=? ORDER BY logline ASC";

        $query = $this->PDO->prepare($sql);
        pdo_execute($query, [$this->BuildId]);

        return $query->fetchAll($fetchStyle);
    }

    public function GetSourceFile($data)
    {
        $sourceFile = [];

        // Detect if the source directory has already been replaced by CTest
        // with /.../.  If so, sourcefile is already a relative path from the
        // root of the source tree.
        if (strpos($data['text'], '/.../') !== false) {
            $parts = explode('/', $data['sourcefile']);
            $sourceFile['file'] = array_pop($parts);
            $sourceFile['directory'] = implode('/', $parts);
        } else {
            $sourceFile['file'] = basename($data['sourcefile']);
            $sourceFile['directory'] = dirname($data['sourcefile']);
        }

        return $sourceFile;
    }

    // Ideally $data would be loaded into $this
    // need an id field?
    /**
     * Marshals the data of a particular build error into a serializable
     * friendly format.
     *
     * Requires the $data of a build error, the $project, and the buildupdate.revision.
     **/
    public static function marshal($data, Project $project, $revision, BuildError $builderror)
    {
        deepEncodeHTMLEntities($data);

        // Sets up access to $file and $directory
        extract($builderror->GetSourceFile($data));
        $marshaled = [
            'new' => (isset($data['newstatus'])) ? $data['newstatus'] : -1,
            'logline' => $data['logline'],
            'cvsurl' => get_diff_url($project->Id, $project->CvsUrl, $directory, $file, $revision),
        ];

        // When building without launchers, CTest truncates the source dir to
        // /.../<project-name>/.  Use this pattern to linkify compiler output.
        $source_dir = "/\.\.\./[^/]+";
        $marshaled = array_merge($marshaled, [
            'precontext' => linkify_compiler_output($project->CvsUrl, $source_dir, $revision, $data['precontext']),
            'text' => linkify_compiler_output($project->CvsUrl, $source_dir, $revision, $data['text']),
            'postcontext' => linkify_compiler_output($project->CvsUrl, $source_dir, $revision, $data['postcontext']),
            'sourcefile' => $data['sourcefile'],
            'sourceline' => $data['sourceline']]);

        if (isset($data['subprojectid'])) {
            $marshaled['subprojectid'] = $data['subprojectid'];
            $marshaled['subprojectname'] = $data['subprojectname'];
        }

        return $marshaled;
    }

    /**
     * Returns a self referencing URI for the current BuildError.
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
