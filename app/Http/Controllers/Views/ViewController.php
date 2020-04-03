<?php
namespace App\Http\Controllers\Views;

use App\Http\Controllers\Controller;

require_once 'include/common.php';

abstract class ViewController extends Controller
{
    protected $cdashCss;
    protected $user;

    public function __construct()
    {
        $this->cdashCss = asset(get_css_file());
        $this->user = [];
    }

    protected function setup()
    {
        $this->user = [
            'id' => \Auth::id()
        ];
    }

    protected function redirectToLogin()
    {
        session(['url.intended' => url()->current()]);
        return redirect()->route('login');
    }
}
