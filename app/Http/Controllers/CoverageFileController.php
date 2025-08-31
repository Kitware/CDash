<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class CoverageFileController extends AbstractBuildController
{
    public function __invoke(Request $request, int $build_id, int $file_id): View
    {
        $this->setBuildById($build_id);

        return $this->vue('coverage-file-page', 'Coverage', [
            'build-id' => $this->build->Id,
            'file-id' => $file_id,
        ]);
    }
}
