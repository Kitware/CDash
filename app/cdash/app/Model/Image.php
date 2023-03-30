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

use CDash\Database;
use PDO;

class Image
{
    public $Id;
    public $Filename;
    public $Extension;
    public $Checksum;

    public $Data; // Loaded from database or the file referred by Filename.
    public $Name; // Use to track the role for test.
    /** @var Database $PDO */
    private $PDO;

    public function __construct()
    {
        $this->Filename = '';
        $this->Name = '';
        $this->PDO = Database::getInstance();
    }

    private function GetData()
    {
        if (strlen($this->Filename) > 0) {
            $h = fopen($this->Filename, 'rb');
            $this->Data = fread($h, filesize($this->Filename));
            fclose($h);
            unset($h);
        }
    }

    /** Check if exists */
    public function Exists()
    {
        $db = Database::getInstance();
        // If no id specify return false
        if ($this->Id) {
            ;
            $query_array = $db->executePreparedSingleRow('SELECT count(*) AS c FROM image WHERE id=?', [$this->Id]);
            if ($query_array['c'] == 0) {
                return false;
            }
            return true;
        } else {
            // Check if the checksum exists
            $query_array = $db->executePreparedSingleRow('SELECT id FROM image WHERE checksum=?', [$this->Checksum]);
            if (!empty($query_array)) {
                $this->Id = $query_array['id'];
                return true;
            }
            return false;
        }
    }

    /** Save the image */
    public function Save($update=false)
    {
        // Get the data from the file if necessary
        $this->GetData();

        if (!$this->Exists()) {
            $success = true;
            if ($this->Id) {
                $stmt = $this->PDO->prepare('
                        INSERT INTO image (id, img, extension, checksum)
                        VALUES (:id, :img, :extension, :checksum)');
                $stmt->bindParam(':id', $this->Id);
                $stmt->bindParam(':img', $this->Data, PDO::PARAM_LOB);
                $stmt->bindParam(':extension', $this->Extension);
                $stmt->bindParam(':checksum', $this->Checksum);
                $success = $this->PDO->execute($stmt);
            } else {
                $stmt = $this->PDO->prepare('
                        INSERT INTO image (img, extension, checksum)
                        VALUES (:img, :extension, :checksum)');
                $stmt->bindParam(':img', $this->Data, PDO::PARAM_LOB);
                $stmt->bindParam(':extension', $this->Extension);
                $stmt->bindParam(':checksum', $this->Checksum);
                $success = (bool) $this->Id = $this->PDO->insert($stmt);
            }
            if (!$success) {
                return false;
            }
        } elseif ($update) {
            // Update the current image.
            $stmt = $this->PDO->prepare('UPDATE image SET img=:img, extension=:extension, checksum=:checksum WHERE id=:id');
            $stmt->bindParam(':img', $this->Data, PDO::PARAM_LOB);
            $stmt->bindParam(':extension', $this->Extension);
            $stmt->bindParam(':checksum', $this->Checksum);
            $stmt->bindParam(':id', $this->Id);
            if (!$this->PDO->execute($stmt)) {
                return false;
            }
        }
        return true;
    }

    /** Load the image from the database. */
    public function Load()
    {
        $stmt = $this->PDO->prepare('SELECT * FROM image WHERE id=?');
        $this->PDO->execute($stmt, [$this->Id]);

        if (!$row = $stmt->fetch()) {
            return false;
        }

        $this->Extension = $row['extension'];
        $this->Checksum = $row['checksum'];

        if (config('database.default') == 'pgsql') {
            $this->Data = stream_get_contents($row['img']);
        } else {
            $this->Data = $row['img'];
        }
        return true;
    }
}
