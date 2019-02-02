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

require_once 'xml_handlers/abstract_handler.php';

use CDash\Config;
use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;
use CDash\Model\Project;
use CDash\Model\UploadFile;

/**
 * For each uploaded file the following steps occur:
 *  1) Temporary file 'tmpXXX-base64' is created (See startElement())
 *  2) Chunk of base64 data are written to that file  (See text())
 *  3) File 'tmpXXX-base64' is then decoded into 'tmpXXX'
 *  4) SHA1 of file 'tmpXXX' is computed
 *  5) If directory '<CDASH_UPLOAD_DIRECTORY>/<SHA1>' doesn't exist, it's created
 *  6) If file '<CDASH_UPLOAD_DIRECTORY>/<SHA1>/<SHA1>' doesn't exist, 'tmpXXX' is renamed accordingly
 *  7) Symbolic link <CDASH_UPLOAD_DIRECTORY>/<SHA1>/<FILENAME> pointing to <CDASH_UPLOAD_DIRECTORY>/<SHA1>/<SHA1> is then created
 *
 * Ideally, CDash and CTest should first exchange information to make sure the file hasn't been uploaded already.
 *
 * As a first step, CTest could provide the SHA1 so that extra processing are avoided.
 */
class UploadHandler extends AbstractHandler
{
    private $BuildId;
    private $UploadFile;
    private $TmpFilename;
    private $Base64TmpFileWriteHandle;
    private $Base64TmpFilename;
    private $Label;

    /** If True, means an error happened while processing the file */
    private $UploadError;

    private $UpdateEndTime;

