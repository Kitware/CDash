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
use CDash\Model\Build;
use CDash\Model\Image;
use Illuminate\Support\Facades\DB;

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
    private $testName;
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

    public function loadImage(Image $image): void
    {
        if ($image->Checksum) {
            return;
        }

        // Decode the data
        $imgStr = base64_decode($image->Data);
        $img = imagecreatefromstring($imgStr);
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

    public function saveImage(Image $image, $outputid): void
    {
        $image->Save();
        $testImage = new TestImage();
        $testImage->imgid = $image->Id;
        $testImage->outputid = $outputid;
        $testImage->role = $image->Name;
        $testImage->save();
    }

    public function computeCrc32(): int
    {
        $crc32_input = $this->testName;
        $crc32_input .= $this->testPath;
        $crc32_input .= $this->testCommand;
        $crc32_input .= $this->testOutput;
        $crc32_input .= $this->testDetails;
        foreach ($this->measurements as $measurement) {
            $crc32_input .= "_" . $measurement->type;
            $crc32_input .= "_" . $measurement->name;
            $crc32_input .= "_" . $measurement->value;
        }

        foreach ($this->images as $image) {
            $this->loadImage($image);
            $crc32_input .= "_{$image->Checksum}";
        }

        return crc32($crc32_input);
    }

    /**
     * Compress test output before storing it in the database.
     */
    public function compressOutput(): void
    {
        if ($this->alreadyCompressed) {
            if (config('database.default') === 'pgsql') {
                $compressed_output = $this->testOutput;
            } else {
                $compressed_output = base64_decode($this->testOutput);
            }
        } elseif (config('cdash.use_compression')) {
            $compressed_output = gzcompress($this->testOutput);
            if ($compressed_output === false) {
                $compressed_output = $this->testOutput;
            } else {
                if (config('database.default') == 'pgsql') {
                    if (strlen($this->testOutput) < 2000) {
                        // Compression doesn't help for small chunks.
                        $compressed_output = $this->testOutput;
                    }
                    $compressed_output = base64_encode($compressed_output);
                }
            }
        } else {
            $compressed_output = $this->testOutput;
        }
        $this->testOutput = $compressed_output;
    }

    /**
     * Set test name, truncated to 255 characters.
     */
    public function setTestName(string $testName): void
    {
        $this->testName = substr($testName, 0, 255);
    }

    /**
     * Record this test in the database.
     **/
    public function create(Build $build): void
    {
        $crc32 = $this->computeCrc32();

        // TODO: Convert this to Eloquent
        $outputid = DB::select('
            SELECT testoutput.id AS id
            FROM build
            INNER JOIN build2test ON build2test.buildid = build.id
            INNER JOIN testoutput ON testoutput.id = build2test.outputid
            WHERE
                build.projectid = ?
                AND testoutput.crc32 = ?
            LIMIT 1 -- There should theoretically only be at most one unique ID...
        ', [
            $this->projectid,
            $crc32,
        ]);

        // testoutput
        if (count($outputid) > 0) {
            $outputid = $outputid[0]->id;
        } else {
            $this->compressOutput();
            DB::insert(
                'INSERT INTO testoutput (path, command, output, crc32)
                VALUES (:path, :command, :output, :crc32)',
                [':path'    => $this->testPath,
                 ':command' => $this->testCommand,
                 ':output'  => $this->testOutput,
                 ':crc32'   => $crc32]);
            $outputid = DB::getPdo()->lastInsertId();

            // test2image
            foreach ($this->images as $image) {
                $this->saveImage($image, $outputid);
            }
        }

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
    }
}
