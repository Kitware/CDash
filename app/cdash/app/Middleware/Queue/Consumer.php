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

class Consumer extends \Bernard\Consumer
{
    public function bind()
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, array($this, 'shutdown'));
            pcntl_signal(SIGQUIT, array($this, 'shutdown'));
            pcntl_signal(SIGINT, array($this, 'shutdown'));
        }
    }
}
