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

class stack
{
    private $stack = array();

    public function __construct()
    {
    }

    public function size()
    {
        return count($this->stack);
    }

    public function push($e)
    {
        $this->stack[] = $e;
        return $this;
    }

    public function pop()
    {
        array_pop($this->stack);
        return $this;
    }

    public function top()
    {
        return $this->stack[count($this->stack) - 1];
    }

    public function isEmpty()
    {
        return count($this->stack) == 0;
    }

    public function at($index)
    {
        if ($index < 0 || $index >= count($this->stack)) {
            return false;
        }
        return $this->stack[$index];
    }
}
