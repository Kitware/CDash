<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

abstract class AbstractController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $cdashCss;
    protected $user;

    public function __construct()
    {
        $this->cdashCss = asset(get_css_file());

        // Get the current user, if applicable.
        $this->user = [
            'id' => Auth::id()
        ];
    }

    /** Returns the version used to find compiled css and javascript files */
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
        session(['url.intended' => url()->current()]);
        return redirect()->route('login');
    }
}
