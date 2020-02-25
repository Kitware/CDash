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
namespace CDash\Model;

use CDash\Config;
use CDash\Database;
use PDO;

/** Test */
class Test
{
    const FAILED = 'failed';
    const PASSED = 'passed';
    const OTHER_FAULT = 'OTHER_FAULT';
    const TIMEOUT = 'Timeout';
    const NOTRUN = 'notrun';
    const DISABLED = 'Disabled';

    public $Id;
    public $Crc32;
    public $ProjectId;
    public $Name;
    public $Path;
    public $Command;
    public $Details;
    public $Output;
    public $CompressedOutput;

    public $Images;
    public $Measurements;

    private $LabelCollection;
    private $Status;
    private $BuildTest;

    public function __construct()
    {
        $this->Command = '';
        $this->Details = '';
        $this->Name = '';
        $this->Path = '';

        $this->Images = [];
        $this->Measurements = [];
        $this->CompressedOutput = false;
        $this->LabelCollection = collect();
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function AddMeasurement($measurement)
    {
        $measurement->TestId = $this->Id;
        $this->Measurements[] = $measurement;

        if ($measurement->Name == 'Label') {
            $label = new Label();
            $label->SetText($measurement->Value);
            $this->AddLabel($label);
        }
    }

    public function AddImage($image)
    {
        $this->Images[] = $image;
    }

    public function AddLabel($label)
    {
        $label->TestId = $this->Id;
        $this->LabelCollection->put($label->Text, $label);
    }

    /** Get the CRC32 */
    public function GetCrc32()
    {
        if (strlen($this->Crc32) > 0) {
            return $this->Crc32;
        }

        $command = pdo_real_escape_string($this->Command);
        $output = pdo_real_escape_string($this->Output);
        $name = pdo_real_escape_string($this->Name);
        $path = pdo_real_escape_string($this->Path);
        $details = pdo_real_escape_string($this->Details);

        // CRC32 is computed with the measurements name and type and value
        $buffer = $name . $path . $command . $output . $details;

        foreach ($this->Measurements as $measurement) {
            $buffer .= $measurement->Type . $measurement->Name . $measurement->Value;
        }
        $this->Crc32 = crc32($buffer);
        return $this->Crc32;
    }

    public function InsertLabelAssociations($buildid)
    {
        if ($this->Id && $buildid) {
            foreach ($this->LabelCollection as $label) {
                $label->TestId = $this->Id;
                $label->TestBuildId = $buildid;
                $label->Insert();
            }
        } else {
            add_log('No Test::Id or buildid - cannot call $label->Insert...',
                'Test::InsertLabelAssociations', LOG_ERR,
                $this->ProjectId, $buildid,
                ModelType::TEST, $this->Id);
        }
    }

    /** Return if exists */
    public function Exists()
    {
        $name = pdo_real_escape_string($this->Name);
        $crc32 = $this->GetCrc32();
        $query = pdo_query('SELECT id FROM test WHERE projectid=' . qnum($this->ProjectId)
            . " AND name='" . $name . "'"
            . " AND crc32='" . $crc32 . "'");
        if (pdo_num_rows($query) > 0) {
            $query_array = pdo_fetch_array($query);
            $this->Id = $query_array['id'];
            return true;
        }
        return false;
    }

    // Save in the database
    public function Insert()
    {
        $config = Config::getInstance();
        if ($this->Exists()) {
            return true;
        }

        $id = '';
        $idvalue = '';
        if ($this->Id) {
            $id = 'id,';
            $idvalue = "'" . $this->Id . "',";
        }

        if ($this->CompressedOutput) {
            if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
                $output = $this->Output;
            } else {
                $output = base64_decode($this->Output);
            }
        } elseif ($config->get('CDASH_USE_COMPRESSION')) {
            $output = gzcompress($this->Output);
            if ($output === false) {
                $output = $this->Output;
            } else {
                if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
                    if (strlen($this->Output) < 2000) {
                        // compression doesn't help for small chunk

                        $output = $this->Output;
                    }
                    $output = base64_encode($output);
                }
            }
        } else {
            $output = $this->Output;
        }

        // We check for mysql that the
        if ($config->get('CDASH_DB_TYPE') == '' || $config->get('CDASH_DB_TYPE') == 'mysql') {
            $query = pdo_query("SHOW VARIABLES LIKE 'max_allowed_packet'");
            $query_array = pdo_fetch_array($query);
            $max = $query_array[1];
            if (strlen($this->Output) > $max) {
                add_log('Output is bigger than max_allowed_packet', 'Test::Insert', LOG_ERR, $this->ProjectId);
                // We cannot truncate the output because it is compressed (too complicated)
            }
        }

