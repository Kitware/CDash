<?php
namespace CDash\Model;

class ActionableTypes
{
    const BUILD_ERROR = 'BuildError';
    const BUILD_WARNING = 'BuildWarning';
    const CONFIGURE = 'Configure';
    const DYNAMIC_ANALYSIS = 'DynamicAnalysis';
    const TEST = 'TestFailure';
    const UPDATE = 'UpdateError';

    const UPDATE_FIX = 'UpdateFix';
    const BUILD_WARNING_FIX = 'BuildWarningFix';
    const BUILD_ERROR_FIX = 'BuildErrorFix';
    const CONFIGURE_FIX = 'ConfigureFix';
    const TEST_FIX = 'TestFix';
    const MISSING_TEST = 'MissingTest';

    public static $categories = [
        self::UPDATE => 1,
        self::CONFIGURE => 2,
        self::BUILD_WARNING => 3,
        self::BUILD_ERROR => 4,
        self::TEST => 5,
        self::UPDATE_FIX => 6,
        self::CONFIGURE_FIX => 7,
        self::BUILD_WARNING_FIX => 8,
        self::BUILD_ERROR_FIX => 9,
        self::TEST_FIX => 10,
        self::DYNAMIC_ANALYSIS => 11,
        self::MISSING_TEST => 12,
    ];
}
