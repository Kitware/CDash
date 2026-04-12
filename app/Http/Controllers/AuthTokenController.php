<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class AuthTokenController extends AbstractController
{
    public function manage(): View
    {
        return $this->vue('manage-auth-tokens', 'Authentication Tokens');
    }
}
