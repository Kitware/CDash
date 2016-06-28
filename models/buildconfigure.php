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

include_once 'models/buildconfigureerror.php';
include_once 'models/buildconfigureerrordiff.php';

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
    public $Labels;
    public $NumberOfWarnings;
    public $NumberOfErrors;
    private $Crc32;

    public function AddError($error)
    {
        $error->BuildId = $this->BuildId;
        $error->Save();
    }

    public function AddErrorDifference($diff)
    {
        $diff->BuildId = $this->BuildId;
        $diff->Save();
    }

    public function AddLabel($label)
    {
        if (!isset($this->Labels)) {
            $this->Labels = array();
        }

        $label->BuildId = $this->BuildId;
        $this->Labels[] = $label;
    }

    /** Check if the configure exists */
    public function Exists()
    {
        if (!$this->BuildId) {
            echo 'BuildConfigure::Exists(): BuildId not set';
            return false;
        }

        if (!is_numeric($this->BuildId)) {
            echo 'BuildConfigure::Exists(): Buildid is not numeric';
            return false;
        }

        $query = pdo_query('SELECT COUNT(*) FROM build2configure WHERE buildid=' . qnum($this->BuildId));
        if (!$query) {
            add_last_sql_error('BuildConfigure Exists()', 0, $this->BuildId);
            return false;
        }

        $query_array = pdo_fetch_array($query);
        if ($query_array[0] > 0) {
            return true;
        }
        return false;
    }

    /** Delete a current configure given a buildid */
    public function Delete()
    {
        if (!$this->BuildId) {
            echo 'BuildConfigure::Delete(): BuildId not set';
            return false;
        }

        // Delete the configure row if it is not shared with any other build.
        $count_row = pdo_single_row_query(
            'SELECT configureid, COUNT(*) AS c FROM build2configure
            WHERE buildid=' . qnum($this->BuildId) . ' GROUP BY configureid');
        if ($count_row['c'] > 1) {
            pdo_query(
                'DELETE FROM configure WHERE id = ' . qnum($count_row['configureid']));
        }

        // Delete the build2configure row for this build.
        $query = pdo_query('DELETE FROM build2configure WHERE buildid=' . qnum($this->BuildId));
        if (!$query) {
            add_last_sql_error('BuildConfigure Delete()', 0, $this->BuildId);
            return false;
        }
        return true;
    }

    public function InsertLabelAssociations()
    {
        if ($this->BuildId) {
            if (!isset($this->Labels)) {
                return;
            }

            foreach ($this->Labels as $label) {
                $label->BuildId = $this->BuildId;
                $label->Insert();
            }
        } else {
            add_log('No BuildConfigure::BuildId - cannot call $label->Insert...',
                'BuildConfigure::InsertLabelAssociations', LOG_ERR,
                0, $this->BuildId, CDASH_OBJECT_CONFIGURE, $this->BuildId);
        }
    }

    // Save in the database.  Returns true is a new configure row was created,
    // false otherwise.
    public function Insert()
    {
        if (!$this->BuildId) {
            echo 'BuildConfigure::Insert(): BuildId not set';
            return false;
        }

        if ($this->Exists()) {
            echo 'BuildConfigure::Exists(): Cannot insert new configure. Use Delete() first';
            return false;
        }

        $this->Crc32 = crc32($this->Command . $this->Log . $this->Status);

        $pdo = get_link_identifier()->getPdo();
        $pdo->beginTransaction();

        $exists_stmt = $pdo->prepare(
                'SELECT id FROM configure WHERE crc32=?');
        $exists_stmt->execute(array($this->Crc32));
        $exists_row = $exists_stmt->fetch(PDO::FETCH_ASSOC);
        $new_configure_inserted = false;

        if (is_array($exists_row)) {
            $this->Id = $exists_row['id'];
        } else {
            // No such configure exists yet, insert a new row.
            $stmt = $pdo->prepare('
                INSERT INTO configure (command, log, status, crc32)
                VALUES (:command, :log, :status, :crc32)');
            $stmt->bindParam('command', $this->Command);
            $stmt->bindParam('log', $this->Log);
            $stmt->bindParam('status', $this->Status);
            $stmt->bindParam('crc32', $this->Crc32);
            if (!$stmt->execute()) {
                $error = pdo_error(null, false);
                // This error might be due to a unique constraint violation.
                // Query again to see if this configure was created since
                // the last time we checked.
                $exists_stmt->execute(array($this->Crc32));
                $exists_row = $exists_stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($exists_row)) {
                    $this->Id = $exists_row['id'];
                } else {
                    add_last_sql_error('BuildConfigure Insert', 0, $this->BuildId);
                    $pdo->rollBack();
                    return false;
                }
            }
            $new_configure_inserted = true;
            $this->Id = pdo_insert_id('configure');
        }

        // Insert a new build2configure row for this build.
        $stmt = $pdo->prepare('
            INSERT INTO build2configure (buildid, configureid, starttime, endtime)
            VALUES (:buildid, :configureid, :starttime, :endtime)');
        $stmt->bindParam('buildid', $this->BuildId);
        $stmt->bindParam('configureid', $this->Id);
        $stmt->bindParam('starttime', $this->StartTime);
        $stmt->bindParam('endtime', $this->EndTime);
        if (!$stmt->execute()) {
            add_last_sql_error('Build2Configure Insert', 0, $this->BuildId);
            $pdo->rollBack();
            return false;
        }

        $pdo->commit();
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

    /** Compute the warnings from the log. In the future we might want to add errors */
    public function ComputeWarnings()
    {
        $this->NumberOfWarnings = 0;
        $log_lines = explode("\n", $this->Log);
        $numlines = count($log_lines);

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
                $warning = pdo_real_escape_string($precontext . $log_lines[$l] . "\n" . $postcontext);

                pdo_query("INSERT INTO configureerror (buildid,type,text)
                        VALUES ('$this->BuildId','1','$warning')");
                add_last_sql_error('BuildConfigure ComputeWarnings', 0, $this->BuildId);
                $this->NumberOfWarnings++;
            }
        }

        pdo_query(
            'UPDATE configure SET warnings=' . qnum($this->NumberOfWarnings) . '
                WHERE id=' . qnum($this->Id));
        add_last_sql_error('BuildConfigure ComputeWarnings', 0, $this->BuildId);
    }

    /** Get the number of configure error for a build */
    public function ComputeErrors()
    {
        if (!$this->BuildId) {
            echo 'BuildConfigure::ComputeErrors(): BuildId not set';
            return false;
        }

        $this->NumberOfErrors = 0;
        $configure = pdo_query(
            'SELECT status FROM configure c
            JOIN build2configure b2c ON (b2c.configureid=c.id)
            WHERE buildid=' . qnum($this->BuildId));
        if (!$configure) {
            add_last_sql_error('BuildConfigure ComputeErrors', 0, $this->BuildId);
            return false;
        }
        $configure_array = pdo_fetch_array($configure);
        if ($configure_array['status'] != 0) {
            $this->NumberOfErrors = $configure_array['status'];
        }
        return $this->NumberOfErrors;
    }

    public static function marshal($data)
    {
        $response = array(
            'status' => $data['status'],
            'command' => $data['command'],
            'output' => $data['log'],
            'configureerrors' => $data['configureerrors'],
            'configurewarnings' => $data['configurewarnings']
        );

        if (isset($data['subprojectid'])) {
            $response['subprojectid'] = $data['subprojectid'];
            $response['subprojectname'] = $data['subprojectname'];
        }

        return $response;
    }
}
