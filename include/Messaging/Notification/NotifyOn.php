<?php
namespace CDash\Messaging\Notification;

class NotifyOn
{
    const AUTHORED = 'Authored';
    const UPDATE_ERROR = 'UpdateError';
    const CONFIGURE = 'Configure';
    const BUILD_WARNING = 'BuildWarning';
    const BUILD_ERROR = 'BuildError';
    const TEST_FAILURE = 'TestFailure';
    const DYNAMIC_ANALYSIS = 'DynamicAnalysis';
    const FIXED = 'Fixed';
    const FILTERED = 'Filtered';
    const LABELED = 'Labeled';
    const SITE_MISSING = 'SiteMissing';
    const GROUP_NIGHTLY = 'GroupMembership';
    const ANY = 'Any';
    const NEVER = 'Never';
    const ONCE = 'Once';
}
