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

        $this->assertTrue($sut->get(NotifyOn::UPDATE_ERROR));
        $this->assertTrue($sut->get(NotifyOn::CONFIGURE));
        $this->assertTrue($sut->get(NotifyOn::BUILD_WARNING));
        $this->assertTrue($sut->get(NotifyOn::BUILD_ERROR));
        $this->assertTrue($sut->get(NotifyOn::TEST_FAILURE));
        $this->assertTrue($sut->get(NotifyOn::DYNAMIC_ANALYSIS));

        $this->assertFalse($sut->get(NotifyOn::FILTERED));
        $this->assertFalse($sut->get(NotifyOn::FIXED));
        $this->assertFalse($sut->get(NotifyOn::SITE_MISSING));
        $this->assertFalse($sut->get(NotifyOn::AUTHORED));
        $this->assertFalse($sut->get(NotifyOn::ANY));
        $this->assertFalse($sut->get(NotifyOn::GROUP_NIGHTLY));
    }
}
