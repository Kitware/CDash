<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

trait LogsInThroughLoginForm
{
    /**
     * @return TestResponse<Response>
     */
    public function loginThroughForm(User $user, string $password, bool $remember = false): TestResponse
    {
        return $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ] + ($remember ? ['remember' => '1'] : []));
    }
}
