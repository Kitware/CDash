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

namespace CDash\Controller\Api;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TestDetails extends BuildTestApi
{
    public $buildtest;

    public function getResponse(): JsonResponse|StreamedResponse
    {
        // If we have a fileid we download it.
        if (isset($_GET['fileid']) && is_numeric($_GET['fileid'])) {
            $query = DB::select("
                SELECT
                    id,
                    value,
                    name
                FROM testmeasurement
                WHERE
                    testid = ?
                    AND type = 'file'
                ORDER BY id
            ", [$this->buildtest->id])[$_GET['fileid'] - 1];

            return response()->streamDownload(
                function () use ($query): void {
                    echo base64_decode($query->value);
                },
                $query->name . '.tgz',
                [
                    'Content-Disposition' => 'attachment',
                    'Content-type' => 'tar/gzip',
                ]
            );
        }

        throw new Exception('fileid query parameter is required');
    }
}
