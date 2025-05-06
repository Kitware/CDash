<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class IndexController extends AbstractProjectController
{
    public function showIndexPage(): View|RedirectResponse
    {
        if (!isset($_GET['project'])) {
            $default_project = config('cdash.default_project');
            $url = $default_project ? "index.php?project={$default_project}" : 'projects';
            return redirect($url);
        }

        $this->setProjectByName($_GET['project']);

        return $this->angular_view('index');
    }
}
