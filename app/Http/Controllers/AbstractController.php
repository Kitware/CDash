<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

abstract class AbstractController extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Add global data to each view
     */
    protected function view(string $view, string $title): View
    {
        session()->put('url.intended', url()->full());
        return view($view)->with('title', $title);
    }

    protected function angular_view(string $view, string $title = ''): View
    {
        // A hack to ensure that redirects work properly after being redirected to the login page
        session(['url.intended' => url()->full()]);

        $controller_name = '';
        $path = request()->path() === '/' ? 'index.php' : request()->path();
        $file = pathinfo(substr($path, strrpos($path, '/')), PATHINFO_FILENAME);

        // Special case: viewBuildGroup.php shares a controller with index.php.
        if ($file === 'viewBuildGroup') {
            $file = 'index';
        }
        $controller_path = config('cdash.file.path.js.controllers');
        $controller = "{$controller_path}/{$file}.js";
        if (is_readable($controller)) {
            $controller_name = Str::studly($file) . 'Controller';
        }

        return $this->view('cdash', $title)
            ->with('xsl_content', file_get_contents(base_path("public/assets/js/angular/views/$view.html")))
            ->with('xsl', true)
            ->with('angular', true)
            ->with('angular_controller', $controller_name);
    }

    /**
     * @param array<string,mixed> $props
     */
    protected function vue(string $component, string $title, array $props = [], bool $enableDaisyUI = true): View
    {
        return $this->view('vue', $title)
            ->with('componentName', $component)
            ->with('props', $props)
            ->with('daisyui', $enableDaisyUI);
    }

    public static function getCDashVersion(): string
    {
        return file_get_contents(public_path('VERSION'));
    }

    protected function redirectToLogin(): RedirectResponse
    {
        session(['url.intended' => url()->full()]);
        return redirect()->route('login');
    }
}