    /** Constructor */
    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->TmpFilename = '';
        $this->Base64TmpFileWriteHandle = 0;
        $this->Base64TmpFilename = '';
        $this->UploadError = false;
    }

    /** Start element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $config = Config::getInstance();

        if ($this->UploadError) {
            return;
        }

        if ($name == 'SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = new SiteInformation();
            $buildInformation = new BuildInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->Site->SetInformation($siteInformation);

            $this->Build->SiteId = $this->Site->Id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
            $this->Build->Information = $buildInformation;
        } elseif ($name == 'UPLOAD') {
            // Setting start time and end time is tricky here, since all
            // we have is the build stamp.  The strategy we take here is:
            // Set the start time as late as possible, and set the end time
            // as early as possible.
            // This way we don't override any existing values for these fields
            // when we call UpdateBuild() below.
            //
            // For end time, we use the start of the testing day.
            // For start time, we use either the submit time (now) or
            // one second before the start time of the *next* testing day
            // (whichever is earlier).
            // Yes, this means the build finished before it began.
            //
            // This associates the build with the correct day if it is only
            // an upload.  Otherwise we defer to the values set by the
            // other handlers.
            $row = pdo_single_row_query(
                "SELECT nightlytime FROM project where id='$this->projectid'");
            $nightly_time = $row['nightlytime'];
            $build_date =
                extract_date_from_buildstamp($this->Build->GetStamp());

            list($prev, $nightly_start_time, $next) =
                get_dates($build_date, $nightly_time);

            // If the nightly start time is after noon (server time)
            // and this buildstamp is on or after the nightly start time
            // then this build belongs to the next testing day.
            if (date(FMT_TIME, $nightly_start_time) > '12:00:00') {
                $build_timestamp = strtotime($build_date);
                $next_timestamp = strtotime($next);
                if (strtotime(date(FMT_TIME, $build_timestamp), $next_timestamp) >=
                    strtotime(date(FMT_TIME, $nightly_start_time), $next_timestamp)) {
                    $nightly_start_time += 3600 * 24;
                }
            }

            $this->Build->EndTime = gmdate(FMT_DATETIME, $nightly_start_time);

            $now = time();
            $one_second_before_tomorrow =
                strtotime('+1 day -1 second', $nightly_start_time);
            if ($one_second_before_tomorrow < time()) {
                $this->Build->StartTime =
                    gmdate(FMT_DATETIME, $one_second_before_tomorrow);
            } else {
                $this->Build->StartTime = gmdate(FMT_DATETIME, $now);
            }

            $this->Build->SubmitTime = gmdate(FMT_DATETIME, $now);

            $this->Build->ProjectId = $this->projectid;
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();

            if ($this->Label) {
                $this->Build->AddLabel($this->Label);
            }

            // If the build doesn't exist we add it
            if ($this->Build->Id == 0) {
                $this->Build->Append = false;
                $this->Build->InsertErrors = false;
                add_build($this->Build, $this->scheduleid);

                $this->UpdateEndTime = true;
            } else {
                if ($this->Label) {
                    $this->Build->InsertLabelAssociations();
                }

                // Otherwise make sure that the build is up-to-date.
                $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            }
            $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->Build->Id;
        } elseif ($name == 'FILE') {
            $this->UploadFile = new UploadFile();
            $this->UploadFile->Filename = $attributes['FILENAME'];
        } elseif ($name == 'CONTENT') {
            $fileEncoding = isset($attributes['ENCODING']) ? $attributes['ENCODING'] : 'base64';

            if (strcmp($fileEncoding, 'base64') != 0) {
                // Only base64 encoding is supported for file upload
                add_log("upload_handler:  Only 'base64' encoding is supported", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->UploadError = true;
                return;
            }

            // Create tmp file
            $this->TmpFilename = tempnam($config->get('CDASH_UPLOAD_DIRECTORY'), 'tmp'); // TODO Handle error
            chmod($this->TmpFilename, 0644);

            if (empty($this->TmpFilename)) {
                add_log('Failed to create temporary filename', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->UploadError = true;
                return;
            }
            $this->Base64TmpFilename = $this->TmpFilename . '-base64';

            // Open base64 temporary file for writting
            $this->Base64TmpFileWriteHandle = fopen($this->Base64TmpFilename, 'w');
            if (!$this->Base64TmpFileWriteHandle) {
                add_log("Failed to open file '" . $this->Base64TmpFilename . "' for writting", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->UploadError = true;
                return;
            }
        } elseif ($name == 'LABEL') {
            $this->Label = new Label();
        }
    }

    /** Function endElement */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);
        $config = Config::getInstance();

        if ($this->UploadError) {
            return;
        }

        if ($name == 'FILE' && $parent == 'UPLOAD') {
            $this->UploadFile->BuildId = $this->Build->Id;

            // Close base64 temporary file writing handler
            fclose($this->Base64TmpFileWriteHandle);
            unset($this->Base64TmpFileWriteHandle);

            // Decode file using 'read by chunk' approach to minimize memory footprint
            // Note: Using stream_filter_append/stream_copy_to_stream is more efficient but
            // return an "invalid byte sequence" on windows
            $rhandle = fopen($this->Base64TmpFilename, 'r');
            $whandle = fopen($this->TmpFilename, 'w+');
            $chunksize = 4096;
            while (!feof($rhandle)) {
                fwrite($whandle, base64_decode(fread($rhandle, $chunksize)));
            }
            fclose($rhandle);
            unset($rhandle);
            fclose($whandle);
            unset($whandle);

            // Delete base64 encoded file
            $success = cdash_unlink($this->Base64TmpFilename);
            if (!$success) {
                add_log("Failed to delete file '" . $this->Base64TmpFilename . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_WARNING);
            }

            // Check file size against the upload quota
            $upload_file_size = filesize($this->TmpFilename);
            $Project = new Project;
            $Project->Id = $this->projectid;
            $Project->Fill();
            if ($upload_file_size > $Project->UploadQuota) {
                add_log("Size of uploaded file $this->TmpFilename is $upload_file_size bytes, which is greater " .
                    "than the total upload quota for this project ($Project->UploadQuota bytes)",
                    __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->UploadError = true;
                cdash_unlink($this->TmpFilename);
                return;
            }

            // Compute SHA1 of decoded file
            $upload_file_sha1 = sha1_file($this->TmpFilename);

            // TODO Check if a file if same buildid, sha1 and name has already been uploaded

            $this->UploadFile->Sha1Sum = $upload_file_sha1;
            $this->UploadFile->Filesize = $upload_file_size;

            // Extension of the file indicates if it's a data file that should be hosted on CDash of if
            // an URL should just be considered. File having extension ".url" are expected to contain an URL.
            $path_parts = pathinfo($this->UploadFile->Filename);
            $ext = $path_parts['extension'];

            if ($ext == 'url') {
                $this->UploadFile->IsUrl = true;

                // Read content of the file
                $url_length = 255; // max length of 'uploadfile.filename' field
                $this->UploadFile->Filename = trim(file_get_contents($this->TmpFilename, null, null, 0, $url_length));
                cdash_unlink($this->TmpFilename);
            } else {
                $this->UploadFile->IsUrl = false;

                $upload_dir = realpath($config->get('CDASH_UPLOAD_DIRECTORY'));
                if (!$upload_dir) {
                    add_log("realpath cannot resolve CDASH_UPLOAD_DIRECTORY '" .
                        $config->get('CDASH_UPLOAD_DIRECTORY') . "' with cwd '" . getcwd() . "'",
                        __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_WARNING);
                }
                $upload_dir .= '/' . $this->UploadFile->Sha1Sum;

                $uploadfilepath = $upload_dir . '/' . $this->UploadFile->Sha1Sum;

                // Check if upload directory should be created
                if (!file_exists($upload_dir)) {
                    $success = mkdir($upload_dir);
                    if (!$success) {
                        add_log("Failed to create directory '" . $upload_dir . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                        $this->UploadError = true;
                        return;
                    }
                }

                // Check if file has already been referenced
                if (!file_exists($uploadfilepath)) {
                    $success = rename($this->TmpFilename, $uploadfilepath);
                    if (!$success) {
                        add_log("Failed to rename file '" . $this->TmpFilename . "' into '" . $uploadfilepath . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                        $this->UploadError = true;
                        return;
                    }
                } else {
                    // Delete decoded temporary file since it has already been addressed
                    $success = cdash_unlink($this->TmpFilename);
                    if (!$success) {
                        add_log("Failed to delete file '" . $this->TmpFilename . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_WARNING);
                    }
                }

                // Generate symlink name
                $symlinkName = $path_parts['basename'];

                // Check if symlink should be created
                $createSymlink = !file_exists($upload_dir . '/' . $symlinkName);

                if ($createSymlink) {
                    // Create symlink
                    if (function_exists('symlink')) {
                        $success = symlink($uploadfilepath, $upload_dir . '/' . $symlinkName);
                    } else {
                        $success = 0;
                    }

                    if (!$success) {
                        // Log actual non-testing symlink failure as an error:
                        $level = LOG_ERR;

                        // But if testing, log as info only:
                        if ($config->get('CDASH_TESTING_MODE')) {
                            $level = LOG_INFO;
                        }

                        add_log("Failed to create symlink [target:'" . $uploadfilepath . "', name: '" . $upload_dir . '/' . $symlinkName . "']", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, $level);

                        // Fall back to a full copy if symlink does not exist, or if it failed:
                        $success = copy($uploadfilepath, $upload_dir . '/' . $symlinkName);

                        if (!$success) {
                            add_log("Failed to copy file (symlink fallback) [target:'" . $uploadfilepath . "', name: '" . $upload_dir . '/' . $symlinkName . "']", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);

                            $this->UploadError = true;
                            return;
                        }
                    }
                }
            }

            // Update model
            $success = $this->UploadFile->Insert();
            if (!$success) {
                add_log("UploadFile model - Failed to insert row associated with file: '" . $this->UploadFile->Filename . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            }
            $Project->CullUploadedFiles();

            // Reset UploadError so that the handler could attempt to process following files
            $this->UploadError = false;
        }
    }

    /** Function Text */
    public function text($parser, $data)
    {
        if ($this->UploadError) {
            return;
        }

        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'FILE') {
            switch ($element) {
                case 'CONTENT':
                    // Write base64 encoded chunch to temporary file
                    $charsToReplace = array("\r\n", "\n", "\r");
                    fwrite($this->Base64TmpFileWriteHandle, str_replace($charsToReplace, '', $data));
                    break;
            }
        } elseif ($element == 'LABEL') {
            $this->Label->SetText($data);
        }
    }
}
