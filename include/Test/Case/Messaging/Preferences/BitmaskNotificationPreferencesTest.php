<?php

use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;

class BitmaskNotificationPreferencesTest extends \CDash\Test\CDashTestCase
{
    private $defaultMask;

    public function setUp()
    {
        parent::setUp();
        $this->defaultMask = BitmaskNotificationPreferences::EMAIL_UPDATE |
            BitmaskNotificationPreferences::EMAIL_CONFIGURE  |
            BitmaskNotificationPreferences::EMAIL_WARNING    |
            BitmaskNotificationPreferences::EMAIL_ERROR      |
            BitmaskNotificationPreferences::EMAIL_TEST       |
            BitmaskNotificationPreferences::EMAIL_DYNAMIC_ANALYSIS;
    }

    public function testConstructWithDefaultMask()
    {
        $sut = new BitmaskNotificationPreferences($this->defaultMask);

        $this->assertFalse($sut->getOnFiltered());
        $this->assertTrue($sut->getOnUpdateError());
        $this->assertTrue($sut->getOnConfigure());
        $this->assertTrue($sut->getOnBuildWarning());
        $this->assertTrue($sut->getOnBuildError());
        $this->assertTrue($sut->getOnTestFailure());
        $this->assertTrue($sut->getOnTestFailure());
        $this->assertTrue($sut->getOnDynamicAnalysis());

        $this->assertFalse($sut->getOnFixed());
        $this->assertFalse($sut->getOnExpectedSiteSubmitMissing());
        $this->assertFalse($sut->getOnMyCheckinIssue());
        $this->assertFalse($sut->getOnAnyCheckinIssue());
        $this->assertFalse($sut->getOnCheckinIssueNightlyOnly());
    }

    public function testMagicCallMethodReturnsNull()
    {
        $sut = new BitmaskNotificationPreferences();
        $this->assertNull($sut->notAProperty());
    }

    public function testMagicCallMethodReturnsFalseIfPropertyNotExists()
    {
        $sut = new BitmaskNotificationPreferences();
        $this->assertFalse($sut->getNotAProperty());
    }

    public function testNotifyOn()
    {
        $mask = BitmaskNotificationPreferences::EMAIL_TEST |
            BitmaskNotificationPreferences::EMAIL_FIXES;

        $sut = new BitmaskNotificationPreferences($mask);

        $this->assertTrue($sut->notifyOn('TestFailure'));
        $this->assertTrue($sut->notifyOn('Fixed'));
        $this->assertFalse($sut->notifyOn('UpdateError'));
    }

    public function testSetPreferencesFromEmailTypeProperty()
    {
        $sut = new BitmaskNotificationPreferences();

        $this->assertFalse($sut->get(NotifyOn::AUTHORED));
        $this->assertFalse($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertFalse($sut->get(NotifyOn::ANY));

        $sut->setPreferencesFromEmailTypeProperty(1);
        $this->assertTrue($sut->get(NotifyOn::AUTHORED));
        $this->assertFalse($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertFalse($sut->get(NotifyOn::ANY));

        $sut->setPreferencesFromEmailTypeProperty(3);
        $this->assertFalse($sut->get(NotifyOn::AUTHORED));
        $this->assertFalse($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertTrue($sut->get(NotifyOn::ANY));

        $sut->setPreferencesFromEmailTypeProperty(2);
        $this->assertFalse($sut->get(NotifyOn::AUTHORED));
        $this->assertTrue($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertFalse($sut->get(NotifyOn::ANY));
    }

    public function testSetPreferencesFromEmailSuccessProperty()
    {
        $sut = new BitmaskNotificationPreferences();

        $this->assertFalse($sut->get(NotifyOn::FIXED));

        $sut->setPreferencesFromEmailSuccessProperty('1');

        $this->assertTrue($sut->get(NotifyOn::FIXED));

        $sut->setPreferencesFromEmailSuccessProperty(0);

        $this->assertFalse($sut->get(NotifyOn::FIXED));
    }

    public function testSetPreferenceFromMissingSiteProperty()
    {
        $sut = new BitmaskNotificationPreferences();

        $this->assertFalse($sut->get(NotifyOn::SITE_MISSING));

        $sut->setPreferenceFromMissingSiteProperty('1');

        $this->assertTrue($sut->get(NotifyOn::SITE_MISSING));

        $sut->setPreferenceFromMissingSiteProperty(0);

        $this->assertFalse($sut->get(NotifyOn::SITE_MISSING));
    }
}
