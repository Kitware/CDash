<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Middleware\Queue;

require_once dirname(__DIR__) . '/../../include/do_submit.php';

use Bernard\Message;
use Bernard\Message\DefaultMessage;
use CDash\Log;

class SubmissionService
{
    public static function createSubmissionMessage(array $parameters)
    {
        return new DefaultMessage('DoSubmit', $parameters);
    }

    public function doSubmit(Message $message)
    {
        try {
            $fh = fopen($message->filename, 'r');
            do_submit($fh, $message->project_id, $message->expected_md5, false);
        } catch (\Exception $e) {
            Log::getInstance()->error($e);
            throw $e;
        }
    }
}
