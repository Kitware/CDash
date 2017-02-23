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
    public $StartTime;
    public $EndTime;
    public $Command;
    public $Log;
    public $Status;
    public $BuildId;
    public $Labels;
    public $NumberOfWarnings;
    public $NumberOfErrors;

    private $PDO;

    /**
     * BuildConfigure constructor.
     */
    public function __construct()
    {
        $this->PDO = get_link_identifier()->getPdo();
    }

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

        $query = pdo_query('SELECT COUNT(*) FROM configure WHERE buildid=' . qnum($this->BuildId));
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

        $query = pdo_query('DELETE FROM configure WHERE buildid=' . qnum($this->BuildId));
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

    // Save in the database
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

        $command = pdo_real_escape_string($this->Command);
        $log = pdo_real_escape_string($this->Log);
        $status = pdo_real_escape_string($this->Status);

        $query = 'INSERT INTO configure (buildid,starttime,endtime,command,log,status)
            VALUES (' . qnum($this->BuildId) . ",'$this->StartTime','$this->EndTime','$command','$log','$status')";
        if (!pdo_query($query)) {
            add_last_sql_error('BuildConfigure Insert', 0, $this->BuildId);
            return false;
        }

        $this->InsertLabelAssociations();
        return true;
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
            echo 'BuildConfigure::GetConfigureForBuild(): BuildId not set';
            return false;
        }

        $sql = "SELECT * FROM configure WHERE buildid=:buildid";
        $query = $this->PDO->prepare($sql);
        $query->bindParam(':buildid', $this->BuildId);

        return $query->fetch($fetchType);
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
                WHERE buildid=' . qnum($this->BuildId));
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
        $configure = pdo_query('SELECT status FROM configure WHERE buildid=' . qnum($this->BuildId));
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
