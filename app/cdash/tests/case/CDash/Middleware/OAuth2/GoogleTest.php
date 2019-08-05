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

use CDash\Middleware\OAuth2\Google;
use Tests\TestCase;

class GoogleTest extends TestCase
{
    public function testGetProvider()
    {
        $sut = new Google();

        $expected = \League\OAuth2\Client\Provider\Google::class;
        $actual = $sut->getProvider();

        $this->assertInstanceOf($expected, $actual);
    }
}
