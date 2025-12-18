<?php

namespace App\Http\Submission\Handlers;

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

use Illuminate\Support\Facades\Storage;

/** Because this class uses SimpleXML it is only suitable for use with small
 * XML files that can fit into memory.
 **/
class RetryHandler
{
    private string $FileName;
    public int $Retries = 0;

    public function __construct(string $filename)
    {
        $this->FileName = $filename;
    }

    /** Increments the "retries" attribute on the root element of the specified
     * XML  file,or sets it to 1 if it does not yet exist.
     **/
    public function increment(): bool
    {
        if (!Storage::exists($this->FileName)) {
            return false;
        }

        $contents = Storage::get($this->FileName);
        if ($contents === null) {
            return false;
        }

        $xml = simplexml_load_string($contents);
        if ($xml === false) {
            return false;
        }
        $attributes = $xml->attributes();
        if (isset($attributes['retries'])) {
            $this->Retries = (int) $attributes['retries'] + 1;
        } else {
            $this->Retries = 1;
        }
        $xml['retries'] = $this->Retries;

        return Storage::put($this->FileName, (string) $xml->asXML());
    }
}
