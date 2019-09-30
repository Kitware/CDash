<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use CDash\Model\Build;

class BuildController extends Controller
{
    public function summary($build_id)
    {
        $build = new Build();
        $build->FillFromId($build_id);

        $store = [
            'build' => $build,
        ];

        return view('build.summary')
            ->with('title', 'Build Summary')
            ->with('store', $store);
    }
}
