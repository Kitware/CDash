<?php

require_once('models/coverage.php');
require_once('cdash/config.php');
require_once('models/build.php');

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
    }

  /**
   * Parse a tarball of JSON files.
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

    // Recursively search for .json files and parse them.
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dirName),
      RecursiveIteratorIterator::CHILD_FIRST);
    $coverageEntries = array();
    $coverageSummary = $this->CoverageSummaries['default'];
    foreach ($iterator as $fileinfo)
      {
      // need the longest extension, so getExtension() won't do here.
      $ext = substr(strstr($fileinfo->getFilename(),'.'), 1);
      if ($ext === "json")
        {
        $this->ParseJSCoverFile($fileinfo,$coverageEntries,$coverageSummary);
        }
      }

    foreach($coverageEntries as $coverageName => $coverageData)
      {
      $coverageSummary->AddCoverage($coverageData);
      }
    // Insert coverage summaries
    $completedSummaries = array();
    foreach($this->CoverageSummaries as $coverageSummary)
      {
      if (in_array($coverageSummary->BuildId, $completedSummaries))
        {
        continue;
        }

      $coverageSummary->Insert();
      $coverageSummary->ComputeDifference();

      $completedSummaries[] = $coverageSummary->BuildId;
      }

    // Delete the directory when we're done.
    $this->DeleteDirectory($dirName);
    return true;
    }

  /**
    * Parse an individual json file.
   **/
  function ParseJSCoverFile($fileinfo,&$coverageEntries,&$coverageSummary)
    {
    // Parse this JSON file.
    $jsonContents = file_get_contents($fileinfo->getRealPath());
    $jsonDecoded = json_decode($jsonContents, true);
    foreach($jsonDecoded as $coverageName => $coverageEntry)
      {
      $coverageFileLog = new CoverageFileLog();
      // Make sure it has the fields we expect.
      if (is_null($coverageEntry) ||
          !array_key_exists("source", $coverageEntry) ||
          !array_key_exists("coverage", $coverageEntry)
          // || !array_key_exists("branchData", $coverageEntry)  //branchData can be found, not in test
          )
        {
        return;
        }
      $path = $coverageName;
      $coverageFile->FullPath = $path;
      if(!array_key_exists($path, $coverageEntries))
        {
        $coverageFile = new CoverageFile();
        $coverage = new Coverage();
        $coverage->CoverageFile = $coverageFile;
        $coverage->BuildId = $coverageSummary->BuildId;
          /*
          * JSON data is line based and has a coverage line for each line of source
          *
          * Loop through the length of coverage lines
          */
        $coverageLines = $coverageEntry['coverage'];
        $fileLength = count($coverageLines);
        for ($i = 1; $i < $fileLength-1; $i++)
            {
            $sourceLine = $coverageEntry['source'][$i-1];
            $coverageFile->File .= $sourceLine;
            $coverageFile->File .= '<br>';
            $timesHit = $coverageLines[$i];
            // non-code lines are "null" in JSON, decodes to empty so we check for non-numeric values.
            if (!isset($timesHit))
              {
              // Uncoverable code
              continue;
              }
            // This is how JSCover indicates an uncovered line.
            if ($timesHit == '0')
              {
              $timesHit = 0;
              $coverage->LocUntested += 1;
              $coverageSummary->LocUntested += 1;
              }
            else
              {
              // value in entry indicates total times hit, coerce the string to a number
              $timesHit = intval($timesHit);
              $coverage->Covered = 1;
              $coverage->LocTested += 1;
              $coverageSummary->LocTested += 1;
              }
            $coverageFileLog->AddLine($i-1, $timesHit);
            }
            // Get the ID for this coverage file, or create a new empty one
            //if it doesn't already exist.
            $sql = pdo_query(
              "SELECT id FROM coveragefile WHERE fullpath='$path' AND file IS NULL");
            if(pdo_num_rows($sql)==0)
              {
              pdo_query ("INSERT INTO coveragefile (fullpath) VALUES ('$path')");
              $fileid = pdo_insert_id("coveragefile");
              }
            else
              {
              $coveragefile_array = pdo_fetch_array($sql);
              $fileid = $coveragefile_array["id"];
              }
        }
      else
        {
        $coverage = $coverageEntries[$path];
        $coverageFile=$coverage->CoverageFile;
        $fileid = $coverageFile->Id;
        $coverageLines = $coverageEntry['coverage'];
        $fileLength = count($coverageLines);
        for ($i = 1; $i < $fileLength; $i++)
          {
          $timesHit = $coverageLines[$i];
          if (isset($timesHit))
            {
            $coverageFileLog->AddLine($i-1, intval($timesHit));
            }
          }
        }
      $buildid = $coverageSummary->BuildId;
      $coverageFile->Id = $fileid;
      $coverageFile->FullPath=$path;
      // Save these models to the database.
      $coverageFile->Update($buildid);
      $coverageFileLog->BuildId = $buildid;
      $coverageFileLog->FileId = $coverageFile->Id;
      $coverageFileLog->Insert();
      // Add this Coverage to our summary.
      //
      $coverage->CoverageFile = $coverageFile;
      $coverageEntries[$path] = $coverage;
      }
    }

  /**
    * PHP won't let you delete a non-empty directory, so we first have to
    * search through it and delete each file & subdirectory that we find.
   **/
  function DeleteDirectory($dirName)
    {
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dirName),
      RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file)
      {
      if (in_array($file->getBasename(), array('.', '..')))
        {
        continue;
        }
      if ($file->isDir())
        {
        rmdir($file->getPathname());
        }
      if ($file->isFile() || $file->isLink())
        {
        unlink($file->getPathname());
        }
      }
    rmdir($dirName);
    }

} // end class

?>
