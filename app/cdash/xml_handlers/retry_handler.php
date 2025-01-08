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

/** Because this class uses SimpleXML it is only suitable for use with small
 * XML files that can fit into memory.
 **/
class RetryHandler
{
    private $FileName;
    public int $Retries;

    public function __construct($filename)
    {
        $this->FileName = $filename;
    }

    /** Increments the "retries" attribute on the root element of the specified
     * XML  file,or sets it to 1 if it does not yet exist.
     **/
    public function increment()
    {
        if (!file_exists($this->FileName)) {
            return false;
        }

        $xml = simplexml_load_file($this->FileName);
        $attributes = $xml->attributes();
        if (isset($attributes['retries'])) {
            $this->Retries = intval($attributes['retries']) + 1;
        } else {
            $this->Retries = 1;
        }
        $xml['retries'] = $this->Retries;

        return $xml->asXML($this->FileName);
    }
}
