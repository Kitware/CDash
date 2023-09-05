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

namespace App\Services;

/**
 * This class handles page load time calculations, reporting, and logging.
 **/
class PageTimer
{
    protected float $start;
    protected float $end;
    protected float $duration;

    public function __construct()
    {
        $this->end = 0.0;
        $this->start = LARAVEL_START;
    }

    public function end(&$response)
    {
        $this->end = microtime(true);
        $this->duration = round($this->end - LARAVEL_START, 2);
        $response['generationtime'] = $this->duration;
    }
}
