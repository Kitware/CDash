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

use App\Models\Site;
use App\Models\SiteInformation;
use App\Utils\SubmissionUtils;
use CDash\Model\Build;
use CDash\Model\Label;
use CDash\Model\Project;
use CDash\Model\UploadFile;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * For each uploaded file the following steps occur:
 *  1) Temporary file 'tmpXXX-base64' is created (See startElement())
 *  2) Chunk of base64 data are written to that file  (See text())
 *  3) File 'tmpXXX-base64' is then decoded into 'tmpXXX'
 *  4) SHA1 of file 'tmpXXX' is computed
 *  5) If file 'storage/app/upload/<SHA1>' doesn't exist, 'tmpXXX' is renamed accordingly
 *
 * Ideally, CDash and CTest should first exchange information to make sure the file hasn't been uploaded already.
 *
 * As a first step, CTest could provide the SHA1 so that extra processing are avoided.
 */
class UploadHandler extends AbstractXmlHandler
{
    private $UploadFile;
    private $TmpFilename;
    private $Base64TmpFileWriteHandle;
    private $Base64TmpFilename;
    private $Label;
    private int $Timestamp;
    private bool $BuildInitialized;

    /** If True, means an error happened while processing the file */
    private $UploadError;

    /** Constructor */
    public function __construct(Project $project)
    {
        parent::__construct($project);
        $this->Site = new Site();
        $this->TmpFilename = '';
        $this->Base64TmpFileWriteHandle = 0;
        $this->Base64TmpFilename = '';
        $this->UploadError = false;
        $this->Timestamp = 0;
        $this->BuildInitialized = false;
    }

