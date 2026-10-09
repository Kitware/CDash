<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RemoveUserCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function testRequiresEmail(): void
    {
        Artisan::call('user:remove');

        self::assertSame("You must specify the --email option\n", Artisan::output());
    }

    public function testFailsForNonexistentUser(): void
    {
        Artisan::call('user:remove', ['--email' => 'does-not-exist@example.com']);

        self::assertSame("User does-not-exist@example.com does not exist\n", Artisan::output());
    }

    public function testDeletesUser(): void
    {
        $user = User::factory()->create();

        Artisan::call('user:remove', ['--email' => $user->email]);

        self::assertSame("Deleted user {$user->email}\n", Artisan::output());
        self::assertModelMissing($user);
    }
}
