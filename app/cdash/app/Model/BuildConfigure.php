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

use Illuminate\Support\Facades\DB;
use PDO;
use CDash\Database;

/** BuildConfigure class */
class BuildConfigure
{
    public $Id;
    public $StartTime;
    public $EndTime;
    public $Command;
    public $Log;
    public $Status;
    public $BuildId;
    public $NumberOfWarnings;
    public $NumberOfErrors;
    private $Crc32;

    private $PDO;

    /**
     * BuildConfigure constructor.
     */
    public function __construct()
    {
        $this->Command = '';
        $this->Log = '';
        $this->Status = '';
        $this->LabelCollection = collect();
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function AddLabel($label)
    {
        $label->BuildId = $this->BuildId;
        $this->LabelCollection->put($label->Text, $label);
    }

    /** Check if the configure exists */
    public function Exists()
    {
        // Check by Id if it is set.
        if ($this->Id > 0) {
            return $this->ExistsHelper('id', $this->Id);
        }

        // Next, try crc32.
        if ($this->Command !== '' && $this->Log !== '' && $this->Status !== '') {
            return $this->ExistsByCrc32();
        }

        // Lastly, try buildid.
        return $this->ExistsByBuildId();
    }

    /** Check if a configure record exists for a given field and value.
     *  Populate this object from the database if such a record is found.
     */
    private function ExistsHelper($field, $value)
    {
        $stmt = $this->PDO->prepare("SELECT * FROM configure WHERE $field = ?");
        pdo_execute($stmt, [$value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $this->Id = $row['id'];
            $this->Command = $row['command'];
            $this->Log = $row['log'];
            $this->NumberOfErrors = $row['status'];
            $this->NumberOfWarnings = $row['warnings'];
            $this->Crc32 = $row['crc32'];
            return true;
        }
        return false;
    }

    /** Check if a configure record exists for these contents. */
    public function ExistsByCrc32()
    {
        if ($this->Command === '' || $this->Status === '') {
            return false;
        }
        $this->Crc32 = crc32($this->Command . $this->Log . $this->Status);
        return $this->ExistsHelper('crc32', $this->Crc32);
    }

    /** Check if a configure record exists for this Id. */
    public function ExistsByBuildId()
    {
        if (!$this->BuildId) {
            add_log('BuildId not set',
                'BuildConfigure::Exists', LOG_ERR,
                0, 0, ModelType::CONFIGURE, 0);
            return false;
        }
        if (!is_numeric($this->BuildId)) {
            add_log('BuildId is not numeric',
                'BuildConfigure::Exists', LOG_ERR,
                0, 0, ModelType::CONFIGURE, 0);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT configureid FROM build2configure WHERE buildid = ?');
        if (!pdo_execute($stmt, [$this->BuildId])) {
            return false;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }
        return $this->ExistsHelper('id', $row['configureid']);
    }

    /** Delete a current configure given a buildid
      * Returns true if the configure row was deleted from the database.
      */
    public function Delete()
    {
        if (!$this->Exists()) {
            add_log('this configure does not exist',
                'BuildConfigure::Delete', LOG_ERR,
                0, 0, ModelType::CONFIGURE, 0);
            return false;
        }

        // Delete the configure row if it is not shared with any other build.
        $retval = false;
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) AS c FROM build2configure
            WHERE configureid = ?');
        pdo_execute($stmt, [$this->Id]);
        $row = $stmt->fetch();
        if ($row['c'] < 2) {
            DB::delete('DELETE FROM configure WHERE id = ?', [$this->Id]);
            $retval = true;
        }

        if ($this->BuildId) {
            // Delete the build2configure row for this build.
            DB::delete('DELETE FROM build2configure WHERE buildid = ?', [$this->BuildId]);
        }

        return $retval;
    }

    public function InsertLabelAssociations()
    {
        if ($this->BuildId) {
            if ($this->LabelCollection->isEmpty()) {
                return;
            }

            foreach ($this->LabelCollection as $label) {
                $label->BuildId = $this->BuildId;
                $label->Insert();
            }
        } else {
            add_log('No BuildConfigure::BuildId - cannot call $label->Insert...',
                'BuildConfigure::InsertLabelAssociations', LOG_ERR,
                0, $this->BuildId, ModelType::CONFIGURE, $this->BuildId);
        }
    }

    // Save in the database.  Returns true is a new configure row was created,
    // false otherwise.
    public function Insert()
    {
        if (!$this->BuildId) {
            add_log('BuildId not set',
                'BuildConfigure::Insert', LOG_ERR,
                0, 0, ModelType::CONFIGURE, $this->Id);
            return false;
        }

        if ($this->ExistsByBuildId()) {
            add_log('This build already has a configure',
                'BuildConfigure::Insert', LOG_ERR,
                0, $this->BuildId, ModelType::CONFIGURE, $this->Id);
            return false;
        }

        $this->PDO->beginTransaction();
        $new_configure_inserted = false;
        if (!$this->ExistsByCrc32()) {
            // No such configure exists yet, insert a new row.
            $stmt = $this->PDO->prepare('
                INSERT INTO configure (command, log, status, crc32)
                VALUES (:command, :log, :status, :crc32)');
            $stmt->bindParam(':command', $this->Command);
            $stmt->bindParam(':log', $this->Log);
            $stmt->bindParam(':status', $this->Status);
            $stmt->bindParam(':crc32', $this->Crc32);
            try {
                if ($stmt->execute()) {
                    $new_configure_inserted = true;
                    $this->Id = pdo_insert_id('configure');
                } else {
                    $error_info = $stmt->errorInfo();
                    $error = $error_info[2];
                    throw new \Exception($error);
                }
            } catch (\Exception $e) {
                // This error might be due to a unique constraint violation.
                // Query again to see if this configure was created since
                // the last time we checked.
                $this->PDO->rollBack();
                if ($this->ExistsByCrc32()) {
                    return true;
                } else {
                    add_log($e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                        'Configure Insert', LOG_ERR);
                    return false;
                }
            }
        }

        // Insert a new build2configure row for this build.
        $stmt = $this->PDO->prepare('
            INSERT INTO build2configure (buildid, configureid, starttime, endtime)
            VALUES (:buildid, :configureid, :starttime, :endtime)');
        $stmt->bindParam(':buildid', $this->BuildId);
        $stmt->bindParam(':configureid', $this->Id);
        $stmt->bindParam(':starttime', $this->StartTime);
        $stmt->bindParam(':endtime', $this->EndTime);
        if (!pdo_execute($stmt)) {
            $this->PDO->rollBack();
            return false;
        }

        $this->PDO->commit();
        $this->InsertLabelAssociations();
        return $new_configure_inserted;
    }

    /** Return true if the specified line contains a configure warning,
     * false otherwise.
     */
    public static function IsConfigureWarning($line)
    {
        if (strpos($line, 'CMake Warning') !== false ||
            strpos($line, 'WARNING:') !== false
        ) {
            return true;
        }
        return false;
    }

    /**
     * Returns configurations for the build
     *
     * @param int $fetchType
     * @return bool|mixed
     */
    public function GetConfigureForBuild($fetchType = PDO::FETCH_ASSOC)
    {
        if (!$this->BuildId) {
            add_log('BuildId not set', 'BuildConfigure::GetConfigureForBuild()', LOG_WARNING);
            return false;
        }

        $sql =
            'SELECT * FROM configure c
            JOIN build2configure b2c ON c.id = b2c.configureid
            WHERE buildid = ?';
        $query = $this->PDO->prepare($sql);

        pdo_execute($query, [$this->BuildId]);

        return $query->fetch($fetchType);
    }

    /** Compute the warnings from the log. In the future we might want to add errors */
    public function ComputeWarnings()
    {
        $this->NumberOfWarnings = 0;
        $log_lines = explode("\n", $this->Log);
        $numlines = count($log_lines);

        $stmt = $this->PDO->prepare(
            'INSERT INTO configureerror (configureid, type, text)
             VALUES (:id, 1, :text)');
        $stmt->bindParam(':id', $this->Id);

        for ($l = 0; $l < $numlines; $l++) {
            if ($this->IsConfigureWarning($log_lines[$l])) {
                $precontext = '';
                $postcontext = '';

                // Get 2 lines of precontext
                $pre_start = max($l - 2, 0);
                for ($j = $pre_start; $j < $l; $j++) {
                    $precontext .= $log_lines[$j] . "\n";
                }

                // Get 5 lines of postcontext
                $post_end = min($l + 6, $numlines);
                for ($j = $l + 1; $j < $post_end; $j++) {
                    $postcontext .= $log_lines[$j] . "\n";
                }

                // Add the warnings in the configureerror table
                $warning = $precontext . $log_lines[$l] . "\n" . $postcontext;
                $stmt->bindParam(':text', $warning);
                pdo_execute($stmt);

                $this->NumberOfWarnings++;
            }
        }

        $stmt = $this->PDO->prepare(
            'UPDATE configure SET warnings = :numwarnings WHERE id = :id');
        $stmt->bindParam(':numwarnings', $this->NumberOfWarnings);
        $stmt->bindParam(':id', $this->Id);
        pdo_execute($stmt);
    }

    /** Get the number of configure error for a build */
    public function ComputeErrors()
    {
        if (!$this->Exists()) {
            return 0;
        }
        return $this->NumberOfErrors;
    }

    public static function marshal($data)
    {
        $response = [
            'status' => $data['status'],
            'command' => $data['command'],
            'output' => $data['log'],
            'configureerrors' => $data['configureerrors'],
            'configurewarnings' => $data['configurewarnings'],
        ];

        if (isset($data['subprojectid'])) {
            $response['subprojectid'] = $data['subprojectid'];
            $response['subprojectname'] = $data['subprojectname'];
        }

        return $response;
    }

    /**
     * This method returns the URI for the given BuildConfigure id, or the URI for the current
     * BuildConfigure if no id is provided.
     *
     * @param null $default_id
     * @return string
     */
    public function getURL($default_id = null)
    {
        $config = \CDash\Config::getInstance();
        $id = is_null($default_id) ? $this->BuildId : $default_id;
        return "{$config->getBaseUrl()}/build/{$id}/configure";
    }

    /**
     * Returns the current BuildConfigure's Label property as a LabelCollection.
     * @return Collection
     */
    public function GetLabelCollection()
    {
        return $this->LabelCollection;
    }
}
