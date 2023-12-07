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
use CDash\Config;

use PDO;
use App\Models\BuildError as EloquentBuildError;

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

    // Insert in the database (no update possible)
    public function Insert(): void
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
            $crc32 = crc32($this->Text . $this->SourceFile . $this->SourceLine); // some warnings can be on the same line
        }

        EloquentBuildError::create([
            'buildid' => (int) $this->BuildId,
            'type' => $this->Type,
            'logline' => intval($this->LogLine),
            'text' => $this->Text,
            'sourcefile' => $this->SourceFile ?? '',
            'sourceline' => (int) $this->SourceLine,
            'precontext' => $this->PreContext,
            'postcontext' => $this->PostContext,
            'repeatcount' => (int) $this->RepeatCount,
            'newstatus' => 0,
            'crc32' => $crc32,
        ]);
    }

    /**
     * Returns all errors from builderror for current build
     */
    public function GetErrorsForBuild(int $fetchStyle = PDO::FETCH_ASSOC): array|false
    {
        if (!$this->BuildId) {
            add_log('BuildId not set', 'BuildError::GetErrorsForBuild', LOG_WARNING);
            return false;
        }

        $result = EloquentBuildError::where('buildid', $this->BuildId)
            ->orderBy('logline')
            ->get();

        return $fetchStyle === PDO::FETCH_ASSOC ? $result->toArray() : $result->all();
    }

    /**
     * @return array{
     *     'file': string,
     *     'directory': string,
     * }
     */
    private static function GetSourceFile($data): array
    {
        $sourceFile = [];

        // Detect if the source directory has already been replaced by CTest
        // with /.../.  If so, sourcefile is already a relative path from the
        // root of the source tree.
        if (str_contains($data['text'], '/.../')) {
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
     *
     * @return array<string,mixed>
     **/
    public static function marshal($data, Project $project, $revision): array
    {
        deepEncodeHTMLEntities($data);

        // Sets up access to $file and $directory
        extract(self::GetSourceFile($data));
        $marshaled = [
            'new' => (int) ($data['newstatus'] ?? -1),
            'logline' => (int) $data['logline'],
            'cvsurl' => RepositoryUtils::get_diff_url($project->Id, $project->CvsUrl, $directory, $file, $revision),
        ];

        // When building without launchers, CTest truncates the source dir to
        // /.../<project-name>/.  Use this pattern to linkify compiler output.
        $source_dir = "/\.\.\./[^/]+";
        $marshaled = array_merge($marshaled, [
            'precontext' => RepositoryUtils::linkify_compiler_output($project->CvsUrl, $source_dir, $revision, $data['precontext']),
            'text' => RepositoryUtils::linkify_compiler_output($project->CvsUrl, $source_dir, $revision, $data['text']),
            'postcontext' => RepositoryUtils::linkify_compiler_output($project->CvsUrl, $source_dir, $revision, $data['postcontext']),
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
     */
    public function GetUrlForSelf(): string
    {
        $config = Config::getInstance();
        $url = $config->getBaseUrl();
        return "{$url}/viewBuildError.php?type={$this->Type}&buildid={$this->BuildId}";
    }
}
