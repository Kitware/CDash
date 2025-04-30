<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class UsersController extends AbstractController
{
    public function users(): View
    {
        return $this->view('user.users', 'Users');
    }
}
