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

use App\Models\Image as EloquentImage;
use CDash\Database;
use Exception;

class Image
{
    public $Id;
    public $Filename;
    public $Extension;
    public $Checksum;

    public $Data; // Loaded from database or the file referred by Filename.
    public $Name; // Use to track the role for test.

    public function __construct()
    {
        $this->Filename = '';
        $this->Name = '';
    }

    private function GetData(): void
    {
        if (strlen($this->Filename) > 0) {
            $h = fopen($this->Filename, 'rb');
            $this->Data = fread($h, filesize($this->Filename));
            fclose($h);
            unset($h);
        }
    }

    /** Check if exists */
    private function Exists(): bool
    {
        $model = EloquentImage::where('id', (int) $this->Id)
            ->orWhere('checksum', $this->Checksum)
            ->first();
        if ($model === null) {
            return false;
        }

        $this->Id = $model->id;
        return true;
    }

    /** Save the image */
    public function Save($update = false): bool
    {
        // Get the data from the file if necessary
        $this->GetData();

        // Convert this string to a stream so Eloquent handles it as a LOB properly.
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new Exception('Unable to open stream.');
        }
        fwrite($stream, $this->Data);
        rewind($stream);

        if (!$this->Exists()) {
            $this->Id = EloquentImage::create([
                'img' => $stream,
                'extension' => $this->Extension,
                'checksum' => $this->Checksum,
            ])->id;
        } elseif ($update) {
            // Update the current image.
            return EloquentImage::findOrFail((int) $this->Id)->update([
                'img' => $stream,
                'extension' => $this->Extension,
                'checksum' => $this->Checksum,
            ]);
        }
        return true;
    }

    /** Load the image from the database. */
    public function Load(): bool
    {
        $image = EloquentImage::find((int) $this->Id);
        if ($image === null) {
            return false;
        }

        $this->Extension = $image->extension;
        $this->Checksum = $image->checksum;
        $this->Data = stream_get_contents($image->img);
        return true;
    }
}
