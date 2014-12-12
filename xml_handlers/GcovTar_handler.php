<?php

require_once('models/coverage.php');
require_once('cdash/config.php');

class GCovTarHandler
{
  private $BuildId;
  private $CoverageSummary;
  private $SourceDirectory;

  public function __construct($buildid)
    {
    $this->BuildId = $buildid;
    $this->CoverageSummary = new CoverageSummary();
    $this->CoverageSummary->BuildId = $this->BuildId;
    $this->SourceDirectory = '';
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
    foreach ($iterator as $fileinfo)
      {
      if ($fileinfo->getFilename() == "data.json")
        {
        $jsonContents = file_get_contents($fileinfo->getRealPath());
        $jsonDecoded = json_decode($jsonContents, true);
        if (is_null($jsonDecoded) || !array_key_exists("Source", $jsonDecoded))
          {
          $this->DeleteDirectory($dirName);
          return false;
          }
        $this->SourceDirectory = $jsonDecoded['Source'];
        break;
        }
      }
    if (empty($this->SourceDirectory))
      {
      $this->DeleteDirectory($dirName);
      return false;
      }

    // Recursively search for .gcov files and parse them.
    $iterator->rewind();
    foreach ($iterator as $fileinfo)
      {
      if ($fileinfo->getExtension() == "gcov")
        {
        $this->ParseGcovFile($fileinfo);
        }
      }

    // Insert coverage summary (removing any old results first)
    //$this->CoverageSummary->RemoveAll();
    $this->CoverageSummary->Insert();
    $this->CoverageSummary->ComputeDifference();

    // Delete the directory when we're done.
    $this->DeleteDirectory($dirName);
    return true;
    }


  /**
    * Parse an individual .gcov file.
   **/
  function ParseGcovFile($fileinfo)
    {
    $coverageFileLog = new CoverageFileLog();
    $coverageFile = new CoverageFile();
    $coverage = new Coverage();
    $coverage->CoverageFile = $coverageFile;

    // Begin parsing this file.
    // The first thing we look for is the full path to this source file.
    $file = new SplFileObject($fileinfo);
    $path = '';
    while (!$file->eof())
      {
      $gcovLine = $file->current();
      $term = ":Source:";
      $pos = strpos($gcovLine, $term);
      if ($pos !== false)
        {
        $path = substr($gcovLine, $pos + strlen($term));
        break;
        }
      $file->next();
      }
    if (empty($path))
      {
      return;
      }

    $path = str_replace($this->SourceDirectory, ".", trim($path));
    $coverageFile->FullPath = $path;

    // The lack of rewind is intentional.
    while (!$file->eof())
      {
      $gcovLine = $file->current();
      $fields = explode(":", $gcovLine);
      if (count($fields) > 2)
        {
        // Separate out delimited values from this line.
        $timesHit = trim($fields[0]);
        $lineNumber = trim($fields[1]);
        $sourceLine = trim($fields[2]);

        if ($lineNumber > 0)
          {
          $coverageFile->File .= $sourceLine;
          // cannot be <br/> for backward compatibility.
          $coverageFile->File .= '<br>';
          }

        // This is how gcov indicates a line of unexecutable code.
        if ($timesHit === '-')
          {
          $file->next();
          continue;
          }

        // This is how gcov indicates an uncovered line.
        if ($timesHit === '#####')
          {
          $timesHit = 0;
          $coverage->LocUntested += 1;
          $this->CoverageSummary->LocUntested += 1;
          }
        else
          {
          $coverage->Covered = 1;
          $coverage->LocTested += 1;
          $this->CoverageSummary->LocTested += 1;
          }

        $coverageFileLog->AddLine($lineNumber - 1, $timesHit);
        }
      $file->next();
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
    $coverageFile->Id = $fileid;

    // Save these models to the database.
    $coverageFile->Update($this->BuildId);
    $coverageFileLog->BuildId = $this->BuildId;
    $coverageFileLog->FileId = $coverageFile->Id;
    $coverageFileLog->Insert();

    // Add this Coverage to our summary.
    $this->CoverageSummary->AddCoverage($coverage);
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
