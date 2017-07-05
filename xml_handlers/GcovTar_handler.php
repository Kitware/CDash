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

require_once 'models/build.php';
require_once 'models/coverage.php';
require_once 'config/config.php';
require_once 'models/label.php';

class GCovTarHandler
{
    private $Build;
    private $ProjectId;
    private $CoverageSummary;
    private $SourceDirectory;
    private $BinaryDirectory;
    private $Labels;
    private $SubProjectPath;
    private $SubProjectSummaries;
    private $AggregateBuildId;
    private $PreviousAggregateParentId;

    public function __construct($buildid)
    {
        $this->Build = new Build();
        $this->Build->Id = $buildid;
        $this->Build->FillFromId($this->Build->Id);
        $this->ProjectId = $this->Build->ProjectId;

        $this->CoverageSummary = new CoverageSummary();
        $this->CoverageSummary->BuildId = $this->Build->Id;
        $this->SourceDirectory = '';
        $this->BinaryDirectory = '';
        $this->Labels = array();

        $this->SubProjectPath = '';

        // Check if we should support cross-SubProject coverage.
        if ($this->Build->SubProjectId > 0) {
            $subproject = new SubProject();
            $subproject->SetId($this->Build->SubProjectId);
            $path = $subproject->GetPath();
            if ($path != '') {
                $this->SubProjectPath = $path;
            }
        }
        $this->SubProjectSummaries = array();
        $this->PreviousAggregateParentId = null;
    }

    /**
     * Parse a tarball of .gcov files.
     **/
    public function Parse($filename)
    {
        // Create a new directory where we can extract our tarball.
        global $CDASH_BACKUP_DIRECTORY;
        $dirName = $CDASH_BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);
        mkdir($dirName);

        // Extract the tarball.
        $result = extract_tar($filename, $dirName);
        if ($result === false) {
            add_log('Could not extract ' . $filename . ' into ' . $dirName, 'GCovTarHandler::Parse', LOG_ERR);
            return false;
        }

