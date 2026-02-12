<?php

namespace App\Http\Controllers;

use App\Utils\TestingDay;
use CDash\Model\Build;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

abstract class AbstractBuildController extends AbstractProjectController
{
    protected Build $build;

    protected function view(string $view, string $title = ''): View
    {
        if (!isset($this->build)) {
            return parent::view($view, $title);
        }

        $previousBuildId = $this->build->GetPreviousBuildId();
        $latestBuildId = $this->build->GetCurrentBuildId();
        $nextBuildId = $this->build->GetNextBuildId();

        $previousUrl = $previousBuildId > 0 ? Str::replace((string) $this->build->Id, (string) $previousBuildId, request()->fullUrl()) : null;
        $latestUrl = $latestBuildId > 0 ? Str::replace((string) $this->build->Id, (string) $latestBuildId, request()->fullUrl()) : null;
        $nextUrl = $nextBuildId > 0 ? Str::replace((string) $this->build->Id, (string) $nextBuildId, request()->fullUrl()) : null;

        // We assume the first instance of the current build ID in the URL is the build ID.  Users
        // of this method can override these values if the routing scheme is different.
        return parent::view($view, $title)
            ->with('build', $this->build)
            ->with('previousUrl', $previousUrl)
            ->with('latestUrl', $latestUrl)
            ->with('nextUrl', $nextUrl);
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
