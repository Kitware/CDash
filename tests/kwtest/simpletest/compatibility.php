<?php
/**
 *  base include file for SimpleTest
 * @version    $Id$
 */

/**
 *  Static methods for compatibility between different
 *  PHP versions.
 */
class SimpleTestCompatibility
{
    /**
     *    Creates a copy whether in PHP5 or PHP4.
     * @param object $object Thing to copy.
     * @return object            A copy.
     */
    public static function copy($object)
    {
        return clone $object;
    }

    /**
     *    Identity test. Drops back to equality + types for PHP5
     *    objects as the === operator counts as the
     *    stronger reference constraint.
     * @param mixed $first Test subject.
     * @param mixed $second Comparison object.
     * @return bool        True if identical.
     */
    public static function isIdentical($first, $second)
    {
        return SimpleTestCompatibility::isIdenticalType($first, $second);
    }

    /**
     *    Recursive type test.
     * @param mixed $first Test subject.
     * @param mixed $second Comparison object.
     * @return bool        True if same type.
     */
    protected static function isIdenticalType($first, $second)
    {
        if (gettype($first) != gettype($second)) {
            return false;
        }
        if (is_object($first) && is_object($second)) {
            if (get_class($first) != get_class($second)) {
                return false;
            }
            return SimpleTestCompatibility::isArrayOfIdenticalTypes(
                (array)$first,
                (array)$second);
        }
        if (is_array($first) && is_array($second)) {
            return SimpleTestCompatibility::isArrayOfIdenticalTypes($first, $second);
        }
        if ($first !== $second) {
            return false;
        }
        return true;
    }

    /**
     *    Recursive type test for each element of an array.
     * @param mixed $first Test subject.
     * @param mixed $second Comparison object.
     * @return bool        True if identical.
     */
    protected static function isArrayOfIdenticalTypes($first, $second)
    {
        if (array_keys($first) != array_keys($second)) {
            return false;
        }
        foreach (array_keys($first) as $key) {
            $is_identical = SimpleTestCompatibility::isIdenticalType(
                $first[$key],
                $second[$key]);
            if (!$is_identical) {
                return false;
            }
        }
        return true;
    }

    /**
     *    Test for two variables being aliases.
     * @param mixed $first Test subject.
     * @param mixed $second Comparison object.
     * @return bool        True if same.
     */
    public static function isReference(&$first, &$second)
    {
        if (is_object($first)) {
            return $first === $second;
        }
        $temp = $first;
        $first = uniqid('test');
        $is_ref = ($first === $second);
        $first = $temp;
        return $is_ref;
    }

    /**
     *    Test to see if an object is a member of a
     *    class hiearchy.
     * @param object $object Object to test.
     * @param string $class Root name of hiearchy.
     * @return bool         True if class in hiearchy.
     */
    public static function isA($object, $class)
    {
        if (!class_exists($class, false)) {
            if (!interface_exists($class, false)) {
                return false;
            }
        }
        return $object instanceof $class;
    }

    /**
     *    Sets a socket timeout for each chunk.
     * @param resource $handle Socket handle.
     * @param int $timeout Limit in seconds.
     */
    public static function setTimeout($handle, $timeout)
    {
        stream_set_timeout($handle, $timeout, 0);
    }
}
