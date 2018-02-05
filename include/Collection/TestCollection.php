<?php
namespace CDash\Collection;

use Test;

class TestCollection extends Collection
{
    public function add(Test $test)
    {
        parent::addItem($test, $test->Name);
    }
}
