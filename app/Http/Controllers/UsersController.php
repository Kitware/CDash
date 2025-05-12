<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GlobalInvitation;
use Illuminate\View\View;

final class UsersController extends AbstractController
{
    public function users(): View
    {
        return $this->vue('users-page', 'Users', [
            'can-invite-users' => auth()->user()?->can('createInvitation', GlobalInvitation::class) ?? false,
        ]);
    }
}
