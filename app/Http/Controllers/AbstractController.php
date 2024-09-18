<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\View\View;

abstract class AbstractController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Add global data to each view
     */
    protected function view(string $view, string $title = ''): View
    {
        session()->put('url.intended', url()->full());

        $result = view($view)->with('js_version', self::getJsVersion());

        if ($title !== '') {
            $result = $result->with('title', $title);
        }

        return $result;
    }

    protected function angular_view(string $view): View
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

        return $this->view('cdash')
            ->with('xsl_content', file_get_contents(base_path("public/build/views/$view.html")))
            ->with('xsl', true)
            ->with('angular', true)
            ->with('angular_controller', $controller_name);
    }

    public static function getCDashVersion(): string
    {
        return file_get_contents(public_path('VERSION'));
    }

    /**
     * Returns the version used to find compiled css and javascript files
     *
     * TODO: (williamjallen) make this a private function and rip the remaining usages.
     */
    public static function getJsVersion(): string
    {
        $path = config('cdash.file.path.js.version');
        $version = '';
        if (is_readable($path)) {
            $file = file_get_contents($path);
            if (preg_match("/'VERSION',\s+'([0-9.]+)'/", $file, $match)) {
                $version = $match[1];
            }
        }
        return $version;
    }

    protected function redirectToLogin(): RedirectResponse
    {
        session(['url.intended' => url()->full()]);
        return redirect()->route('login');
    }
}
