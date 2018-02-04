<?php
namespace CDash\Collection;

use TestMeasurement;

class TestMeasurementCollection extends Collection
{
    public function add(TestMeasurement $measurement)
    {
        $key = str_replace(' ', '', $measurement->Name);
        parent::addItem($measurement, $key);
    }
}
