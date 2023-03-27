<?php
declare(strict_types=1);
namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot()
    {
        View::composer(
            'build.page-header', 'App\Http\View\Composers\BuildPageHeaderComposer'
        );
    }
}
