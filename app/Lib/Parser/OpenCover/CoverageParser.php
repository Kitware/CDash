<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Lib\Parser\OpenCover;

use CDash\Config;
use CDash\Lib\Parser\AbstractXmlParser;
use CDash\Lib\Parser\ParserInterface;
use CDash\Model\Build;
use CDash\Model\Coverage;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;
use CDash\Model\CoverageSummary;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class CoverageParser
 * @package CDash\Lib\Parser\OpenCover
 */
class CoverageParser extends AbstractXmlParser implements ParserInterface
{
    protected $build;
    protected $coverageSummaries;
    protected $coverages;
    protected $coverageFiles;
    protected $coverageFile;
    protected $coverageFileLogs;
    protected $coverageFileLog;
    protected $parseCSFiles;
    protected $currentModule;
    protected $tarDir;

    /**
     * CoverageParser constructor.
     * @param $buildId
     */
    public function __construct($buildId)
    {
        parent::__construct(0);
        $this->build = $this->getInstance(Build::class);
        $this->build->Id = $buildId;
        $this->build->FillFromId($buildId);

        $this->coverageSummaries = [];
        $coverageSummary = $this->getInstance(CoverageSummary::class);
        $coverageSummary->BuildId = $buildId;
        $this->coverageSummaries['default'] = $coverageSummary;

        $this->coverages = [];
        $this->coverageFiles = [];
        $this->coverageFileLogs = [];

        $this->parseCSFiles = true;
    }

    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed|void
     */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        /*
         *  SEQUENCEPOINT denotes a line in the source file that is executable and
         *  may have been executed.
         *
         *  VC -> Visit Count
         *  EL -> Line offset, reduced by one to start the file at line 0
         */
        if (($name == "SEQUENCEPOINT") && ($this->coverageFileLog)) {
            $this->coverageFileLog->AddLine($attributes['EL']-1, $attributes['VC']);
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        $data = trim($data);
        // FULLNAME refers to the "namespace" of the individual file
        if ($element == 'FULLNAME' && (strlen($data))) {
            $path = $this->parseFullName($data);
            // Lookup our models & create them if they don't exist yet.
            if ($path) {
                $this->getCoverageObjects($path);
            } else {
                $this->coverageFile = false;
                $this->coverageFileLog = false;
            }
        }
        // MODULENAME gives the folder structure that the .cs file belongs in
        if ($element == 'MODULENAME' && (strlen($data))) {
            $this->currentModule = array($data, strtolower($data));
        }
    }

    /**
     *  Removes the current module, and assumes all that is left is a
     *  subdirectory and the file name, append '.cs' to get the file path
     *  in the un-tarred directory
     *
     * @param $string
     * @return bool|string
     */
    public function parseFullName($string)
    {
        foreach ($this->currentModule as $path) {
            $filePath = str_ireplace($path, '', $string);
            $filePath = str_replace('.', '/', $filePath);
            if (file_exists($this->tarDir.'/'.$path.$filePath.'.cs')) {
                return $path.$filePath.'.cs';
            }
        }
        return false;
    }

    /**
     * Queries for the coverage objects for both adding source and
     * adding coverage values
     * @param $path
     */
    public function getCoverageObjects($path)
    {
        if (!array_key_exists($path, $this->coverageFileLogs)) {
            $coverageFileLog = $this->getInstance(CoverageFileLog::class);
            $coverageFileLog->BuildId =  $this->build->Id;
            $coverageFile = $this->getInstance(CoverageFile::class);
            $coverageFile->FullPath = trim($path);

            //Run update which will create a new entry if there
            // isn't one for the current path
            $coverageFile->Update($this->build->Id);

            $coverage = $this->getInstance(Coverage::class);
            $coverage->CoverageFile = $coverageFile;
            $coverage->BuildId = $this->build->Id;

            $this->coverages[$path] = $coverage;

            $this->coverageFiles[$path] = $coverageFile;
            $this->coverageFileLogs[$path] = $coverageFileLog;
            $this->coverageFile =$coverageFile;
            $this->coverageFileLog = $coverageFileLog;
        } else {
            $this->coverageFile = $this->coverageFiles[$path];
            $this->coverageFileLog = $this->coverageFileLogs[$path];
        }
    }


    /**
     * Parse a tarball of JSON files.
     * @param $filename
     * @return bool
     */
    public function parse($filename)
    {
        $config = Config::getInstance();

        // Create a new directory where we can extract our tarball.
        $dirName = $config->get('CDASH_BACKUP_DIRECTORY') . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME);
        mkdir($dirName);
        $this->tarDir = $dirName;
        $result = extract_tar($filename, $dirName);
        if ($result === false) {
            add_log('Could not extract ' . $filename . ' into ' . $dirName, __FUNCTION__, LOG_ERR);
            return false;
        }

