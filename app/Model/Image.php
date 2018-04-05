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
    private $PDO;

    public function __construct()
    {
        $this->Filename = '';
        $this->Name = '';
        $this->PDO = Database::getInstance()->getPdo();
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
        // If no id specify return false
        if ($this->Id) {
            $query = pdo_query("SELECT count(*) AS c FROM image WHERE id='" . $this->Id . "'");
            $query_array = pdo_fetch_array($query);
            if ($query_array['c'] == 0) {
                return false;
            }
            return true;
        } else {
            // Check if the checksum exists
            $query = pdo_query("SELECT id FROM image WHERE checksum='" . $this->Checksum . "'");
            if (pdo_num_rows($query) > 0) {
                $query_array = pdo_fetch_array($query);
                $this->Id = $query_array['id'];
                return true;
            }
            return false;
        }
        return true;
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
                $success = pdo_execute($stmt);
            } else {
                $stmt = $this->PDO->prepare('
                        INSERT INTO image (img, extension, checksum)
                        VALUES (:img, :extension, :checksum)');
                $stmt->bindParam(':img', $this->Data, PDO::PARAM_LOB);
                $stmt->bindParam(':extension', $this->Extension);
                $stmt->bindParam(':checksum', $this->Checksum);
                $success = pdo_execute($stmt);
                $this->Id = pdo_insert_id('image');
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
            if (!pdo_execute($stmt)) {
                return false;
            }
        }
        return true;
    }

    /** Load the image from the database. */
    public function Load()
    {
        if (!$this->Exists()) {
            return false;
        }

        $stmt = $this->PDO->prepare('SELECT * FROM image WHERE id=?');
        pdo_execute($stmt, [$this->Id]);
        $row = $stmt->fetch();

        $this->Extension = $row['extension'];
        $this->Checksum = $row['checksum'];

        global $CDASH_DB_TYPE;
        if ($CDASH_DB_TYPE == 'pgsql') {
            $this->Data = stream_get_contents($row['img']);
        } else {
            $this->Data = $row['img'];
        }
    }
}
