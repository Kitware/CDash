<?php

/**
 *    base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *    include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/dumper.php';
require_once dirname(__FILE__) . '/compatibility.php';
/**#@-*/

/**
 *    Assertion that can display failure information.
 *    Also includes various helper methods.
 *
 * @abstract
 */
class SimpleExpectation
{
    protected $dumper = false;
    private $message;

    /**
     *    Creates a dumper for displaying values and sets
     *    the test message.
     *
     * @param string $message customised message on failure
     */
    public function __construct($message = '%s')
    {
        $this->message = $message;
    }

    /**
     *    Tests the expectation. True if correct.
     *
     * @param mixed $compare comparison value
     *
     * @return bool true if correct
     *
     * @abstract
     */
    public function test($compare)
    {
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     *
     * @abstract
     */
    public function testMessage($compare)
    {
    }

    /**
     *    Overlays the generated message onto the stored user
     *    message. An additional message can be interjected.
     *
     * @param mixed $compare comparison value
     * @param SimpleDumper $dumper for formatting the results
     *
     * @return string description of success
     *                or failure
     */
    public function overlayMessage($compare, $dumper)
    {
        $this->dumper = $dumper;
        return sprintf($this->message, $this->testMessage($compare));
    }

    /**
     *    Accessor for the dumper.
     *
     * @return SimpleDumper current value dumper
     */
    protected function getDumper()
    {
        if (!$this->dumper) {
            $dumper = new SimpleDumper();
            return $dumper;
        }
        return $this->dumper;
    }

    /**
     *    Test to see if a value is an expectation object.
     *    A useful utility method.
     *
     * @param mixed $expectation hopefully an Expectation
     *                           class
     *
     * @return bool true if descended from
     *              this class
     */
    public static function isExpectation($expectation)
    {
        return is_object($expectation)
        && SimpleTestCompatibility::isA($expectation, 'SimpleExpectation');
    }
}

/**
 *    A wildcard expectation always matches.
 */
class AnythingExpectation extends SimpleExpectation
{
    /**
     *    Tests the expectation. Always true.
     *
     * @param mixed $compare ignored
     *
     * @return bool true
     */
    public function test($compare)
    {
        return true;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        return 'Anything always matches [' . $dumper->describeValue($compare) . ']';
    }
}

/**
 *    An expectation that never matches.
 */
class FailedExpectation extends SimpleExpectation
{
    /**
     *    Tests the expectation. Always false.
     *
     * @param mixed $compare ignored
     *
     * @return bool true
     */
    public function test($compare)
    {
        return false;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        return 'Failed expectation never matches [' . $dumper->describeValue($compare) . ']';
    }
}

/**
 *    An expectation that passes on boolean true.
 */
class TrueExpectation extends SimpleExpectation
{
    /**
     *    Tests the expectation.
     *
     * @param mixed $compare should be true
     *
     * @return bool true on match
     */
    public function test($compare)
    {
        return (bool) $compare;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        return 'Expected true, got [' . $dumper->describeValue($compare) . ']';
    }
}

/**
 *    An expectation that passes on boolean false.
 */
class FalseExpectation extends SimpleExpectation
{
    /**
     *    Tests the expectation.
     *
     * @param mixed $compare should be false
     *
     * @return bool true on match
     */
    public function test($compare)
    {
        return !(bool) $compare;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        return 'Expected false, got [' . $dumper->describeValue($compare) . ']';
    }
}

/**
 *    Test for equality.
 */
class EqualExpectation extends SimpleExpectation
{
    private $value;

    /**
     *    Sets the value to compare against.
     *
     * @param mixed $value test value to match
     * @param string $message customised message on failure
     */
    public function __construct($value, $message = '%s')
    {
        parent::__construct($message);
        $this->value = $value;
    }

    /**
     *    Tests the expectation. True if it matches the
     *    held value.
     *
     * @param mixed $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return ($this->value == $compare) && ($compare == $this->value);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if ($this->test($compare)) {
            return 'Equal expectation [' . $this->dumper->describeValue($this->value) . ']';
        } else {
            return 'Equal expectation fails ' .
            $this->dumper->describeDifference($this->value, $compare);
        }
    }

    /**
     *    Accessor for comparison value.
     *
     * @return mixed held value to compare with
     */
    protected function getValue()
    {
        return $this->value;
    }
}

/**
 *    Test for inequality.
 */
class NotEqualExpectation extends EqualExpectation
{
    /**
     *    Sets the value to compare against.
     *
     * @param mixed $value test value to match
     * @param string $message customised message on failure
     */
    public function __construct($value, $message = '%s')
    {
        parent::__construct($value, $message);
    }

