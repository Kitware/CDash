<?php
namespace App\Http\Controllers;

use App\Models\BuildTest;

class TestController extends AbstractProjectController
{
    // Render the test details page.
    public function details($buildtest_id = null)
    {
        $buildtest = BuildTest::findOrFail($buildtest_id);
        $this->setProjectById($buildtest->test->projectid);
        return view('test.details');
    }
}
