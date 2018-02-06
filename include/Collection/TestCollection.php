<?php
namespace CDash\Collection;

use Test;

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
