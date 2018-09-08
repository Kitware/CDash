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
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Messaging\Topic\EmailSentTopic;
use CDash\Model\Build;
use CDash\Model\BuildEmail;
use CDash\Model\BuildError;
use CDash\Model\Subscriber;

class EmailSentTest extends PHPUnit_Framework_TestCase
{
    public function testSubscribesToBuild()
    {
        $topic = new BuildErrorTopic();
        $topic->setType(Build::TYPE_WARN);

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);
        $subscriber->setAddress('ricky.bobby@taladega.tld');

        $sut = new EmailSentTopic($topic);
        $sut->setSubscriber($subscriber);

        $build = new Build();
        $buildEmailCollection = new BuildEmailCollection();

        $build->SetBuildEmailCollection($buildEmailCollection);

        // this results in false because the $build has no BuildErrors yet
        $this->assertFalse($sut->subscribesToBuild($build));

        $buildError = new BuildError();
        $buildError->Type = Build::TYPE_WARN;
        $build->AddError($buildError);

        // now that our build error is set, we should get a return value of true
        $this->assertTrue($sut->subscribesToBuild($build));

        $e1 = new BuildEmail();

        $e1->SetEmail('ricky.bobby@taladega.tld');

        $buildEmailCollection->add($e1);

        // now we can test that if there is a build email with the key
        // that is the email address, the email has already been sent
        $this->assertFalse($sut->subscribesToBuild($build));
    }
}
