<?php

namespace App\Http\Submission\Handlers;

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

use CDash\Model\Build;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;
use CDash\Model\CoverageSummary;
use Illuminate\Support\Facades\DB;
use League\Flysystem\UnableToReadFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class JSCoverTarHandler extends AbstractSubmissionHandler
{
    private $CoverageSummaries;

    public function __construct(Build $build)
    {
        parent::__construct($build);

        $this->CoverageSummaries = [];
        $coverageSummary = new CoverageSummary();
        $coverageSummary->BuildId = $this->Build->Id;
        $this->CoverageSummaries['default'] = $coverageSummary;

        $this->Coverages = [];
        $this->CoverageFiles = [];
        $this->CoverageFileLogs = [];
    }

    /**
     * Parse a tarball of JSON files.
     **/
    public function Parse(string $filename): bool
    {
        // Extract the tarball.
        try {
            $dirName = extract_tar($filename);
        } catch (FileNotFoundException|UnableToReadFile|RuntimeException $e) {
            report($e);
            return false;
        }

        // Recursively search for .json files and parse them.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirName),
            RecursiveIteratorIterator::CHILD_FIRST);
        $coverageSummary = $this->CoverageSummaries['default'];
        foreach ($iterator as $fileinfo) {
            // need the longest extension, so getExtension() won't do here.
            $ext = substr(strstr($fileinfo->getFilename(), '.'), 1);
            if ($ext === 'json') {
                $this->ParseJSCoverFile($fileinfo);
            }
        }

        // Record parsed coverage info to the database.
        foreach ($this->CoverageFileLogs as $path => $coverageFileLog) {
            $coverage = $this->Coverages[$path];
            $coverageFile = $this->CoverageFiles[$path];

            // Tally up how many lines of code were covered & uncovered.
            foreach ($coverageFileLog->Lines as $line) {
                if ($line == 0) {
                    $coverage->LocUntested++;
                } else {
                    $coverage->Covered = 1;
                    $coverage->LocTested++;
                }
            }

            // Save these models to the database.
            $coverageFile->TrimLastNewline();
            $coverageFile->Update($this->Build->Id);
            $coverageFileLog->BuildId = $this->Build->Id;
            $coverageFileLog->FileId = $coverageFile->Id;
            $coverageFileLog->Insert();

            // Add this Coverage to our summary.
            $coverage->CoverageFile = $coverageFile;
            $coverageSummary->AddCoverage($coverage);
        }

        // Insert coverage summaries
        $completedSummaries = [];
        foreach ($this->CoverageSummaries as $coverageSummary) {
            if (in_array($coverageSummary->BuildId, $completedSummaries)) {
                continue;
            }

            $coverageSummary->Insert();
            $coverageSummary->ComputeDifference();

            $completedSummaries[] = $coverageSummary->BuildId;
        }

        // Delete the directory when we're done.
        DeleteDirectory($dirName);
        return true;
    }

    /**
     * Parse an individual json file.
     **/
    public function ParseJSCoverFile($fileinfo)
    {
        // Parse this JSON file.
        $jsonContents = file_get_contents($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
        $jsonDecoded = json_decode($jsonContents, true);
        foreach ($jsonDecoded as $path => $coverageEntry) {
            // Make sure it has the fields we expect.
            if (is_null($coverageEntry)
                || !array_key_exists('source', $coverageEntry)
                || !array_key_exists('coverage', $coverageEntry)
            ) {
                return;
            }

            // Lookup our models & create them if they don't exist yet.
            $newFileCreated = false;
            if (!array_key_exists($path, $this->CoverageFileLogs)) {
                $newFileCreated = true;
                $coverageFileLog = new CoverageFileLog();

                $coverageFile = new CoverageFile();
                $coverageFile->FullPath = trim($path);
                // Get the ID for this coverage file, or create a new empty one
                // if it doesn't already exist.
                $coveragefile_array = DB::select('
                                          SELECT id
                                          FROM coveragefile
                                          WHERE fullpath=? AND file IS NULL
                                      ', [$path]);
                if (count($coveragefile_array) === 0) {
                    DB::insert('INSERT INTO coveragefile (fullpath) VALUES (?)', [$path]);
                    $coveragefile_array = DB::select('
                                              SELECT id
                                              FROM coveragefile
                                              WHERE fullpath=? AND file IS NULL
                                          ', [$path]);
                }
                $coverageFile->Id = $coveragefile_array[0]?->id;

                $coverage = new Coverage();
                $coverage->CoverageFile = $coverageFile;
                $coverage->BuildId = $this->Build->Id;

                $this->Coverages[$path] = $coverage;
                $this->CoverageFiles[$path] = $coverageFile;
                $this->CoverageFileLogs[$path] = $coverageFileLog;
            } else {
                $coverageFile = $this->CoverageFiles[$path];
                $coverageFileLog = $this->CoverageFileLogs[$path];
            }

            /*
            * JSON data is line based and has a coverage line for each source line.
            * Loop through the length of coverage lines.
            */
            $coverageLines = $coverageEntry['coverage'];
            $fileLength = count($coverageLines);
            for ($i = 1; $i < $fileLength - 1; $i++) {
                if ($newFileCreated) {
                    // Record this line of code if this is the first time that
                    // this file has been encountered.
                    $sourceLine = $coverageEntry['source'][$i - 1];
                    $coverageFile->File .= rtrim($sourceLine);
                    $coverageFile->File .= '<br>';
                }

                $timesHit = $coverageLines[$i];
                // non-code lines are "null" in JSON.  This decodes to empty so
                // we check for non-numeric values.
                if (!isset($timesHit)) {
                    continue;
                }

                // This is how JSCover indicates an uncovered line of code.
                if ($timesHit == '0') {
                    $timesHit = 0;
                } else {
                    // value in entry indicates total times hit,
                    // coerce the string to a number.
                    $timesHit = intval($timesHit);
                }
                $coverageFileLog->AddLine($i - 1, $timesHit);
            }
        }
    }
}
