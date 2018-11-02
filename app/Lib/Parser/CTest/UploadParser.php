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

namespace CDash\Lib\Parser\CTest;

use CDash\Lib\Parser\AbstractXmlParser;
use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\Label;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\SiteInformation;
use CDash\Model\UploadFile;

/**
 * Class UploadParser
 * @package CDash\Lib\Parser\CTest
 */
class UploadParser extends AbstractXmlParser
{
    private $uploadFile;
    private $tmpFilename;
    private $base64TmpFileWriteHandle;
    private $base64TmpFilename;
    private $label;

    /** If True, means an error happened while processing the file */
    private $uploadError;

    private $updateEndTime;

    /**
     * UploadParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->site = $this->getInstance(Site::class);
        $this->tmpFilename = '';
        $this->base64TmpFileWriteHandle = 0;
        $this->base64TmpFilename = '';
        $this->uploadError = false;
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

        if ($this->uploadError) {
            return;
        }

        if ($name == 'SITE') {
            $this->site->Name = $attributes['NAME'];
            if (empty($this->site->Name)) {
                $this->site->Name = '(empty)';
            }
            $this->site->Insert();

            $siteInformation = $this->getInstance(SiteInformation::class);
            $buildInformation = $this->getInstance(BuildInformation::class);

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->site->SetInformation($siteInformation);

            $this->build->SiteId = $this->site->Id;
            $this->build->Name = $attributes['BUILDNAME'];
            if (empty($this->build->Name)) {
                $this->build->Name = '(empty)';
            }
            $this->build->SetStamp($attributes['BUILDSTAMP']);
            $this->build->Generator = $attributes['GENERATOR'];
            $this->build->Information = $buildInformation;
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
                "SELECT nightlytime FROM project where id='$this->projectId'");
            $nightly_time = $row['nightlytime'];
            $build_date =
                extract_date_from_buildstamp($this->build->GetStamp());

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

            $this->build->EndTime = gmdate(FMT_DATETIME, $nightly_start_time);

            $now = time();
            $one_second_before_tomorrow =
                strtotime('+1 day -1 second', $nightly_start_time);
            if ($one_second_before_tomorrow < time()) {
                $this->build->StartTime =
                    gmdate(FMT_DATETIME, $one_second_before_tomorrow);
            } else {
                $this->build->StartTime = gmdate(FMT_DATETIME, $now);
            }

            $this->build->SubmitTime = gmdate(FMT_DATETIME, $now);

            $this->build->ProjectId = $this->projectId;
            $this->build->SetSubProject($this->subProjectName);
            $this->build->GetIdFromName($this->subProjectName);
            $this->build->RemoveIfDone();

            if ($this->label) {
                $this->build->AddLabel($this->label);
            }

            // If the build doesn't exist we add it
            if ($this->build->Id == 0) {
                $this->build->Append = false;
                $this->build->InsertErrors = false;
                add_build($this->build);

                $this->updateEndTime = true;
            } else {
                if ($this->label) {
                    $this->build->InsertLabelAssociations();
                }

                // Otherwise make sure that the build is up-to-date.
                $this->build->UpdateBuild($this->build->Id, -1, -1);
            }
            $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->build->Id;
        } elseif ($name == 'FILE') {
            $this->uploadFile = $this->getInstance(UploadFile::class);
            $this->uploadFile->Filename = $attributes['FILENAME'];
        } elseif ($name == 'CONTENT') {
            $fileEncoding = isset($attributes['ENCODING']) ? $attributes['ENCODING'] : 'base64';

            if (strcmp($fileEncoding, 'base64') != 0) {
                // Only base64 encoding is supported for file upload
                add_log("upload_handler:  Only 'base64' encoding is supported", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->uploadError = true;
                return;
            }

            // Create tmp file
            // TODO Handle error
            $this->tmpFilename = tempnam($this->getConfigValue('CDASH_UPLOAD_DIRECTORY'), 'tmp');
            if (empty($this->tmpFilename)) {
                add_log('Failed to create temporary filename', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->uploadError = true;
                return;
            }
            $this->base64TmpFilename = $this->tmpFilename . '-base64';

            // Open base64 temporary file for writting
            $this->base64TmpFileWriteHandle = fopen($this->base64TmpFilename, 'w');
            if (!$this->base64TmpFileWriteHandle) {
                add_log("Failed to open file '" . $this->base64TmpFilename . "' for writting", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->uploadError = true;
                return;
            }
        } elseif ($name == 'LABEL') {
            $this->label = $this->getInstance(Label::class);
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     * @throws \Exception
     */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($this->uploadError) {
            return;
        }

