<?php

namespace App\Http\Controllers;

use App\Models\Build as EloquentBuild;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CoverageFileController extends AbstractBuildController
{
    public function __invoke(Request $request, int $build_id, int $file_id): View
    {
        $this->setBuildById($build_id);

        if (EloquentBuild::findOrFail($build_id)->coverage()->whereKey($file_id)->doesntExist()) {
            abort(404, 'Coverage file not found');
        }

        return $this->vue('coverage-file-page', 'Coverage', [
            'build-id' => $this->build->Id,
            'file-id' => $file_id,
        ]);
    }
}