    /**
     *    Tests the expectation. True if it differs from the
     *    held value.
     *
     * @param mixed $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return !parent::test($compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        if ($this->test($compare)) {
            return 'Not equal expectation passes ' .
            $dumper->describeDifference($this->getValue(), $compare);
        } else {
            return 'Not equal expectation fails [' .
            $dumper->describeValue($this->getValue()) .
            '] matches';
        }
    }
}

/**
 *    Test for being within a range.
 */
class WithinMarginExpectation extends SimpleExpectation
{
    private $upper;
    private $lower;

    /**
     *    Sets the value to compare against and the fuzziness of
     *    the match. Used for comparing floating point values.
     *
     * @param mixed $value test value to match
     * @param mixed $margin fuzziness of match
     * @param string $message customised message on failure
     */
    public function __construct($value, $margin, $message = '%s')
    {
        parent::__construct($message);
        $this->upper = $value + $margin;
        $this->lower = $value - $margin;
    }

    /**
     *    Tests the expectation. True if it matches the
     *    held value.
     *
     * @param mixed $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return ($compare <= $this->upper) && ($compare >= $this->lower);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if ($this->test($compare)) {
            return $this->withinMessage($compare);
        } else {
            return $this->outsideMessage($compare);
        }
    }

    /**
     *    Creates a the message for being within the range.
     *
     * @param mixed $compare value being tested
     */
    protected function withinMessage($compare)
    {
        return 'Within expectation [' . $this->dumper->describeValue($this->lower) . '] and [' .
        $this->dumper->describeValue($this->upper) . ']';
    }

    /**
     *    Creates a the message for being within the range.
     *
     * @param mixed $compare value being tested
     */
    protected function outsideMessage($compare)
    {
        if ($compare > $this->upper) {
            return 'Outside expectation ' .
            $this->dumper->describeDifference($compare, $this->upper);
        } else {
            return 'Outside expectation ' .
            $this->dumper->describeDifference($compare, $this->lower);
        }
    }
}

/**
 *    Test for being outside of a range.
 */
class OutsideMarginExpectation extends WithinMarginExpectation
{
    /**
     *    Sets the value to compare against and the fuzziness of
     *    the match. Used for comparing floating point values.
     *
     * @param mixed $value test value to not match
     * @param mixed $margin fuzziness of match
     * @param string $message customised message on failure
     */
    public function __construct($value, $margin, $message = '%s')
    {
        parent::__construct($value, $margin, $message);
    }

    /**
     *    Tests the expectation. True if it matches the
     *    held value.
     *
     * @param mixed $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return !parent::test($compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if (!$this->test($compare)) {
            return $this->withinMessage($compare);
        } else {
            return $this->outsideMessage($compare);
        }
    }
}

/**
 *    Test for reference.
 */
class ReferenceExpectation
{
    private $value;

    /**
     *    Sets the reference value to compare against.
     *
     * @param mixed $value test reference to match
     * @param string $message customised message on failure
     */
    public function __construct(&$value, $message = '%s')
    {
        $this->message = $message;
        $this->value = &$value;
    }

    /**
     *    Tests the expectation. True if it exactly
     *    references the held value.
     *
     * @param mixed $compare comparison reference
     *
     * @return bool true if correct
     */
    public function test(&$compare)
    {
        return SimpleTestCompatibility::isReference($this->value, $compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if ($this->test($compare)) {
            return 'Reference expectation [' . $this->dumper->describeValue($this->value) . ']';
        } else {
            return 'Reference expectation fails ' .
            $this->dumper->describeDifference($this->value, $compare);
        }
    }

    /**
     *    Overlays the generated message onto the stored user
     *    message. An additional message can be interjected.
     *
     * @param mixed $compare comparison value
     * @param SimpleDumper $dumper for formatting the results
     *
     * @return string description of success
     *                or failure
     */
    public function overlayMessage($compare, $dumper)
    {
        $this->dumper = $dumper;
        return sprintf($this->message, $this->testMessage($compare));
    }

    /**
     *    Accessor for the dumper.
     *
     * @return SimpleDumper current value dumper
     */
    protected function getDumper()
    {
        if (!$this->dumper) {
            $dumper = new SimpleDumper();
            return $dumper;
        }
        return $this->dumper;
    }
}

/**
 *    Test for identity.
 */
class IdenticalExpectation extends EqualExpectation
{
    /**
     *    Sets the value to compare against.
     *
     * @param mixed $value test value to match
     * @param string $message customised message on failure
     */
    public function __construct($value, $message = '%s')
    {
        parent::__construct($value, $message);
    }

