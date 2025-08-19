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

namespace App\Utils;

use App\Models\Test;
use App\Models\TestImage;
use App\Models\TestOutput;
use CDash\Model\Build;
use CDash\Model\Image;
use ErrorException;
use Illuminate\Support\Facades\Log;

/**
 * This class is responsible for creating the various models associated
 * with a single run of a test.
 **/
class TestCreator
{
    public $alreadyCompressed;
    public $buildTestTime;
    public $projectid;

    // Collections.
    public $images;
    public $labels;
    public $measurements;

    public $testCommand;
    public $testDetails;
    public $testOutput;
    public $testName;
    public $testPath;
    public $testStatus;

    public function __construct()
    {
        $this->buildTestTime = 0.0;
        $this->alreadyCompressed = false;

        $this->images = collect();
        $this->labels = collect();
        $this->measurements = collect();

        $this->testCommand = '';
        $this->testDetails = '';
        $this->testName = '';
        $this->testOutput = '';
        $this->testPath = '';
        $this->testStatus = '';
    }

    private function saveImage(Image $image, int $testid): void
    {
        if (!$image->Checksum) {
            // Decode the data
            $imgStr = base64_decode($image->Data);
            try {
                $img = imagecreatefromstring($imgStr);
            } catch (ErrorException) {
                // Unable to create a valid image, substitute the broken image from CDash
                Log::error("Unable to create image object from data in #{$this->testName}");
                $image->Extension = 'image/png';
                $img = imagecreatefrompng(public_path('/img/image_missing.png'));
            }

            ob_start();
            switch ($image->Extension) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($img);
                    break;
                case 'image/gif':
                    imagegif($img);
                    break;
                case 'image/png':
                    imagepng($img);
                    break;
                default:
                    echo "Unknown image type: {$image->Extension}";
                    return;
            }
            $imageVariable = ob_get_contents();
            ob_end_clean();

            $image->Data = $imageVariable;
            $image->Checksum = crc32($imageVariable);
        }

        $image->Save();

        $testImage = new TestImage();
        $testImage->imgid = $image->Id;
        $testImage->testid = $testid;
        $testImage->role = $image->Name;
        $testImage->save();
    }

    /**
     * Record this test in the database.
     **/
    public function create(Build $build): void
    {
        // Truncate testName to 255 characters.
        $this->testName = substr($this->testName, 0, 255);

        // Decompress before database insertion if the data was compressed by CTest
        if ($this->alreadyCompressed) {
            $this->testOutput = base64_decode($this->testOutput);
            $this->testOutput = gzuncompress($this->testOutput);
        }

        // Store nothing if we can't convert to UTF-8
        if (mb_detect_encoding($this->testOutput, 'UTF-8', true) === false) {
            $this->testOutput = mb_convert_encoding($this->testOutput, 'UTF-8', 'UTF-8');
            if ($this->testOutput === false) {
                Log::error("Unable to encode {$this->testName} output as UTF-8");
                $this->testOutput = '';
            }
        }

        $outputid = TestOutput::firstOrCreate([
            'path' => $this->testPath,
            'command' => $this->testCommand,
            'output' => $this->testOutput,
        ])->id;

        // build2test
        $buildtest = new Test();
        $buildtest->buildid = $build->Id;
        $buildtest->outputid = $outputid;
        $buildtest->status = $this->testStatus;
        $buildtest->details = $this->testDetails;
        $buildtest->time = "$this->buildTestTime";
        $buildtest->testname = $this->testName;

        // Note: the newstatus column is currently handled in
        // ctestparserutils::compute_test_difference. This gets updated when we call
        // Build::ComputeTestTiming.
        $buildtest->save();

        foreach ($this->measurements as $measurement) {
            $measurement->testid = $buildtest->id;
            $measurement->save();
        }

        // Give measurements to the buildtest model so we can properly calculate
        // proctime later on.
        $buildtest->measurements = $this->measurements;
        $build->AddTest($buildtest);

        foreach ($this->labels as $label) {
            $label->Test = $buildtest;
            $label->Insert();
            $buildtest->addLabel($label);
        }

        // test2image
        foreach ($this->images as $image) {
            $this->saveImage($image, $buildtest->id);
        }
    }
}
