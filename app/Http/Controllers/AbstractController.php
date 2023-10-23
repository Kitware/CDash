<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;

abstract class AbstractController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Add global data to each view
     */
    protected function view(string $view, string $title = ''): View
    {
        $result = view($view)->with('js_version', self::getJsVersion());

        if ($title !== '') {
            $result = $result->with('title', $title);
        }

        return $result;
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
        session(['url.intended' => Request::getRequestUri()]);
        return redirect()->route('login');
    }
}
