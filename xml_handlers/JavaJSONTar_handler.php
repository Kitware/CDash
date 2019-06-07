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
require_once 'xml_handlers/NonSaxHandler.php';

use CDash\Config;
use CDash\Model\Build;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;
use CDash\Model\CoverageSummary;

class JavaJSONTarHandler extends NonSaxHandler
{
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
    }

    /**
     * Parse a tarball of JSON files.
     **/
    public function Parse($filename)
    {
        $config = Config::getInstance();
        // Create a new directory where we can extract our tarball.
        $dirName = $config->get('CDASH_BACKUP_DIRECTORY') . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);
        mkdir($dirName);

        // Extract the tarball.
        $result = extract_tar($filename, $dirName);
        if ($result === false) {
            add_log('Could not extract ' . $filename . ' into ' . $dirName, 'JavaJSONTarHandler::Parse', LOG_ERR);
            return false;
        }

        // Check if this submission included a  package_map.json file.
        // This tells us how Java packages correspond to CDash subprojects.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirName),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->getFilename() == 'package_map.json') {
                $this->ParsePackageMap($fileinfo);
            }
        }

        // Recursively search for .java.json files and parse them.
        $iterator->rewind();
        foreach ($iterator as $fileinfo) {
            // need the longest extension, so getExtension() won't do here.
            $ext = substr(strstr($fileinfo->getFilename(), '.'), 1);
            if ($ext === 'java.json') {
                $this->ParseJavaJSONFile($fileinfo);
            }
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
     * Parse an individual .java.json file.
     **/
    public function ParsePackageMap($fileinfo)
    {
        $jsonContents = file_get_contents($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
        $jsonDecoded = json_decode($jsonContents, true);
        if (is_null($jsonDecoded)) {
            return;
        }

        foreach ($jsonDecoded as $row) {
            if (!array_key_exists('package', $row) ||
                !array_key_exists('subproject', $row)
            ) {
                return;
            }
            $packageName = $row['package'];
            $subprojectName = $row['subproject'];

            // get the buildid that corresponds to this subproject.
            $query =
                "SELECT buildid FROM subproject2build AS sp2b
         INNER JOIN subproject AS sp ON (sp.id = sp2b.subprojectid)
         INNER JOIN build AS b ON (b.id = sp2b.buildid)
         WHERE sp.name = '$subprojectName'
         AND b.parentid=" . qnum($this->Build->GetParentId());
            $buildid_result = pdo_single_row_query($query);

            // If we found a different buildid, create a new CoverageSummary.
            if ($buildid_result && array_key_exists('buildid', $buildid_result) &&
                $buildid_result['buildid'] != $this->Build->Id
            ) {
                $coverageSummary = new CoverageSummary();
                $coverageSummary->BuildId = $buildid_result['buildid'];
                $this->CoverageSummaries[$packageName] = $coverageSummary;
            } else {
                // Otherwise, just associate this package with our default.
                $this->CoverageSummaries[$packageName] =
          &$this->CoverageSummaries['default'];
            }
        }
    }

    /**
     * Parse an individual .java.json file.
     **/
    public function ParseJavaJSONFile($fileinfo)
    {
        $coverageFileLog = new CoverageFileLog();
        $coverageFile = new CoverageFile();
        $coverage = new Coverage();
        $coverage->CoverageFile = $coverageFile;

        // Parse this JSON file.
        $jsonContents = file_get_contents($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
        $jsonDecoded = json_decode($jsonContents, true);

        // Make sure it has the fields we expect.
        if (is_null($jsonDecoded) ||
            !array_key_exists('file', $jsonDecoded) ||
            !array_key_exists('package', $jsonDecoded) ||
            !array_key_exists('lines', $jsonDecoded)
        ) {
            return;
        }

        $path = $jsonDecoded['file'];
        $coverageFile->FullPath = trim($path);

        $packageName = str_replace('/', '.', $jsonDecoded['package']);
        if (array_key_exists($packageName, $this->CoverageSummaries)) {
            $coverageSummary = $this->CoverageSummaries[$packageName];
        } else {
            $coverageSummary = $this->CoverageSummaries['default'];
        }
        $buildid = $coverageSummary->BuildId;

        $coverageLines = $jsonDecoded['lines'];
        $lineNumber = 0;

        foreach ($coverageLines as $coverageLine) {
            $sourceLine = $coverageLine['source'];
            $coverageFile->File .= rtrim($sourceLine);
            $coverageFile->File .= '<br>';

            $timesHit = $coverageLine['covered'];

            if ($timesHit == '-1') {
                // Uncoverable code
                $lineNumber += 1;
                continue;
            }

            // This is how gcov indicates an uncovered line.
            if ($timesHit === '0') {
                $timesHit = 0;
                $coverage->LocUntested += 1;
            } else {
                $timesHit = 1;
                $coverage->Covered = 1;
                $coverage->LocTested += 1;
            }

            $coverageFileLog->AddLine($lineNumber, $timesHit);
            $lineNumber += 1;
        }

        // Save these models to the database.
        $coverageFile->TrimLastNewline();
        $coverageFile->Update($buildid);
        $coverageFileLog->BuildId = $buildid;
        $coverageFileLog->FileId = $coverageFile->Id;
        $coverageFileLog->Insert();

        // Add this Coverage to our summary.
        $coverageSummary->AddCoverage($coverage);
    }
}