        // Search for data.json
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirName),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $fileinfo */
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->getFilename() == 'data.json') {
                $jsonContents = file_get_contents($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
                $jsonDecoded = json_decode($jsonContents, true);
                if (array_key_exists('parseCSFiles', $jsonDecoded)) {
                    $this->parseCSFiles = $jsonDecoded['parseCSFiles'];
                }
            }
        }

        // Now that coverageFile objects exist, add source to each .cs file
        $coverageSummary = $this->coverageSummaries['default'];
        $iterator->rewind();
        foreach ($iterator as $fileinfo) {
            $ext = substr(strstr($fileinfo->getFilename(), '.'), 1);
            if ($ext === 'cs') {
                $this->readSourceFile($fileinfo);
            }
        }

        // Recursively search for .xml files and parse them.
        $iterator->rewind();
        foreach ($iterator as $fileinfo) {
            // need the longest extension, so getExtension() won't do here.
            $ext = substr(strstr($fileinfo->getFilename(), '.'), 1);
            if ($ext === 'xml') {
                $this->parseOpenCoverFile($fileinfo);
            }
        }
        // Record parsed coverage info to the database.
        foreach ($this->coverageFileLogs as $path => $coverageFileLog) {
            $coverage = $this->coverages[$path];
            $coverageFile = $this->coverageFiles[$path];

            // Tally up how many lines of code were covered & uncovered.
            foreach ($coverageFileLog->Lines as $line) {
                $coverage->Covered = 1;
                if ($line == 0) {
                    $coverage->LocUntested += 1;
                } else {
                    $coverage->LocTested += 1;
                }
            }

            // Save these models to the database.
            $coverageFile->TrimLastNewline();
            $coverageFile->Update($this->build->Id);
            $coverageFileLog->BuildId = $this->build->Id;
            $coverageFileLog->FileId = $coverageFile->Id;
            $coverageFileLog->Insert();

            // Add this Coverage to our summary.
            $coverage->CoverageFile = $coverageFile;
            $coverageSummary->AddCoverage($coverage);
        }

        // Insert coverage summaries
        $completedSummaries = [];
        foreach ($this->coverageSummaries as $coverageSummary) {
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
     * Read in the source for each .cs file
     *
     * @param SplFileInfo $fileinfo
     * @return bool
     */
    public function readSourceFile(SplFileInfo $fileinfo)
    {
        // If the name starts with "TemporaryGenerated", ignore the file
        if (preg_match("/^TemporaryGenerated/", $fileinfo->getFilename())) {
            return true;
        }
        $path = $fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename();
        $path = str_replace($this->tarDir.'/', '', $path);
        $fileContents = file($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
        $this->getCoverageObjects($path);
        $inlongComment= false;
        if ($this->coverageFile) {
            foreach ($fileContents as $key=>$line) {
                $trimmedLine = trim($line);
                $displayLine = rtrim($line);
                // Matches the beginning of a comment block
                if (preg_match("/\/[*]+/", $trimmedLine)) {
                    $inlongComment=true;
                }

                $this->coverageFile->File .= $displayLine.'<br>';
                if (!((preg_match("/^\/\//", $trimmedLine)) or
                        (preg_match("/using /", $trimmedLine)) or
                        (preg_match("/^namespace/", $trimmedLine)) or
                        (preg_match("/^public/", $trimmedLine)) or
                        (preg_match("/^protected/", $trimmedLine)) or
                        (preg_match("/^private/", $trimmedLine)) or
                        (preg_match("/^\[/", $trimmedLine)) or
                        (preg_match("/[{}]/", $trimmedLine)) or
                        ("" == $trimmedLine) or
                        ($inlongComment)
                    ) && $this->parseCSFiles) {
                    $this->coverageFileLog->AddLine($key, 0);
                }
                // Captures the end of a comment block
                if (preg_match("/[*]+\//", $trimmedLine)) {
                    $inlongComment=false;
                }
            }
        }
    }

    /**
     * Parse an individual XML file.
     * @param SplFileInfo $fileinfo
     */
    public function parseOpenCoverFile(SplFileInfo $fileinfo)
    {
        // Parse this XML file.
        $fileContents = file_get_contents($fileinfo->getPath() . DIRECTORY_SEPARATOR . $fileinfo->getFilename());
        $parser = xml_parser_create();
        xml_set_element_handler($parser, [$this,'startElement'], [$this,'endElement']);
        xml_set_character_data_handler($parser, [$this, 'text']);
        xml_parse($parser, $fileContents, true);
        xml_parser_free($parser);
    }
}
