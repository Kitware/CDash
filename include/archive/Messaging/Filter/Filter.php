<?php
namespace CDash\archive\Messaging\Filter;

use ActionableBuildInterface;
/**
 * Class Filter
 */
abstract class Filter implements FilterInterface
{
    private $property;
    private $type;
    private $value;

    public function __construct($property, $type, $value)
    {
        $this->property = $property;
        $this->type = $type;
        $this->value = $value;
    }

    public function getProperty() : string
    {
        return $this->property;
    }

    public function getType() : string
    {
        return $this->type;
    }

    public function getValue() : string
    {
        return $this->value;
    }

    protected function is()
    {

    }

    protected function isNot()
    {

    }

    protected function isGreaterThan()
    {

    }

    protected function isLessThan()
    {

    }

    protected function contains()
    {

    }

    protected function doesNotContain()
    {

    }

    protected function startsWith()
    {

    }

    protected function endsWith()
    {

    }

    protected function isAfter()
    {

    }

    protected function isBefore()
    {

    }
}
