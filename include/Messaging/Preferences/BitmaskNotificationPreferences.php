<?php
namespace CDash\Messaging\Preferences;

class BitmaskNotificationPreferences extends NotificationPreferences
{
    const EMAIL_UPDATE = 2;                                      // 2^1
    const EMAIL_CONFIGURE = 4;                                   // 2^2
    const EMAIL_WARNING = 8;                                     // 2^3
    const EMAIL_ERROR = 16;                                      // 2^4
    const EMAIL_TEST = 32;                                       // 2^5
    const EMAIL_DYNAMIC_ANALYSIS = 64;                           // 2^6
    const EMAIL_FIXES = 128;                                     // 2^7
    const EMAIL_MISSING_SITES = 256;                             // 2^8

    // NEW MASKS
    const EMAIL_NEVER    = 0;
    const EMAIL_FILTERED = 1;                                    // 2^0
    const EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION = 512;            // 2^9
    const EMAIL_ANY_USER_CHECKIN_ISSUE_NIGHTLY_SECTION = 1024;   // 2^10
    const EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION = 2048;       // 2^11
    const EMAIL_USER_CHECKIN_FIX = 4096;                         // 2^12
    const EMAIL_EXPECTED_SITE_NOT_SUBMITTING = 8192;             // 2^13

    const EMAIL_SUBSCRIBED_LABELS = 16384;                       // 2^14

    protected $preferences = [];

    public function __construct($mask = 0)
    {
        $bits = array_reverse(str_split(sprintf("%'.032b", $mask)));
        foreach ($this->properties as $bit => $name) {
            $this->set($name, $bits[$bit]);
        }
    }
}
