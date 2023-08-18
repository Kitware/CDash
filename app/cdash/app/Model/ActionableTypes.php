<?php
namespace CDash\Model;

/**
 * Class ActionableTypes
 *
 * ActionableTypes represent CTest submissions that require MAY processing after parsing by
 * CDash xml handlers.
 *
 * @package CDash\Model
 */
class ActionableTypes
{
    public const BUILD_ERROR = 'BuildError';
    public const BUILD_WARNING = 'BuildWarning';
    public const CONFIGURE = 'Configure';
    public const DYNAMIC_ANALYSIS = 'DynamicAnalysis';
    public const TEST = 'TestFailure';
    public const UPDATE = 'UpdateError';

    public const UPDATE_FIX = 'UpdateFix';
    public const BUILD_WARNING_FIX = 'BuildWarningFix';
    public const BUILD_ERROR_FIX = 'BuildErrorFix';
    public const CONFIGURE_FIX = 'ConfigureFix';
    public const TEST_FIX = 'TestFix';
    public const MISSING_TEST = 'TestMissing';

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