        if ($this->Id) {
            $stmt = $this->PDO->prepare('
                INSERT INTO test (id, projectid, crc32, name, path, command, details, output)
                VALUES (:id, :projectid, :crc32, :name, :path, :command, :details, :output)');
            $stmt->bindParam(':id', $this->Id);
            $stmt->bindParam(':projectid', $this->ProjectId);
            $stmt->bindParam(':crc32', $this->Crc32);
            $stmt->bindParam(':name', $this->Name);
            $stmt->bindParam(':path', $this->Path);
            $stmt->bindParam(':command', $this->Command);
            $stmt->bindParam(':details', $this->Details);
            $stmt->bindParam(':output', $output, PDO::PARAM_LOB);
            $success = pdo_execute($stmt);
        } else {
            $stmt = $this->PDO->prepare('
                INSERT INTO test (projectid, crc32, name, path, command, details, output)
                VALUES (:projectid, :crc32, :name, :path, :command, :details, :output)');
            $stmt->bindParam(':projectid', $this->ProjectId);
            $stmt->bindParam(':crc32', $this->Crc32);
            $stmt->bindParam(':name', $this->Name);
            $stmt->bindParam(':path', $this->Path);
            $stmt->bindParam(':command', $this->Command);
            $stmt->bindParam(':details', $this->Details);
            $stmt->bindParam(':output', $output, \PDO::PARAM_LOB);
            $success = pdo_execute($stmt);
            $this->Id = pdo_insert_id('test');
        }

        if (!$success) {
            return false;
        }

        // Add the measurements
        foreach ($this->Measurements as $measurement) {
            $measurement->TestId = $this->Id;
            $measurement->Insert();
        }

        // Add the images
        foreach ($this->Images as $image) {
            // Decode the data
            $imgStr = base64_decode($image->Data);
            $img = imagecreatefromstring($imgStr);
            ob_start();
            switch ($image->Extension) {
                case 'image/jpg':
                    imagejpeg($img);
                    break;
                case 'image/jpeg':
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
            $image->Save();

            $testImage = new TestImage();
            $testImage->Id = $image->Id;
            $testImage->TestId = $this->Id;
            $testImage->Role = $image->Name;
            $testImage->Insert();
        }
        return true;
    }

    /**
     * @return BuildTest
     */
    public function GetBuildTest()
    {
        if (!$this->BuildTest) {
            $this->BuildTest = new BuildTest();
            $this->BuildTest->TestId = $this->Id;
        }
        return $this->BuildTest;
    }

    /**
     * @param BuildTest $BuildTest
     */
    public function SetBuildTest(BuildTest $BuildTest)
    {
        $this->BuildTest = $BuildTest;
    }

    public function GetStatus()
    {
        $buildTest = $this->GetBuildTest();
        return $buildTest->Status;
    }

    /**
    /**
     * Returns the LabelCollection object for the current Test.
     *
     * @return Collection
     */
    public function GetLabelCollection()
    {
        return $this->LabelCollection;
    }

    /**
     * Returns the ExecutionTime property of the current Test.
     *
     * @return double
     */
    public function GetExecutionTime()
    {
        $buildTest = $this->GetBuildTest();
        return (double)$buildTest->Time;
    }

    /**
     * Returns true if the test has failed.
     *
     * @return bool
     */
    public function IsFailed()
    {
        return $this->GetStatus() === self::FAILED;
    }

    /**
     * Returns true if the test has not run.
     *
     * @return bool
     */
    public function IsNotRun()
    {
        return $this->GetStatus() === self::NOTRUN;
    }

    /**
     * Returns a self referencing URI for the current Test.
     *
     * @return string
     */
    public function GetUrlForSelf()
    {
        $config = \CDash\Config::getInstance();
        $host_base = $config->getBaseUrl();
        return "{$host_base}/testDetails.php?test={$this->Id}&build={$this->BuildTest->BuildId}";
    }

    /**
     * Returns uncompressed test output.
     *
     * @return string
     */
    public static function DecompressOutput($output)
    {
        $config = \CDash\Config::getInstance();
        if (!$config->get('CDASH_USE_COMPRESSION')) {
            return $output;
        }
        if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
            if (is_resource($output)) {
                $output = base64_decode(stream_get_contents($output));
            } else {
                $output = base64_decode($output);
            }
        }
        @$uncompressedrow = gzuncompress($output);
        if ($uncompressedrow !== false) {
            $output = $uncompressedrow;
        }
        return $output;
    }
}
