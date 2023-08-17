<?php
/**
 *  base include file for SimpleTest
 * @version    $Id$
 */

/**
 *  Version specific reflection API.
 * @ignore duplicate with reflection_php5.php
 */
class SimpleReflection
{
    public $_interface;

    /**
     *    Stashes the class/interface.
     * @param string $interface Class or interface
     *                                to inspect.
     */
    public function SimpleReflection($interface)
    {
        $this->_interface = $interface;
    }

    /**
     *    Checks that a class has been declared.
     * @return bool        True if defined.
     */
    public function classExists()
    {
        return class_exists($this->_interface);
    }

    /**
     *    Needed to kill the autoload feature in PHP5
     *    for classes created dynamically.
     * @return bool        True if defined.
     */
    public function classExistsSansAutoload()
    {
        return class_exists($this->_interface);
    }

    /**
     *    Checks that a class or interface has been
     *    declared.
     * @return bool        True if defined.
     */
    public function classOrInterfaceExists()
    {
        return class_exists($this->_interface);
    }

    /**
     *    Needed to kill the autoload feature in PHP5
     *    for classes created dynamically.
     * @return bool        True if defined.
     */
    public function classOrInterfaceExistsSansAutoload()
    {
        return class_exists($this->_interface);
    }

    /**
     *    Gets the list of methods on a class or
     *    interface.
     * @returns array          List of method names.
     */
    public function getMethods()
    {
        return get_class_methods($this->_interface);
    }

    /**
     *    Gets the list of interfaces from a class. If the
     *    class name is actually an interface then just that
     *    interface is returned.
     * @returns array          List of interfaces.
     */
    public function getInterfaces()
    {
        return [];
    }

    /**
     *    Finds the parent class name.
     * @returns string      Parent class name.
     */
    public function getParent()
    {
        return strtolower(get_parent_class($this->_interface));
    }

    /**
     *    Determines if the class is abstract, which for PHP 4
     *    will never be the case.
     * @returns boolean      True if abstract.
     */
    public function isAbstract()
    {
        return false;
    }

    /**
     *    Determines if the the entity is an interface, which for PHP 4
     *    will never be the case.
     * @returns boolean      True if interface.
     */
    public function isInterface()
    {
        return false;
    }

    /**
     *    Scans for final methods, but as it's PHP 4 there
     *    aren't any.
     * @returns boolean   True if the class has a final method.
     */
    public function hasFinal()
    {
        return false;
    }

    /**
     *    Gets the source code matching the declaration
     *    of a method.
     * @param string $method Method name.
     */
    public function getSignature($method)
    {
        return "function &$method()";
    }
}
