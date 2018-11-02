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
 * Class Stack
 * @package CDash\Lib\Parser\CTest
 */
class Stack implements StackInterface
{
    private $stack = [];

    /**
     * @return int
     */
    public function size()
    {
        return count($this->stack);
    }

    /**
     * @param $item
     * @return $this
     */
    public function push($item)
    {
        $this->stack[] = $item;
        return $this;
    }

    /**
     * @return $this
     */
    public function pop()
    {
        array_pop($this->stack);
        return $this;
    }

    /**
     * @return mixed
     */
    public function top()
    {
        return $this->stack[count($this->stack) - 1];
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return count($this->stack) == 0;
    }

    /**
     * @param $index
     * @return bool|mixed
     */
    public function at($index)
    {
        if ($index < 0 || $index >= count($this->stack)) {
            return false;
        }
        return $this->stack[$index];
    }
}
