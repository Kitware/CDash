<?php
namespace CDash\Messaging\Preferences;

class BitmaskNotificationPreferences extends NotificationPreferences
{
    public const EMAIL_UPDATE = 2;                                      // 2^1
    public const EMAIL_CONFIGURE = 4;                                   // 2^2
    public const EMAIL_WARNING = 8;                                     // 2^3
    public const EMAIL_ERROR = 16;                                      // 2^4
    public const EMAIL_TEST = 32;                                       // 2^5
    public const EMAIL_DYNAMIC_ANALYSIS = 64;                           // 2^6
    public const EMAIL_FIXES = 128;                                     // 2^7
    public const EMAIL_MISSING_SITES = 256;                             // 2^8

    // NEW MASKS
    public const EMAIL_NEVER    = 0;
    public const EMAIL_FILTERED = 1;                                    // 2^0
    public const EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION = 512;            // 2^9
    public const EMAIL_ANY_USER_CHECKIN_ISSUE_NIGHTLY_SECTION = 1024;   // 2^10
    public const EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION = 2048;       // 2^11
    public const EMAIL_SUBSCRIBED_LABELS = 4096;                        // 2^12
    public const EMAIL_NO_REDUNDANT = 8192;                             // 2^13

    public const DEFAULT_PREFERENCES = 8248;

    protected $preferences = [];

    public function __construct($mask = 0)
    {
        $bits = array_reverse(str_split(sprintf("%'.032b", $mask)));
        foreach ($this->properties as $bit => $name) {
            $this->set($name, $bits[$bit]);
        }
    }
}