    /** Start element */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);

        if ($this->UploadError) {
            return;
        }

        if ($name === 'SITE') {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                switch ($key) {
                    case 'OSNAME':
                        $this->Build->OSName = $value;
                        break;
                    case 'OSRELEASE':
                        $this->Build->OSRelease = $value;
                        break;
                    case 'OSVERSION':
                        $this->Build->OSVersion = $value;
                        break;
                    case 'OSPLATFORM':
                        $this->Build->OSPlatform = $value;
                        break;
                    case 'COMPILERNAME':
                        $this->Build->CompilerName = $value;
                        break;
                    case 'COMPILERVERSION':
                        $this->Build->CompilerVersion = $value;
                        break;
                }
            }

            $this->Site->mostRecentInformation()->save($siteInformation);

            $this->Build->SiteId = $this->Site->id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
        } elseif ($name === 'FILE') {
            $this->initializeBuild();
            $this->UploadFile = new UploadFile();
            $this->UploadFile->Filename = $attributes['FILENAME'];
        } elseif ($name === 'CONTENT') {
            $fileEncoding = $attributes['ENCODING'] ?? 'base64';

            if (strcmp($fileEncoding, 'base64') != 0) {
                // Only base64 encoding is supported for file upload
                Log::error('Only base64 encoding is supported');
                $this->UploadError = true;
                return;
            }

            // Create tmp file
            $this->TmpFilename = tempnam(storage_path('app/tmp'), 'cdash_upload');
            if ($this->TmpFilename === false) {
                Log::error('Failed to create temporary filename');
                $this->UploadError = true;
                return;
            }
            $this->Base64TmpFilename = $this->TmpFilename . '-base64';

            // Open base64 temporary file for writing
            $this->Base64TmpFileWriteHandle = fopen($this->Base64TmpFilename, 'w');
            if (!$this->Base64TmpFileWriteHandle) {
                Log::error("Failed to open file '{$this->Base64TmpFilename}' for writing");
                $this->UploadError = true;
                return;
            }
        } elseif ($name === 'LABEL') {
            $this->Label = new Label();
        }
    }

    /**
     * Function endElement
     */
    public function endElement($parser, $name): void
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($this->UploadError) {
            return;
        }

        if ($name === 'FILE' && $parent === 'UPLOAD') {
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
            if (!unlink($this->Base64TmpFilename)) {
                Log::warning("Failed to delete file '{$this->Base64TmpFilename}'");
            }

            // Check file size against the upload quota
            $upload_file_size = filesize($this->TmpFilename);
            if ($upload_file_size > $this->GetProject()->UploadQuota) {
                Log::error("Size of uploaded file {$this->TmpFilename} is {$upload_file_size} bytes, which is greater than the total upload quota for this project ({$this->GetProject()->UploadQuota} bytes)");
                $this->UploadError = true;
                if (!unlink($this->TmpFilename)) {
                    Log::warning("Failed to delete file '{$this->TmpFilename}'");
                }
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

            if ($ext === 'url') {
                $this->UploadFile->IsUrl = true;

                // Read content of the file
                $url_length = 255; // max length of 'uploadfile.filename' field
                $this->UploadFile->Filename = trim(file_get_contents($this->TmpFilename, null, null, 0, $url_length));
            } else {
                $this->UploadFile->IsUrl = false;

                if ((bool) config('cdash.remote_workers')) {
                    // Make an API request to store this file.
                    $encrypted_sha1sum = encrypt($this->UploadFile->Sha1Sum);
                    $fp_to_upload = fopen($this->TmpFilename, 'r');
                    if ($fp_to_upload === false) {
                        Log::error("Failed to open temporary file {$this->TmpFilename} for upload");
                        unlink($this->TmpFilename);
                        $this->UploadError = true;
                        return;
                    }
                    $response = Http::attach(
                        'attachment', $fp_to_upload, (string) $this->UploadFile->Sha1Sum
                    )->post(url('/api/v1/store_upload'), [
                        'sha1sum' => $encrypted_sha1sum,
                    ]);
                    fclose($fp_to_upload);
                    if (!$response->successful()) {
                        Log::error('Error uploading file via API: ' . $response->status() . ' ' . $response->body());
                        unlink($this->TmpFilename);
                        $this->UploadError = true;
                        return;
                    }
                } else {
                    // Store the file if we don't already have it.
                    $uploadFilepath = "upload/{$this->UploadFile->Sha1Sum}";
                    if (!Storage::exists($uploadFilepath)) {
                        try {
                            $fileToUpload = new File($this->TmpFilename);
                        } catch (FileNotFoundException $e) {
                            Log::error("Could not find file {$this->TmpFilename} to upload");
                            unlink($this->TmpFilename);
                            $this->UploadError = true;
                            return;
                        }
                        if (Storage::putFileAs('upload', $fileToUpload, (string) $this->UploadFile->Sha1Sum) === false) {
                            Log::error("Failed to store {$this->TmpFilename} as {$uploadFilepath}");
                            unlink($this->TmpFilename);
                            $this->UploadError = true;
                            return;
                        }
                    }
                }
            }

            // Delete decoded temporary file.
            if (!unlink($this->TmpFilename)) {
                Log::error("Failed to delete file '{$this->TmpFilename}");
            }

            // Update model
            $success = $this->UploadFile->Insert();
            if (!$success) {
                Log::error("UploadFile model - Failed to insert row associated with file: '{$this->UploadFile->Filename}'");
            }
            $this->GetProject()->CullUploadedFiles();

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

        if ($parent === 'FILE') {
            switch ($element) {
                case 'CONTENT':
                    // Write base64 encoded chunch to temporary file
                    $charsToReplace = ["\r\n", "\n", "\r"];
                    fwrite($this->Base64TmpFileWriteHandle, str_replace($charsToReplace, '', $data));
                    break;
            }
        } elseif ($parent === 'UPLOAD' && $element === 'TIME') {
            $this->Timestamp = (int) $data;
        } elseif ($element === 'LABEL') {
            $this->Label->SetText($data);
        }
    }

    private function initializeBuild(): void
    {
        if ($this->BuildInitialized) {
            return;
        }

        if ($this->Timestamp > 0) {
            $dateTimeString = Carbon::createFromTimestampUTC($this->Timestamp)->format(FMT_DATETIME);
            $this->Build->EndTime = $dateTimeString;
            $this->Build->StartTime = $dateTimeString;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
        } else {
            // Setting start time and end time is tricky here, since all
            // we have is the build stamp.  The strategy we take here is:
            // Set the start time as late as possible, and set the end time
            // as early as possible.
            // This way we don't override any existing values for these fields
            // when we call UpdateBuild() below.
            //
            // For end time, we use the start of the testing day.
            // For start time, we use the end of the testing day.
            // Yes, this means the build finished before it began.
            //
            // This associates the build with the correct day if it is only
            // an upload.  Otherwise we defer to the values set by the
            // other handlers.
            $buildDate = SubmissionUtils::extract_date_from_buildstamp($this->Build->GetStamp());
            [$beginningOfDay, $endOfDay] = $this->GetProject()->ComputeTestingDayBounds($buildDate);

            $this->Build->EndTime = $beginningOfDay;
            $this->Build->StartTime = $endOfDay;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
        }

        $this->Build->ProjectId = $this->GetProject()->Id;
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
            SubmissionUtils::add_build($this->Build);
        } else {
            if ($this->Label) {
                $this->Build->InsertLabelAssociations();
            }

            // Otherwise make sure that the build is up-to-date.
            $this->Build->UpdateBuild($this->Build->Id, -1, -1);
        }
        $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->Build->Id;

        $this->BuildInitialized = true;
    }
}