    /**
     *    Tests the expectation. True if it exactly
     *    matches the held value.
     *
     * @param mixed $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return SimpleTestCompatibility::isIdentical($this->getValue(), $compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        if ($this->test($compare)) {
            return 'Identical expectation [' . $dumper->describeValue($this->getValue()) . ']';
        } else {
            return 'Identical expectation [' . $dumper->describeValue($this->getValue()) .
            '] fails with [' .
            $dumper->describeValue($compare) . '] ' .
            $dumper->describeDifference($this->getValue(), $compare, TYPE_MATTERS);
        }
    }
}

/**
 *    Test for non-identity.
 */
class NotIdenticalExpectation extends IdenticalExpectation
{
    /**
     *    Sets the value to compare against.
     *
     * @param mixed $value test value to match
     * @param string $message customised message on failure
     */
    public function __construct($value, $message = '%s')
    {
        parent::__construct($value, $message);
    }

    /**
     *    Tests the expectation. True if it differs from the
     *    held value.
     *
     * @param mixed $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return !parent::test($compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        if ($this->test($compare)) {
            return 'Not identical expectation passes ' .
            $dumper->describeDifference($this->getValue(), $compare, TYPE_MATTERS);
        } else {
            return 'Not identical expectation [' . $dumper->describeValue($this->getValue()) . '] matches';
        }
    }
}

/**
 *    Test for a pattern using Perl regex rules.
 */
class PatternExpectation extends SimpleExpectation
{
    private $pattern;

    /**
     *    Sets the value to compare against.
     *
     * @param string $pattern pattern to search for
     * @param string $message customised message on failure
     */
    public function __construct($pattern, $message = '%s')
    {
        parent::__construct($message);
        $this->pattern = $pattern;
    }

    /**
     *    Accessor for the pattern.
     *
     * @return string perl regex as string
     */
    protected function getPattern()
    {
        return $this->pattern;
    }

    /**
     *    Tests the expectation. True if the Perl regex
     *    matches the comparison value.
     *
     * @param string $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return (bool) preg_match($this->getPattern(), $compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if ($this->test($compare)) {
            return $this->describePatternMatch($this->getPattern(), $compare);
        } else {
            $dumper = $this->getDumper();
            return 'Pattern [' . $this->getPattern() .
            '] not detected in [' .
            $dumper->describeValue($compare) . ']';
        }
    }

    /**
     *    Describes a pattern match including the string
     *    found and it's position.
     *
     * @param string $pattern regex to match against
     * @param string $subject subject to search
     */
    protected function describePatternMatch($pattern, $subject)
    {
        preg_match($pattern, $subject, $matches);
        $position = strpos($subject, $matches[0]);
        $dumper = $this->getDumper();
        return "Pattern [$pattern] detected at character [$position] in [" .
        $dumper->describeValue($subject) . '] as [' .
        $matches[0] . '] in region [' .
        $dumper->clipString($subject, 100, $position) . ']';
    }
}

/**
 *    Fail if a pattern is detected within the
 *    comparison.
 */
class NoPatternExpectation extends PatternExpectation
{
    /**
     *    Sets the reject pattern
     *
     * @param string $pattern pattern to search for
     * @param string $message customised message on failure
     */
    public function __construct($pattern, $message = '%s')
    {
        parent::__construct($pattern, $message);
    }

    /**
     *    Tests the expectation. False if the Perl regex
     *    matches the comparison value.
     *
     * @param string $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return !parent::test($compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param string $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        if ($this->test($compare)) {
            $dumper = $this->getDumper();
            return 'Pattern [' . $this->getPattern() .
            '] not detected in [' .
            $dumper->describeValue($compare) . ']';
        } else {
            return $this->describePatternMatch($this->getPattern(), $compare);
        }
    }
}

/**
 *    Tests either type or class name if it's an object.
 */
class IsAExpectation extends SimpleExpectation
{
    private $type;

    /**
     *    Sets the type to compare with.
     *
     * @param string $type type or class name
     * @param string $message customised message on failure
     */
    public function __construct($type, $message = '%s')
    {
        parent::__construct($message);
        $this->type = $type;
    }

    /**
     *    Accessor for type to check against.
     *
     * @return string type or class name
     */
    protected function getType()
    {
        return $this->type;
    }

