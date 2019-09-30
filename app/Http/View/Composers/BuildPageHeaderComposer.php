<?php
declare(strict_types=1);

namespace App\Http\View\Composers;


use CDash\Model\Build;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class BuildPageHeaderComposer
{
    protected $build;

    public function __construct(Build $build)
    {
        $this->build = $build;
    }

    public function compose(View $view)
    {
        $user = Auth::user();
        $menu = [
            'home' => 'viewProjects.php',
            'project' => 'CDash',
            'projectId' => 1234,
            'homeUrl' => 'https://www.cdash.org',
            'docUrl' =>  'https://public.kitware.com/Wiki/CDash',
            'vcsUrl' => 'https://github/Kitware/CDash',
            'bugUrl' => 'https://github/Kitware/CDash/issues',
            'today' => date('Y-m-d'),
        ];

        $view->with('user', json_encode((object)$user))
            ->with('menu', json_encode((object)$menu));
    }
}
