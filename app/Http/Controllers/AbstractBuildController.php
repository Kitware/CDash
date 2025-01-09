<?php

namespace App\Http\Controllers;

use App\Utils\TestingDay;
use CDash\Model\Build;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

abstract class AbstractBuildController extends AbstractProjectController
{
    protected Build $build;

    protected function view(string $view, string $title = ''): View
    {
        if (!isset($this->build)) {
            return parent::view($view, $title);
        }

        return parent::view($view, $title)
            ->with('build', $this->build);
    }

    // Fetch data used by all build-specific pages in CDash.
    protected function setBuild(Build $build): void
    {
        if (!$build->Exists()) {
            abort(404, 'Build does not exist. Maybe it has been deleted.');
        }

        Log::shareContext([
            'buildid' => $build->Id,
        ]);

        $this->setProject($build->GetProject());
        $this->build = $build;
        $this->date = TestingDay::get($this->project, $this->build->StartTime);
    }

    protected function setBuildById(int $buildid): void
    {
        $build = new Build();
        $build->Id = $buildid;
        $build->FillFromId($buildid);
        $this->setBuild($build);
    }
}
