<?php
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
        $this->assertTrue($sut->getOnConfigureError());
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

    public function testConstructWithCancelableInterfacePermissions()
    {
        $mask = $this->defaultMask |
            BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION;

        $sut = new BitmaskNotificationPreferences($mask);

        $this->assertTrue($sut->getOnAnyCheckinIssue());

        $mask = $this->defaultMask |
            BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_NIGHTLY_SECTION;

        $sut = new BitmaskNotificationPreferences($mask);

        $this->assertTrue($sut->getOnCheckinIssueNightlyOnly());

        $mask = $this->defaultMask |
            BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION;

        $sut = new BitmaskNotificationPreferences($mask);

        $this->assertTrue($sut->getOnMyCheckinIssue());
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
}