        if ($name == 'FILE' && $parent == 'UPLOAD') {
            $this->uploadFile->BuildId = $this->build->Id;

            // Close base64 temporary file writing handler
            fclose($this->base64TmpFileWriteHandle);
            unset($this->base64TmpFileWriteHandle);

            // Decode file using 'read by chunk' approach to minimize memory footprint
            // Note: Using stream_filter_append/stream_copy_to_stream is more efficient but
            // return an "invalid byte sequence" on windows
            $rhandle = fopen($this->base64TmpFilename, 'r');
            $whandle = fopen($this->tmpFilename, 'w+');
            $chunksize = 4096;
            while (!feof($rhandle)) {
                fwrite($whandle, base64_decode(fread($rhandle, $chunksize)));
            }
            fclose($rhandle);
            unset($rhandle);
            fclose($whandle);
            unset($whandle);

            // Delete base64 encoded file
            $success = cdash_unlink($this->base64TmpFilename);
            if (!$success) {
                add_log("Failed to delete file '" . $this->base64TmpFilename . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_WARNING);
            }

            // Check file size against the upload quota
            $upload_file_size = filesize($this->tmpFilename);
            $Project = $this->getInstance(Project::class);
            $Project->Id = $this->projectId;
            $Project->Fill();
            if ($upload_file_size > $Project->UploadQuota) {
                add_log("Size of uploaded file $this->tmpFilename is $upload_file_size bytes, which is greater " .
                    "than the total upload quota for this project ($Project->UploadQuota bytes)",
                    __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                $this->uploadError = true;
                cdash_unlink($this->tmpFilename);
                return;
            }

            // Compute SHA1 of decoded file
            $upload_file_sha1 = sha1_file($this->tmpFilename);

            // TODO Check if a file if same buildid, sha1 and name has already been uploaded

            $this->uploadFile->Sha1Sum = $upload_file_sha1;
            $this->uploadFile->Filesize = $upload_file_size;

            // Extension of the file indicates if it's a data file that should be hosted on CDash of if
            // an URL should just be considered. File having extension ".url" are expected to contain an URL.
            $path_parts = pathinfo($this->uploadFile->Filename);
            $ext = $path_parts['extension'];

            if ($ext == 'url') {
                $this->uploadFile->IsUrl = true;

                // Read content of the file
                $url_length = 255; // max length of 'uploadfile.filename' field
                $this->uploadFile->Filename = trim(file_get_contents($this->tmpFilename, null, null, 0, $url_length));
                cdash_unlink($this->tmpFilename);
            } else {
                $this->uploadFile->IsUrl = false;

                $upload_dir = realpath($this->getConfigValue('CDASH_UPLOAD_DIRECTORY'));
                if (!$upload_dir) {
                    add_log("realpath cannot resolve CDASH_UPLOAD_DIRECTORY '" .
                        $this->getConfigValue('CDASH_UPLOAD_DIRECTORY') . "' with cwd '" . getcwd() . "'",
                        __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_WARNING);
                }
                $upload_dir .= '/' . $this->uploadFile->Sha1Sum;

                $uploadfilepath = $upload_dir . '/' . $this->uploadFile->Sha1Sum;

                // Check if upload directory should be created
                if (!file_exists($upload_dir)) {
                    $success = mkdir($upload_dir);
                    if (!$success) {
                        add_log("Failed to create directory '" . $upload_dir . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                        $this->uploadError = true;
                        return;
                    }
                }

                // Check if file has already been referenced
                if (!file_exists($uploadfilepath)) {
                    $success = rename($this->tmpFilename, $uploadfilepath);
                    if (!$success) {
                        add_log("Failed to rename file '" . $this->tmpFilename . "' into '" . $uploadfilepath . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
                        $this->uploadError = true;
                        return;
                    }
                } else {
                    // Delete decoded temporary file since it has already been addressed
                    $success = cdash_unlink($this->tmpFilename);
                    if (!$success) {
                        add_log("Failed to delete file '" . $this->tmpFilename . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_WARNING);
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
                        if ($this->getConfigValue('CDASH_TESTING_MODE')) {
                            $level = LOG_INFO;
                        }

                        add_log("Failed to create symlink [target:'" . $uploadfilepath . "', name: '" . $upload_dir . '/' . $symlinkName . "']", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, $level);

                        // Fall back to a full copy if symlink does not exist, or if it failed:
                        $success = copy($uploadfilepath, $upload_dir . '/' . $symlinkName);

                        if (!$success) {
                            add_log("Failed to copy file (symlink fallback) [target:'" . $uploadfilepath . "', name: '" . $upload_dir . '/' . $symlinkName . "']", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);

                            $this->uploadError = true;
                            return;
                        }
                    }
                }
            }

            // Update model
            $success = $this->uploadFile->Insert();
            if (!$success) {
                add_log("UploadFile model - Failed to insert row associated with file: '" . $this->uploadFile->Filename . "'", __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            }
            $Project->CullUploadedFiles();

            // Reset UploadError so that the handler could attempt to process following files
            $this->uploadError = false;
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        if ($this->uploadError) {
            return;
        }

        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'FILE') {
            switch ($element) {
                case 'CONTENT':
                    // Write base64 encoded chunch to temporary file
                    $charsToReplace = array("\r\n", "\n", "\r");
                    fwrite($this->base64TmpFileWriteHandle, str_replace($charsToReplace, '', $data));
                    break;
            }
        } elseif ($element == 'LABEL') {
            $this->label->SetText($data);
        }
    }
}
