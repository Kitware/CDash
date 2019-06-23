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
require_once 'include/login_functions.php';

class ViewIssuesTestCase extends KWWebTestCase
{
    public $complexity;
    public function __construct()
    {
        parent::__construct();
        $this->complexity = \CDash\Config::getInstance()->get('CDASH_PASSWORD_COMPLEXITY_COUNT');
    }

    public function testViewIssues()
    {
        $success = true;

        $success = $this->complexityTest('a', 1, 1);
        $success = $this->complexityTest('aA', 2, 1);
        $success = $this->complexityTest('aA1', 3, 1);
        $success = $this->complexityTest('aA1_', 4, 1);

        $success = $this->complexityTest('a', 0, 2);
        $success = $this->complexityTest('aA', 0, 2);
        $success = $this->complexityTest('aA1', 0, 2);
        $success = $this->complexityTest('aA1_', 0, 2);

        $success = $this->complexityTest('ab', 1, 2);
        $success = $this->complexityTest('abAB', 2, 2);
        $success = $this->complexityTest('abAB12', 3, 2);
        $success = $this->complexityTest('abAB12_%', 4, 2);

        if ($success) {
            $this->pass('Passed');
        }

        \CDash\Config::getInstance()->set('CDASH_PASSWORD_COMPLEXITY_COUNT', $this->complexity);
    }

    public function complexityTest($password, $answer, $count)
    {
        \CDash\Config::getInstance()->set('CDASH_PASSWORD_COMPLEXITY_COUNT', $count);
        $response = getPasswordComplexity($password);
        if ($response != $answer) {
            $this->fail("Expected $answer for '$password' when count is $count, instead got $response");
            return false;
        }
        return true;
    }
}
