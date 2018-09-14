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

use CDash\Collection\BuildEmailCollection;
use CDash\Model\BuildEmail;
use CDash\Test\CDashTestCase;

class BuildEmailCollectionTest extends CDashTestCase
{

    public function testAdd()
    {
        $user1_1 = new BuildEmail();
        $user1_2 = new BuildEmail();
        $user2_1 = new BuildEmail();

        $user1_1->SetEmail('one@company.tld');
        $user1_2->SetEmail('one@company.tld');
        $user2_1->SetEmail('two@company.tld');

        $sut = new BuildEmailCollection();

        $this->assertNull($sut->get('one@company.tld'));
        $this->assertNull($sut->get('two@company.tld'));

        $sut->add($user1_1);

        $this->assertTrue($sut->has('one@company.tld'));
        $actual = $sut->get('one@company.tld');

        $this->assertInternalType('array', $actual);
        $this->assertNotEmpty($actual);
        $this->assertSame($user1_1, $actual[0]);

        $sut->add($user1_2);
        $actual = $sut->get('one@company.tld');

        $this->assertCount(2, $actual);
        $this->assertSame($user1_1, $actual[0]);
        $this->assertSame($user1_2, $actual[1]);

        $sut->add($user2_1);
        $actual = $sut->get('two@company.tld');

        $this->assertSame($user2_1, $actual[0]);
    }
}
