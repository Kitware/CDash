<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\View\View;

final class SiteController extends AbstractController
{
    public function viewSite(Site $site): View
    {
        return $this->vue('sites-id-page', $site->name, [
            'site-id' => $site->id,
            'user-id' => auth()->user()?->id,
        ]);
    }
}
