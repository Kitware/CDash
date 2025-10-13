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

use App\Models\BuildProperties;
use CDash\Model\Build;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BuildPropertiesJSONHandler extends AbstractSubmissionHandler
{
    public function __construct(Build $build)
    {
        parent::__construct($build);
    }

    /**
     * Parse the build properties file.
     **/
    public function Parse($filename): bool
    {
        // Test that this file contains valid JSON that PHP can decode.
        $json_str = Storage::get($filename);
        if ($json_str === null) {
            Log::error("Failed to retrieve $filename from Storage", [
                'function' => 'BuildPropertiesJSONHandler::Parse',
            ]);
            return false;
        }
        $json_obj = json_decode($json_str, true);
        if ($json_obj === null) {
            $err = json_last_error_msg();
            Log::error("Failed to parse $filename: $err", [
                'function' => 'BuildPropertiesJSONHandler::Parse',
            ]);
            return false;
        }

        BuildProperties::upsert([
            'buildid' => (int) $this->Build->Id,
            'properties' => json_encode($json_obj),
        ], 'buildid');

        return true;
    }
}
