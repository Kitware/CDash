<?php
namespace CDash\Lib\Collection;

use CDash\Model\Test;

class TestCollection extends Collection
{
    /**
     * @param Test $test
     */
    public function add(Test $test)
    {
        parent::addItem($test, $test->Name);
    }
}