    /**
     *    Tests the expectation. True if the type or
     *    class matches the string value.
     *
     * @param string $compare comparison value
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        if (is_object($compare)) {
            return SimpleTestCompatibility::isA($compare, $this->type);
        } else {
            $function = 'is_' . $this->canonicalType($this->type);
            if (is_callable($function)) {
                return $function($compare);
            }
            return false;
        }
    }

    /**
     *    Coerces type name into a is_*() match.
     *
     * @param string $type user type
     *
     * @return string simpler type
     */
    protected function canonicalType($type)
    {
        $type = strtolower($type);
        $map = ['boolean' => 'bool'];
        if (isset($map[$type])) {
            $type = $map[$type];
        }
        return $type;
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        return 'Value [' . $dumper->describeValue($compare) .
        '] should be type [' . $this->type . ']';
    }
}

/**
 *    Tests either type or class name if it's an object.
 *    Will succeed if the type does not match.
 */
class NotAExpectation extends IsAExpectation
{
    private $type;

    /**
     *    Sets the type to compare with.
     *
     * @param string $type type or class name
     * @param string $message customised message on failure
     */
    public function __construct($type, $message = '%s')
    {
        parent::__construct($type, $message);
    }

    /**
     *    Tests the expectation. False if the type or
     *    class matches the string value.
     *
     * @param string $compare comparison value
     *
     * @return bool true if different
     */
    public function test($compare)
    {
        return !parent::test($compare);
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        return 'Value [' . $dumper->describeValue($compare) .
        '] should not be type [' . $this->getType() . ']';
    }
}

/**
 *    Tests for existance of a method in an object
 */
class MethodExistsExpectation extends SimpleExpectation
{
    private $method;

    /**
     *    Sets the value to compare against.
     *
     * @param string $method method to check
     * @param string $message customised message on failure
     */
    public function __construct($method, $message = '%s')
    {
        parent::__construct($message);
        $this->method = &$method;
    }

    /**
     *    Tests the expectation. True if the method exists in the test object.
     *
     * @param string $compare comparison method name
     *
     * @return bool true if correct
     */
    public function test($compare)
    {
        return (bool) (is_object($compare) && method_exists($compare, $this->method));
    }

    /**
     *    Returns a human readable test message.
     *
     * @param mixed $compare comparison value
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($compare)
    {
        $dumper = $this->getDumper();
        if (!is_object($compare)) {
            return 'No method on non-object [' . $dumper->describeValue($compare) . ']';
        }
        $method = $this->method;
        return 'Object [' . $dumper->describeValue($compare) .
        "] should contain method [$method]";
    }
}

/**
 *    Compares an object member's value even if private.
 */
class MemberExpectation extends IdenticalExpectation
{
    private $name;

    /**
     *    Sets the value to compare against.
     */
    public function __construct($name, $expected)
    {
        $this->name = $name;
        parent::__construct($expected);
    }

    /**
     *    Tests the expectation. True if the property value is identical.
     *
     * @param object $actual comparison object
     *
     * @return bool true if identical
     */
    public function test($actual)
    {
        if (!is_object($actual)) {
            return false;
        }
        return parent::test($this->getProperty($this->name, $actual));
    }

    /**
     *    Returns a human readable test message.
     *
     * @return string description of success
     *                or failure
     */
    public function testMessage($actual)
    {
        return parent::testMessage($this->getProperty($this->name, $actual));
    }

    /**
     *    Extracts the member value even if private using reflection.
     *
     * @param string $name property name
     * @param object $object object to read
     *
     * @return mixed value of property
     */
    private function getProperty($name, $object)
    {
        $reflection = new ReflectionObject($object);
        $property = $reflection->getProperty($name);
        if (method_exists($property, 'setAccessible')) {
            $property->setAccessible(true);
        }
        try {
            return $property->getValue($object);
        } catch (ReflectionException $e) {
            return $this->getPrivatePropertyNoMatterWhat($name, $object);
        }
    }

    /**
     *    Extracts a private member's value when reflection won't play ball.
     *
     * @param string $name property name
     * @param object $object object to read
     *
     * @return mixed value of property
     */
    private function getPrivatePropertyNoMatterWhat($name, $object)
    {
        foreach ((array) $object as $mangled_name => $value) {
            if ($this->unmangle($mangled_name) == $name) {
                return $value;
            }
        }
    }

    /**
     *    Removes crud from property name after it's been converted
     *    to an array.
     *
     * @param string $mangled name from array cast
     *
     * @return string cleaned up name
     */
    public function unmangle($mangled)
    {
        $parts = preg_split('/[^a-zA-Z0-9_\x7f-\xff]+/', $mangled);
        return array_pop($parts);
    }
}