        // Find the data.json file and extract the source directory from it.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirName),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->getFilename() == 'data.json') {
                $jsonContents = file_get_contents($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
                $jsonDecoded = json_decode($jsonContents, true);
                if (is_null($jsonDecoded) || !array_key_exists('Source', $jsonDecoded)
                    || !array_key_exists('Binary', $jsonDecoded)
                ) {
                    DeleteDirectory($dirName);
                    return false;
                }
                $this->SourceDirectory = $jsonDecoded['Source'];
                $this->BinaryDirectory = $jsonDecoded['Binary'];
                break;
            }
        }

        if (empty($this->SourceDirectory) || empty($this->BinaryDirectory)) {
            DeleteDirectory($dirName);
            return false;
        }

        // Check if any Labels.json files were included
        $iterator->rewind();
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->getFilename() == 'Labels.json') {
                $this->ParseLabelsFile($fileinfo);
            }
        }

        // Lookup some data used during coverage aggregation.
        $this->Build->ComputeTestingDayBounds();
        if ($this->Build->Type === 'Nightly') {
            $aggregateBuild = get_aggregate_build($this->Build);
            $aggregateParentId = $aggregateBuild->GetParentId();
            if ($aggregateParentId > 0) {
                $this->AggregateBuildId = $aggregateParentId;
                $aggregateParent = new Build();
                $aggregateParent->Id = $aggregateParentId;
                $this->PreviousAggregateParentId = $aggregateParent->GetPreviousBuildId();
            } else {
                $this->AggregateBuildId = $aggregateBuild->Id;
            }
        }

        // Recursively search for .gcov files and parse them.
        $iterator->rewind();
        foreach ($iterator as $fileinfo) {
            if (pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION) == 'gcov') {
                $this->ParseGcovFile($fileinfo);
            }
        }

        // Search for uncovered files.
        if (file_exists("$dirName/uncovered")) {
            $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator("$dirName/uncovered"),
                    RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    // Get the path of this uncovered file relative to its
                    // source directory.
                    $relativePath =
                        str_replace("$dirName/uncovered/", "./", $fileinfo);
                    $this->ParseUncoveredSourceFile($fileinfo, $relativePath);
                }
            }
        }

        // Insert coverage summary
        $this->CoverageSummary->Insert(true);
        $this->CoverageSummary->ComputeDifference();
        foreach ($this->SubProjectSummaries as $buildid => $subprojectSummary) {
            $subprojectSummary->Insert(true);
            $subprojectSummary->ComputeDifference();
        }

        // Delete the directory when we're done.
        DeleteDirectory($dirName);
        return true;
    }

    /**
     * Parse an individual .gcov file.
     **/
    public function ParseGcovFile($fileinfo)
    {
        $coverageFileLog = new CoverageFileLog();
        $coverageFileLog->AggregateBuildId = $this->AggregateBuildId;
        $coverageFileLog->PreviousAggregateParentId =
            $this->PreviousAggregateParentId;
        $coverageFile = new CoverageFile();
        $coverage = new Coverage();
        $coverage->CoverageFile = $coverageFile;

        // Begin parsing this file.
        // The first thing we look for is the full path to this source file.
        $file = new SplFileObject($fileinfo);
        $path = '';
        while (!$file->eof()) {
            $gcovLine = $file->current();
            $term = ':Source:';
            $pos = strpos($gcovLine, $term);
            if ($pos !== false) {
                $path = substr($gcovLine, $pos + strlen($term));
                break;
            }
            $file->next();
        }
        if (empty($path)) {
            return;
        }

        // Check if this file belongs to a different SubProject.
        $buildid = $this->Build->Id;
        if (!empty($this->SubProjectPath) &&
            strpos($path, $this->SubProjectPath) === false
        ) {
            // Find the SubProject that corresponds to this path.
            $subproject = SubProject::GetSubProjectFromPath($path, $this->ProjectId);
            if (is_null($subproject)) {
                // Error already logged.
                return;
            }
            $subprojectid = $subproject->GetId();
            $subprojectpath = $subproject->GetPath();

            // Find the sibling build that performed this SubProject.
            $siblingBuild = new Build();
            $query =
                'SELECT b.id FROM build AS b
                INNER JOIN subproject2build AS sp2b ON (sp2b.buildid=b.id)
                WHERE b.parentid=
                (SELECT parentid FROM build WHERE id=' . $this->Build->Id . ")
                AND sp2b.subprojectid=$subprojectid";
            $row = pdo_single_row_query($query);
            if ($row && array_key_exists('id', $row)) {
                $buildid = $row['id'];
                $siblingBuild->Id = $buildid;
                $siblingBuild->FillFromId($buildid);
            } else {
                // Build doesn't exist yet, add it here.
                $siblingBuild->Name = $this->Build->Name;
                $siblingBuild->ProjectId = $this->ProjectId;
                $siblingBuild->SiteId = $this->Build->SiteId;
                $siblingBuild->SetParentId($this->Build->GetParentId());
                $siblingBuild->SetStamp($this->Build->GetStamp());
                $siblingBuild->SetSubProject($subproject->GetName());
                $siblingBuild->StartTime = $this->Build->StartTime;
                $siblingBuild->EndTime = $this->Build->EndTime;
                $siblingBuild->SubmitTime = gmdate(FMT_DATETIME);
                add_build($siblingBuild, 0);
                $buildid = $siblingBuild->Id;
            }
            $coverageFileLog->Build = $siblingBuild;
            // Remove any part of the file path that comes before
            // the subproject path.
            $path = substr($path, strpos($path, $subprojectpath));

            // Replace the subproject path with '.'
            $path = substr_replace($path, '.', 0, strlen($subprojectpath));
        } else {
            // If this source file isn't from the source or binary directory
            // we shouldn't include it in our coverage report.
            if (!empty($this->SubProjectPath) &&
                    strpos($path, $this->SubProjectPath) !== false) {
                $path = substr($path, strpos($path, $this->SubProjectPath));
                $path =
                    substr_replace($path, '.', 0, strlen($this->SubProjectPath));
            } elseif (strpos($path, $this->SourceDirectory) !== false) {
                $path = str_replace($this->SourceDirectory, '.', trim($path));
            } elseif (strpos($path, $this->BinaryDirectory) !== false) {
                $path = str_replace($this->BinaryDirectory, '.', trim($path));
            } else {
                return;
            }
            $coverageFileLog->Build = $this->Build;
        }

        // Get a reference to the coverage summary for this build.
        if ($buildid === $this->Build->Id) {
            $coverageSummary = $this->CoverageSummary;
        } else {
            if (!array_key_exists($buildid, $this->SubProjectSummaries)) {
                $coverageSummary = new CoverageSummary();
                $coverageSummary->BuildId = $buildid;
                $this->SubProjectSummaries[$buildid] = $coverageSummary;
            } else {
                $coverageSummary = $this->SubProjectSummaries[$buildid];
            }
        }

        // Use a regexp to resolve any /../ in this path.
        // We can't use realpath() because we're referencing a path that
        // doesn't exist on the server.
        // For a source file that contains:
        //   #include "src/../include/foo.h"
        // CDash will report the covered file as include/foo.h
        $pattern = "#/[^/]*?/\.\./#";
        while (strpos($path, "/../") !== false) {
            $path = preg_replace($pattern, "/", $path, 1);
        }

        $coverageFile->FullPath = trim($path);
        $lineNumber = 0;

        // The lack of rewind is intentional.
        while (!$file->eof()) {
            $gcovLine = $file->current();

            // "Ordinary" entries in a .gcov file take the following format:
            // <lineNumber>: <timesHit>: <source code at that line>
            // So we check if this line matches the format & parse the
            // data out of it if so.
            $fields = explode(':', $gcovLine, 3);
            if (count($fields) > 2) {
                // Separate out delimited values from this line.
                $timesHit = trim($fields[0]);
                $lineNumber = trim($fields[1]);
                $sourceLine = rtrim($fields[2]);

                if ($lineNumber > 0) {
                    $coverageFile->File .= $sourceLine;
                    // cannot be <br/> for backward compatibility.
                    $coverageFile->File .= '<br>';
                }

                // This is how gcov indicates a line of unexecutable code.
                if ($timesHit === '-') {
                    $file->next();
                    continue;
                }

                // This is how gcov indicates an uncovered line.
                if ($timesHit === '#####') {
                    $timesHit = 0;
                }

                $coverageFileLog->AddLine($lineNumber - 1, $timesHit);
                $file->next();
            }

            // Otherwise we read through a block of these lines that doesn't
            // follow this format.  Such lines typically contain branch or
            // function level coverage data.
            else {
                $coveredBranches = 0;
                $uncoveredBranches = 0;
                $throwBranches = 0;
                $fallthroughBranches = 0;
                while (count($fields) < 3 && !$file->eof()) {
                    // Parse branch coverage here.
                    if (substr($gcovLine, 0, 6) === 'branch') {
                        // Figure out whether this branch was covered or not.
                        if (strpos($gcovLine, 'taken 0%') !== false) {
                            $uncoveredBranches += 1;
                        } else {
                            $coveredBranches += 1;
                        }

                        // Also keep track of the different types of branches encountered.
                        if (strpos($gcovLine, '(throw)') !== false) {
                            $throwBranches += 1;
                        } elseif (strpos($gcovLine, '(fallthrough)') !== false) {
                            $fallthroughBranches += 1;
                        }
                    }

                    $file->next();
                    $gcovLine = $file->current();
                    $fields = explode(':', $gcovLine);
                }

                // Don't report branch coverage for this line if we only
                // encountered (throw) and (fallthrough) branches here.
                $totalBranches = $coveredBranches + $uncoveredBranches;
                if ($totalBranches > 0 &&
                    $totalBranches > ($throwBranches + $fallthroughBranches)
                ) {
                    $coverageFileLog->AddBranch($lineNumber - 1, $coveredBranches,
                        $totalBranches);
                }
            }
        }

        // Save these models to the database.
        $coverageFile->TrimLastNewline();
        $coverageFile->Update($buildid);
        $coverageFileLog->BuildId = $buildid;
        $coverageFileLog->FileId = $coverageFile->Id;
        $coverageFileLog->Insert(true);

        // Query the filelog to get how many lines & branches were covered.
        // We do this after inserting the filelog because we want to accurately
        // reflect the union of the current and previously existing results
        // (if any).
        $stats = $coverageFileLog->GetStats();
        $coverage->LocUntested = $stats['locuntested'];
        $coverage->LocTested = $stats['loctested'];
        $coverage->Covered = 1;
        $coverage->BranchesUntested = $stats['branchesuntested'];
        $coverage->BranchesTested = $stats['branchestested'];

        // Add any labels.
        if (array_key_exists($path, $this->Labels)) {
            foreach ($this->Labels[$path] as $labelText) {
                $label = new Label();
                $label->SetText($labelText);
                $coverage->AddLabel($label);
            }
        }

        // Add this Coverage to our summary.
        $coverageSummary->AddCoverage($coverage);
    }

    /**
     * Parse the Labels.json file.
     **/
    public function ParseLabelsFile($fileinfo)
    {
        // read the file & decode the JSON.
        $jsonContents = file_get_contents($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
        $jsonDecoded = json_decode($jsonContents, true);
        if (is_null($jsonDecoded) || !array_key_exists('sources', $jsonDecoded)) {
            return;
        }

        // Parse out any target-wide labels first.  These apply to
        // every source file found below.
        $target_labels = array();
        if (array_key_exists('target', $jsonDecoded)) {
            $target = $jsonDecoded['target'];
            if (array_key_exists('labels', $target)) {
                $target_labels = $target['labels'];
            }
        }

        $sources = $jsonDecoded['sources'];
        foreach ($sources as $source) {
            if (!array_key_exists('file', $source)) {
                continue;
            }

            $path = $source['file'];
            if (strpos($path, $this->SourceDirectory) !== false) {
                $path = str_replace($this->SourceDirectory, '.', trim($path));
            } elseif (strpos($path, $this->BinaryDirectory) !== false) {
                $path = str_replace($this->BinaryDirectory, '.', trim($path));
            } else {
                continue;
            }

            $source_labels = $target_labels;

            if (array_key_exists('labels', $source)) {
                $source_labels = array_merge($source_labels, $source['labels']);
            }

            $this->Labels[$path] = $source_labels;
        }
    }

    /**
     * Parse an individual uncovered source file.
     **/
    public function ParseUncoveredSourceFile($fileinfo, $path)
    {
        $coverageFileLog = new CoverageFileLog();
        $coverageFileLog->AggregateBuildId = $this->AggregateBuildId;
        $coverageFileLog->PreviousAggregateParentId =
            $this->PreviousAggregateParentId;
        $coverageFile = new CoverageFile();
        $coverageFile->FullPath = trim($path);
        $coverage = new Coverage();
        $coverage->CoverageFile = $coverageFile;

        // SplFileObject was giving me an erroneous extra line at the
        // end of the file, so instead we use good old file().
        $lines = file($fileinfo);
        $lineNumber = 0;
        foreach ($lines as $line) {
            $sourceLine = rtrim($line);
            $coverageFile->File .= $sourceLine;
            $coverageFile->File .= '<br>';
            $coverageFileLog->AddLine($lineNumber, 0);
            $lineNumber++;
        }

        // Save this source file to the database.
        $coverageFile->TrimLastNewline();
        $coverageFile->Update($this->Build->Id);

        // Check if this build already has coverage for this file.
        // If so, return early so we don't overwrite it with a blank entry.
        $coverage->BuildId = $this->Build->Id;
        if ($coverage->Exists()) {
            return false;
        }

        // Otherwise save the line-by-line coverage to the database.
        $coverageFileLog->BuildId = $this->Build->Id;
        $coverageFileLog->FileId = $coverageFile->Id;
        $coverageFileLog->Insert(true);

        $coverage->LocUntested = $lineNumber;
        $coverage->LocTested = 0;
        $coverage->Covered = 1;

        // Add any labels.
        if (array_key_exists($path, $this->Labels)) {
            foreach ($this->Labels[$path] as $labelText) {
                $label = new Label();
                $label->SetText($labelText);
                $coverage->AddLabel($label);
            }
        }

        // Add this Coverage to our summary.
        $this->CoverageSummary->AddCoverage($coverage);
    }
}
