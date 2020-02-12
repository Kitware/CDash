<?php
declare(strict_types=1);

namespace App\Http\View\Composers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class BuildPageHeaderComposer
{
    public function compose(View $view)
    {
        $user = Auth::user();
        $view->with('user', json_encode((object)$user));
    }
}
