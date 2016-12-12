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

require_once 'models/coverage.php';
require_once 'config/config.php';
require_once 'models/build.php';

class JSCoverTarHandler
{
    private $Build;
    private $CoverageSummaries;

    public function __construct($buildid)
    {
        $this->Build = new Build();
        $this->Build->Id = $buildid;
        $this->Build->FillFromId($this->Build->Id);

        $this->CoverageSummaries = array();
        $coverageSummary = new CoverageSummary();
        $coverageSummary->BuildId = $this->Build->Id;
        $this->CoverageSummaries['default'] = $coverageSummary;

        $this->Coverages = array();
        $this->CoverageFiles = array();
        $this->CoverageFileLogs = array();
    }

    /**
     * Parse a tarball of JSON files.
     **/
    public function Parse($filename)
    {
        global $CDASH_BACKUP_DIRECTORY;

        // Create a new directory where we can extract our tarball.
        $dirName = $CDASH_BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);
        mkdir($dirName);

        // Extract the tarball.
        $result = extract_tar($filename, $dirName);
        if ($result === false) {
            add_log('Could not extract ' . $filename . ' into ' . $dirName, 'JSCoverTarHandler::Parse', LOG_ERR);
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
                    $coverage->LocUntested += 1;
                } else {
                    $coverage->Covered = 1;
                    $coverage->LocTested += 1;
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
        $completedSummaries = array();
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
            if (is_null($coverageEntry) ||
                !array_key_exists('source', $coverageEntry) ||
                !array_key_exists('coverage', $coverageEntry)
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
                //if it doesn't already exist.
                $sql = pdo_query(
                    "SELECT id FROM coveragefile
           WHERE fullpath='$path' AND file IS NULL");
                if (pdo_num_rows($sql) == 0) {
                    pdo_query("INSERT INTO coveragefile (fullpath) VALUES ('$path')");
                    $fileid = pdo_insert_id('coveragefile');
                } else {
                    $coveragefile_array = pdo_fetch_array($sql);
                    $fileid = $coveragefile_array['id'];
                }
                $coverageFile->Id = $fileid;

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
