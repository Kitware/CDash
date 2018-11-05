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

namespace CDash\Lib\Parser;

/**
 * Interface ParserInterface
 * @package CDash\Lib\Parser
 */
interface ParserInterface
{
    /**
     * ParserInterface constructor.
     * @param $buildId
     */
    public function __construct($buildId);

    /**
     * @param $fileName
     * @return bool
     */
    public function parse($fileName);
}
