<?php
namespace CDash\Messaging\Notification;

class NotifyOn
{
    public const AUTHORED = 'Authored';
    public const UPDATE_ERROR = 'UpdateError';
    public const UPDATE = 'Update';
    public const CONFIGURE = 'Configure';
    public const BUILD_WARNING = 'BuildWarning';
    public const BUILD_ERROR = 'BuildError';
    public const TEST_FAILURE = 'TestFailure';
    public const DYNAMIC_ANALYSIS = 'DynamicAnalysis';
    public const FIXED = 'Fixed';
    public const FILTERED = 'Filtered';
    public const LABELED = 'Labeled';
    public const SITE_MISSING = 'SiteMissing';
    public const GROUP_NIGHTLY = 'GroupMembership';
    public const ANY = 'Any';
    public const NEVER = 'Never';
    public const REDUNDANT = 'Redundant';
    public const SUMMARY = 'Summary';
}
