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

require_once dirname(__FILE__) . '/cdash_test_case.php';

class PasswordComplexityTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->validator = new \App\Validators\Password;
    }

    public function testPasswordComplexity()
    {
        $this->complexityTest('a', 1, 1);
        $this->complexityTest('aA', 2, 1);
        $this->complexityTest('aA1', 3, 1);
        $this->complexityTest('aA1_', 4, 1);

        $this->complexityTest('a', 0, 2);
        $this->complexityTest('aA', 0, 2);
        $this->complexityTest('aA1', 0, 2);
        $this->complexityTest('aA1_', 0, 2);

        $this->complexityTest('ab', 1, 2);
        $this->complexityTest('abAB', 2, 2);
        $this->complexityTest('abAB12', 3, 2);
        $this->complexityTest('abAB12_%', 4, 2);
    }

    public function complexityTest($password, $expected, $count)
    {
        $found = $this->validator->computeComplexity($password, $count);
        if ($found != $expected) {
            $this->fail("Expected $expected for '$password' when count is $count, instead got $found");
        }
    }
}
