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

namespace CDash\Lib\Parsing\Xml;

/**
 * Interface StackInterface
 * @package CDash\Lib\Parsing\Xml
 */
interface StackInterface
{
    /**
     * Returns the array item at the index
     *
     * @param $index
     * @return mixed
     */
    public function at($index);

    /**
     * Return whether or not stack has items
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Standard push
     *
     * @param $item
     * @return mixed
     */
    public function push($item);

    /**
     * Standard pop
     *
     * @return mixed
     */
    public function pop();

    /**
     * Return the number of items on the stack
     *
     * @return int
     */
    public function size();

    /**
     * Return the first item on the stack
     *
     * @return mixed
     */
    public function top();
}
