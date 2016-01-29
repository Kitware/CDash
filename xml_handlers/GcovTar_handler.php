<?php

require_once('models/coverage.php');
require_once('config/config.php');
require_once('models/label.php');

class GCovTarHandler
{
    private $BuildId;
    private $CoverageSummary;
    private $SourceDirectory;
    private $BinaryDirectory;
    private $Labels;

    public function __construct($buildid)
    {
        $this->BuildId = $buildid;
        $this->CoverageSummary = new CoverageSummary();
        $this->CoverageSummary->BuildId = $this->BuildId;
        $this->SourceDirectory = '';
        $this->BinaryDirectory = '';
        $this->Labels = array();
    }

  /**
   * Parse a tarball of .gcov files.
   **/
  public function Parse($handle)
  {
      global $CDASH_BACKUP_DIRECTORY;

    // This function receives an open file handle, but we really just need
    // the path to this file so that we can extract it.
    $meta_data = stream_get_meta_data($handle);
      $filename = $meta_data["uri"];
      fclose($handle);

    // Create a new directory where we can extract our tarball.
    $pathParts = pathinfo($filename);
      $dirName = $CDASH_BACKUP_DIRECTORY . "/" . $pathParts['filename'];
      mkdir($dirName);

    // Extract the tarball.
    $phar = new PharData($filename);
      $phar->extractTo($dirName);

    // Find the data.json file and extract the source directory from it.
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dirName),
      RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($iterator as $fileinfo) {
          if ($fileinfo->getFilename() == "data.json") {
              $jsonContents = file_get_contents($fileinfo->getRealPath());
              $jsonDecoded = json_decode($jsonContents, true);
              if (is_null($jsonDecoded) || !array_key_exists("Source", $jsonDecoded)
          || !array_key_exists("Binary", $jsonDecoded)) {
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
          if ($fileinfo->getFilename() == "Labels.json") {
              $this->ParseLabelsFile($fileinfo);
          }
      }

    // Recursively search for .gcov files and parse them.
    $iterator->rewind();
      foreach ($iterator as $fileinfo) {
          if (pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION) == "gcov") {
              $this->ParseGcovFile($fileinfo);
          }
      }

    // Insert coverage summary (removing any old results first)
    //$this->CoverageSummary->RemoveAll();
    $this->CoverageSummary->Insert();
      $this->CoverageSummary->ComputeDifference();

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
      $coverageFile = new CoverageFile();
      $coverage = new Coverage();
      $coverage->CoverageFile = $coverageFile;

    // Begin parsing this file.
    // The first thing we look for is the full path to this source file.
    $file = new SplFileObject($fileinfo);
      $path = '';
      while (!$file->eof()) {
          $gcovLine = $file->current();
          $term = ":Source:";
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

    // If this source file isn't from the source or binary directory
    // we shouldn't include it in our coverage report.
    if (strpos($path, $this->SourceDirectory) !== false) {
        $path = str_replace($this->SourceDirectory, ".", trim($path));
    } elseif (strpos($path, $this->BinaryDirectory) !== false) {
        $path = str_replace($this->BinaryDirectory, ".", trim($path));
    } else {
        return;
    }

      $coverageFile->FullPath = $path;
      $lineNumber = 0;

    // The lack of rewind is intentional.
    while (!$file->eof()) {
        $gcovLine = $file->current();

      // "Ordinary" entries in a .gcov file take the following format:
      // <lineNumber>: <timesHit>: <source code at that line>
      // So we check if this line matches the format & parse the
      // data out of it if so.
      $fields = explode(":", $gcovLine, 3);
        if (count($fields) > 2) {
            // Separate out delimited values from this line.
        $timesHit = trim($fields[0]);
            $lineNumber = trim($fields[1]);

            $sourceLine = trim($fields[2]);

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
            $coverage->LocUntested += 1;
            $this->CoverageSummary->LocUntested += 1;
        } else {
            $coverage->Covered = 1;
            $coverage->LocTested += 1;
            $this->CoverageSummary->LocTested += 1;
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
          if (substr($gcovLine, 0, 6) === "branch") {
              // Figure out whether this branch was covered or not.
            if (strpos($gcovLine, "taken 0%") !== false) {
                $uncoveredBranches += 1;
            } else {
                $coveredBranches += 1;
            }

            // Also keep track of the different types of branches encountered.
            if (strpos($gcovLine, "(throw)") !== false) {
                $throwBranches += 1;
            } elseif (strpos($gcovLine, "(fallthrough)") !== false) {
                $fallthroughBranches += 1;
            }
          }


              $file->next();
              $gcovLine = $file->current();
              $fields = explode(":", $gcovLine);
          }

        // Don't report branch coverage for this line if we only
        // encountered (throw) and (fallthrough) branches here.
        $totalBranches = $coveredBranches + $uncoveredBranches;
          if ($totalBranches > 0 &&
            $totalBranches > ($throwBranches + $fallthroughBranches)) {
              $coverageFileLog->AddBranch($lineNumber - 1, $coveredBranches,
                                      $totalBranches);
          }
      }
    }

    // Save these models to the database.
    $coverageFile->Update($this->BuildId);
      $coverageFileLog->BuildId = $this->BuildId;
      $coverageFileLog->FileId = $coverageFile->Id;
      $coverageFileLog->Insert();

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

  /**
    * Parse the Labels.json file.
   **/
  public function ParseLabelsFile($fileinfo)
  {
      // read the file & decode the JSON.
    $jsonContents = file_get_contents($fileinfo->getRealPath());
      $jsonDecoded = json_decode($jsonContents, true);
      if (is_null($jsonDecoded) || !array_key_exists("sources", $jsonDecoded)) {
          return;
      }

    // Parse out any target-wide labels first.  These apply to
    // every source file found below.
    $target_labels = array();
      if (array_key_exists("target", $jsonDecoded)) {
          $target = $jsonDecoded["target"];
          if (array_key_exists("labels", $target)) {
              $target_labels = $target["labels"];
          }
      }

      $sources = $jsonDecoded["sources"];
      foreach ($sources as $source) {
          if (!array_key_exists("file", $source)) {
              continue;
          }

          $path = $source["file"];
          if (strpos($path, $this->SourceDirectory) !== false) {
              $path = str_replace($this->SourceDirectory, ".", trim($path));
          } elseif (strpos($path, $this->BinaryDirectory) !== false) {
              $path = str_replace($this->BinaryDirectory, ".", trim($path));
          } else {
              continue;
          }

          $source_labels = $target_labels;

          if (array_key_exists("labels", $source)) {
              $source_labels = array_merge($source_labels, $source["labels"]);
          }

          $this->Labels[$path] = $source_labels;
      }
  }
} // end class;
